<?php

namespace App\Support;

use App\MegaDealer;
use App\OrderDetail;
use App\TransactionDetail;
use Illuminate\Support\Facades\Schema;

/** Build one table page from the same catalog and stock ledger used by fulfilment. */
class ReceivedDealerOrderData
{
    public function rows($orders, $user, OfflinePortalContext $context)
    {
        if ($orders->isEmpty()) return [];
        $catalog = $context->products($user);
        $resolved = [];
        $products = collect();
        foreach ($orders as $order) {
            $product = $catalog->first(function ($product) use ($context, $order) {
                return $context->matchesProduct($order, $product);
            });
            $resolved[$order->id] = $product;
            if ($product) $products->put($product->id, $product);
        }

        $stock = [];
        if ($products->isNotEmpty()) {
            // A fixed number of ledger reads per page; never query stock per row.
            $received = $this->matchingProducts(OrderDetail::where('dealer_id', $user->id)
                ->where('status', 'Completed'), $products, 'order_details')->get();
            $salesQuery = TransactionDetail::where('dealer_id', $user->id);
            if (Schema::hasColumn('transaction_details', 'status')) $salesQuery->where('status', 'Completed');
            $sales = $this->matchingProducts($salesQuery, $products, 'transaction_details')->get();
            $profileIds = MegaDealer::where('user_id', $user->id)->pluck('id')->all();
            $outgoing = $this->matchingProducts(OrderDetail::whereIn('mega_dealer_id', $profileIds)->localMegaDealerSource()
                ->whereIn('status', ['For Verification', 'Completed']), $products, 'order_details')->get();
            foreach ($products as $product) {
                $stock[$product->id] = $context->remainingStock($product, $received, $sales, $outgoing);
            }
        }

        return $orders->map(function ($order) use ($resolved, $stock) {
            $product = $resolved[$order->id];
            $quantity = (int) $order->qty;
            $available = $product ? (int) $stock[$product->id] : null;
            if (!$product || $quantity < 1) {
                $state = 'unavailable';
                $message = !$product ? 'Product is unavailable in your sales territory.' : 'This order has an invalid quantity.';
            } elseif ($available === 0) {
                $state = 'out_of_stock'; $message = 'No stock available. Restock before fulfilling this order.';
            } elseif ($available < $quantity) {
                $state = 'insufficient'; $message = 'Insufficient stock. ' . ($quantity - $available) . ' more needed.';
            } else {
                $state = 'available'; $message = 'Enough stock to fulfil this order.';
            }
            if ($order->status === 'For Verification' && $quantity > 0) {
                $message = $quantity . ' unit' . ($quantity === 1 ? '' : 's') . ' reserved for this order. Awaiting Dealer verification.';
            }
            $payment = strtolower(trim((string) $order->payment_method));
            $delivery = strtolower(trim((string) $order->delivery_type));
            $created = $order->created_at;
            return ['id' => (int) $order->id,
                'dealer_name' => trim((string) $order->received_dealer_name) ?: 'Dealer',
                'transaction_id' => (string) $order->transaction_id,
                'product_name' => (string) $order->item, 'sku' => (string) $order->sku,
                'quantity' => $quantity, 'available_stock' => $available, 'stock_state' => $state,
                'stock_message' => $message, 'can_fulfil' => $order->status === 'Pending' && $state === 'available',
                'payment_method' => $payment, 'payment_label' => $this->label($payment,
                    ['cash' => 'Cash', 'gcash' => 'GCash', 'bank' => 'Bank Transfer', 'bank_transfer' => 'Bank Transfer', 'credit' => 'Credit']),
                'delivery_type' => $delivery, 'delivery_label' => $this->label($delivery, ['pickup' => 'Pickup', 'delivery' => 'Delivery']),
                'total_amount' => round((float) $order->price * $quantity, 2), 'status' => (string) $order->status,
                'created_at' => $created ? $created->toIso8601String() : null,
                'created_at_display' => $created ? $created->format('M d, Y · h:i A') : 'Date unavailable'];
        })->values()->all();
    }

    private function matchingProducts($query, $products, $table)
    {
        $hasProductId = Schema::hasColumn($table, 'product_id');
        $hasSku = Schema::hasColumn($table, 'sku');
        return $query->where(function ($query) use ($products, $hasProductId, $hasSku) {
            $query->whereIn('item', $products->pluck('product_name')->all());
            if ($hasSku) $query->orWhereIn('sku', $products->pluck('sku')->filter()->all());
            if ($hasProductId) $query->orWhereIn('product_id', $products->pluck('id')->all());
        });
    }

    private function label($value, array $labels)
    {
        return $labels[$value] ?? ($value !== '' ? ucwords(str_replace('_', ' ', $value)) : 'Not specified');
    }
}
