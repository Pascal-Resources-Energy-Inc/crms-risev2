<?php

namespace App\Http\Controllers;

use App\Dealer;
use App\Client;
use App\TransactionDetail;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    public function index(){
        if(auth()->user()->isDealerLike())
        {
             $profile = Dealer::where('user_id',auth()->user()->id)->first();
             $transactions = TransactionDetail::where('dealer_id',$profile->user_id)->get();
        }
        else
        {
            $profile = Client::where('user_id',auth()->user()->id)->first();
            $transactions = TransactionDetail::where('client_id',$profile->id)->get();
        }
       
       return view('history',
            array(
                'profile' => $profile,
                'transactions' => $transactions,
            )
        );
    }
}
