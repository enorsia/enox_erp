<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_daily_site_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->unsignedInteger('session_count')->default(0);
            $table->unsignedInteger('visitor_count')->default(0);
            $table->unsignedInteger('action_count')->default(0);
            $table->unsignedInteger('add_to_cart_count')->default(0);
            $table->unsignedInteger('begin_checkout_count')->default(0);
            $table->unsignedInteger('proceed_checkout_count')->default(0);
            $table->unsignedInteger('payment_success_count')->default(0);
            $table->unsignedInteger('order_count')->default(0);
            $table->decimal('revenue_total', 14, 2)->default(0);
            $table->decimal('items_sold_qty', 14, 2)->default(0);
            $table->timestamps();

            $table->unique('metric_date', 'uq_site_metrics_date');
            $table->index('metric_date', 'idx_site_metrics_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_daily_site_metrics');
    }
};
