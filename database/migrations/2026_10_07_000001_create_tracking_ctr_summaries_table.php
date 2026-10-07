<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracking_ctr_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('department_id')->comment('1=mens, 2=womens, 3=boys, 4=girls');
            $table->unsignedBigInteger('category_id');
            $table->unsignedBigInteger('product_id');
            $table->string('sku', 100);
            $table->unsignedInteger('total_click')->default(0);
            $table->unsignedInteger('total_impression')->default(0);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('ctr_average', 10, 6)->default(0);
            $table->boolean('new_in')->default(false);
            $table->unsignedInteger('total_sold')->default(0);
            $table->unsignedInteger('total_sold_rolling')->default(0)
                ->comment('Sold in rolling N-day window (N configurable, default 30 days)');
            $table->boolean('sold_out')->default(false);
            $table->timestamps();

            $table->unique(
                ['department_id', 'category_id', 'product_id'],
                'uq_tracking_ctr_summary_dept_category_product'
            );
            $table->index('department_id', 'idx_tracking_ctr_summary_department');
            $table->index('category_id', 'idx_tracking_ctr_summary_category');
            $table->index('product_id', 'idx_tracking_ctr_summary_product');
            $table->index('sku', 'idx_tracking_ctr_summary_sku');
            $table->index(['department_id', 'category_id'], 'idx_tracking_ctr_summary_dept_category');
            $table->index('new_in', 'idx_tracking_ctr_summary_new_in');
            $table->index('sold_out', 'idx_tracking_ctr_summary_sold_out');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_ctr_summaries');
    }
};
