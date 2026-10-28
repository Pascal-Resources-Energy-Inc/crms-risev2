<?php

namespace App\Http\Controllers;

use App\Mail\NewADOrderMail;
use App\OrderDetail;
use App\Product;
use App\Support\OfflinePortalContext;
use App\Support\OfflineSnapshotData;
use App\Support\OrderReference;
use App\TransactionDetail;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** One atomic server operation per saved offline transaction. */
class OfflineOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function snapshot(Request $request, OfflineSnapshotData $snapshot)
    {
        $user = $this->user($request);
        $data = DB::transaction(function () use ($snapshot, $user) { return $snapshot->build($user); });
        return new JsonResponse($data, 200, ['Cache-Control' => 'private, no-store']);
    }

    public function session(Request $request)
    {
        $user = $this->user($request);
        return new JsonResponse(['user_id' => (int) $user->id, 'csrf_token' => $request->session()->token()], 200,
            ['Cache-Control' => 'private, no-store']);
    }

    public function store(Request $request, OfflinePortalContext $context)
    {
        $user = $this->user($request);
        if (!$user->isDealerLike()) throw new HttpException(403, 'This account cannot create dealer transactions.');
        if ((int) $request->input('user_id') !== (int) $user->id) throw new HttpException(403, 'Sign in with the account that saved this transaction.');
        $data = Validator::make($request->all(), [
            'idempotency_key' => 'required|uuid', 'user_id' => 'required|integer',
            'type' => 'required|in:client,order', 'ad_id' => 'nullable|integer', 'client_id' => 'nullable|integer',
            'payment_method' => 'required|in:cash,gcash,bank_transfer,credit', 'delivery_type' => 'required|in:pickup,delivery',
            'items' => 'required|array|min:1|max:100', 'items.*.product_id' => 'required|integer|distinct',
            'items.*.quantity' => 'required|integer|min:1|max:1000000', 'items.*.unit_price' => 'required|numeric|min:0',
            'snapshot_saved_at' => 'required|date',
        ])->validate();
        if (!Schema::hasTable('offline_order_receipts')) {
            throw new HttpException(503, 'Offline syncing is not prepared on the server. Your saved transactions are kept.');
        }
        $hash = $this->payloadHash($data);
        $notifications = [];
        try {
            $result = DB::transaction(function () use ($data, $hash, $context, $user, &$notifications) {
                $notifications = [];
                // Serialize sales by account so two devices cannot spend the same dealer stock.
                DB::table('users')->where('id', $user->id)->lockForUpdate()->first();
                $receipt = DB::table('offline_order_receipts')->where('idempotency_key', $data['idempotency_key'])->lockForUpdate()->first();
                if ($receipt) return $this->replay($receipt, $user->id, $hash);
                DB::table('offline_order_receipts')->insert([
                    'user_id' => $user->id, 'idempotency_key' => $data['idempotency_key'], 'request_hash' => $hash,
                    'response_json' => null, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $suppliers = $context->distributors($user);
                $products = $context->products($user, $suppliers)->keyBy('id');
                $recipient = $data['type'] === 'order'
                    ? $suppliers->firstWhere('id', $data['ad_id'] ?? null)
                    : $context->customers($user)->firstWhere('id', $data['client_id'] ?? null);
                if (!$recipient) $this->invalid('recipient', 'The selected customer or Area Distributor is no longer assigned to this account. Refresh and review the saved order.');
                $checked = [];
                foreach ($data['items'] as $item) {
                    $product = $products->get($item['product_id']);
                    if (!$product) $this->invalid('items', 'A product is no longer available in your sales territory. Refresh and review the saved order.');
                    if ($data['type'] === 'order' && (int) $product->ad_user_id !== (int) $recipient->user_id) {
                        $this->invalid('items', $product->product_name . ': this product does not belong to the selected Area Distributor.');
                    }
                    $price = $data['type'] === 'order' ? $context->orderPrice($product, $user) : $context->clientPrice($product, $user);
                    if (abs($price - (float) $item['unit_price']) > 0.005) {
                        $this->invalid('items', $product->product_name . ': the price changed. Refresh and review your saved order.');
                    }
                    if ($data['type'] === 'client') {
                        if (!$context->clientAllowed($product)) $this->invalid('items', $product->product_name . ': this product is unavailable for client transactions.');
                        if ((int) $item['quantity'] > $context->dealerStock($product, $user->id)) {
                            $this->invalid('items', $product->product_name . ': quantity exceeds your current available stock.');
                        }
                    }
                    // AD orders are requests for supply. Zero AD inventory never
                    // blocks them or marks them delivered; every order stays Pending.
                    $checked[] = [$product, (int) $item['quantity'], $price];
                }
                $records = [];
                foreach ($checked as $checkedItem) {
                    [$product, $quantity, $price] = $checkedItem;
                    $record = $this->saveItem($data, $product, $quantity, $price, $recipient, $user);
                    $records[] = ['id' => (int) $record->id, 'transaction_id' => $record->transaction_id,
                        'product_id' => (int) $product->id, 'status' => $data['type'] === 'order' ? 'Pending' : 'Completed'];
                    if ($data['type'] === 'order') $notifications[] = [$record, $recipient, $product, $user];
                }
                $result = ['success' => true, 'records' => $records, 'message' => $data['type'] === 'order'
                    ? 'Order received by your Area Distributor and saved as pending fulfilment.' : 'Client transaction saved successfully.'];
                DB::table('offline_order_receipts')->where('idempotency_key', $data['idempotency_key'])
                    ->update(['response_json' => json_encode($result), 'updated_at' => date('Y-m-d H:i:s')]);
                return $result;
            }, 3);
        } catch (QueryException $error) {
            // A concurrent replay on another connection may win the unique key.
            // Its committed receipt is the only authoritative acknowledgement.
            $receipt = DB::table('offline_order_receipts')->where('idempotency_key', $data['idempotency_key'])->first();
            if (!$receipt || !$receipt->response_json) throw $error;
            $notifications = [];
            $result = $this->replay($receipt, $user->id, $hash);
        }
        $notificationFailed = false;
        foreach ($notifications as $notification) {
            try { $this->notifyDistributor(...$notification); }
            catch (\Throwable $error) {
                $notificationFailed = true;
                Log::warning('Offline AD order saved; email notification failed.', ['order_id' => $notification[0]->id]);
            }
        }
        if ($notificationFailed) $result['warning'] = 'Your order is saved. The distributor email notification could not be sent.';
        return new JsonResponse($result, 200, ['Cache-Control' => 'private, no-store']);
    }

    protected function notifyDistributor($order, $ad, $product, $user)
    {
        $ad->load('user');
        if ($ad->user && $ad->user->email) Mail::to($ad->user->email)->send(new NewADOrderMail($order, $ad, $product, $user));
    }

    private function saveItem($data, $product, $quantity, $price, $recipient, $user)
    {
        $isOrder = $data['type'] === 'order';
        $record = $isOrder ? new OrderDetail : new TransactionDetail;
        $table = $record->getTable();
        $attributes = [
            'product_id' => $product->id, 'item' => $product->product_name, 'item_description' => $product->description,
            'sku' => $product->sku, 'qty' => $quantity, 'price' => $price,
            'dealer_id' => $user->id, 'created_by' => $user->id, 'date' => date('Y-m-d'),
            'payment_method' => $data['payment_method'], 'delivery_type' => $data['delivery_type'],
            'points_dealer' => (float) $product->dealer_points * $quantity,
            'status' => $isOrder ? 'Pending' : 'Completed',
        ];
        if (!$isOrder) $attributes['transaction_id'] = 'TRX-' . time() . '-' . rand(1000, 9999);
        if ($isOrder) $attributes += ['ad_id' => $recipient->id, 'ad_address' => $recipient->address ?? '', 'delivery_fee' => 0];
        else $attributes += ['client_id' => $recipient->id, 'client_address' => $recipient->address ?? '',
            'points_client' => (float) $product->customer_points * $quantity, 'is_guest' => false];
        foreach ($attributes as $name => $value) {
            if (Schema::hasColumn($table, $name)) $record->setAttribute($name, $value);
        }
        if ($isOrder) OrderReference::save($record);
        else $record->save();
        return $record;
    }

    private function user(Request $request)
    {
        if (!$request->user()) throw new HttpException(401, 'Sign in online before updating or syncing offline data.');
        return $request->user();
    }

    private function invalid($field, $message)
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }

    private function replay($receipt, $userId, $hash)
    {
        if ((int) $receipt->user_id !== (int) $userId || !hash_equals((string) $receipt->request_hash, $hash)) {
            throw new HttpException(409, 'This saved order reference was already used. Keep the original transaction and review History.');
        }
        $result = json_decode($receipt->response_json, true);
        if (!$result || empty($result['success'])) throw new HttpException(503, 'This transaction is still being saved. Retry with the same reference.');
        return $result;
    }

    private function payloadHash($data)
    {
        $items = array_map(function ($item) {
            return ['product_id' => (int) $item['product_id'], 'quantity' => (int) $item['quantity'], 'unit_price' => (float) $item['unit_price']];
        }, $data['items']);
        usort($items, function ($a, $b) { return $a['product_id'] <=> $b['product_id']; });
        return hash('sha256', json_encode(['user_id' => (int) $data['user_id'], 'type' => $data['type'],
            'ad_id' => $data['type'] === 'order' ? (int) ($data['ad_id'] ?? 0) : null,
            'client_id' => $data['type'] === 'client' ? (int) ($data['client_id'] ?? 0) : null,
            'payment_method' => $data['payment_method'], 'delivery_type' => $data['delivery_type'], 'items' => $items]));
    }
}
