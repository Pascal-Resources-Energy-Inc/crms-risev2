<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddCoordinatesToCenterChiefsTable extends Migration
{
    public function up()
    {
        Schema::table('center_chiefs', function (Blueprint $table) {
            if (!Schema::hasColumn('center_chiefs', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('location_region');
            }

            if (!Schema::hasColumn('center_chiefs', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            }
        });
    }

    public function down()
    {
        Schema::table('center_chiefs', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('center_chiefs', 'longitude')) {
                $columns[] = 'longitude';
            }

            if (Schema::hasColumn('center_chiefs', 'latitude')) {
                $columns[] = 'latitude';
            }

            if (!empty($columns)) {
                $table->dropColumn($columns);
            }
        });
    }
}
