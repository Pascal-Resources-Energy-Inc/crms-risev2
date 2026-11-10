<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddMegaDealerSourceToOrderDetails extends Migration
{
    public function up()
    {
        if (Schema::hasTable('order_details') && !Schema::hasColumn('order_details', 'mega_dealer_source')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->string('mega_dealer_source', 24)->nullable()->index();
            });
        }
    }

    public function down()
    {
        if (Schema::hasTable('order_details') && Schema::hasColumn('order_details', 'mega_dealer_source')) {
            Schema::table('order_details', function (Blueprint $table) {
                $table->dropColumn('mega_dealer_source');
            });
        }
    }
}
