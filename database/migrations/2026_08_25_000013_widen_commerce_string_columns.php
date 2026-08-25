<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align string lengths with frontend TRACKER_FIELD_LIMITS (helpers.js) and
     * observed payload sizes so normalized rows are not truncated on ingest.
     */
    public function up(): void
    {
        Schema::table('activity_ecom_orders', function (Blueprint $table) {
            $table->string('payment_method', 100)->nullable()->change();
            $table->string('customer_email', 255)->nullable()->change();
            $table->string('customer_phone', 64)->nullable()->change();
        });

        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->string('product_code', 255)->nullable()->change();
            $table->string('sku', 255)->nullable()->change();
            $table->string('department_name', 255)->nullable()->change();
            $table->string('category_name', 255)->nullable()->change();
            $table->string('category_code', 255)->nullable()->change();
            $table->string('color_name', 255)->nullable()->change();
            $table->string('size_name', 255)->nullable()->change();
        });

        Schema::table('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->string('product_code', 255)->change();
            $table->string('sku', 255)->nullable()->change();
            $table->string('department_name', 255)->nullable()->change();
            $table->string('category_name', 255)->nullable()->change();
        });

        Schema::table('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->string('department_name', 255)->change();
            $table->string('category_name', 255)->change();
        });

        Schema::table('activity_ecom_daily_dimension_metrics', function (Blueprint $table) {
            $table->string('dimension_type', 64)->change();
            $table->string('dimension_value', 500)->change();
        });

        Schema::table('tracker_backfill_checkpoints', function (Blueprint $table) {
            $table->string('chunk_key', 100)->change();
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_orders', function (Blueprint $table) {
            $table->string('payment_method', 50)->nullable()->change();
            $table->string('customer_email', 191)->nullable()->change();
            $table->string('customer_phone', 50)->nullable()->change();
        });

        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->string('product_code', 191)->nullable()->change();
            $table->string('sku', 191)->nullable()->change();
            $table->string('department_name', 191)->nullable()->change();
            $table->string('category_name', 191)->nullable()->change();
            $table->string('category_code', 191)->nullable()->change();
            $table->string('color_name', 100)->nullable()->change();
            $table->string('size_name', 100)->nullable()->change();
        });

        Schema::table('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->string('product_code', 191)->change();
            $table->string('sku', 191)->nullable()->change();
            $table->string('department_name', 191)->nullable()->change();
            $table->string('category_name', 191)->nullable()->change();
        });

        Schema::table('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->string('department_name', 191)->change();
            $table->string('category_name', 191)->change();
        });

        Schema::table('activity_ecom_daily_dimension_metrics', function (Blueprint $table) {
            $table->string('dimension_type', 50)->change();
            $table->string('dimension_value', 191)->change();
        });

        Schema::table('tracker_backfill_checkpoints', function (Blueprint $table) {
            $table->string('chunk_key', 50)->change();
        });
    }
};
