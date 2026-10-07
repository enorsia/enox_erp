<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_ctr_daily_summaries', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date');
            $table->unsignedBigInteger('department_id')->comment('1=mens, 2=womens, 3=boys, 4=girls');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedInteger('total_click')->default(0);
            $table->unsignedInteger('total_impression')->default(0);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('ctr_average', 10, 6)->default(0);
            $table->timestamps();

            $table->unique(
                ['metric_date', 'department_id', 'category_id', 'product_id'],
                'uq_tracking_ctr_daily_date_dept_category_product'
            );
            $table->index('metric_date', 'idx_tracking_ctr_daily_date');
            $table->index('department_id', 'idx_tracking_ctr_daily_department');
            $table->index('category_id', 'idx_tracking_ctr_daily_category');
            $table->index('product_id', 'idx_tracking_ctr_daily_product');
            $table->index(['metric_date', 'department_id', 'category_id'], 'idx_tracking_ctr_daily_date_dept_category');
            $table->index(['department_id', 'category_id', 'product_id'], 'idx_tracking_ctr_daily_dept_category_product');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_ctr_daily_summaries');
    }
};
