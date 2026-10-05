<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class CreateDealerProfilesForCenterChiefs extends Migration
{
    public function up()
    {
        DB::table('center_chiefs')
            ->whereNotNull('user_id')
            ->orderBy('id')
            ->get()
            ->each(function ($chief) {
                if (DB::table('dealers')->where('user_id', $chief->user_id)->exists()) {
                    return;
                }

                DB::table('dealers')->insert([
                    'user_id' => $chief->user_id,
                    'name' => $chief->name,
                    'email_address' => $chief->email,
                    'number' => $chief->number,
                    'store_name' => 'Center Chief Workspace',
                    'store_type' => 'Center Chief',
                    'mfi' => $chief->mfi,
                    'status' => $chief->status ?: 'Active',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    public function down()
    {
        DB::table('dealers')
            ->where('store_type', 'Center Chief')
            ->where('store_name', 'Center Chief Workspace')
            ->delete();
    }
}
