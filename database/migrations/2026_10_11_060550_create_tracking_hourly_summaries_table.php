<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_hourly_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->unsignedTinyInteger('metric_hour')->comment('0–23 store local hour');
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedBigInteger('stay_seconds')->default(0);
            $table->unsignedInteger('category_views')->default(0);
            $table->unsignedInteger('product_views')->default(0);
            $table->unsignedInteger('cart_sessions')->default(0);
            $table->unsignedInteger('checkout_sessions')->default(0);
            $table->unsignedInteger('proceed_sessions')->default(0);
            $table->unsignedInteger('payments')->default(0);
            $table->unsignedInteger('items_sold')->default(0);
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->timestamps();

            $table->unique(['metric_date', 'metric_hour'], 'ths_date_hour_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_hourly_summaries');
    }
};
