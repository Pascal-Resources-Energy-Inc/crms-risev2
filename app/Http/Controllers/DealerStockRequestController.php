<?php

namespace App\Http\Controllers;

use App\DealerStockRequest;
use App\OrderDetail;
use App\Product;
use App\TransactionDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DealerStockRequestController extends Controller
{
    public function __construct() { $this->middleware('auth'); }

    public function store(Request $request)
    {
        abort_unless(auth()->user()->role === 'Dealer', 403);
        $request->validate(['product_id' => 'required|integer|exists:dms.products,id', 'quantity' => 'required|integer|min:1|max:100000']);
        $product = Product::findOrFail($request->product_id);

        DB::transaction(function () use ($request, $product) {
            $existing = DealerStockRequest::where('dealer_id', auth()->id())->where('product_id', $product->id)->lockForUpdate()->first();
            abort_if($this->stockForDealer($product, auth()->id()) > 0, 422, 'You already have stock for this item. You cannot request more until all current stock is used.');
            abort_if($existing && $existing->status === 'Pending', 422, 'This item already has a pending stock request.');
            DealerStockRequest::updateOrCreate(
                ['dealer_id' => auth()->id(), 'product_id' => $product->id],
                ['quantity' => $request->quantity, 'status' => 'Pending', 'remarks' => null, 'reviewed_by' => null, 'reviewed_at' => null, 'approved_order_id' => null]
            );
        });
        return redirect()->route('dealer.stock.inventory')->with('success', 'Stock request submitted for admin approval.');
    }

    public function adminIndex()
    {
        abort_unless(auth()->user()->role === 'Admin', 403);
        $requests = DealerStockRequest::with(['dealer.dealer', 'reviewer'])->latest()->get();
        return view('admin_stock_requests', ['requests' => $requests, 'products' => Product::whereIn('id', $requests->pluck('product_id')->unique())->get()->keyBy('id')]);
    }

    public function approve(DealerStockRequest $stockRequest)
    {
        abort_unless(auth()->user()->role === 'Admin', 403);
        DB::transaction(function () use ($stockRequest) {
            $stockRequest = DealerStockRequest::lockForUpdate()->findOrFail($stockRequest->id);
            abort_unless($stockRequest->status === 'Pending', 422, 'This request has already been reviewed.');
            $product = Product::findOrFail($stockRequest->product_id);
            abort_if($this->stockForDealer($product, $stockRequest->dealer_id) > 0, 422, 'The dealer already has this item in stock. Approval is blocked.');
            $last = OrderDetail::latest('id')->first();
            $order = new OrderDetail;
            if (Schema::hasColumn('order_details', 'product_id')) $order->product_id = $product->id;
            $order->item = $product->product_name;
            if (Schema::hasColumn('order_details', 'sku')) $order->sku = $product->sku;
            $order->transaction_id = 'STK-' . str_pad(($last ? $last->id : 0) + 1, 6, '0', STR_PAD_LEFT);
            $order->item_description = $product->description;
            $order->qty = $stockRequest->quantity;
            $order->price = $product->dealer_price ?? $product->price ?? 0;
            $order->date = now()->toDateString();
            $order->dealer_id = $stockRequest->dealer_id;
            $order->created_by = auth()->id();
            $order->status = 'Completed';
            if (Schema::hasColumn('order_details', 'completed_at')) $order->completed_at = now();
            if (Schema::hasColumn('order_details', 'remarks')) $order->remarks = 'Dealer stock request approved by ' . auth()->user()->name;
            $order->save();
            $stockRequest->update(['status' => 'Approved', 'reviewed_by' => auth()->id(), 'reviewed_at' => now(), 'approved_order_id' => $order->id]);
        });
        return back()->with('success', 'Approved. The item has been added to the dealer inventory.');
    }

    public function reject(Request $request, DealerStockRequest $stockRequest)
    {
        abort_unless(auth()->user()->role === 'Admin', 403);
        $request->validate(['remarks' => 'required|string|max:500']);
        abort_unless($stockRequest->status === 'Pending', 422, 'This request has already been reviewed.');
        $stockRequest->update(['status' => 'Rejected', 'remarks' => $request->remarks, 'reviewed_by' => auth()->id(), 'reviewed_at' => now()]);
        return back()->with('success', 'Request rejected and the dealer has been notified in their inventory.');
    }

    private function stockForDealer(Product $product, $dealerId)
    {
        $orders = OrderDetail::where('dealer_id', $dealerId)->where('status', 'Completed')->where(function ($q) use ($product) {
            Schema::hasColumn('order_details', 'product_id') ? $q->where('product_id', $product->id)->orWhere('item', $product->product_name) : $q->where('item', $product->product_name);
        })->sum('qty');
        $sales = TransactionDetail::where('dealer_id', $dealerId)->when(Schema::hasColumn('transaction_details', 'status'), function ($q) { $q->where('status', 'Completed'); })->where(function ($q) use ($product) {
            Schema::hasColumn('transaction_details', 'product_id') ? $q->where('product_id', $product->id)->orWhere('item', $product->product_name)->orWhere('item_description', $product->product_name) : $q->where('item', $product->product_name)->orWhere('item_description', $product->product_name);
        })->sum('qty');
        return max(0, (int) $orders - (int) $sales);
    }
}
