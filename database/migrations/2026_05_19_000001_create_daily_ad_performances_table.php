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
            $table->decimal('ads_tax_payments', 15, 2)->nullable();
            $table->integer('number_of_orders')->nullable();
            $table->integer('number_of_products')->nullable();
            $table->timestamps();

            $table->foreign('sale_platform_id')->references('id')->on('sale_platforms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_ad_performances');
    }
};

