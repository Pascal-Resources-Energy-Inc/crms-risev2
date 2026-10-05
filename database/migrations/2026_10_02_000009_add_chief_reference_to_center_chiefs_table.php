<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddChiefReferenceToCenterChiefsTable extends Migration
{
    public function up()
    {
        Schema::table('center_chiefs', function (Blueprint $table) {
            $table->string('chief_reference', 20)->nullable()->unique()->after('user_id');
        });

        DB::table('center_chiefs')->orderBy('id')->get()->each(function ($chief) {
            DB::table('center_chiefs')->where('id', $chief->id)->update([
                'chief_reference' => sprintf('PRCC-%05d', $chief->id),
                'updated_at' => now(),
            ]);
        });
    }

    public function down()
    {
        Schema::table('center_chiefs', function (Blueprint $table) {
            $table->dropUnique(['chief_reference']);
            $table->dropColumn('chief_reference');
        });
    }
}
