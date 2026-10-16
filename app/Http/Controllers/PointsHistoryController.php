<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\TransactionDetail;
use App\RedeemedHistory;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class PointsHistoryController extends Controller
{
    /**
     * Point history is only available for an authenticated account.
     */
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        $user = Auth::user();
        
        // Get transactions based on user role
        if ($user->isDealerLike()) {
            // For dealers, show transactions they created
            $earnedPoints = TransactionDetail::where('dealer_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($transaction) {
                    return (object)[
                        'type' => 'earned',
                        'points' => $transaction->points_dealer, // Use dealer points
                        'description' => 'Sale: ' . $transaction->item,
                        'created_at' => $transaction->created_at,
                    ];
                });
        } else {
            // For clients, show their purchases
            $earnedPoints = TransactionDetail::where('client_id', $user->id)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($transaction) {
                    return (object)[
                        'type' => 'earned',
                        'points' => $transaction->points_client,
                        'description' => 'Purchased ' . $transaction->item,
                        'created_at' => $transaction->created_at,
                    ];
                });
        }
        
        // Get redeemed points from vouchers
        $redeemedPoints = RedeemedHistory::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function($voucher) {
                return (object)[
                    'type' => 'redeemed',
                    'points' => $voucher->points_amount,
                    'description' => 'Redeemed ' . ($voucher->reward ? $voucher->reward->description : 'Voucher'),
                    'created_at' => $voucher->created_at,
                ];
            });
        
        // Merge and sort by date (newest first)
        $allHistory = $earnedPoints->concat($redeemedPoints)
            ->sortByDesc('created_at');
        
        // Group by month
        $groupedHistory = $allHistory->groupBy(function($item) {
            return Carbon::parse($item->created_at)->format('F Y');
        });
        
        return view('points-history', compact('groupedHistory'));
    }
}
