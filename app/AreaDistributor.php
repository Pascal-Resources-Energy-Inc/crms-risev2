<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AreaDistributor extends Model
{
    protected $connection = 'dms';

    protected $table = 'area_distributors';

    public function areas()
    {
        return $this->hasMany(AreaAd::class, 'ad_id');
    }

    public function user()
    {
        return $this->belongsTo(DmsUser::class, 'user_id');
    }
}
