<?php

namespace App\Http\Controllers;

use App\OrderDetail;
use App\Support\OfflinePortalContext;
use App\Support\ReceivedDealerOrderData;
use App\Support\MegaDealerOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Server-side table for orders assigned to the signed-in Mega Dealer. */
class ReceivedDealerOrderController extends Controller
{
    public function index(Request $request, OfflinePortalContext $context)
    {
        $draw = max(0, $this->integer($request->input('draw'), 0));
        $user = $request->user();
        if (!$user) return $this->error($draw, 'Your session has expired. Sign in again.', 401);
        if (!$user->usesMegaDealerPricing()) return $this->error($draw, 'Only Mega Dealers can view received Dealer orders.', 403);
        $profile = $context->profile($user);
        if (!$profile) return $this->error($draw, 'Your Mega Dealer profile is unavailable.', 403);

        $status = $this->text($request->input('status'));
        if ($status !== '' && !in_array($status, ['Pending', 'For Verification', 'Completed', 'Cancelled', 'For Delivery'], true)) {
            return $this->error($draw, 'Choose a valid order status.', 422);
        }

        // Keep ownership in SQL before counting, searching, sorting or paging.
        $query = OrderDetail::query()
            ->leftJoin('users as received_dealers', 'received_dealers.id', '=', 'order_details.dealer_id')
            ->where('order_details.mega_dealer_id', $profile->id)
            ->localMegaDealerSource()
            ->whereNull('order_details.ad_id');
        $recordsTotal = (clone $query)->count();
        if ($status !== '') $query->where('order_details.status', $status);
        $search = mb_substr($this->text($request->input('search.value')), 0, 200);
        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search) {
                $pattern = '%' . $search . '%';
                foreach (['received_dealers.name', 'order_details.transaction_id', 'order_details.item',
                    'order_details.sku', 'order_details.payment_method', 'order_details.delivery_type', 'order_details.status'] as $column) {
                    $searchQuery->orWhere($column, 'like', $pattern);
                }
            });
        }
        $recordsFiltered = (clone $query)->count();

        $columnIndex = $this->integer($request->input('order.0.column'), 0);
        $indices = [0 => 'created_at', 1 => 'product_name', 2 => 'quantity', 4 => 'payment_method',
            5 => 'delivery_type', 6 => 'total_amount', 7 => 'status'];
        $name = $columnIndex >= 0 && $columnIndex <= 8
            ? $this->text($request->input('columns.' . $columnIndex . '.name')) : '';
        $sort = $name !== '' ? $name : ($indices[$columnIndex] ?? 'created_at');
        $columns = ['id' => 'order_details.id', 'dealer_name' => 'received_dealers.name',
            'transaction_id' => 'order_details.transaction_id', 'product_name' => 'order_details.item',
            'quantity' => 'order_details.qty', 'payment_method' => 'order_details.payment_method',
            'delivery_type' => 'order_details.delivery_type', 'status' => 'order_details.status',
            'created_at' => 'order_details.created_at'];
        $direction = strtolower($this->text($request->input('order.0.dir')));
        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'desc';
        if ($sort === 'total_amount') {
            $query->orderByRaw('(COALESCE(order_details.qty, 0) * COALESCE(order_details.price, 0)) ' . $direction);
        } else {
            $query->orderBy($columns[$sort] ?? $columns['created_at'], $direction);
        }
        if ($sort !== 'id') $query->orderBy('order_details.id', $direction);

        $start = max(0, $this->integer($request->input('start'), 0));
        $length = $this->integer($request->input('length'), 10);
        $length = $length > 0 ? min(100, $length) : 10;
        $orders = $query->select('order_details.*', 'received_dealers.name as received_dealer_name')
            ->offset($start)->limit($length)->get();

        return new JsonResponse(['draw' => $draw, 'recordsTotal' => (int) $recordsTotal,
            'recordsFiltered' => (int) $recordsFiltered,
            'data' => (new ReceivedDealerOrderData)->rows($orders, $user, $context)], 200,
            ['Cache-Control' => 'no-store, private']);
    }

    public function fulfil(Request $request, $id, OfflinePortalContext $context)
    {
        $user = $request->user();
        if (!$user) return new JsonResponse(['success' => false, 'message' => 'Your session has expired. Sign in again.'], 401);
        if (!$user->usesMegaDealerPricing() && strtolower(trim((string) $user->role)) !== 'admin') {
            return new JsonResponse(['success' => false, 'message' => 'Only the assigned Mega Dealer or an administrator can fulfil this order.'], 403);
        }
        try {
            [$order, $changed] = (new MegaDealerOrderService($context))->fulfil($id, $user, $request->input('remarks', ''));
            return new JsonResponse(['success' => true, 'status' => $order->status,
                'message' => $order->status === 'Completed' ? 'The Dealer has already confirmed this order.'
                    : ($changed ? 'Fulfilment submitted. The order is now For Verification by the Dealer.'
                        : 'This order is already awaiting Dealer verification.')]);
        } catch (\Illuminate\Validation\ValidationException $error) {
            return new JsonResponse(['success' => false, 'message' => collect($error->errors())->flatten()->first(), 'errors' => $error->errors()], 422);
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $error) {
            return new JsonResponse(['success' => false, 'message' => $error->getMessage()], $error->getStatusCode());
        } catch (\Exception $error) {
            \Log::error($error);
            return new JsonResponse(['success' => false, 'message' => 'Fulfilment could not be confirmed. Refresh the order status before trying again.'], 500);
        }
    }

    private function integer($value, $default)
    {
        return is_scalar($value) && preg_match('/^-?\d+$/', (string) $value) ? (int) $value : $default;
    }

    private function text($value)
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function error($draw, $message, $status)
    {
        return new JsonResponse(['draw' => $draw, 'recordsTotal' => 0, 'recordsFiltered' => 0,
            'data' => [], 'message' => $message, 'error' => $message], $status,
            ['Cache-Control' => 'no-store, private']);
    }
}
