<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->index(
                ['funnel_stage', 'department_name', 'staged_at'],
                'idx_line_funnel_dept_date'
            );
            $table->index(
                ['funnel_stage', 'category_name', 'staged_at'],
                'idx_line_funnel_cat_date'
            );
            $table->index(
                ['department_name', 'category_name', 'product_code'],
                'idx_line_merch_combo'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->dropIndex('idx_line_funnel_dept_date');
            $table->dropIndex('idx_line_funnel_cat_date');
            $table->dropIndex('idx_line_merch_combo');
        });
    }
};
