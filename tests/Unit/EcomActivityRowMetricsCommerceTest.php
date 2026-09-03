<?php

use App\Models\ActivityEcomUser;
use App\Services\EcomActivityRowMetrics;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

beforeEach(function () {
    CommerceTestSchema::up();
});

afterEach(function () {
    CommerceTestSchema::down();
});

test('activity commerce summary falls back to session checkout state before the filtered day', function () {
    $sessionId = (string) Str::uuid();
    $checkoutAt = Carbon::parse('2026-08-26 09:18:33', 'UTC');
    $viewAt = Carbon::parse('2026-09-01 09:00:22', 'UTC');
    $from = Carbon::parse('2026-09-01', 'Europe/London')->startOfDay()->utc();
    $to = Carbon::parse('2026-09-01', 'Europe/London')->endOfDay()->utc();

    DB::table('activity_ecom_user')->insert([
        'session_id' => $sessionId,
        'has_begin_checkout' => true,
        'latest_funnel_stage' => 'begin_checkout',
        'created_at' => $viewAt,
        'updated_at' => $viewAt,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'begin_checkout',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'product_name' => 'Mens Blue Ripped Denim Jeans',
            'line_total' => 23.99,
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => $checkoutAt,
            'created_at' => $checkoutAt,
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'product_view',
            'department_name' => 'Men',
            'category_name' => 'Trousers',
            'product_name' => 'Mens Classic Front Seam Jersey Trouser',
            'line_total' => null,
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => $viewAt,
            'created_at' => $viewAt,
        ],
    ]);

    $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();
    $metrics = app(EcomActivityRowMetrics::class)->forSessions(collect([$session]), null, $from, $to, [], []);

    expect($metrics[$sessionId]['commerce_label'])->toBe('Checkout')
        ->and($metrics[$sessionId]['commerce_display'])->toBe('Checkout · £23.99')
        ->and($metrics[$sessionId]['commerce_events'])->toHaveCount(1)
        ->and($metrics[$sessionId]['commerce_events'][0]['stage'])->toBe('begin_checkout');
});

test('activity commerce summary shows view when only product views exist in the filtered day', function () {
    $sessionId = (string) Str::uuid();
    $viewAt = Carbon::parse('2026-09-01 09:00:22', 'UTC');
    $from = Carbon::parse('2026-09-01', 'Europe/London')->startOfDay()->utc();
    $to = Carbon::parse('2026-09-01', 'Europe/London')->endOfDay()->utc();

    DB::table('activity_ecom_user')->insert([
        'session_id' => $sessionId,
        'created_at' => $viewAt,
        'updated_at' => $viewAt,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        'event_id' => (string) Str::uuid(),
        'session_id' => $sessionId,
        'funnel_stage' => 'product_view',
        'department_name' => 'Men',
        'category_name' => 'Trousers',
        'product_name' => 'Mens Classic Front Seam Jersey Trouser',
        'line_no' => 1,
        'qty' => 1,
        'staged_at' => $viewAt,
        'created_at' => $viewAt,
    ]);

    $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();
    $metrics = app(EcomActivityRowMetrics::class)->forSessions(collect([$session]), null, $from, $to, [], []);

    expect($metrics[$sessionId]['commerce_label'])->toBe('View')
        ->and($metrics[$sessionId]['commerce_display'])->toBe('View')
        ->and($metrics[$sessionId]['commerce_events'])->toBe([]);
});
