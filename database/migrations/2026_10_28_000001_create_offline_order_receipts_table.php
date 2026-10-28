<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateOfflineOrderReceiptsTable extends Migration
{
    public function up()
    {
        if (Schema::hasTable('offline_order_receipts')) return;
        Schema::create('offline_order_receipts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('user_id')->index();
            $table->uuid('idempotency_key')->unique();
            $table->string('request_hash', 64);
            $table->longText('response_json')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        // Receipts are the durable duplicate-prevention ledger, including
        // receipts created by earlier releases. Rolling back UI/API code must
        // not allow an already accepted device transaction to be inserted twice.
    }
}
