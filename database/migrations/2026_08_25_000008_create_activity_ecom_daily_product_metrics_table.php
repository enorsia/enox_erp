<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('product_code', 255);
            $table->string('product_name', 500)->nullable();
            $table->string('sku', 255)->nullable();
            $table->string('department_name', 255)->nullable();
            $table->string('category_name', 255)->nullable();
            $table->unsignedInteger('add_to_cart_count')->default(0);
            $table->unsignedInteger('begin_checkout_count')->default(0);
            $table->unsignedInteger('proceed_checkout_count')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('units_sold', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'product_code'], 'uq_product_metrics_date_code');
            $table->index('metric_date', 'idx_product_metrics_date');
            $table->index('product_code', 'idx_product_metrics_code');
            $table->index(['department_name', 'category_name'], 'idx_product_metrics_merch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_daily_product_metrics');
    }
};
