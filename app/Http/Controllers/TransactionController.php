<?php

namespace App\Http\Controllers;
use App\TransactionDetail;
use App\OrderDetail;
use App\AreaDistributor;
use App\Mail\NewADOrderMail;
use App\Item;
use App\Product;
use App\Client;
use App\Dealer;
use Illuminate\Support\Facades\Mail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

use RealRashid\SweetAlert\Facades\Alert;
class TransactionController extends Controller
{
    // public function index(Request $request)
    // {
    //     $customers = Client::whereHas('serial')->get();
    //     $items = Item::get();
    //     $dealers = Dealer::get();
    //     $place_order = [];
    //     //  dd(auth()->user());
    //     if(auth()->user()->role == "Admin")
    //     {
    //         $place_order = TransactionDetail::get();
    //     }
    //     elseif(auth()->user()->role == "Dealer")
    //     {
    //         $place_order = TransactionDetail::where('dealer_id',auth()->user()->id)->get();
    //     }
    //     return view('place_order',
    //         array(
    //             'place_order' => $place_order,
    //             'items' => $items,
    //             'customers' => $customers,
    //             'dealers' => $dealers,
    //         )
    //     );
    // }
    public function index(Request $request)
    {
        $user = auth()->user();

        $customers = Client::whereHas('serial')->get();
        $items = Item::get();
        $dealers = Dealer::get();
        $transactions = [];

        $areaDistributor = AreaDistributor::with('areas')->get();

        if ($user->role == "Admin") {
            $transactions = TransactionDetail::get();
        } elseif ($user->role == "Dealer") {
            $transactions = TransactionDetail::where('dealer_id', $user->id)->get();
        }

        $center = optional($user->dealer)->area;

        // ✅ GET ALL MATCHING ADs (NOT just first)
        $matchedADs = collect();

        if ($center) {
            $matchedADs = AreaDistributor::whereHas('areas', function ($q) use ($center) {
                $q->where('area_name', $center);
            })->get();
        }

        // ✅ If no match → fallback to ALL
        $availableADs = $matchedADs->isNotEmpty() ? $matchedADs : $areaDistributor;

        return view('place_order', [
            'transactions' => $transactions,
            'items' => $items,
            'customers' => $customers,
            'dealers' => $dealers,
            'areaDistributor' => $areaDistributor,
            'userCenter' => $center,
            'matchedADs' => $matchedADs,     // ✅ collection
            'availableADs' => $availableADs, // ✅ always usable
        ]);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                // 'item_id' => 'required|exists:items,id',
                'item_id' => 'required|exists:dms.products,id',
                'qty' => 'required|integer|min:1',
                'customer_id' => 'required',
                'payment_method' => 'nullable|string|in:cash,gcash,credit,bank_transfer',
                'delivery_type' => 'nullable|string|in:pickup,delivery',
            ]);

            // $item = Item::findOrFail($request->item_id);
            $item = Product::findOrFail($request->item_id);
            $isGuestTransaction = $request->customer_id === 'guest';

            if (! $isGuestTransaction) {
                $request->validate([
                    'customer_id' => 'exists:clients,id',
                ]);
            }

            $client = $isGuestTransaction
                ? null
                : Client::with('user')->findOrFail($request->customer_id);
            $dealerStock = $this->getDealerProductStock($item, auth()->id());
            $transactionStatus = $dealerStock >= (int) $request->qty ? 'Completed' : 'Pending';

            $transaction = new TransactionDetail;
            $transaction->transaction_id = 'TRX-' . time() . '-' . rand(1000, 9999);
            if (Schema::hasColumn('transaction_details', 'product_id')) {
                $transaction->product_id = $item->id;
            }
            $transaction->item = $item->product_name;
            $transaction->points_dealer = $isGuestTransaction ? 0 : $item->dealer_points * $request->qty;
            $transaction->points_client = $isGuestTransaction ? 0 : $item->customer_points * $request->qty;
            $transaction->item_description = $item->description;
            $transaction->qty = $request->qty;
            $transaction->price = $this->getProductPriceForUser($item);
            $transaction->client_id = $isGuestTransaction ? null : $request->customer_id;
            $transaction->client_address = $isGuestTransaction ? '' : ($client->address ?? '');
            if (Schema::hasColumn('transaction_details', 'is_guest')) {
                $transaction->is_guest = $isGuestTransaction;
            }
            $transaction->date = date('Y-m-d');
            $transaction->dealer_id = auth()->user()->id;
            $transaction->created_by = auth()->user()->id;
            $transaction->payment_method = $request->payment_method;
            if (Schema::hasColumn('transaction_details', 'status')) {
                $transaction->status = $transactionStatus;
            }
            $transaction->save();

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => $transactionStatus === 'Pending'
                        ? 'Transaction saved as pending because dealer stock is not available yet'
                        : 'Transaction saved successfully',
                    'status' => $transactionStatus,
                    'transaction_id' => $transaction->id
                ], 200);
            }

            if ($transactionStatus === 'Pending') {
                Alert::warning('Saved as Pending', 'Dealer has no stock for this product yet.')->persistent('Dismiss');
            } else {
                Alert::success('Successfully Save')->persistent('Dismiss');
            }
            return back();

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            Alert::error('Validation failed')->persistent('Dismiss');
            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save transaction: ' . $e->getMessage()
                ], 500);
            }
            
            Alert::error('Failed to save transaction')->persistent('Dismiss');
            return back();
        }
    }

    public function adStore(Request $request)
    {
        try {
            $validated = $request->validate([
                // 'item_id' => 'required|exists:items,id',
                'item_id' => 'required|exists:dms.products,id',
                'qty' => 'required|integer|min:1',
                'area_distributor_id' => 'required|exists:dms.area_distributors,id',
                'payment_method' => 'nullable|string|in:cash,gcash,credit,bank_transfer',
                'delivery_type' => 'nullable|string|in:pickup,delivery',
                'delivery_fee' => 'nullable|numeric|min:0',
            ]);

            // $item = Item::findOrFail($request->item_id);
            $item = Product::findOrFail($request->item_id);
            // $ad = AreaDistributor::findOrFail($request->area_distributor_id);
            $ad = AreaDistributor::with('user')->findOrFail($request->area_distributor_id);

            $dealer = auth()->user();

            $last = OrderDetail::latest('id')->first();
            $nextNumber = $last ? $last->id + 1 : 1;
            $transactionCode = 'PRORD-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

            $transaction = new OrderDetail;
            if (Schema::hasColumn('order_details', 'product_id')) {
                $transaction->product_id = $item->id;
            }
            $transaction->item = $item->product_name;
            $transaction->sku = $item->sku;
            $transaction->transaction_id = $transactionCode;
            $transaction->points_dealer = $item->dealer_points * $request->qty;
            // $transaction->points_client = $item->customer_points * $request->qty;
            $transaction->item_description = $item->description;
            $transaction->qty = $request->qty;
            $transaction->price = $this->getProductPriceForUser($item);

            $transaction->ad_id = $request->area_distributor_id;
            // $transaction->ad_id = $ad->user_id; // ✅ SAVE USER ID (NOT AD ID)
            $transaction->ad_address = $ad->address ?? '';
            $transaction->date = date('Y-m-d');
            $transaction->dealer_id = $dealer->id;
            $transaction->created_by = $dealer->id;
            $transaction->payment_method = $request->payment_method;
            $transaction->delivery_type = $request->delivery_type;
            if (Schema::hasColumn('order_details', 'delivery_fee')) {
                $transaction->delivery_fee = $request->delivery_fee ?? 0;
            }
            $transaction->status = 'Pending';
            $transaction->save();

            // ✅ SEND EMAIL TO AD
            if ($ad->user && $ad->user->email) {
                Mail::to($ad->user->email)->send(
                    new NewADOrderMail($transaction, $ad, $item, $dealer)
                );
            }

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Transaction saved successfully',
                    'transaction_id' => $transaction->id
                ], 200);
            }

            Alert::success('Successfully Save')->persistent('Dismiss');
            return back();

        } catch (\Illuminate\Validation\ValidationException $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $e->errors()
                ], 422);
            }
            
            Alert::error('Validation failed')->persistent('Dismiss');
            return back()->withErrors($e->errors())->withInput();

        } catch (\Exception $e) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to save transaction: ' . $e->getMessage()
                ], 500);
            }
            
            Alert::error('Failed to save transaction')->persistent('Dismiss');
            return back();
        }
    }

    public function storeApi(Request $request)
    {
        try {
            $validated = $request->validate([
                'item_id' => 'required|exists:items,id',
                'customer_id' => 'required|exists:clients,id',
                'qty' => 'required|integer|min:1',
                'payment_method' => 'nullable|string|in:cash,gcash,bank',
                'dealer_id' => 'nullable|exists:users,id'
            ]);

            $item = Item::findOrFail($request->item_id);
            $client = Client::findOrFail($request->customer_id);

            $dealerId = $request->dealer_id ?? (auth()->check() ? auth()->id() : null);
            
            if (!$dealerId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Dealer ID is required. Please ensure you are logged in.'
                ], 422);
            }

            $transaction = new TransactionDetail;
            $transaction->item = $item->item;
            $transaction->points_dealer = $item->dealer_points * $request->qty;
            $transaction->points_client = $item->customer_points * $request->qty;
            $transaction->item_description = $item->item_description;
            $transaction->qty = $request->qty;
            $transaction->price = $item->price;
            $transaction->client_id = $request->customer_id;
            $transaction->client_address = $client->address ?? '';
            $transaction->date = date('Y-m-d');
            $transaction->dealer_id = $dealerId;
            $transaction->created_by = $dealerId;
            $transaction->payment_method = $request->payment_method ?? 'cash';
            $transaction->save();

            return response()->json([
                'success' => true,
                'message' => 'Transaction saved successfully',
                'data' => [
                    'transaction_id' => $transaction->id,
                    'item' => $transaction->item,
                    'qty' => $transaction->qty,
                    'price' => $transaction->price,
                    'total' => $transaction->price * $transaction->qty,
                    'points_dealer' => $transaction->points_dealer,
                    'points_client' => $transaction->points_client,
                    'client_address' => $transaction->client_address,
                    'created_at' => $transaction->created_at
                ]
            ], 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $e->errors()
            ], 422);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Item or Customer not found. Please check the IDs.'
            ], 404);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save transaction: ' . $e->getMessage()
            ], 500);
        }
    }

    public function storeAdmin(Request $request)
    {
        // dd($request->all());
        $item = Item::findOrfail($request->item_id);
        $client = Client::findOrFail($request->customer_id);

        $transaction = new TransactionDetail;
        $transaction->item = $item->item;
        $transaction->points_dealer = $item->dealer_points * $request->qty;
        $transaction->points_client = $item->customer_points * $request->qty;
        $transaction->item_description = $item->item_description;
        $transaction->qty = $request->qty;
        $transaction->price = $item->price;
        $transaction->client_id = $request->customer_id;
        $transaction->client_address = $client->address ?? '';
        $transaction->dealer_id = $request->dealer;
        $transaction->date = $request->date;
        $transaction->created_by = auth()->user()->id;
        $transaction->save();

         Alert::success('Successfully Save')->persistent('Dismiss');
        return back();
    }

    public function placeOrder(Request $request)
    {
        try {
            $orderData = $request->all();
            
            if (empty($orderData['customer_id'])) {
                Alert::error('Customer information is required')->persistent('Dismiss');
                return redirect()->back();
            }
            
            if (empty($orderData['items']) || !is_array($orderData['items'])) {
                Alert::error('No items in cart')->persistent('Dismiss');
                return redirect('cart');
            }
            
            $customerId = $orderData['customer_id'];
            $items = $orderData['items'];
            $paymentMethod = $orderData['payment_method'] ?? 'cash';
            
            $customer = Client::find($customerId);
            if (!$customer) {
                Alert::error('Customer not found')->persistent('Dismiss');
                return redirect('cart');
            }
            
            $successCount = 0;
            foreach ($items as $itemData) {
                $item = Item::find($itemData['item_id']);
                
                if (!$item) {
                    continue;
                }
                
                $transaction = new TransactionDetail();
                $transaction->item = $item->item;
                $transaction->points_dealer = $item->dealer_points * $itemData['quantity'];
                $transaction->points_client = $item->customer_points * $itemData['quantity'];
                $transaction->item_description = $item->item_description;
                $transaction->qty = $itemData['quantity'];
                $transaction->price = $item->price;
                $transaction->client_id = $customerId;
                $transaction->client_address = $customer->address ?? '';
                $transaction->date = date('Y-m-d');
                $transaction->dealer_id = auth()->user()->id;
                $transaction->created_by = auth()->user()->id;
                $transaction->payment_method = $paymentMethod;
                $transaction->save();
                
                $successCount++;
            }
            
            if ($successCount > 0) {
                Alert::success("Order placed successfully! {$successCount} item(s) ordered.")->persistent('Dismiss');
                return redirect('history');
            } else {
                Alert::error('Failed to create order. Please try again.')->persistent('Dismiss');
                return redirect('cart');
            }
            
        } catch (\Exception $e) {
            \Log::error('Place Order Error: ' . $e->getMessage());
            Alert::error('An error occurred while placing order')->persistent('Dismiss');
            return redirect('cart');
        }
    }

    public function destroy($id)
    {
        try {
            if (!is_numeric($id) || $id <= 0) {
                return response()->json(['error' => 'Invalid transaction ID'], 400);
            }

            $transaction = TransactionDetail::findOrFail($id);

            if (auth()->user()->role === "Dealer" && $transaction->dealer_id != auth()->user()->id) {
                return response()->json(['error' => 'Unauthorized to delete this transaction'], 403);
            }

            $transaction->delete();

            return response()->json([
                'success' => 'Transaction deleted successfully',
                'transaction_id' => $id
            ]);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json(['error' => 'Transaction not found'], 404);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete transaction'], 500);
        }
    }


   public function bulkDelete(Request $request)
    {
        try {
            $ids = $request->input('ids');

            if (!$ids || !is_array($ids) || empty($ids)) {
                return response()->json(['error' => 'No transactions selected'], 400);
            }

            $validIds = array_filter($ids, function ($id) {
                return is_numeric($id) && intval($id) > 0;
            });

            if (empty($validIds)) {
                return response()->json(['error' => 'Invalid transaction IDs provided'], 400);
            }

            $validIds = array_map('intval', $validIds);

            $query = TransactionDetail::whereIn('id', $validIds);

            if (auth()->user()->role === "Dealer") {
                $query->where('dealer_id', auth()->user()->id);
            }

            $transactions = $query->get();

            if ($transactions->isEmpty()) {
                return response()->json(['error' => 'No valid transactions found or unauthorized'], 403);
            }

            $deletedIds = $transactions->pluck('id')->toArray();
            $deletedCount = TransactionDetail::whereIn('id', $deletedIds)->delete();

            return response()->json([
                'success' => "Successfully deleted {$deletedCount} transaction(s)",
                'deleted_count' => $deletedCount,
                'deleted_ids' => $deletedIds
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete transactions'], 500);
        }
    }

    public function getTransactions()
    {
        try {
            $transactions = DB::select('SELECT * FROM transaction_details');
            
            $transactionsArray = array_map(function($transaction) {
                $transactionData = (array) $transaction;
                
                foreach ($transactionData as $key => $value) {
                    if (is_null($value)) {
                        $transactionData[$key] = '';
                    }
                }
                
                return $transactionData;
            }, $transactions);
            
            \Log::info('Transactions synced: ' . count($transactionsArray));
            
            return response()->json($transactionsArray);
            
        } catch (\Exception $e) {
            \Log::error('Error fetching transactions: ' . $e->getMessage());
            
            return response()->json([
                'error' => 'Failed to fetch transactions: ' . $e->getMessage()
            ], 500);
        }
    }

    private function getProductPriceForUser(Product $product, $user = null)
    {
        $role = strtolower(str_replace([' ', '-'], '_', optional($user ?: auth()->user())->role ?? 'client'));
        $priceColumn = [
            'dealer' => 'dealer_price',
            'mega_dealer' => 'mega_dealer_price',
        ][$role] ?? 'price';

        return $product->{$priceColumn} ?? $product->price ?? 0;
    }

    private function getDealerProductStock(Product $product, $dealerId)
    {
        $productNames = collect([$product->product_name, $product->description])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $productSkus = collect([$product->sku])->filter()->values()->all();

        $completedOrderQty = 0;
        if (Schema::hasTable('order_details')) {
            $completedOrderQty = OrderDetail::query()
                ->where('dealer_id', $dealerId)
                ->where('status', 'Completed')
                ->where(function ($query) use ($product, $productNames, $productSkus) {
                    $query->whereIn('item', $productNames);

                    if (Schema::hasColumn('order_details', 'product_id')) {
                        $query->orWhere('product_id', $product->id);
                    }

                    if (Schema::hasColumn('order_details', 'sku') && !empty($productSkus)) {
                        $query->orWhereIn('sku', $productSkus);
                    }
                })
                ->sum('qty');
        }

        $soldQty = 0;
        if (Schema::hasTable('transaction_details')) {
            $soldQty = TransactionDetail::where('dealer_id', $dealerId)
                ->where(function ($query) use ($product, $productNames, $productSkus) {
                    $query->whereIn('item', $productNames)
                        ->orWhereIn('item_description', $productNames);

                    if (Schema::hasColumn('transaction_details', 'product_id')) {
                        $query->orWhere('product_id', $product->id);
                    }

                    if (Schema::hasColumn('transaction_details', 'sku') && !empty($productSkus)) {
                        $query->orWhereIn('sku', $productSkus);
                    }
                })
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->sum('qty');
        }

        return max(0, (int) $completedOrderQty - (int) $soldQty);
    }
}
