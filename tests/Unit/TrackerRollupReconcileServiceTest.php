<?php

use App\Services\TrackerRollupReconcileService;
use App\Support\EcomDailyDimensionType;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

beforeEach(function () {
    CommerceTestSchema::up();
    Schema::dropIfExists('activity_ecom_daily_site_metrics');
    Schema::dropIfExists('activity_ecom_daily_dimension_metrics');
    Schema::create('activity_ecom_daily_dimension_metrics', function (Blueprint $table) {
        $table->id();
        $table->date('metric_date');
        $table->string('dimension_type', 64);
        $table->string('dimension_value', 500);
        $table->unsignedInteger('session_count')->default(0);
        $table->unsignedInteger('visitor_count')->default(0);
        $table->unsignedInteger('payment_count')->default(0);
        $table->decimal('revenue', 14, 2)->default(0);
        $table->timestamps();
        $table->unique(['metric_date', 'dimension_type', 'dimension_value'], 'uq_dimension_metrics_date_type_value');
    });

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
        $table->unique('metric_date');
    });
});

afterEach(function () {
    Schema::dropIfExists('activity_ecom_daily_site_metrics');
    Schema::dropIfExists('activity_ecom_daily_dimension_metrics');
    CommerceTestSchema::down();
});

test('reconcile reports match when rollup equals orders on ordered_at', function () {
    $metricDate = '2026-09-15';
    [$start, $end] = TrackerTime::localCalendarDateStorageRange($metricDate);
    $orderedAt = Carbon::parse($start, 'UTC')->addHours(10);

    DB::table('activity_ecom_daily_site_metrics')->insert([
        'metric_date' => $metricDate,
        'session_count' => 1,
        'visitor_count' => 1,
        'action_count' => 1,
        'add_to_cart_count' => 0,
        'begin_checkout_count' => 0,
        'proceed_checkout_count' => 0,
        'payment_success_count' => 1,
        'order_count' => 1,
        'revenue_total' => 99.50,
        'items_sold_qty' => 2,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('activity_ecom_user')->insert([
        'session_id' => Str::uuid()->toString(),
        'created_at' => $orderedAt,
        'updated_at' => $orderedAt,
    ]);

    $sessionId = DB::table('activity_ecom_user')->value('session_id');

    DB::table('activity_ecom_daily_dimension_metrics')->insert([
        'metric_date' => $metricDate,
        'dimension_type' => EcomDailyDimensionType::CURRENCY,
        'dimension_value' => 'GBP',
        'payment_count' => 1,
        'revenue' => 99.50,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('activity_ecom_orders')->insert([
        'order_id' => 'ORD-RECON-1',
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'amount_paid' => 99.50,
        'item_qty' => 2,
        'currency' => 'GBP',
        'ordered_at' => $orderedAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $service = app(TrackerRollupReconcileService::class);
    $row = $service->compareDay($metricDate);

    expect($row['within_tolerance'])->toBeTrue()
        ->and($row['rollup_revenue'])->toBe(99.50)
        ->and($row['orders_revenue'])->toBe(99.50)
        ->and($row['rollup_order_count'])->toBe(1)
        ->and($row['orders_count'])->toBe(1);
});

test('reconcile command exits failure when revenue diverges', function () {
    $metricDate = TrackerTime::defaultRollupMetricDate();
    [$start] = TrackerTime::localCalendarDateStorageRange($metricDate);
    $orderedAt = Carbon::parse($start, 'UTC')->addHours(8);

    DB::table('activity_ecom_daily_dimension_metrics')->insert([
        'metric_date' => $metricDate,
        'dimension_type' => EcomDailyDimensionType::CURRENCY,
        'dimension_value' => 'GBP',
        'payment_count' => 1,
        'revenue' => 50.00,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('activity_ecom_daily_site_metrics')->insert([
        'metric_date' => $metricDate,
        'session_count' => 0,
        'visitor_count' => 0,
        'action_count' => 0,
        'add_to_cart_count' => 0,
        'begin_checkout_count' => 0,
        'proceed_checkout_count' => 0,
        'payment_success_count' => 1,
        'order_count' => 1,
        'revenue_total' => 50.00,
        'items_sold_qty' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $sessionId = Str::uuid()->toString();
    DB::table('activity_ecom_user')->insert([
        'session_id' => $sessionId,
        'created_at' => $orderedAt,
        'updated_at' => $orderedAt,
    ]);

    DB::table('activity_ecom_orders')->insert([
        'order_id' => 'ORD-RECON-2',
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'amount_paid' => 75.00,
        'item_qty' => 1,
        'currency' => 'GBP',
        'ordered_at' => $orderedAt,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->artisan('tracker:reconcile-rollups', ['--from' => $metricDate])
        ->assertFailed();
});
