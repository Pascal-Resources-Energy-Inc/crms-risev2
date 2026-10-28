<?php

namespace App\Support;

use App\OrderDetail;
use App\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OfflineSnapshotData
{
    private $context;

    public function __construct(OfflinePortalContext $context)
    {
        $this->context = $context;
    }

    public function build($user)
    {
        $context = $this->context;
        $profile = $context->profile($user);
        $distributors = $context->distributors($user);
        $products = $context->products($user, $distributors);
        $customers = $context->customers($user);
        $fullNames = $context->showCustomerFullName($user);
        [$dealerStock, $adStock] = $context->stockMaps($products, $user);
        $adByUser = $distributors->keyBy('user_id');
        $customerNames = [];
        $customerData = $customers->map(function ($customer) use ($fullNames, &$customerNames) {
            $name = trim((string) $customer->name);
            $parts = preg_split('/\s+/', $name, -1, PREG_SPLIT_NO_EMPTY);
            $last = count($parts) ? array_pop($parts) : '';
            $display = $fullNames ? $name : (count($parts) ? str_repeat('*', mb_strlen(implode(' ', $parts))) . ' ' : '') . $last;
            $customerNames[$customer->id] = $display;
            return ['id' => (int) $customer->id, 'user_id' => (int) $customer->user_id, 'name' => $display, 'display_name' => $display,
                'mfi' => $customer->mfi, 'number' => $this->masked($customer->number), 'serial' => $this->masked(optional($customer->serial)->serial_number ?? $customer->serial_number),
                'address' => $fullNames ? (string) $customer->address : ''];
        })->values()->all();
        $transactions = Schema::hasTable('transaction_details') ? TransactionDetail::query()
            ->when($user->isDealerLike(), function ($query) use ($user) { $query->where('dealer_id', $user->id); },
                function ($query) use ($profile) { $query->where('client_id', optional($profile)->id ?: -1); })
            ->orderBy('id', 'desc')->limit(500)->get() : collect();
        $orders = $user->isDealerLike() && Schema::hasTable('order_details') ? OrderDetail::where('dealer_id', $user->id)
            ->orderBy('id', 'desc')->limit(500)->get() : collect();
        $earned = Schema::hasTable('transaction_details') ? TransactionDetail::query()
            ->when($user->isDealerLike(), function ($query) use ($user) { $query->where('dealer_id', $user->id); },
                function ($query) use ($profile) { $query->where('client_id', optional($profile)->id ?: -1); })
            ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) { $query->where('status', 'Completed'); })
            ->sum($user->isDealerLike() ? 'points_dealer' : 'points_client') : 0;
        $redeemed = Schema::hasTable('redeemed_histories') ? abs((float) DB::table('redeemed_histories')->where('user_id', $user->id)->sum('points_amount')) : 0;
        $profileData = [];
        if ($profile) foreach (['id', 'user_id', 'name', 'number', 'address', 'area', 'mfi', 'created_at', 'dealer_reference', 'chief_reference', 'partner_code', 'street_address', 'location_barangay', 'location_city', 'location_province', 'location_region'] as $field) {
            $value = $profile->getAttribute($field);
            if ($value !== null) $profileData[$field] = $value instanceof \DateTimeInterface ? $value->format(DATE_ATOM) : $value;
        }
        $profileData['sales_territory'] = $context->territory($user);
        [$acknowledgedOrders, $acknowledgedResults] = $this->acknowledgements($user->id);
        return [
            'schema_version' => 1, 'saved_at' => date(DATE_ATOM),
            'user' => ['id' => (int) $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
            'offline_login' => OfflineLoginGrant::forUser($user),
            'profile' => $profileData, 'points' => max(0, (float) $earned - $redeemed),
            'settings' => ['can_order' => $user->isDealerLike(), 'can_transact' => $user->isDealerLike(), 'show_customer_full_name' => $fullNames],
            'products' => $products->map(function ($product) use ($user, $context, $adByUser, $dealerStock, $adStock) {
                $ad = $adByUser->get($product->ad_user_id);
                $image = $product->product_image ? rtrim((string) config('app.crms_admin_url'), '/') . '/public/uploads/products/' . rawurlencode($product->product_image) : null;
                return ['id' => (int) $product->id, 'product_id' => (int) $product->id, 'name' => $product->product_name,
                    'description' => $product->description, 'sku' => $product->sku, 'image' => $image,
                    'price' => $context->clientPrice($product, $user), 'order_price' => $context->orderPrice($product, $user),
                    'ad_id' => $ad ? (int) $ad->id : null, 'ad_user_id' => (int) $product->ad_user_id,
                    'dealer_stock' => $dealerStock[$product->id] ?? 0, 'ad_stock' => $adStock[$product->id] ?? 0,
                    'client_allowed' => $context->clientAllowed($product)];
            })->values()->all(),
            'customers' => $customerData,
            'distributors' => $distributors->map(function ($ad) use ($context, $profile) {
                return ['id' => (int) $ad->id, 'user_id' => (int) $ad->user_id, 'name' => $ad->name,
                    'number' => $ad->contact_number ?? $ad->number, 'address' => $ad->address, 'sales_territories' => $ad->areas->pluck('area_name')->values()->all(),
                    'distance' => $context->distance($profile, $ad)];
            })->values()->all(),
            'transactions' => $transactions->map(function ($row) use ($customerNames) {
                return $this->historyRow($row) + ['client_id' => $row->client_id, 'customer_name' => $customerNames[$row->client_id] ?? 'Customer'];
            })->values()->all(),
            'orders' => $orders->map(function ($row) { return $this->historyRow($row) + ['ad_id' => $row->ad_id]; })->values()->all(),
            'acknowledged_orders' => $acknowledgedOrders,
            'acknowledged_order_results' => (object) $acknowledgedResults,
            'stoves' => [],
        ];
    }

    private function acknowledgements($userId)
    {
        $keys = []; $results = [];
        if (!Schema::hasTable('offline_order_receipts')) return [$keys, $results];
        $receipts = DB::table('offline_order_receipts')->where('user_id', $userId)->whereNotNull('response_json')
            ->get(['idempotency_key', 'response_json']);
        foreach ($receipts as $receipt) {
            $keys[] = $receipt->idempotency_key;
            $response = json_decode($receipt->response_json, true);
            if (!is_array($response) || empty($response['success']) || !isset($response['records']) || !is_array($response['records'])) continue;
            $records = [];
            foreach ($response['records'] as $record) {
                if (!is_array($record) || !isset($record['id'], $record['transaction_id'], $record['product_id'], $record['status']) ||
                    !is_numeric($record['id']) || !is_numeric($record['product_id']) ||
                    !is_string($record['transaction_id']) || !is_string($record['status'])) continue;
                $records[] = ['id' => (int) $record['id'], 'product_id' => (int) $record['product_id'],
                    'transaction_id' => (string) $record['transaction_id'], 'status' => (string) $record['status']];
            }
            // Recover the authoritative IDs even if the POST acknowledgement was
            // lost or the order has moved beyond the latest 500 history records.
            $results[$receipt->idempotency_key] = $records;
        }
        return [$keys, $results];
    }

    private function historyRow($row)
    {
        return ['id' => (int) $row->id, 'transaction_id' => $row->transaction_id, 'product_id' => $row->product_id,
            'item' => $row->item, 'item_description' => $row->item_description, 'qty' => (int) $row->qty, 'price' => (float) $row->price,
            'total_amount' => (int) $row->qty * (float) $row->price, 'status' => $row->status ?? 'Completed',
            'date' => $row->date, 'created_at' => $row->created_at ? $row->created_at->format(DATE_ATOM) : null,
            'payment_method' => $row->payment_method, 'delivery_type' => $row->delivery_type];
    }

    private function masked($value)
    {
        $value = (string) $value;
        return str_repeat('*', max(0, strlen($value) - 5)) . substr($value, -5);
    }
}
