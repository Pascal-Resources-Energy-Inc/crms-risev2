<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CenterChief extends Model
{
    protected $table = 'center_chiefs';

    protected $fillable = [
        'user_id', 'name', 'email', 'number', 'mfi', 'status',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
