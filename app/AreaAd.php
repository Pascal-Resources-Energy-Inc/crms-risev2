<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class AreaAd extends Model
{
    protected $connection = 'dms';

    protected $table = 'ad_areas';
    protected $fillable = ['ad_id', 'area_name'];
}
