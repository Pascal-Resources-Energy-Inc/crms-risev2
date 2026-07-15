<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddIsGuestToTransactionDetailsTable extends Migration
{
    public function up()
    {
        if (!Schema::hasColumn('transaction_details', 'is_guest')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->boolean('is_guest')->default(false)->after('client_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('transaction_details', 'is_guest')) {
            Schema::table('transaction_details', function (Blueprint $table) {
                $table->dropColumn('is_guest');
            });
        }
    }
}
