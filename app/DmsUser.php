<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/** DMS-owned user record used by Area Distributor relationships. */
class DmsUser extends Model
{
    protected $connection = 'dms';

    protected $table = 'users';
}
