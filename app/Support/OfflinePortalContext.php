<?php

namespace App\Support;

use App\AreaDistributor;
use App\Client;
use App\OrderDetail;
use App\Product;
use App\TransactionDetail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Server-owned assignments, prices and stock for the saved dealer workspace. */
class OfflinePortalContext
{
    public function profile($user)
    {
        if ($user->isDealerLike()) {
            return $user->usesSrpPricing() ? $user->centerChief
                : ($user->usesMegaDealerPricing() ? $user->megaDealer : $user->dealer);
        }

        return Client::where('user_id', $user->id)->first();
    }

    public function territory($user)
    {
        return trim((string) optional($this->profile($user))->area);
    }

    public function distributors($user)
    {
        $territory = $this->territory($user);
        if (!$user->isDealerLike() || $territory === '') {
            return collect();
        }

        return AreaDistributor::with('areas')->whereNull('deleted_at')
            ->whereHas('areas', function ($query) use ($territory) {
                $query->whereRaw('LOWER(TRIM(area_name)) = ?', [strtolower($territory)])
                    ->whereRaw('LOWER(TRIM(user_role)) = ?', ['area distributor'])
                    ->whereNull('deleted_at');
            })->get();
    }

    public function products($user, $distributors = null)
    {
        $distributors = $distributors ?: $this->distributors($user);
        if (!$user->isDealerLike() || $distributors->isEmpty()) {
            return collect();
        }

        return Product::where('status', 'Activate')
            ->whereIn('ad_user_id', $distributors->pluck('user_id')->filter()->all())
            ->orderBy('id')->get();
    }

    public function customers($user)
    {
        if (!$user->isDealerLike()) {
            return collect();
        }
        $mfi = trim((string) optional($this->profile($user))->mfi);
        return $mfi === '' ? collect() : Client::with('serial')
            ->whereRaw('LOWER(TRIM(mfi)) = ?', [strtolower($mfi)])->get();
    }

    public function showCustomerFullName($user)
    {
        $mfi = trim((string) optional($this->profile($user))->mfi);
        return $mfi !== '' && Schema::connection('dms')->hasTable('mfis') &&
            (int) DB::connection('dms')->table('mfis')
                ->whereRaw('LOWER(TRIM(name)) = ?', [strtolower($mfi)])
                ->value('show_customer_full_name') === 1;
    }

    public function clientPrice($product, $user)
    {
        return (float) ($user->usesSrpPricing() ? ($product->srp_price ?? $product->price ?? 0) : ($product->price ?? 0));
    }

    public function orderPrice($product, $user)
    {
        return (float) ($user->usesSrpPricing() ? ($product->srp_price ?? $product->price ?? 0)
            : ($user->usesMegaDealerPricing() ? ($product->mega_dealer_price ?? $product->dealer_price ?? $product->price ?? 0)
                : ($product->dealer_price ?? $product->price ?? 0)));
    }

    public function clientAllowed($product)
    {
        // Respect an explicit product restriction when provided by DMS.
        return $product->getAttribute('client_allowed') === null || (bool) $product->client_allowed;
    }

    public function matchesProduct($row, $product)
    {
        if (!empty($row->product_id)) {
            return (int) $row->product_id === (int) $product->id;
        }
        return (string) ($row->item ?? $row->item_name ?? '') === (string) $product->product_name ||
            (!empty($product->sku) && !empty($row->sku) && (string) $row->sku === (string) $product->sku);
    }

    public function dealerStock($product, $userId)
    {
        $orders = Schema::hasTable('order_details') ? OrderDetail::where('dealer_id', $userId)
            ->where('status', 'Completed')->get() : collect();
        $sales = Schema::hasTable('transaction_details') ? TransactionDetail::where('dealer_id', $userId)
            ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                $query->where('status', 'Completed');
            })->get() : collect();
        return $this->remainingStock($product, $orders, $sales);
    }

    public function remainingStock($product, $orders, $sales)
    {
        return max(0, (int) $orders->filter(function ($row) use ($product) {
            return $this->matchesProduct($row, $product);
        })->sum('qty') - (int) $sales->filter(function ($row) use ($product) {
            return $this->matchesProduct($row, $product);
        })->sum('qty'));
    }

    public function stockMaps($products, $user)
    {
        $orders = Schema::hasTable('order_details') ? OrderDetail::where('status', 'Completed')->get() : collect();
        $ownOrders = $orders->where('dealer_id', $user->id);
        $sales = Schema::hasTable('transaction_details') ? TransactionDetail::where('dealer_id', $user->id)
            ->when(Schema::hasColumn('transaction_details', 'status'), function ($query) {
                $query->where('status', 'Completed');
            })->get() : collect();
        $movements = Schema::connection('dms')->hasTable('inventory_transfers')
            ? DB::connection('dms')->table('inventory_transfers')->whereNull('deleted_at')->get() : collect();
        $ads = $this->distributors($user)->keyBy('user_id');
        $territory = $this->normalizeArea($this->territory($user));
        $dealerIdsInTerritory = collect([$user->id]);
        foreach (['dealers', 'mega_dealers', 'center_chiefs'] as $table) {
            if (Schema::hasTable($table)) $dealerIdsInTerritory = $dealerIdsInTerritory->merge(DB::table($table)
                ->whereRaw('LOWER(TRIM(area)) = ?', [strtolower($this->territory($user))])->pluck('user_id'));
        }
        $dealerIdsInTerritory = $dealerIdsInTerritory->map(function ($id) { return (int) $id; })->unique();
        $dealer = []; $ad = [];
        foreach ($products as $product) {
            $dealer[$product->id] = $this->remainingStock($product, $ownOrders, $sales);
            $supplier = $ads->get($product->ad_user_id);
            $available = $movements->filter(function ($row) use ($product) {
                return (int) $row->ad_user_id === (int) $product->ad_user_id && $this->matchesProduct($row, $product);
            })->sum(function ($row) use ($territory) { return $this->movementQuantity($row, $territory); });
            $delivered = $orders->filter(function ($row) use ($product, $supplier, $dealerIdsInTerritory) {
                return $supplier && (int) $row->ad_id === (int) $supplier->id && $dealerIdsInTerritory->contains((int) $row->dealer_id) && $this->matchesProduct($row, $product);
            })->sum('qty');
            $ad[$product->id] = max(0, (int) $available - (int) $delivered);
        }
        return [$dealer, $ad];
    }

    public function normalizeArea($area)
    {
        return strtolower(trim(preg_replace('/\s+/', ' ', (string) $area)));
    }

    public function movementQuantity($row, $area)
    {
        $from = $this->normalizeArea($row->from_area ?? '');
        $to = $this->normalizeArea($row->to_area ?? '');
        $qty = (int) $row->qty;
        if ($row->movement_type === 'in') return !$area || $to === $area ? $qty : 0;
        if ($row->movement_type === 'out') return !$area || $from === $area ? -$qty : 0;
        if ($row->movement_type === 'transfer' && $area) {
            if ($from === $area) return -$qty;
            if ($to === $area) return $qty;
        }
        return 0;
    }

    public function distance($profile, $ad)
    {
        $coords = [optional($profile)->latitude, optional($profile)->longitude, $ad->latitude, $ad->longitude];
        foreach ($coords as $coordinate) if (!is_numeric($coordinate)) return null;
        [$lat1, $lng1, $lat2, $lng2] = array_map('floatval', $coords);
        $a = pow(sin(deg2rad($lat2 - $lat1) / 2), 2) + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * pow(sin(deg2rad($lng2 - $lng1) / 2), 2);
        return round(6371 * 2 * atan2(sqrt($a), sqrt(max(0, 1 - $a))), 2);
    }
}
