<?php

namespace App\Http\Controllers;

use App\TransactionDetail;
use App\AreaDistributor;
use App\Item;
use App\Client;
use App\Dealer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CartController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

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
        [$originLat, $originLng] = $this->distanceOriginFor($user, $dealer);

        // Include every client in the dealer's MFI; a stove/serial record is
        // not required to appear in the customer selector.
        $customersQuery = Client::query();
        $dealerMfi = '';

        if ($user->isDealerLike()) {
            $dealerMfi = $user->usesSrpPricing()
                ? trim((string) optional($user->centerChief)->mfi)
                : trim((string) optional($dealer)->mfi);

            // Center Chief customer access is based on center_chiefs.mfi.
            // A missing MFI intentionally produces an empty customer list.
            if ($dealerMfi === '') {
                $customersQuery->whereRaw('1 = 0');
            } else {
                $customersQuery->whereRaw('LOWER(TRIM(mfi)) = ?', [strtolower($dealerMfi)]);
            }
        }

        // DMS controls whether a dealer's MFI may see full customer names.
        $showCustomerFullName = $dealerMfi !== '' &&
            Schema::connection('dms')->hasTable('mfis') &&
            (int) DB::connection('dms')->table('mfis')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($dealerMfi)])
                ->value('show_customer_full_name') === 1;

        $customers = $customersQuery->get();
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
        } elseif ($user->isDealerLike()) {
            $transactions = TransactionDetail::where('dealer_id', $user->id)->get();
        }

        $center = $user->usesSrpPricing()
            ? trim((string) optional($user->centerChief)->area)
            : trim((string) optional($dealer)->area);
        
        // ✅ STEP 1: compute ALL ADs with distance
        $adsWithDistance = $areaDistributor->map(function ($ad) use ($originLat, $originLng) {

            if (is_numeric($ad->latitude) && is_numeric($ad->longitude) &&
                is_numeric($originLat) && is_numeric($originLng)) {
                $ad->distance = $this->calculateDistance(
                    $originLat,
                    $originLng,
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
        $matchedADs = $center !== ''
            ? $adsWithDistance->filter(function ($ad) use ($center) {
                return $ad->areas->contains(function ($area) use ($center) {
                    return strtolower(trim((string) $area->area_name)) === strtolower($center);
                });
            })->values()
            : collect();

        $matchedADs = $matchedADs
            ->sortBy(fn ($ad) => $ad->distance === null ? PHP_FLOAT_MAX : $ad->distance)
            ->values();

        // A Center Chief must remain within the territory defined on their
        // center_chiefs profile. Dealers retain their current fallback.
        $availableADs = $user->usesSrpPricing()
            ? $matchedADs
            : ($matchedADs->isNotEmpty() ? $matchedADs : $areaDistributor);

        return view('cart', [
            'transactions' => $transactions,
            'items' => $items,
            'customers' => $customers,
            'dealers' => $dealers,
            'areaDistributor' => $areaDistributor,
            'userCenter' => $center,
            'matchedADs' => $matchedADs,
            'availableADs' => $availableADs,
            'showCustomerFullName' => $showCustomerFullName,
        ]);
    }

    /**
     * Center Chief distances must originate from the Center Chief profile,
     * not the legacy dealer compatibility record.
     */
    private function distanceOriginFor($user, $dealer)
    {
        if ($user->usesSrpPricing()) {
            return $this->centerChiefCoordinates($user->centerChief);
        }

        return [optional($dealer)->latitude, optional($dealer)->longitude];
    }

    /**
     * Resolve a Center Chief's structured profile address once, then retain
     * its coordinates for later distance calculations.
     */
    private function centerChiefCoordinates($centerChief)
    {
        if (!$centerChief) {
            return [null, null];
        }

        if (is_numeric($centerChief->latitude) && is_numeric($centerChief->longitude)) {
            return [(float) $centerChief->latitude, (float) $centerChief->longitude];
        }

        $address = collect([
            $centerChief->street_address,
            $centerChief->location_barangay,
            $centerChief->location_city,
            $centerChief->location_province,
            $centerChief->location_region,
            'Philippines',
        ])->filter()->implode(', ');

        $coordinates = $this->geocodeAddress($address);

        if ($coordinates === null) {
            return [null, null];
        }

        [$latitude, $longitude] = $coordinates;
        $centerChief->latitude = $latitude;
        $centerChief->longitude = $longitude;
        $centerChief->save();

        return $coordinates;
    }

    /**
     * Use OpenStreetMap's geocoder only when profile coordinates have not
     * yet been stored. The result is cached on the Center Chief profile.
     */
    private function geocodeAddress($address)
    {
        if (trim($address) === '') {
            return null;
        }

        $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
            'q' => $address,
            'format' => 'jsonv2',
            'limit' => 1,
            'countrycodes' => 'ph',
        ]);

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "User-Agent: CRMS-RISE/1.0\r\nAccept: application/json\r\n",
            ],
        ]);
        $response = @file_get_contents($url, false, $context);
        $results = $response === false ? null : json_decode($response, true);

        if (!is_array($results) || empty($results[0]) ||
            !isset($results[0]['lat'], $results[0]['lon']) ||
            !is_numeric($results[0]['lat']) || !is_numeric($results[0]['lon'])) {
            return null;
        }

        return [(float) $results[0]['lat'], (float) $results[0]['lon']];
    }

    private function calculateDistance($lat1, $lon1, $lat2, $lon2)
    {
        if (!is_numeric($lat1) || !is_numeric($lon1) || !is_numeric($lat2) || !is_numeric($lon2)) {
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
