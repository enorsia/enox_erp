<?php

use App\Models\ActivityEcomUser;
use App\Support\EcomActivityFocus;
use App\Support\EcomActivitySessionSort;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

test('catalog funnel sort uses sql ranking when category filter is active', function () {
    expect(EcomActivitySessionSort::shouldRankCatalogSessionsInPhp('funnel_stage', [
        'catalog_options' => ['category' => 'Polo Shirts', 'department' => 'Men'],
    ]))->toBeFalse()
        ->and(EcomActivitySessionSort::shouldRankCatalogSessionsInPhp('actions', [
            'catalog_options' => ['category' => 'Polo Shirts'],
        ]))->toBeFalse();
});

test('funnel sort uses catalog action scope when category or product filters are set', function () {
    expect(EcomActivitySessionSort::usesCatalogActionScope([
        'department' => 'Men',
        'category' => 'Polo Shirts',
    ]))->toBeTrue()
        ->and(EcomActivitySessionSort::usesCatalogActionScope([
            'department' => 'Men',
        ]))->toBeTrue()
        ->and(EcomActivitySessionSort::usesCatalogActionScope([
            'product_code' => 'TEE-1',
        ]))->toBeTrue()
        ->and(EcomActivitySessionSort::usesCatalogActionScope([
            'search' => 'MS31262181',
        ]))->toBeTrue()
        ->and(EcomActivitySessionSort::usesCatalogActionScope([
            'search' => 'hoodie',
        ]))->toBeFalse();
});

test('session sort defaults to funnel stage sold first when sort_by is absent', function () {
    $request = Request::create('https://example.test/activity', 'GET', [
        'period' => '7d',
    ]);

    expect(EcomActivitySessionSort::effectiveSortBy($request))->toBe('funnel_stage')
        ->and(EcomActivitySessionSort::isActive($request, 'funnel_stage'))->toBeTrue()
        ->and(EcomActivitySessionSort::usesDefaultSort($request))->toBeTrue();
});

test('session sort url toggles direction for active column', function () {
    $request = Request::create('https://example.test/activity', 'GET', [
        'sort_by' => 'actions',
        'sort_dir' => 'desc',
    ]);

    $url = EcomActivitySessionSort::sortUrl($request, 'actions');

    expect($url)->toContain('sort_by=actions')
        ->and($url)->toContain('sort_dir=asc');
});

test('session sort url defaults to desc for new column', function () {
    $request = Request::create('https://example.test/activity', 'GET', [
        'sort_by' => 'actions',
        'sort_dir' => 'desc',
    ]);

    $url = EcomActivitySessionSort::sortUrl($request, 'funnel_stage');

    expect($url)->toContain('sort_by=funnel_stage')
        ->and($url)->toContain('sort_dir=desc');
});

test('drawer funnel filter is skipped when focus already matches', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'payment_success',
        'funnel' => 'payment_success',
    ]);

    expect(\App\Support\EcomActivityFocus::shouldApplyDrawerFunnelFilter($request))->toBeFalse()
        ->and(\App\Support\EcomActivityFocus::drawerFunnelSelectedValue($request))->toBe('payment_success');
});

test('funnel stage sort qualifies session_id when catalog constraints join funnel times', function () {
    $query = ActivityEcomUser::query();
    EcomActivityFocus::constrainToSessionIds($query, collect(['dcb20d76-bb52-41c9-9088-12d776f929b7']));

    $sql = EcomActivitySessionSort::apply($query, 'funnel_stage', 'desc', [
        'from' => now()->subDay(),
        'to' => now(),
    ])->toSql();

    $normalized = str_replace(['`', '"'], '', $sql);

    expect($normalized)->toContain('funnel_line_times')
        ->and($normalized)->toContain('funnel_order_times')
        ->and($normalized)->toContain('line_session_id')
        ->and($normalized)->toContain('order_session_id')
        ->and($normalized)->toContain('activity_ecom_user.session_id')
        ->and($normalized)->not->toContain('where session_id in');
});

test('funnel stage sort ranks from catalog matching line items when product filter is set', function () {
    $query = ActivityEcomUser::query();

    $applied = EcomActivitySessionSort::apply($query, 'funnel_stage', 'desc', [
        'from' => now()->subDay(),
        'to' => now(),
        'catalog_options' => ['product_code' => 'MS3432143'],
    ]);
    $normalized = str_replace(['`', '"'], '', $applied->toSql());

    expect($normalized)->toContain('funnel_line_times.stage_rank')
        ->and($normalized)->toContain('product_code')
        ->and($applied->getBindings())->toContain('MS3432143')
        ->and($normalized)->not->toContain('has_begin_checkout')
        ->and($normalized)->not->toContain('funnel_order_times');
});

test('catalog funnel sort places product views after checkout even when the session checked out other products', function () {
    CommerceTestSchema::up();

    $from = Carbon::parse('2026-08-01 00:00:00', 'UTC');
    $to = Carbon::parse('2026-08-31 23:59:59', 'UTC');
    $when = $from->copy()->addDay();
    $productCode = 'MS3432143';
    $soldId = (string) Str::uuid();
    $checkoutId = (string) Str::uuid();
    $viewOnlyId = (string) Str::uuid();

    foreach ([$soldId, $checkoutId, $viewOnlyId] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'has_add_to_cart' => true,
            'has_begin_checkout' => true,
            'has_proceed_checkout' => false,
            'has_payment_success' => $sessionId === $soldId,
            'created_at' => $when,
            'updated_at' => $when,
            'last_active_at' => $when,
        ]);
    }

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $soldId,
            'funnel_stage' => 'payment_success',
            'product_code' => $productCode,
            'sku' => $productCode,
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 35,
            'staged_at' => $when,
            'created_at' => $when,
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $checkoutId,
            'funnel_stage' => 'begin_checkout',
            'product_code' => $productCode,
            'sku' => $productCode,
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 10.99,
            'staged_at' => $when,
            'created_at' => $when,
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $viewOnlyId,
            'funnel_stage' => 'product_view',
            'product_code' => $productCode,
            'sku' => $productCode,
            'line_no' => 1,
            'qty' => 1,
            'line_total' => null,
            'staged_at' => $when,
            'created_at' => $when,
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $viewOnlyId,
            'funnel_stage' => 'begin_checkout',
            'product_code' => 'OTHER-1',
            'sku' => 'OTHER-1',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 20,
            'staged_at' => $when,
            'created_at' => $when,
        ],
    ]);

    try {
        $ordered = EcomActivitySessionSort::apply(
            ActivityEcomUser::query()->whereIn('session_id', [$soldId, $checkoutId, $viewOnlyId]),
            'funnel_stage',
            'desc',
            [
                'from' => $from,
                'to' => $to,
                'catalog_options' => ['product_code' => $productCode],
            ],
        )->pluck('session_id')->all();
    } finally {
        CommerceTestSchema::down();
    }

    expect($ordered)->toBe([$soldId, $checkoutId, $viewOnlyId]);
});

test('order value sort aliases joined session_id so catalog constraints stay unambiguous', function () {
    $query = ActivityEcomUser::query();
    EcomActivityFocus::constrainToSessionIds($query, collect(['dcb20d76-bb52-41c9-9088-12d776f929b7']));

    $sql = EcomActivitySessionSort::apply($query, 'order_value', 'desc', [
        'from' => now()->subDay(),
        'to' => now(),
    ])->toSql();

    $normalized = str_replace(['`', '"'], '', $sql);

    expect($normalized)->toContain('period_orders')
        ->and($normalized)->toContain('period_order_session_id')
        ->and($normalized)->toContain('activity_ecom_user.session_id')
        ->and($normalized)->not->toContain('where session_id in');
});

test('session sort url removes fragment param from generated links', function () {
    $request = Request::create('https://example.test/activity', 'GET', [
        'period' => '7d',
        'fragment' => 'table',
        'sort_by' => 'duration',
        'sort_dir' => 'desc',
    ]);

    $url = EcomActivitySessionSort::sortUrl($request, 'actions');

    expect($url)->toContain('sort_by=actions')
        ->and($url)->not->toContain('fragment=');
});
