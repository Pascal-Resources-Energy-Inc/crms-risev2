<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * Mega Dealer profiles are owned by the primary admin_crms connection.
 */
class MegaDealer extends Model
{
    protected $table = 'mega_dealers';

    public function getSupplierSourceAttribute()
    {
        return $this->getConnectionName() === 'dms' ? 'dms_prei' : 'admin_crms';
    }

    public function getSupplierKeyAttribute()
    {
        return 'mega_dealer:' . ($this->supplier_source === 'dms_prei' ? 'dms_prei:' : '') . $this->id;
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
