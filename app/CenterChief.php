<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CenterChief extends Model
{
    protected $table = 'center_chiefs';

    protected $fillable = [
        'user_id', 'name', 'email', 'number', 'street_address',
        'location_barangay', 'location_city', 'location_province',
        'location_region', 'area', 'mfi', 'latitude', 'longitude', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
