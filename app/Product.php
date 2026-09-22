<?php

namespace App;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $connection = 'dms';

    protected $table = 'products';
    
    protected $fillable = [
        'product_name',
        'price',
        'dealer_price',
        'srp_price',
        'deposit',
        'product_image'
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'dealer_price' => 'decimal:2',
        'srp_price' => 'decimal:2',
        'deposit' => 'decimal:2',
    ];

    // public function adProduct()
    // {
    //     return $this->belongsTo(AreaDistributor::class, 'ad_user_id', 'user_id');
    // }

    // public function areas()
    // {
    //     return $this->hasMany(AreaAd::class, 'ad_id', 'id');
    // }

    public function adProduct()
    {
        return $this->belongsTo(AreaDistributor::class, 'ad_user_id', 'user_id');
    }

    public function areas()
    {
        return $this->hasMany(AreaAd::class, 'ad_user_id', 'ad_user_id');
    }

    // public function areas()
    // {
    //     return $this->hasMany(AreaAd::class, 'ad_id');
    // }

}
