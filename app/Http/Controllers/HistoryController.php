<?php

namespace App\Http\Controllers;

use App\Dealer;
use App\Client;
use App\TransactionDetail;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(){
        $user = auth()->user();

        if ($user->isDealerLike()) {
            // History is keyed by the authenticated account ID. A Mega Dealer
            // profile lives in mega_dealers, so never require a Dealer row.
            $profile = $user->usesSrpPricing()
                ? $user->centerChief
                : ($user->usesMegaDealerPricing() ? $user->megaDealer : $user->dealer);
            $transactions = TransactionDetail::where('dealer_id', $user->id)->get();
        } else {
            $profile = $user->client;
            $transactions = $profile
                ? TransactionDetail::where('client_id', $profile->id)->get()
                : collect();
        }
       
       return view('history',
            array(
                'profile' => $profile,
                'transactions' => $transactions,
            )
        );
    }
}
