<?php

use App\Services\TrackerCatalogResolver;
use App\Support\TrackerCatalogSentinels;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

beforeEach(function () {
    CommerceTestSchema::up();
    Schema::dropIfExists('tracker_category_name_aliases');
    Schema::dropIfExists('tracker_products');
    Schema::dropIfExists('tracker_categories');

    Schema::create('tracker_categories', function (Blueprint $table) {
        $table->id();
        $table->string('type', 32);
        $table->string('parent_key', 255)->default('');
        $table->string('name_key', 255);
        $table->string('display_name', 500);
        $table->string('code', 255)->nullable();
        $table->timestamps();
        $table->unique(['type', 'parent_key', 'name_key']);
    });

    Schema::create('tracker_products', function (Blueprint $table) {
        $table->id();
        $table->string('product_code', 255)->unique();
        $table->unsignedBigInteger('store_product_id')->nullable();
        $table->string('product_name', 500)->nullable();
        $table->unsignedBigInteger('tracker_department_id')->nullable();
        $table->unsignedBigInteger('tracker_category_id')->nullable();
        $table->timestamps();
    });

    Schema::create('tracker_category_name_aliases', function (Blueprint $table) {
        $table->id();
        $table->string('type', 32);
        $table->string('parent_key', 255)->default('');
        $table->string('alias_name_key', 255);
        $table->string('canonical_name_key', 255);
        $table->timestamps();
        $table->unique(['type', 'parent_key', 'alias_name_key']);
    });

    $now = now();
    DB::table('tracker_categories')->insert([
        [
            'id' => TrackerCatalogSentinels::DEPARTMENT_ID,
            'type' => 'department',
            'parent_key' => '',
            'name_key' => TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY,
            'display_name' => 'Uncategorized',
            'created_at' => $now,
            'updated_at' => $now,
        ],
        [
            'id' => TrackerCatalogSentinels::CATEGORY_ID,
            'type' => 'category',
            'parent_key' => TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY,
            'name_key' => TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY,
            'display_name' => 'Uncategorized',
            'created_at' => $now,
            'updated_at' => $now,
        ],
    ]);

    DB::table('tracker_products')->insert([
        'id' => TrackerCatalogSentinels::PRODUCT_ID,
        'product_code' => TrackerCatalogSentinels::UNKNOWN_PRODUCT_CODE,
        'product_name' => 'Unknown',
        'tracker_department_id' => TrackerCatalogSentinels::DEPARTMENT_ID,
        'tracker_category_id' => TrackerCatalogSentinels::CATEGORY_ID,
        'created_at' => $now,
        'updated_at' => $now,
    ]);
});

afterEach(function () {
    Schema::dropIfExists('tracker_category_name_aliases');
    Schema::dropIfExists('tracker_products');
    Schema::dropIfExists('tracker_categories');
    CommerceTestSchema::down();
});

test('resolver creates product and category rows for line identity', function () {
    $resolver = app(TrackerCatalogResolver::class);

    $ids = $resolver->resolveLineSnapshotIds([
        'department_name' => 'Men',
        'category_name' => 'T-Shirts',
        'product_code' => 'SKU-100',
        'product_id' => 42,
    ]);

    expect($ids['tracker_department_id'])->toBeGreaterThan(0)
        ->and($ids['tracker_category_id'])->toBeGreaterThan(0)
        ->and($ids['tracker_product_id'])->toBeGreaterThan(0);

    expect(DB::table('tracker_products')->where('product_code', 'SKU-100')->exists())->toBeTrue();
});

test('resolver uses sentinels for empty product code', function () {
    $resolver = app(TrackerCatalogResolver::class);
    $ids = $resolver->resolveLineSnapshotIds([
        'department_name' => '',
        'category_name' => '',
        'product_code' => '',
    ]);

    expect($ids['tracker_product_id'])->toBe(TrackerCatalogSentinels::PRODUCT_ID);
});
