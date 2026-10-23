<?php

namespace App\Http\Controllers;
use App\Transaction;
use App\Dealer;
use App\CenterChief;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use App\Client;
use Illuminate\Support\Collection;
use App\TransactionDetail;
use App\OrderDetail;
use Illuminate\Http\Request;
use App\Product;
use App\RedeemedHistory;
use Illuminate\Support\Facades\Schema;
use Mail;
use App\Mail\OrderCompletedMail;

class HomeController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $dealer = "";
        $customer = "";
        $centerChief = null;
        $threeDaysAgo = Carbon::now()->subDays(7)->toDateString();
        
        // Get selected year and month from request
        $selectedYear = $request->get('year', Carbon::now()->year);
        $selectedMonth = $request->get('month', null);
        $viewType = $selectedMonth ? 'monthly' : 'yearly';
        
        $customers_less = Client::whereDoesntHave('latestTransaction', function ($q) use ($threeDaysAgo) {
            $q->where('date', '>=', $threeDaysAgo);
        })
        ->whereHas('latestTransaction')
        ->orderBy(
            DB::raw('(SELECT date FROM transaction_details WHERE transaction_details.client_id = clients.id ORDER BY date DESC LIMIT 1)'),
            'desc'
        )
        ->get();

        $customers = Client::whereHas('transactions')->get();
        // $transactions = Transaction::orderBy('id','desc')->get();
        $dealers = Dealer::get();
        $client_transaction_feed = TransactionDetail::with('customer')
            ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                $query->whereIn('status', ['Completed', 'Pending']);
            })
            ->orderBy('id', 'desc')
            ->take(10)
            ->get();
        $transactions_details = TransactionDetail::with('customer')
        ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
            $query->where('status', 'Completed');
        })
        ->orderBy('id', 'desc')
        ->take(10)
        ->get();

        // $ad_transactions = OrderDetail::whereNotNull('ad_id')
        //     // ->with(['ad', 'areas'])
        //     ->with(['ad.areas']) 
        //     ->latest()
        //     ->take(20)
        //     ->get();
        $ad_transactions = OrderDetail::whereNotNull('ad_id')
            ->with([
                'ad.areas' => function ($query) {
                    $query->whereNull('deleted_at');
                }
            ])
            ->latest()
            ->take(20)
            ->get();
        // dd($ad_transactions);

        if (auth()->user()->isDealerLike()) {
            $userId = auth()->id();

            $dealer = Dealer::with('sales')->where('user_id', $userId)->first();
            $centerChief = auth()->user()->usesSrpPricing()
                ? CenterChief::where('user_id', $userId)->first()
                : null;

            $transactions_raw = TransactionDetail::where('dealer_id', $userId)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->latest()
                ->get();
            $client_feed_raw = TransactionDetail::where('dealer_id', $userId)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->whereIn('status', ['Completed', 'Pending']);
                })
                ->latest()
                ->get();
            $orders_raw = OrderDetail::where('dealer_id', $userId)
                ->latest()
                ->get();
            // dd($userId); 

            $transactions_details = $transactions_raw->groupBy(fn($item) =>
                "{$item->client_id}_{$item->created_at->format('Y-m-d H:i:s')}"
            )->map(function ($group) {
                $first = $group->first();
                $first->qty = $group->sum('qty');
                $first->transaction_count = $group->count();
                return $first;
            })->values();

            $client_transaction_feed = $client_feed_raw->groupBy(fn($item) =>
                "{$item->client_id}_{$item->created_at->format('Y-m-d H:i:s')}"
            )->map(function ($group) {
                $first = $group->first();
                $first->qty = $group->sum('qty');
                $first->transaction_count = $group->count();
                $first->status = $group->contains(fn($item) => $item->status === 'Pending') ? 'Pending' : 'Completed';
                return $first;
            })->values();

            // $ad_transactions = $orders_raw->groupBy(fn($item) =>
            //     "{$item->client_id}_{$item->created_at->format('Y-m-d H:i:s')}"
            // )->map(function ($group) {
            //     $first = $group->first();
            //     $first->qty = $group->sum('qty');
            //     $first->transaction_count = $group->count();
            //     return $first;
            // })->values();
            $ad_transactions = $orders_raw
                ->groupBy(function ($item) {
                    return $item->ad_id . '_' . $item->created_at->format('Y-m-d H:i:s');
                })
                ->map(function ($group) {

                    $first = $group->first();

                    return [
                        'id' => $first->id, 
                        'ad' => $first->ad,
                        'created_at' => $first->created_at,
                        'status' => $first->status,
                        'ad_address' => $first->ad_address,
                        'subtotal' => $group->sum(fn($i) => $i->price * $i->qty),
                        'delivery_fee' => $group->max(fn($i) => (float) ($i->delivery_fee ?? 0)),
                        'price_total' => $group->sum(fn($i) => $i->price * $i->qty)
                            + $group->max(fn($i) => (float) ($i->delivery_fee ?? 0)),
                        'payment_method' => $first->payment_method ?? 'cash',
                        'delivery_type'  => $first->delivery_type ?? 'pickup',
                        'items' => $group->map(function ($i) {
                            return [
                                'name' => $i->item,
                                'qty' => $i->qty,
                                'price' => $i->price,
                                'total' => $i->price * $i->qty,
                                'payment_method' => $i->payment_method ?? 'cash',
                                'delivery_type' => $i->delivery_type ?? 'pickup',
                            ];
                        })->values()
                    ];
                })
                ->values();
            // dd($ad_transactions);
            $total_sales = TransactionDetail::where('dealer_id', $userId)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->sum('price');

            $totalEarnedPointsDealer = $transactions_raw->sum('points_dealer');
            $redeemedPointsDealer = abs(RedeemedHistory::where('user_id', $userId)->sum('points_amount'));
            $dealerAvailablePoints = $totalEarnedPointsDealer - $redeemedPointsDealer;
        }

        if (auth()->user()->role === 'Client') {
            $userId = auth()->id();

            $customer = Client::with('transactions')->where('user_id', $userId)->first();

            $transactions_raw = TransactionDetail::where('client_id', $customer->id)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->latest()
                ->get();
            $client_feed_raw = TransactionDetail::where('client_id', $customer->id)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->whereIn('status', ['Completed', 'Pending']);
                })
                ->latest()
                ->get();

            $transactions_details = $transactions_raw->groupBy(fn($item) =>
                "{$item->dealer_id}_{$item->created_at->format('Y-m-d H:i:s')}"
            )->map(function ($group) {
                $first = $group->first();
                $first->qty = $group->sum('qty');
                $first->transaction_count = $group->count();
                return $first;
            })->values();

            $client_transaction_feed = $client_feed_raw->groupBy(fn($item) =>
                "{$item->dealer_id}_{$item->created_at->format('Y-m-d H:i:s')}"
            )->map(function ($group) {
                $first = $group->first();
                $first->qty = $group->sum('qty');
                $first->transaction_count = $group->count();
                $first->status = $group->contains(fn($item) => $item->status === 'Pending') ? 'Pending' : 'Completed';
                return $first;
            })->values();

            $total_sales = TransactionDetail::where('client_id', $customer->id)
                ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                    $query->where('status', 'Completed');
                })
                ->sum('price');

            $totalEarnedPointsCustomer = $transactions_raw->sum('points_client');
            $redeemedPointsCustomer = abs(RedeemedHistory::where('user_id', $userId)->sum('points_amount'));
            $customerAvailablePoints = $totalEarnedPointsCustomer - $redeemedPointsCustomer;
        }

        // Get chart data based on view type
        if ($viewType === 'monthly') {
            $chartData = $this->getDailyData($selectedYear, $selectedMonth);
        } else {
            $chartData = $this->getMonthlyData($selectedYear);
        }
        
        $categories = $chartData['categories'];
        $qty = $chartData['qty'];

        // Get available years and months for dropdowns
        $availableYears = $this->getAvailableYears();
        $availableMonths = $this->getAvailableMonths($selectedYear);

        $dealers = TransactionDetail::select(
            'dealer_id',
            DB::raw('SUM(points_dealer) as total_points'),
            DB::raw('MAX(date) as latest_transaction')
        )
        ->with('dealer')
        ->groupBy('dealer_id')
        ->orderByDesc('total_points')
        ->get();

        $top_customers = TransactionDetail::select(
            'client_id',
            DB::raw('SUM(points_client) as total_points'),
            DB::raw('MAX(created_at) as latest_transaction')
        )
        ->with('customer')
        ->whereNotNull('client_id')
        ->groupBy('client_id')
        ->orderByDesc('total_points')
        ->limit(10)
        ->get();

        $salesTrend = $this->calculateSalesTrend();
        $qtyTrend = $this->calculateQtyTrend();

        return view('home',
            array(
                // 'transactions' => $transactions,
                'transactions_details' => $transactions_details,
                'client_transaction_feed' => $client_transaction_feed,
                'ad_transactions' => $ad_transactions,
                'dealers' => $dealers,
                'categories' =>  $categories,
                'qty' =>  $qty,
                'customers' =>  $customers,
                'dealer' =>  $dealer,
                'center_chief' => $centerChief,
                'customer' =>  $customer,
                'customers_less' =>  $customers_less,
                'total_sales' => $total_sales,
                'top_customers' => $top_customers,
                'sales_trend' => $salesTrend,
                'qty_trend' => $qtyTrend,
                'available_years' => $availableYears,
                'available_months' => $availableMonths,
                'selected_year' => $selectedYear,
                'selected_month' => $selectedMonth,
                'view_type' => $viewType,
                'dealer_available_points' => $dealerAvailablePoints ?? 0,
                'customer_available_points' => $customerAvailablePoints ?? 0,
            )
        );
    }

    public function getChartDataAjax(Request $request)
    {
        $year = $request->get('year', Carbon::now()->year);
        $month = $request->get('month', null);
        $viewType = $month ? 'monthly' : 'yearly';
        
        if (!is_numeric($year) || $year < 1900 || $year > Carbon::now()->year + 10) {
            return response()->json(['error' => 'Invalid year'], 400);
        }
        
        if ($month !== null && (!is_numeric($month) || $month < 1 || $month > 12)) {
            return response()->json(['error' => 'Invalid month'], 400);
        }
        
        if ($viewType === 'monthly') {
            $chartData = $this->getDailyData($year, $month);
        } else {
            $chartData = $this->getMonthlyData($year);
        }
        
        $availableMonths = $this->getAvailableMonths($year);
        
        $totalRecords = DB::table('transaction_details')
            ->whereYear('created_at', $year)
            ->when($month, function ($query) use ($year, $month) {
                return $query->whereMonth('created_at', $month);
            })
            ->count();
        
        return response()->json([
            'categories' => $chartData['categories'],
            'qty' => $chartData['qty'],
            'year' => (int) $year,
            'month' => $month ? (int) $month : null,
            'view_type' => $viewType,
            'available_months' => $availableMonths,
            'total_records' => $totalRecords,
            'debug' => [
                'requested_year' => $year,
                'requested_month' => $month,
                'data_found' => $totalRecords > 0
            ]
        ]);
    }

    /**
     * Get monthly sales data for a specific year
     */
    private function getMonthlyData($year)
    {
        $year = (int) $year;
        
        $sales = DB::table('transaction_details')
            ->selectRaw('MONTH(created_at) as month_number, MONTHNAME(created_at) as month_name, SUM(qty) as total_qty')
            ->whereYear('created_at', $year)
            ->whereNotNull('created_at')
            ->groupBy(DB::raw('MONTH(created_at), MONTHNAME(created_at)'))
            ->orderBy('month_number')
            ->get();

        $salesData = $sales->keyBy('month_number');

        $allMonths = collect(range(1, 12))->map(function ($monthNumber) use ($salesData) {
            $monthData = $salesData->get($monthNumber);
            return [
                'month' => Carbon::create()->month($monthNumber)->format('F'),
                'total_qty' => $monthData ? (int) $monthData->total_qty : 0,
            ];
        });

        $categories = $allMonths->pluck('month')->toArray();
        $qty = $allMonths->pluck('total_qty')->toArray();

        return [
            'categories' => $categories,
            'qty' => $qty
        ];
    }

    /**
     * Get daily sales data for a specific year and month
     */
    private function getDailyData($year, $month)
    {
        $year = (int) $year;
        $month = (int) $month;
        
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;
        
        $sales = DB::table('transaction_details')
            ->selectRaw('DAY(created_at) as day_number, SUM(qty) as total_qty')
            ->whereYear('created_at', $year)
            ->whereMonth('created_at', $month)
            ->whereNotNull('created_at')
            ->groupBy(DB::raw('DAY(created_at)'))
            ->orderBy('day_number')
            ->get();

        $salesData = $sales->keyBy('day_number');

        $allDays = collect(range(1, $daysInMonth))->map(function ($dayNumber) use ($salesData) {
            $dayData = $salesData->get($dayNumber);
            return [
                'day' => $dayNumber,
                'total_qty' => $dayData ? (int) $dayData->total_qty : 0,
            ];
        });

        $categories = $allDays->pluck('day')->toArray();
        $qty = $allDays->pluck('total_qty')->toArray();

        return [
            'categories' => $categories,
            'qty' => $qty
        ];
    }

   
    private function getAvailableYears()
    {
        $years = DB::table('transaction_details')
            ->selectRaw('DISTINCT YEAR(created_at) as year')
            ->whereNotNull('created_at')
            ->orderBy('year', 'desc')
            ->pluck('year')
            ->toArray();

        if (empty($years)) {
            $years = [Carbon::now()->year];
        }

        return $years;
    }

    /**
     * Get available months for a specific year
     */
    private function getAvailableMonths($year)
    {
        $months = DB::table('transaction_details')
            ->selectRaw('DISTINCT MONTH(created_at) as month_number, MONTHNAME(created_at) as month_name')
            ->whereYear('created_at', $year)
            ->whereNotNull('created_at')
            ->orderBy('month_number')
            ->get()
            ->map(function ($month) {
                return [
                    'number' => $month->month_number,
                    'name' => $month->month_name
                ];
            })
            ->toArray();

        return $months;
    }

    private function calculateSalesTrend()
    {
        $currentYear = Carbon::now()->year;
        $previousYear = $currentYear - 1;
        
        $currentYearSales = TransactionDetail::whereYear('created_at', $currentYear)->sum('price');
        $previousYearSales = TransactionDetail::whereYear('created_at', $previousYear)->sum('price');
        
        if ($previousYearSales == 0) {
            return [
                'percentage' => $currentYearSales > 0 ? 100 : 0,
                'trend' => $currentYearSales > 0 ? 'up' : 'neutral',
                'icon' => $currentYearSales > 0 ? 'ti-trending-up' : 'ti-minus'
            ];
        }
        
        $percentageChange = (($currentYearSales - $previousYearSales) / $previousYearSales) * 100;
        
        return [
            'percentage' => round(abs($percentageChange), 2),
            'trend' => $percentageChange > 0 ? 'up' : ($percentageChange < 0 ? 'down' : 'neutral'),
            'icon' => $percentageChange > 0 ? 'ti-trending-up' : ($percentageChange < 0 ? 'ti-trending-down' : 'ti-minus')
        ];
    }

    private function calculateQtyTrend()
    {
        $currentYear = Carbon::now()->year;
        $previousYear = $currentYear - 1;
        
        $currentYearQty = TransactionDetail::whereYear('created_at', $currentYear)->sum('qty');
        $previousYearQty = TransactionDetail::whereYear('created_at', $previousYear)->sum('qty');
        
        if ($previousYearQty == 0) {
            return [
                'percentage' => $currentYearQty > 0 ? 100 : 0,
                'trend' => $currentYearQty > 0 ? 'up' : 'neutral',
                'icon' => $currentYearQty > 0 ? 'ti-trending-up' : 'ti-minus'
            ];
        }
        
        $percentageChange = (($currentYearQty - $previousYearQty) / $previousYearQty) * 100;
        
        return [
            'percentage' => round(abs($percentageChange), 2),
            'trend' => $percentageChange > 0 ? 'up' : ($percentageChange < 0 ? 'down' : 'neutral'),
            'icon' => $percentageChange > 0 ? 'ti-trending-up' : ($percentageChange < 0 ? 'ti-trending-down' : 'ti-minus')
        ];
    }

    public function about()
    {
        return view('about');
    }
    
    public function storelocation()
    {
        $dealers = $this->getFormattedDealers();
        $customers = $this->getFormattedCustomers();
        $locations = $dealers->concat($customers);
        return view('storelocation', compact('locations'));
    }

    public function getLocationsForMap()
    {
        $dealers = $this->getFormattedDealers(true);
        $customers = $this->getFormattedCustomers(true);
        $locations = $dealers->concat($customers);
        return response()->json($locations);
    }

    public function getLocationDetails($id, $type)
    {
        $location = null;

        if ($type === 'dealer') {
            $location = Dealer::select('id', 'name', 'address', 'store_name', 'store_type', 'number', 'email_address', 'latitude', 'longitude')
                ->where('id', $id)
                ->where('status', 'Active')
                ->first();

            if ($location) {
                $location->location_type = 'dealer';
            }
        } elseif ($type === 'customer') {
            $location = Client::select('id', 'name', 'address', 'number', 'email_address')
                ->where('id', $id)
                ->first();

            if ($location) {
                $location->store_name = $location->name;
                $location->store_type = null;
                $location->location_type = 'customer';
                $location->latitude = null;
                $location->longitude = null;
            }
        }

        if (!$location) {
            return response()->json(['error' => 'Location not found'], 404);
        }

        return response()->json($location);
    }

    private function getFormattedDealers($withCoordinates = false)
    {
        $columns = ['id', 'name', 'address', 'store_name', 'store_type', 'number', 'email_address'];

        if ($withCoordinates) {
            $columns[] = 'latitude';
            $columns[] = 'longitude';
        }

        return Dealer::select($columns)
            ->where('status', 'Active')
            ->whereNotNull('address')
            ->get()
            ->map(function ($dealer) {
                $dealer->location_type = 'dealer';
                return $dealer;
            });
    }

    private function getFormattedCustomers($withCoordinates = false)
    {
        $customers = Client::select('id', 'name', 'address', 'number', 'email_address')
            ->whereNotNull('address')
            ->get()
            ->map(function ($customer) use ($withCoordinates) {
                $customer->store_name = $customer->name;
                $customer->store_type = null;
                $customer->location_type = 'customer';

                if ($withCoordinates) {
                    $customer->latitude = null;
                    $customer->longitude = null;
                }

                return $customer;
            });

        return $customers;
    }

    // public function markCompleted($id)
    // {
    //     try {
    //         $order = OrderDetail::find($id);

    //         if (!$order) {
    //             return response()->json([
    //                 'success' => false,
    //                 'message' => 'Order not found'
    //             ], 404);
    //         }

    //         $order->status = 'Completed';
    //         $order->save();

    //         return response()->json([
    //             'success' => true,
    //             'message' => 'Order marked as completed'
    //         ]);

    //     } catch (\Exception $e) {
    //         return response()->json([
    //             'success' => false,
    //             'message' => $e->getMessage()
    //         ], 500);
    //     }
    // }
    public function markCompleted(Request $request, $id)
    {
        try {
            $remarks = trim((string) $request->input('remarks', ''));

            if ($remarks === '') {
                return response()->json([
                    'success' => false,
                    'message' => 'Remarks are required before marking the order as completed.'
                ], 422);
            }

            if (strlen($remarks) > 500) {
                return response()->json([
                    'success' => false,
                    'message' => 'Remarks may not be greater than 500 characters.'
                ], 422);
            }

            $order = OrderDetail::with(['ad', 'dealer', 'product'])->find($id);

            if (!$order) {

                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);

            }

            // UPDATE STATUS
            $order->status = 'Completed';
            $order->completed_at = now();

            if (Schema::hasColumn('order_details', 'remarks')) {
                $order->remarks = $remarks;
            }

            $order->save();

            $completedPendingCount = $this->completePendingClientTransactionsForOrder($order);

            if (
                !empty($order->ad) &&
                !empty($order->ad->email_address)
            ) {

                Mail::to($order->ad->email_address)
                    ->send(new OrderCompletedMail($order));

            }

            return response()->json([
                'success' => true,
                'message' => $completedPendingCount > 0
                    ? "Order marked as completed. {$completedPendingCount} pending client transaction(s) completed."
                    : 'Order marked as completed.'
            ]);

        } catch (\Exception $e) {

            \Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    public function cancelPendingOrder(Request $request, $id)
    {
        try {
            $remarks = trim((string) $request->input('remarks', ''));

            if (strlen($remarks) > 500) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cancellation reason may not be greater than 500 characters.'
                ], 422);
            }

            $order = OrderDetail::find($id);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            if ($order->status !== 'Pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Only pending orders can be cancelled.'
                ], 409);
            }

            $cancelExpiresAt = $order->created_at->copy()->addHour();

            if (now()->greaterThan($cancelExpiresAt)) {
                return response()->json([
                    'success' => false,
                    'message' => 'This order can only be cancelled within 1 hour after it was created.'
                ], 409);
            }

            $cancelledCount = DB::transaction(function () use ($order, $remarks) {
                $orders = OrderDetail::where('dealer_id', $order->dealer_id)
                    ->where('ad_id', $order->ad_id)
                    ->where('created_at', $order->created_at)
                    ->where('status', 'Pending')
                    ->lockForUpdate()
                    ->get();

                foreach ($orders as $pendingOrder) {
                    $pendingOrder->status = 'Cancelled';

                    if (Schema::hasColumn('order_details', 'cancelled_at')) {
                        $pendingOrder->cancelled_at = now();
                    }

                    if (Schema::hasColumn('order_details', 'remarks')) {
                        $pendingOrder->remarks = $remarks !== ''
                            ? 'Cancelled: ' . $remarks
                            : 'Cancelled within 1 hour of order creation.';
                    }

                    $pendingOrder->save();
                }

                return $orders->count();
            });

            return response()->json([
                'success' => true,
                'message' => $cancelledCount > 1
                    ? "{$cancelledCount} order item(s) cancelled successfully."
                    : 'Order cancelled successfully.'
            ]);

        } catch (\Exception $e) {

            \Log::error($e);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);

        }
    }

    private function completePendingClientTransactionsForOrder(OrderDetail $order)
    {
        if (!Schema::hasColumn('transaction_details', 'status')) {
            return 0;
        }

        $availableStock = $this->getAvailableDealerStockForOrderProduct($order);

        if ($availableStock <= 0) {
            return 0;
        }

        // An item description can be shared by different products. Use the
        // ordered product name so one product cannot consume another's stock.
        $productNames = collect([$order->item])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $productSkus = collect([$order->sku])->filter()->values()->all();
        $hasSku = Schema::hasColumn('transaction_details', 'sku');
        $completedCount = 0;

        $pendingTransactions = TransactionDetail::where('dealer_id', $order->dealer_id)
            ->where('status', 'Pending')
            ->where(function ($query) use ($productNames, $productSkus, $hasSku) {
                $query->whereIn('item', $productNames);

                if ($hasSku && !empty($productSkus)) {
                    $query->orWhereIn('sku', $productSkus);
                }
            })
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();

        foreach ($pendingTransactions as $transaction) {
            $qty = (int) $transaction->qty;

            if ($qty <= 0 || $qty > $availableStock) {
                continue;
            }

            $transaction->status = 'Completed';
            $transaction->save();

            $availableStock -= $qty;
            $completedCount++;
        }

        return $completedCount;
    }

    private function getAvailableDealerStockForOrderProduct(OrderDetail $order)
    {
        $productNames = collect([$order->item])
            ->filter()
            ->unique()
            ->values()
            ->all();
        $productSkus = collect([$order->sku])->filter()->values()->all();

        $completedOrderQty = OrderDetail::where('dealer_id', $order->dealer_id)
            ->where('status', 'Completed')
            ->where(function ($query) use ($productNames, $productSkus) {
                $query->whereIn('item', $productNames);

                if (Schema::hasColumn('order_details', 'sku') && !empty($productSkus)) {
                    $query->orWhereIn('sku', $productSkus);
                }
            })
            ->sum('qty');

        $completedSalesQty = TransactionDetail::where('dealer_id', $order->dealer_id)
            ->where('status', 'Completed')
            ->where(function ($query) use ($productNames, $productSkus) {
                $query->whereIn('item', $productNames);

                if (Schema::hasColumn('transaction_details', 'sku') && !empty($productSkus)) {
                    $query->orWhereIn('sku', $productSkus);
                }
            })
            ->sum('qty');

        return max(0, (int) $completedOrderQty - (int) $completedSalesQty);
    }

}
