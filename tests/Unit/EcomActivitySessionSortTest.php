<?php

use App\Models\ActivityEcomUser;
use App\Support\EcomActivityFocus;
use App\Support\EcomActivitySessionSort;
use Illuminate\Http\Request;

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
        ]))->toBeFalse()
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
