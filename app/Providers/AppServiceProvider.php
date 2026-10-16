<?php

namespace App\Providers;

use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Auth;
use App\Dealer;
use App\CenterChief;
use App\Client;
use App\Item;
use App\RedeemedHistory;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer('*', function ($view) {
            $profile = null;
            $unreadNotifications = 0;
            $dealer_available_points = 0;
            $availablePoints = 0;
            
            if (Auth::check()) {
                $user = Auth::user();

                if ($user->usesSrpPricing()) {
                    // Center Chief identity and profile details are owned by
                    // center_chiefs; the Dealer record is only a workspace
                    // compatibility link.
                    $profile = CenterChief::where('user_id', $user->id)->first();

                    $unreadNotifications = RedeemedHistory::where('user_id', $user->id)
                        ->where('viewed', 0)
                        ->count();

                    $totalPoints = \App\TransactionDetail::where('dealer_id', $user->id)->sum('points_dealer');
                    $redeemedPoints = abs(RedeemedHistory::where('user_id', $user->id)->sum('points_amount'));
                    $availablePoints = $totalPoints - $redeemedPoints;
                    $dealer_available_points = $availablePoints;
                } elseif ($user->isDealerLike()) {
                    $profile = Dealer::where('user_id', $user->id)->first();
                    
                    $unreadNotifications = RedeemedHistory::where('user_id', $user->id)
                        ->where('viewed', 0)
                        ->count();
                        
                    if ($profile) {
                        $totalPoints = $profile->sales->sum('points_dealer');
                        $redeemedPoints = abs(RedeemedHistory::where('user_id', $user->id)->sum('points_amount'));
                        $availablePoints = $totalPoints - $redeemedPoints;
                        $dealer_available_points = $availablePoints;
                    }
                } elseif ($user->role == 'Client') {
                    $profile = Client::where('user_id', $user->id)->first();
                    
                    $unreadNotifications = RedeemedHistory::where('user_id', $user->id)
                        ->where('viewed', 0)
                        ->count();
                    
                    if ($profile) {
                        $totalPoints = $profile->transactions->sum('points_client');
                        $redeemedPoints = abs(RedeemedHistory::where('user_id', $user->id)->sum('points_amount'));
                        $availablePoints = $totalPoints - $redeemedPoints;
                    }
                }
            }
            
            $items = Item::all();
            
            $view->with([
                'profile' => $profile,
                'items' => $items,
                'unreadNotifications' => $unreadNotifications,
                'dealer_available_points' => $dealer_available_points,
                'availablePoints' => $availablePoints
            ]);
        });
    }
}
