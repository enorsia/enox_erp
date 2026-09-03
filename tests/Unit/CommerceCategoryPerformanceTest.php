<?php

use App\Services\EcomTrackerDashboardService;
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

test('category performance excludes categories without tracked actions', function () {
    $from = Carbon::parse('2026-09-03 00:00:00');
    $to = Carbon::parse('2026-09-03 23:59:59');
    $sessionId = (string) Str::uuid();

    DB::table('activity_ecom_user')->insert([
        'session_id' => $sessionId,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'category_view',
            'department_name' => 'Women',
            'category_name' => 'Dresses',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'begin_checkout',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => $from->copy()->addHours(2),
            'created_at' => $from->copy()->addHours(2),
        ],
    ]);

    $service = app(EcomTrackerDashboardService::class);
    $categories = (new ReflectionClass($service))
        ->getMethod('buildCategoryPerformance')
        ->invoke($service, $from, $to, null, [], null);

    expect($categories)->toHaveCount(1)
        ->and($categories[0]['department_name'])->toBe('Women')
        ->and($categories[0]['category_name'])->toBe('Dresses')
        ->and($categories[0]['category_views'])->toBe(1);
});

test('category filter options only include departments and categories with activity', function () {
    $from = Carbon::parse('2026-09-03 00:00:00');
    $to = Carbon::parse('2026-09-03 23:59:59');
    $sessionId = (string) Str::uuid();

    DB::table('activity_ecom_user')->insert([
        'session_id' => $sessionId,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'product_view',
            'product_code' => 'MA31214415',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
    ]);

    $options = app(EcomTrackerDashboardService::class)->categoryFilterOptionsForRange($from, $to);

    expect($options['departments'])->toBe(['Men'])
        ->and($options['categories_by_department'])->toBe([
            'Men' => ['Jeans'],
        ]);
});
