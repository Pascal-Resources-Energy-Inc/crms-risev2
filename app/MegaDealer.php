<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Mega Dealer profiles are owned by the primary admin_crms connection.
 */
class MegaDealer extends Model
{
    protected $table = 'mega_dealers';

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
