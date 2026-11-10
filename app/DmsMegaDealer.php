<?php

namespace App;

/** A DMS supplier profile; its user IDs belong only to the DMS database. */
class DmsMegaDealer extends MegaDealer
{
    protected $connection = 'dms';

    public function user()
    {
        return $this->belongsTo(DmsUser::class, 'user_id');
    }
}
