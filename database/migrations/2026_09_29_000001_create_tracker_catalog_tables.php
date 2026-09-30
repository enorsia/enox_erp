<?php

use App\Support\TrackerCatalogSentinels;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tracker_categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('parent_key', 255)->default('');
            $table->string('name_key', 255);
            $table->string('display_name', 500);
            $table->string('code', 255)->nullable();
            $table->timestamps();

            $table->unique(['type', 'parent_key', 'name_key'], 'uq_tracker_categories_identity');
            $table->index('type', 'idx_tracker_categories_type');
        });

        Schema::create('tracker_products', function (Blueprint $table) {
            $table->id();
            $table->string('product_code', 255);
            $table->unsignedBigInteger('store_product_id')->nullable();
            $table->string('product_name', 500)->nullable();
            $table->unsignedBigInteger('tracker_department_id')->nullable();
            $table->unsignedBigInteger('tracker_category_id')->nullable();
            $table->timestamps();

            $table->unique('product_code', 'uq_tracker_products_code');
            $table->index('store_product_id', 'idx_tracker_products_store_product_id');
        });

        Schema::create('tracker_category_name_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('type', 32);
            $table->string('parent_key', 255)->default('');
            $table->string('alias_name_key', 255);
            $table->string('canonical_name_key', 255);
            $table->timestamps();

            $table->unique(
                ['type', 'parent_key', 'alias_name_key'],
                'uq_tracker_category_aliases_identity',
            );
        });

        $now = now();
        DB::table('tracker_categories')->insert([
            [
                'id' => TrackerCatalogSentinels::DEPARTMENT_ID,
                'type' => 'department',
                'parent_key' => '',
                'name_key' => TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY,
                'display_name' => 'Uncategorized',
                'code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => TrackerCatalogSentinels::CATEGORY_ID,
                'type' => 'category',
                'parent_key' => TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY,
                'name_key' => TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY,
                'display_name' => 'Uncategorized',
                'code' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ]);

        DB::table('tracker_products')->insert([
            'id' => TrackerCatalogSentinels::PRODUCT_ID,
            'product_code' => TrackerCatalogSentinels::UNKNOWN_PRODUCT_CODE,
            'store_product_id' => null,
            'product_name' => 'Unknown',
            'tracker_department_id' => TrackerCatalogSentinels::DEPARTMENT_ID,
            'tracker_category_id' => TrackerCatalogSentinels::CATEGORY_ID,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->unsignedBigInteger('tracker_department_id')->nullable()->after('category_code');
            $table->unsignedBigInteger('tracker_category_id')->nullable()->after('tracker_department_id');
            $table->unsignedBigInteger('tracker_product_id')->nullable()->after('tracker_category_id');

            $table->index('tracker_product_id', 'idx_line_tracker_product_id');
            $table->index(['tracker_department_id', 'tracker_category_id'], 'idx_line_tracker_merch_ids');
        });

        Schema::table('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('tracker_product_id')->nullable()->after('product_code');
            $table->index(['metric_date', 'tracker_product_id'], 'idx_product_metrics_date_tracker_id');
        });

        Schema::table('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->unsignedBigInteger('tracker_department_id')->nullable()->after('category_name');
            $table->unsignedBigInteger('tracker_category_id')->nullable()->after('tracker_department_id');
            $table->index(
                ['metric_date', 'tracker_department_id', 'tracker_category_id'],
                'idx_category_metrics_date_tracker_ids',
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_ecom_daily_category_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_category_metrics_date_tracker_ids');
            $table->dropColumn(['tracker_department_id', 'tracker_category_id']);
        });

        Schema::table('activity_ecom_daily_product_metrics', function (Blueprint $table) {
            $table->dropIndex('idx_product_metrics_date_tracker_id');
            $table->dropColumn('tracker_product_id');
        });

        Schema::table('activity_ecom_commerce_line_items', function (Blueprint $table) {
            $table->dropIndex('idx_line_tracker_product_id');
            $table->dropIndex('idx_line_tracker_merch_ids');
            $table->dropColumn(['tracker_department_id', 'tracker_category_id', 'tracker_product_id']);
        });

        Schema::dropIfExists('tracker_category_name_aliases');
        Schema::dropIfExists('tracker_products');
        Schema::dropIfExists('tracker_categories');
    }
};
