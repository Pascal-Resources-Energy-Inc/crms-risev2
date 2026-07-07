<?php

namespace App\Http\Controllers;

use App\Product;
use App\Item;
use App\AreaDistributor;
use App\Client;
use App\Dealer;
use App\OrderDetail;
use App\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index()
    {
        // dd(auth()->user()->dealer);
        $userDealer = optional(auth()->user()->dealer);
        // dd($userDealer);
        $products = Product::with(['adProduct.areas'])
            ->where('status', 'Activate')
            // ->whereNotNull('dealer_price')
            ->when($userDealer->area, function ($query) use ($userDealer) {
                $query->whereHas('adProduct.areas', function ($q) use ($userDealer) {
                    $q->where('area_name', $userDealer->area)
                        ->whereNull('deleted_at');
                });
            })
            ->latest()
            ->get();  

        [$dealerStockByProduct, $adStockByProduct] = $this->getProductStockMaps($products);
        
        // $products = Product::with('adProduct.areas')->where('status', 'Activate')->orderBy('created_at', 'desc')->get();
        
        $customers = Client::whereHas('serial')->get();
        $items = Item::get();
        $dealers = Dealer::get();
        
        $transactions = [];

        if (auth()->user()->role == "Admin") {
            $transactions = TransactionDetail::get();
        } elseif (auth()->user()->role == "Dealer") {
            $transactions = TransactionDetail::where('dealer_id', auth()->user()->id)->get();
        }

        $dealerProfile = null;
        if (auth()->user()->role == "Dealer") {
            $dealerProfile = Dealer::where('user_id', auth()->user()->id)->first();
        }

        return view('products', [
            'products' => $products,
            'transactions' => $transactions,
            'items' => $items,
            'customers' => $customers,
            'dealers' => $dealers,
            'dealerProfile' => $dealerProfile,
            'dealerStockByProduct' => $dealerStockByProduct,
            'adStockByProduct' => $adStockByProduct,
        ]);
    }

    public function stockSummary()
    {
        $userDealer = optional(auth()->user()->dealer);

        $products = Product::with(['adProduct.areas'])
            ->where('status', 'Activate')
            ->whereNotNull('dealer_price')
            ->when($userDealer->area, function ($query) use ($userDealer) {
                $query->whereHas('adProduct.areas', function ($q) use ($userDealer) {
                    $q->where('area_name', $userDealer->area)
                        ->whereNull('deleted_at');
                });
            })
            ->latest()
            ->get();

        [$dealerStockByProduct, $adStockByProduct] = $this->getProductStockMaps(
            $products,
            request('ad_id')
        );

        return response()->json([
            'ad' => $adStockByProduct->map(function ($stock) {
                return (int) ($stock['stock'] ?? 0);
            }),
            'dealer' => $dealerStockByProduct->map(function ($stock) {
                return (int) $stock;
            }),
        ]);
    }

    public function dealerStockInventory(Request $request)
    {
        $user = auth()->user();
        $userDealer = optional($user->dealer);

        $products = Product::with(['adProduct.areas'])
            ->where('status', 'Activate')
            ->when($userDealer->area, function ($query) use ($userDealer) {
                $query->whereHas('adProduct.areas', function ($q) use ($userDealer) {
                    $q->where('area_name', $userDealer->area)
                        ->whereNull('deleted_at');
                });
            })
            ->orderBy('product_name')
            ->get();

        [$dealerStockByProduct, $adStockByProduct] = $this->getProductStockMaps($products);

        $role = strtolower(str_replace([' ', '-'], '_', optional($user)->role ?? 'client'));
        $priceColumn = [
            'dealer' => 'dealer_price',
            'mega_dealer' => 'mega_dealer_price',
        ][$role] ?? 'price';

        $inventoryItems = $products->map(function ($product) use ($dealerStockByProduct, $adStockByProduct, $priceColumn) {
            $dealerStock = (int) ($dealerStockByProduct[$product->id] ?? 0);
            $adStock = (int) (($adStockByProduct[$product->id]['stock'] ?? 0));
            $price = (float) ($product->{$priceColumn} ?? $product->price ?? 0);

            if ($dealerStock <= 0) {
                $stockStatus = 'out';
                $stockLabel = 'No stock yet';
            } elseif ($dealerStock <= 5) {
                $stockStatus = 'low';
                $stockLabel = 'Low stock';
            } else {
                $stockStatus = 'healthy';
                $stockLabel = 'In stock';
            }

            return [
                'id' => $product->id,
                'name' => $product->product_name,
                'sku' => $product->sku,
                'description' => $product->description,
                'image' => config('app.crms_admin_url') . '/public/uploads/products/' . $product->product_image,
                'dealer_stock' => $dealerStock,
                'ad_stock' => $adStock,
                'price' => $price,
                'value' => $dealerStock * $price,
                'status' => $stockStatus,
                'status_label' => $stockLabel,
            ];
        });

        return view('dealer_stock_inventory', [
            'dealerProfile' => $userDealer,
            'inventoryItems' => $inventoryItems,
            'totalProducts' => $inventoryItems->count(),
            'totalUnits' => $inventoryItems->sum('dealer_stock'),
            'totalValue' => $inventoryItems->sum('value'),
            'inStockCount' => $inventoryItems->where('dealer_stock', '>', 0)->count(),
            'lowStockCount' => $inventoryItems->where('status', 'low')->count(),
            'outStockCount' => $inventoryItems->where('status', 'out')->count(),
        ]);
    }

    public function dealerStockTransactions($id)
    {
        $user = auth()->user();
        $product = Product::with(['adProduct.areas'])->findOrFail($id);
        $productNames = collect([$product->product_name, $product->description])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $productSkus = collect([$product->sku])->filter()->values()->all();
        $orderDetailsHasSku = Schema::hasTable('order_details') &&
            Schema::hasColumn('order_details', 'sku');
        $orderDetailsHasProductId = Schema::hasTable('order_details') &&
            Schema::hasColumn('order_details', 'product_id');
        $transactionDetailsHasSku = Schema::hasTable('transaction_details') && Schema::hasColumn('transaction_details', 'sku');
        $transactionDetailsHasProductId = Schema::hasTable('transaction_details') &&
            Schema::hasColumn('transaction_details', 'product_id');

        [$dealerStockByProduct] = $this->getProductStockMaps(collect([$product]));

        $role = strtolower(str_replace([' ', '-'], '_', optional($user)->role ?? 'client'));
        $priceColumn = [
            'dealer' => 'dealer_price',
            'mega_dealer' => 'mega_dealer_price',
        ][$role] ?? 'price';
        $price = (float) ($product->{$priceColumn} ?? $product->price ?? 0);

        $stockInRows = collect();
        if (Schema::hasTable('order_details')) {
            $stockInRows = OrderDetail::query()
                ->with('ad')
                ->where('dealer_id', $user->id)
                ->where('status', 'Completed')
                ->where(function ($query) use ($product, $productNames, $productSkus, $orderDetailsHasSku, $orderDetailsHasProductId) {
                    $query->whereIn('item', $productNames);

                    if ($orderDetailsHasProductId) {
                        $query->orWhere('product_id', $product->id);
                    }

                    if ($orderDetailsHasSku && !empty($productSkus)) {
                        $query->orWhereIn('sku', $productSkus);
                    }
                })
                ->get()
                ->map(function ($order) use ($price) {
                    $date = $order->date ?: $order->created_at;

                    return [
                        'type' => 'in',
                        'label' => 'Stock In',
                        'reference' => $order->transaction_id ?: 'AD Order #' . $order->id,
                        'party' => optional($order->ad)->name ?: 'Area Distributor',
                        'qty' => (int) $order->qty,
                        'signed_qty' => (int) $order->qty,
                        'unit_price' => (float) ($order->price ?: $price),
                        'amount' => ((float) ($order->price ?: $price)) * (int) $order->qty,
                        'status' => $order->status ?: 'Completed',
                        'date' => $this->formatStockDate($date),
                        'sort_date' => $this->stockSortDate($date),
                    ];
                });
        }

        $stockOutRows = collect();
        if (Schema::hasTable('transaction_details')) {
            $stockOutRows = TransactionDetail::with('customer')
                ->where('dealer_id', $user->id)
                ->where(function ($query) use ($product, $productNames, $productSkus, $transactionDetailsHasSku, $transactionDetailsHasProductId) {
                    $query->whereIn('item', $productNames)
                        ->orWhereIn('item_description', $productNames);

                    if ($transactionDetailsHasProductId) {
                        $query->orWhere('product_id', $product->id);
                    }

                    if ($transactionDetailsHasSku && !empty($productSkus)) {
                        $query->orWhereIn('sku', $productSkus);
                    }
                })
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->get()
                ->map(function ($transaction) use ($price) {
                    $date = $transaction->date ?: $transaction->created_at;

                    return [
                        'type' => 'out',
                        'label' => 'Stock Out',
                        'reference' => 'Sale #' . $transaction->id,
                        'party' => optional($transaction->customer)->name ?: 'Customer',
                        'qty' => (int) $transaction->qty,
                        'signed_qty' => -1 * (int) $transaction->qty,
                        'unit_price' => (float) ($transaction->price ?: $price),
                        'amount' => ((float) ($transaction->price ?: $price)) * (int) $transaction->qty,
                        'status' => 'Sold',
                        'date' => $this->formatStockDate($date),
                        'sort_date' => $this->stockSortDate($date),
                    ];
                });
        }

        $transactions = $stockInRows
            ->merge($stockOutRows)
            ->sortByDesc('sort_date')
            ->values();

        $totalIn = $stockInRows->sum('qty');
        $totalOut = $stockOutRows->sum('qty');
        $currentStock = (int) ($dealerStockByProduct[$product->id] ?? max(0, $totalIn - $totalOut));

        return view('dealer_stock_transactions', [
            'product' => $product,
            'transactions' => $transactions,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'currentStock' => $currentStock,
            'stockValue' => $currentStock * $price,
            'price' => $price,
        ]);
    }

    private function getProductStockMaps($products, $selectedAdId = null)
    {
        $productIds = $products->pluck('id')->filter()->values()->all();
        $productNames = $products->pluck('product_name')->filter()->values()->all();
        $productSkus = $products->pluck('sku')->filter()->values()->all();
        $dealerStockByProduct = collect();
        $adStockByProduct = collect();
        $completedOrders = collect();
        $dealerTransactions = collect();
        $selectedAd = $selectedAdId ? AreaDistributor::find($selectedAdId) : null;
        $selectedAdUserId = optional($selectedAd)->user_id;
        $dealerArea = optional(auth()->user()->dealer)->area;
        $dealerUserIdsInArea = collect();
        
        if ($dealerArea && Schema::hasTable('dealers')) {
            $dealerUserIdsInArea = Dealer::where('area', $dealerArea)
                ->pluck('user_id')
                ->filter()
                ->map(function ($userId) {
                    return (int) $userId;
                });
        }
            
        if (Schema::hasTable('order_details')) {
            $orderDetailsHasSku = Schema::hasColumn('order_details', 'sku');
            $orderDetailsHasProductId = Schema::hasColumn('order_details', 'product_id');

            $completedOrders = OrderDetail::query()
                ->where('status', 'Completed')
                ->where(function ($query) use ($productIds, $productNames, $productSkus, $orderDetailsHasSku, $orderDetailsHasProductId) {
                    $query->whereIn('item', $productNames);

                    if ($orderDetailsHasProductId && !empty($productIds)) {
                        $query->orWhereIn('product_id', $productIds);
                    }

                    if ($orderDetailsHasSku && !empty($productSkus)) {
                        $query->orWhereIn('sku', $productSkus);
                    }
                })
                ->get();
        }

        if (Schema::hasTable('transaction_details')) {
            $transactionDetailsHasSku = Schema::hasColumn('transaction_details', 'sku');
            $transactionDetailsHasProductId = Schema::hasColumn('transaction_details', 'product_id');

            $dealerTransactions = TransactionDetail::where(function ($query) use ($productIds, $productNames, $productSkus, $transactionDetailsHasSku, $transactionDetailsHasProductId) {
                    $query->whereIn('item', $productNames)
                        ->orWhereIn('item_description', $productNames);

                    if ($transactionDetailsHasProductId && !empty($productIds)) {
                        $query->orWhereIn('product_id', $productIds);
                    }

                    if ($transactionDetailsHasSku && !empty($productSkus)) {
                        $query->orWhereIn('sku', $productSkus);
                    }
                })
                ->when(auth()->user()->role == "Dealer", function ($query) {
                    $query->where('dealer_id', auth()->id());
                })
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->get();
        }

        if (Schema::connection('dms')->hasTable('inventory_transfers')) {
            $inventoryTransfers = DB::connection('dms')
                ->table('inventory_transfers')
                // ->where('movement_type', '!=', 'transfer')
                ->whereNull('deleted_at')
                ->where(function ($query) use ($productIds, $productNames) {
                    $query->whereIn('product_id', $productIds)
                        ->orWhereIn('item_name', $productNames);
                })
                ->get();
            
            $adStockByProduct = $products->mapWithKeys(function ($product) use ($inventoryTransfers, $completedOrders, $selectedAd, $selectedAdUserId, $dealerArea, $dealerUserIdsInArea) {
                $adId = $selectedAd ? $selectedAd->id : optional($product->adProduct)->id;
                $adUserId = $selectedAdUserId ?: $product->ad_user_id;
                $normalizedDealerArea = $this->normalizeAreaName($dealerArea);

                $inventoryStock = $inventoryTransfers
                    ->filter(function ($transfer) use ($product, $adUserId) {
                        $matchesProduct = (int) $transfer->product_id === (int) $product->id ||
                            $transfer->item_name === $product->product_name;
                        $matchesAd = (int) $transfer->ad_user_id === (int) $adUserId;

                        return $matchesProduct && $matchesAd;
                    })
                    ->sum(function ($transfer) use ($normalizedDealerArea) {
                        return $this->getMovementQtyForArea($transfer, $normalizedDealerArea);
                    });
                
                $completedOrderQty = $completedOrders
                    ->filter(function ($order) use ($product, $adId, $dealerArea, $dealerUserIdsInArea) {
                        $matchesProduct = (int) ($order->product_id ?? 0) === (int) $product->id ||
                            $order->item === $product->product_name ||
                            (!empty($product->sku) && $order->sku === $product->sku);
                        $matchesAd = $adId && (int) $order->ad_id === (int) $adId;
                        $matchesDealerArea = !$dealerArea || $dealerUserIdsInArea->contains((int) $order->dealer_id);

                        return $matchesProduct && $matchesAd && $matchesDealerArea;
                    })
                    ->sum('qty');

                return [
                    $product->id => [
                        'stock' => max(0, $inventoryStock - $completedOrderQty),
                        'name' => optional($product->adProduct)->name,
                    ],
                ];
            });
        }
       
        if (Schema::hasTable('order_details')) {
            $dealerCompletedOrders = $completedOrders;

            if (auth()->user()->role == "Dealer") {
                $dealerCompletedOrders = $dealerCompletedOrders->where('dealer_id', auth()->id());
            }

            $dealerStockByProduct = $products->mapWithKeys(function ($product) use ($dealerCompletedOrders, $dealerTransactions) {
                $completedOrderQty = $dealerCompletedOrders
                    ->filter(function ($order) use ($product) {
                        return (int) ($order->product_id ?? 0) === (int) $product->id ||
                            $order->item === $product->product_name ||
                            (!empty($product->sku) && $order->sku === $product->sku);
                    })
                    ->sum('qty');

                $soldQty = $dealerTransactions
                    ->filter(function ($transaction) use ($product) {
                        return (int) ($transaction->product_id ?? 0) === (int) $product->id ||
                            $transaction->item === $product->product_name ||
                            $transaction->item_description === $product->product_name ||
                            (!empty($product->description) && $transaction->item_description === $product->description) ||
                            (!empty($product->sku) && $transaction->sku === $product->sku);
                    })
                    ->sum('qty');

                return [$product->id => max(0, (int) $completedOrderQty - (int) $soldQty)];
            });
        }

        return [$dealerStockByProduct, $adStockByProduct];
    }

    private function normalizeAreaName($area)
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $area)));
    }

    private function getMovementQtyForArea($movement, $normalizedArea)
    {
        $qty = (int) $movement->qty;
        $fromArea = $this->normalizeAreaName($movement->from_area ?? '');
        $toArea = $this->normalizeAreaName($movement->to_area ?? '');

        if ($movement->movement_type === 'in') {
            return !$normalizedArea || $toArea === $normalizedArea ? $qty : 0;
        }

        if ($movement->movement_type === 'out') {
            return !$normalizedArea || $fromArea === $normalizedArea ? -1 * $qty : 0;
        }

        if ($movement->movement_type === 'transfer') {
            if (!$normalizedArea) {
                return 0;
            }

            if ($fromArea === $normalizedArea) {
                return -1 * $qty;
            }

            if ($toArea === $normalizedArea) {
                return $qty;
            }
        }

        return 0;
    }

    private function formatStockDate($date)
    {
        if (!$date) {
            return 'No date';
        }

        $timestamp = strtotime((string) $date);

        return $timestamp ? date('M d, Y', $timestamp) : 'No date';
    }

    private function stockSortDate($date)
    {
        if (!$date) {
            return 0;
        }

        $timestamp = strtotime((string) $date);

        return $timestamp ?: 0;
    }

}
