<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->string('department_name', 255);
            $table->string('category_name', 255);
            $table->unsignedInteger('add_to_cart_count')->default(0);
            $table->unsignedInteger('begin_checkout_count')->default(0);
            $table->unsignedInteger('proceed_checkout_count')->default(0);
            $table->unsignedInteger('payment_count')->default(0);
            $table->decimal('units_sold', 14, 2)->default(0);
            $table->decimal('revenue', 14, 2)->default(0);
            $table->timestamps();

            $table->unique(
                ['metric_date', 'department_name', 'category_name'],
                'uq_category_metrics_date_dept_cat'
            );
            $table->index('metric_date', 'idx_category_metrics_date');
            $table->index(['department_name', 'category_name'], 'idx_category_metrics_merch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_ecom_daily_category_metrics');
    }
};
