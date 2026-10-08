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
        Schema::create('tracking_daily_categories', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->foreignId('tracking_category_id')->constrained('tracking_category')->cascadeOnDelete();
            $table->unsignedInteger('category_views')->default(0);
            $table->unsignedInteger('product_views')->default(0);
            $table->unsignedInteger('add_to_carts')->default(0);
            $table->unsignedInteger('proceed_checkouts')->default(0);
            $table->unsignedInteger('sold_qty')->default(0);
            $table->unsignedInteger('orders')->default(0);
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'tracking_category_id'], 'uq_tdc_date_category');
            $table->index(['tracking_category_id', 'metric_date'], 'tdc_category_date_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_categories');
    }
};
