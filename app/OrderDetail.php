<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderDetail extends Model
{
    public function megaDealer()
    {
        return $this->belongsTo(MegaDealer::class, 'mega_dealer_id');
    }

    public function dmsMegaDealer()
    {
        return $this->belongsTo(DmsMegaDealer::class, 'mega_dealer_id');
    }

    /** Existing orders without a source keep their original primary supplier. */
    public function getSupplierSourceAttribute()
    {
        return $this->mega_dealer_id ? ($this->mega_dealer_source ?: 'admin_crms') : 'dms_prei';
    }

    public function getSupplierAttribute()
    {
        return $this->mega_dealer_id
            ? ($this->supplier_source === 'dms_prei' ? $this->dmsMegaDealer : $this->megaDealer) : $this->ad;
    }

    public function scopeLocalMegaDealerSource($query)
    {
        if (!\Illuminate\Support\Facades\Schema::hasColumn('order_details', 'mega_dealer_source')) return $query;
        return $query->where(function ($query) {
            $query->whereNull('mega_dealer_source')->orWhere('mega_dealer_source', 'admin_crms');
        });
    }

    public function getSupplierTypeAttribute()
    {
        return $this->mega_dealer_id ? 'mega_dealer' : 'area_distributor';
    }

    public function getSupplierNameAttribute()
    {
        return optional($this->supplier)->name;
    }

    public function dealer()
    {
        return $this->belongsTo(User::class,'dealer_id','id');
    }

    public function product()
    {
        return $this->belongsTo(Product::class,'product_id','id');
    }

    public function ad()
    {
        return $this->belongsTo(
            AreaDistributor::class,
            'ad_id',
            'id'
        );
    }

    public function areas()
    {
        return $this->hasMany(AreaAd::class,'ad_id');
    }
}
