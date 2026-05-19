<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_ad_performances', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('sale_platform_id')->nullable()->index();
            $table->date('month');                         // stored as first day of month e.g. 2023-09-01
            $table->bigInteger('reach')->nullable();
            $table->bigInteger('impressions')->nullable();
            $table->integer('clicks')->nullable();
            $table->integer('sessions')->nullable();
            $table->integer('engaged_sessions')->nullable();
            $table->integer('users')->nullable();
            $table->decimal('net_cost', 15, 2)->nullable();
            $table->decimal('ads_tax_payments', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->nullable();       // net_cost + ads_tax_payments
            $table->integer('number_of_orders')->nullable();
            $table->integer('number_of_products')->nullable();
            $table->decimal('sales_grow_percent', 10, 4)->nullable();
            $table->decimal('revenue', 15, 2)->nullable();
            $table->decimal('total_revenue', 15, 2)->nullable();    // = revenue (or parent row total)
            $table->decimal('total_return', 15, 2)->nullable();
            $table->decimal('net_revenue', 15, 2)->nullable();      // total_revenue - total_return
            $table->decimal('roi', 10, 4)->nullable();              // (revenue / total_cost) * 100
            $table->decimal('roas', 10, 4)->nullable();             // revenue / total_cost
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('sale_platform_id')->references('id')->on('sale_platforms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ad_performances');
    }
};

