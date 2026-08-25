<?php

use App\Support\EcomActivitySessionSort;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

test('catalog funnel sort uses php ranking when category filter is active', function () {
    expect(EcomActivitySessionSort::shouldRankCatalogSessionsInPhp('funnel_stage', [
        'catalog_options' => ['category' => 'Polo Shirts', 'department' => 'Men'],
    ]))->toBeTrue()
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

test('catalog funnel stage rank orders sold proceed checkout cart view', function () {
    $method = new ReflectionMethod(EcomActivitySessionSort::class, 'funnelStageRankForActionType');
    $method->setAccessible(true);

    $rank = fn (string $actionType) => $method->invoke(null, $actionType);

    expect($rank('payment_success'))->toBeGreaterThan($rank('proceed_checkout'))
        ->and($rank('proceed_checkout'))->toBeGreaterThan($rank('begin_checkout'))
        ->and($rank('begin_checkout'))->toBeGreaterThan($rank('add_to_cart'))
        ->and($rank('add_to_cart'))->toBeGreaterThan($rank('product_view'))
        ->and($rank('product_view'))->toBe($rank('product_view_popup'))
        ->and($rank('product_view'))->toBeGreaterThan(0);
});
