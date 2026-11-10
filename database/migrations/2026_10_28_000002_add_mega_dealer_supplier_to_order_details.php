<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMegaDealerSupplierToOrderDetails extends Migration
{
    public function up()
    {
        if (Schema::hasTable('order_details') && !Schema::hasColumn('order_details', 'mega_dealer_id')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->unsignedInteger('mega_dealer_id')->nullable()->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'mega_dealer_id')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('mega_dealer_id');
            });
        }
    }
}
