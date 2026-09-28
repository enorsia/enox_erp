<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_ecom_daily_site_metrics', function (Blueprint $table) {
            $table->unsignedInteger('category_view_count')->default(0)->after('action_count');
            $table->unsignedInteger('product_view_count')->default(0)->after('category_view_count');
        });

        Schema::table('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->unsignedInteger('view_count')->default(0)->after('category_name');
        });

        Schema::table('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->unsignedInteger('category_view_count')->default(0)->after('category_name');
            $table->unsignedInteger('product_view_count')->default(0)->after('category_view_count');
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_daily_site_metrics', function (Blueprint $table) {
            $table->dropColumn(['category_view_count', 'product_view_count']);
        });

        Schema::table('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->dropColumn('view_count');
        });

        Schema::table('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->dropColumn(['category_view_count', 'product_view_count']);
        });
    }
};
