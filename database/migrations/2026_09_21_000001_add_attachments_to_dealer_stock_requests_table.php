<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAttachmentsToDealerStockRequestsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('dealer_stock_requests', 'attachments')) {
            Schema::table('dealer_stock_requests', function (Blueprint $table) {
                $table->text('attachments')->nullable()->after('quantity');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('dealer_stock_requests', 'attachments')) {
            Schema::table('dealer_stock_requests', function (Blueprint $table) {
                $table->dropColumn('attachments');
            });
        }
    }
}
