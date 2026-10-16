<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UseUsersCredentialsForCenterChiefs extends Migration
{
    public function up()
    {
        DB::table('center_chiefs')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get()
            ->each(function ($chief) {
                DB::table('users')->where('id', $chief->user_id)->update([
                    'role' => 'center_chief',
                    'password' => Hash::make('12345678'),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down()
    {
        DB::table('center_chiefs')
            ->whereNotNull('user_id')
            ->update(['updated_at' => now()]);
    }
}
