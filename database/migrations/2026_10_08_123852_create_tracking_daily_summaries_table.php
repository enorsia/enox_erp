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
        Schema::create('tracking_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->unique();
            $table->unsignedInteger('unique_visitors')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedBigInteger('stay_seconds')->default(0)->comment('Total session duration');
            $table->unsignedInteger('category_views')->default(0);
            $table->unsignedInteger('product_views')->default(0);
            $table->unsignedInteger('cart_sessions')->default(0)->comment('Sessions that reached add to cart');
            $table->unsignedInteger('checkout_sessions')->default(0)->comment('Sessions that reached begin checkout');
            $table->unsignedInteger('proceed_sessions')->default(0)->comment('Sessions that reached proceed checkout');
            $table->unsignedInteger('cart_drops')->default(0);
            $table->unsignedInteger('checkout_drops')->default(0);
            $table->unsignedInteger('proceed_drops')->default(0);
            $table->unsignedInteger('payments')->default(0)->comment('Sessions with payment success');
            $table->unsignedInteger('items_sold')->default(0);
            $table->decimal('sale_amount', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tracking_daily_summaries');
    }
};
