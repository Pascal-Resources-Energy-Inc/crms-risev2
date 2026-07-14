<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateDealerStockRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('dealer_stock_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('dealer_id');
            $table->unsignedInteger('product_id');
            $table->unsignedInteger('quantity');
            $table->string('status', 20)->default('Pending');
            $table->string('remarks', 500)->nullable();
            $table->unsignedInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->unsignedInteger('approved_order_id')->nullable();
            $table->timestamps();
            $table->unique(['dealer_id', 'product_id']);
            $table->index('status');
        });
    }
    public function down() { Schema::dropIfExists('dealer_stock_requests'); }
}
