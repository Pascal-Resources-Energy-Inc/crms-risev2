<?php

namespace App\Http\Controllers;

use App\TransactionDetail;
use App\AreaDistributor;
use App\Item;
use App\Client;
use App\Dealer;
use Illuminate\Http\Request;

class CartController extends Controller
{
    // public function index(Request $request)
    // {
    //     $customers = Client::whereHas('serial')->get();
    //     $items = Item::get();
    //     $dealers = Dealer::get();
    //     $transactions = [];
    //     //  dd(auth()->user());
    //     if(auth()->user()->role == "Admin")
    //     {
    //         $transactions = TransactionDetail::get();
    //     }
    //     elseif(auth()->user()->role == "Dealer")
    //     {
    //         $transactions = TransactionDetail::where('dealer_id',auth()->user()->id)->get();
    //     }
    //     return view('cart',
    //         array(
    //             'transactions' => $transactions,
    //             'items' => $items,
    //             'customers' => $customers,
    //             'dealers' => $dealers,
    //         )
    //     );
    // }
    // public function index(Request $request)
    // {
    //     $user = auth()->user();
    //     $dealer = $user->dealer;

    //     $dealerLat = $dealer->latitude;
    //     $dealerLng = $dealer->longitude;

    //     $customers = Client::whereHas('serial')->get();
    //     $items = Item::get();
    //     $dealers = Dealer::get();
    //     $transactions = [];

    //     $areaDistributor = AreaDistributor::with('areas')->get();

    //     if ($user->role == "Admin") {
    //         $transactions = TransactionDetail::get();
    //     } elseif ($user->role == "Dealer") {
    //         $transactions = TransactionDetail::where('dealer_id', $user->id)->get();
    //     }

    //     $center = optional($user->dealer)->area;

    //     // ✅ GET ALL MATCHING ADs (NOT just first)
    //     $matchedADs = collect();

    //     foreach ($areaDistributor as $ad) {
    //         if (!$ad->latitude || !$ad->longitude) continue;

    //         $distance = $this->calculateDistance(
    //             $dealerLat,
    //             $dealerLng,
    //             $ad->latitude,
    //             $ad->longitude
    //         );

    //         $ad->distance = $distance; // attach dynamically
            
    //         $matchedADs->push($ad);
    //     }

    //     if ($center) {
    //         $matchedADs = AreaDistributor::with('areas')->whereHas('areas', function ($q) use ($center) {
    //             $q->where('area_name', $center);
    //         })->get();
    //         // dd($matchedADs);
    //     }

    //     // ✅ If no match → fallback to ALL
    //     // $availableADs = $matchedADs->isNotEmpty() ? $matchedADs : $areaDistributor;
    //     $availableADs = $matchedADs->count()
    //         ? $matchedADs
    //         : AreaDistributor::with('areas')->get();

    //     return view('cart', [
    //         'transactions' => $transactions,
    //         'items' => $items,
    //         'customers' => $customers,
    //         'dealers' => $dealers,
    //         'areaDistributor' => $areaDistributor,
    //         'distance' => $distance,
    //         'userCenter' => $center,
    //         'matchedADs' => $matchedADs,     // ✅ collection
    //         'availableADs' => $availableADs, // ✅ always usable
    //     ]);
    // }

    public function index(Request $request)
    {
        $user = auth()->user();
        $dealer = $user->dealer;

        $dealerLat = $dealer->latitude;
        $dealerLng = $dealer->longitude;

        $customers = Client::whereHas('serial')->get();
        $items = Item::get();
        $dealers = Dealer::get();
        $transactions = [];

        // $areaDistributor = AreaDistributor::with('areas')->whereNull('deleted_at')->get();
        $areaDistributor = AreaDistributor::with([
            'areas' => function ($query) {
                $query->whereNull('deleted_at');
            }
        ])
        ->whereNull('deleted_at')
        ->get();
        // dd($areaDistributor);
        if ($user->role == "Admin") {
            $transactions = TransactionDetail::get();
        } elseif ($user->role == "Dealer") {
            $transactions = TransactionDetail::where('dealer_id', $user->id)->get();
        }

        $center = optional($dealer)->area;
        
        // ✅ STEP 1: compute ALL ADs with distance
        $adsWithDistance = $areaDistributor->map(function ($ad) use ($dealerLat, $dealerLng) {

            if ($ad->latitude && $ad->longitude && $dealerLat && $dealerLng) {
                $ad->distance = $this->calculateDistance(
                    $dealerLat,
                    $dealerLng,
                    $ad->latitude,
                    $ad->longitude
                );
            } else {
                $ad->distance = null;
            }

            return $ad;
        });

        // Keep every AD assigned to the dealer's area. Coordinates are optional;
        // when present, they only determine the display order.
        $matchedADs = $center
            ? $adsWithDistance->filter(function ($ad) use ($center) {
                return $ad->areas->contains('area_name', $center);
            })->values()
            : collect();

        $matchedADs = $matchedADs
            ->sortBy(fn ($ad) => $ad->distance === null ? PHP_FLOAT_MAX : $ad->distance)
            ->values();

        // If no AD is assigned to the dealer's area, let the dealer choose from all ADs.
        $availableADs = $matchedADs->isNotEmpty()
            ? $matchedADs
            : $areaDistributor;

        return view('cart', [
            'transactions' => $transactions,
            'items' => $items,
            'customers' => $customers,
            'dealers' => $dealers,
            'areaDistributor' => $areaDistributor,
            'userCenter' => $center,
            'matchedADs' => $matchedADs,
            'availableADs' => $availableADs,
        ]);
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (!$lat1 || !$lon1 || !$lat2 || !$lon2) {
            return null;
        }

        $earthRadius = 6371; // KM

        $latDiff = deg2rad($lat2 - $lat1);
        $lonDiff = deg2rad($lon2 - $lon1);

        $a = sin($latDiff / 2) * sin($latDiff / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($lonDiff / 2) * sin($lonDiff / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2); // rounded KM
    }
}
