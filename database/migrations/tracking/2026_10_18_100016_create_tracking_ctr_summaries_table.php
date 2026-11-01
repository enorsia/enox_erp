<?php

use App\Database\Migrations\TrackingMigration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends TrackingMigration
{
    public function up(): void
    {
        Schema::create('tracking_ctr_summaries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tracking_ctr_category_id');
            $table->unsignedBigInteger('department_id')->nullable();
            $table->string('department_slug')->nullable();
            $table->unsignedBigInteger('category_id');
            $table->string('category_slug', 255)->nullable();
            $table->unsignedBigInteger('micro_category_id')->nullable();
            $table->string('micro_category_slug', 255)->nullable();
            $table->string('type', 32)->nullable()->comment('static, dynamic_custom_link, category, micro_category');
            $table->string('product_code', 100);
            $table->decimal('ctr', 10, 6)->default(0);
            $table->decimal('ctr_average', 10, 6)->default(0);
            $table->unsignedInteger('total_click')->default(0);
            $table->unsignedInteger('total_impression')->default(0);
            $table->timestamps();

            $table->unique(
                ['tracking_ctr_category_id', 'product_code'],
                'uq_tracking_ctr_summary_category_product',
            );
            $table->index('tracking_ctr_category_id', 'idx_tracking_ctr_summary_ctr_category');
            $table->index('department_id', 'idx_tracking_ctr_summary_department');
            $table->index('category_id', 'idx_tracking_ctr_summary_category');
            $table->index('micro_category_id', 'idx_tracking_ctr_summary_micro_category');
            $table->index('product_code', 'idx_tracking_ctr_summary_product_code');
            $table->index(['department_id', 'category_id'], 'idx_tracking_ctr_summary_dept_category');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tracking_ctr_summaries');
    }
};
