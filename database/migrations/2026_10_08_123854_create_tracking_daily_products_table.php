<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('tracking_daily_products', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('product_code', 100)->comment('tracking_product.code; all skus of a product share one row');
            $table->unsignedInteger('product_views')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('proceed_checkouts')->default(0);
            $table->unsignedInteger('sold_qty')->default(0);
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'product_code'], 'uq_tdp_date_product');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_products');
    }
};
