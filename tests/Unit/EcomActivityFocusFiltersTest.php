<?php

use App\Support\EcomActivityFocus;
use App\Services\EcomTrackerDashboardService;
use Carbon\Carbon;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

test('catalog scoped search is enabled for products and categories focus', function () {
    $productsRequest = Request::create('/', 'GET', ['focus' => 'products']);
    $categoriesRequest = Request::create('/', 'GET', ['focus' => 'categories']);
    $audienceRequest = Request::create('/', 'GET', ['focus' => 'audience']);

    expect(EcomActivityFocus::usesCatalogScopedSearch($productsRequest))->toBeTrue()
        ->and(EcomActivityFocus::usesCatalogScopedSearch($categoriesRequest))->toBeTrue()
        ->and(EcomActivityFocus::usesCatalogScopedSearch($audienceRequest))->toBeFalse();
});

test('drawer preserve params keep drill-down context but expose catalog filters in drawer', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'categories',
        'back' => 'http://example.test/dashboard',
        'department' => 'Boys',
        'category' => 'Hoodies',
        'color' => 'Red',
        'activity' => 'views',
        'device_type' => 'mobile',
        'search' => 'hoodie',
    ]);

    $preserved = EcomActivityFocus::drawerPreserveQueryParams($request);

    expect($preserved)->toHaveKeys(['focus', 'back'])
        ->and($preserved)->not->toHaveKey('category')
        ->and($preserved)->not->toHaveKey('color')
        ->and($preserved)->not->toHaveKey('activity')
        ->and($preserved)->not->toHaveKey('search')
        ->and($preserved)->not->toHaveKey('device_type');
});

test('active filter count includes session and catalog filters on catalog focus', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'device_type' => 'mobile',
        'category' => 'Tops',
        'activity' => 'views',
        'product_code' => 'TEE-1',
    ]);

    expect(EcomActivityFocus::activeFilterCount($request))->toBe(4);
});

test('active filter chips include catalog filters on catalog focus', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'category' => 'Tops',
        'activity' => 'views',
        'device_type' => 'mobile',
    ]);

    $labels = collect(EcomActivityFocus::activeFilterChipsFromRequest($request))
        ->pluck('label')
        ->all();

    expect($labels)->toContain('Category: Tops')
        ->and($labels)->toContain('Activity: Views')
        ->and($labels)->toContain('Device: Mobile');
});

test('format funnel summary metrics from normalized acquisition row', function () {
    $normalize = new ReflectionMethod(EcomActivityFocus::class, 'normalizeAcquisitionRow');
    $normalize->setAccessible(true);

    $format = new ReflectionMethod(EcomActivityFocus::class, 'formatFunnelSummaryMetrics');
    $format->setAccessible(true);

    $normalized = $normalize->invoke(null, [
        'views' => 11,
        'add_to_cart' => 3,
        'proceed_checkout' => 1,
        'payment_success' => 2,
        'sold_qty' => 4,
        'revenue' => 125.5,
    ]);

    $metrics = $format->invoke(null, $normalized);
    $labels = collect($metrics)->pluck('label')->all();
    $values = collect($metrics)->pluck('value', 'label')->all();

    expect($labels)->toBe([
        'Views',
        'Adds',
        'Proceed',
        'Cart abandoned',
        'Sold',
        'Sold qty',
        'Sale',
    ])
        ->and($values['Views'])->toBe('11')
        ->and($values['Adds'])->toBe('3')
        ->and($values['Proceed'])->toBe('1')
        ->and($values['Cart abandoned'])->toBe('2')
        ->and($values['Sold'])->toBe('2')
        ->and($values['Sold qty'])->toBe('4')
        ->and($values['Sale'])->toBe('£125.50');
});

test('category performance summary metrics use shared funnel formatter', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'categories',
        'category' => 'Tops and T-Shirts',
        'department' => 'Women',
    ]);

    $dashboard = Mockery::mock(EcomTrackerDashboardService::class);
    $dashboard->shouldReceive('categoryPerformanceForName')
        ->once()
        ->andReturn([
            'views' => 5,
            'adds' => 2,
            'proceed_checkouts' => 1,
            'purchases' => 1,
            'sale_items' => 3,
            'sale_amount' => 45.0,
        ]);
    $dashboard->shouldReceive('productCatalogSessionIds')
        ->once()
        ->andReturn(collect(['session-a']));
    $dashboard->shouldReceive('categoryCatalogCommerceTotalsForSessions')
        ->once()
        ->andReturn([
            'revenue' => 45.0,
            'qty' => 3,
            'purchases' => 1,
        ]);

    app()->instance(EcomTrackerDashboardService::class, $dashboard);

    $method = new ReflectionMethod(EcomActivityFocus::class, 'categoryPerformanceSummaryMetrics');
    $method->setAccessible(true);

    $metrics = $method->invoke(
        null,
        $request,
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        'all',
    );

    $values = collect($metrics)->pluck('value', 'label')->all();

    expect($values['Views'])->toBe('5')
        ->and($values['Cart abandoned'])->toBe('1')
        ->and($values['Sale'])->toBe('£45.00');
});

test('resolve filter summary focus infers devices and traffic from sidebar filters', function () {
    $deviceRequest = Request::create('/', 'GET', ['device_type' => 'mobile']);
    $trafficRequest = Request::create('/', 'GET', ['utm_source' => 'google']);
    $plainRequest = Request::create('/', 'GET', ['period' => '7d']);

    expect(EcomActivityFocus::resolveFilterSummaryFocus($deviceRequest))->toBe('devices')
        ->and(EcomActivityFocus::resolveFilterSummaryFocus($trafficRequest))->toBe('traffic')
        ->and(EcomActivityFocus::resolveFilterSummaryFocus($plainRequest))->toBeNull();
});

test('activity list context is built for sidebar device filters without dashboard focus', function () {
    $request = Request::create('/', 'GET', ['device_type' => 'mobile']);

    $dashboard = Mockery::mock(EcomTrackerDashboardService::class);
    $dashboard->shouldReceive('productCatalogEventScenarioOptions')->andReturn([]);
    $dashboard->shouldReceive('productCatalogActivityFilterOptions')->andReturn([]);
    $dashboard->shouldReceive('devicePerformanceSummaryForFilters')
        ->once()
        ->andReturn([
            'views' => 4,
            'adds' => 2,
            'proceed_checkouts' => 1,
            'purchases' => 0,
            'qty' => 0,
            'revenue' => 0.0,
        ]);

    app()->instance(EcomTrackerDashboardService::class, $dashboard);

    $context = EcomActivityFocus::activityListContext(
        $request,
        'All time',
        3,
        [],
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        'all',
    );

    expect($context)->not->toBeNull()
        ->and($context['section'])->toBe('Device & browser')
        ->and($context['clear_label'])->toBe('Clear filters')
        ->and(collect($context['metrics'])->pluck('label')->all())->toContain('Views', 'Adds', 'Sale');
});

test('activity sidebar filter keys exclude audience and date fields', function () {
    $request = Request::create('/', 'GET', ['focus' => 'traffic']);

    $keys = EcomActivityFocus::sidebarFilterQueryKeys($request);

    expect($keys)->toContain('device_type', 'utm_source', 'logged_in')
        ->and($keys)->not->toContain('visitor_type', 'country', 'date_from', 'date_to');
});

test('drawer preserve params keep drill-down visitor type when not in activity sidebar', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'session_quality',
        'visitor_type' => 'human',
        'period' => '7d',
    ]);

    $preserved = EcomActivityFocus::drawerPreserveQueryParams($request);

    expect($preserved)->toHaveKeys(['focus', 'visitor_type', 'period'])
        ->and($preserved['visitor_type'])->toBe('human');
});

test('activity sidebar chips exclude visitor type and country', function () {
    $request = Request::create('/', 'GET', [
        'device_type' => 'mobile',
        'visitor_type' => 'human',
        'country' => 'GB',
        'utm_source' => 'google',
    ]);

    $labels = collect(EcomActivityFocus::sidebarFilterChipsFromRequest($request))
        ->pluck('label')
        ->all();

    expect($labels)->toContain('Device: Mobile')
        ->and(collect($labels)->contains(fn (string $label) => str_starts_with($label, 'Source:')))->toBeTrue()
        ->and($labels)->not->toContain('Visitor type: Real visitors', 'Country: GB');
});

test('activity sidebar filter keys include department and category', function () {
    $keys = EcomActivityFocus::sidebarFilterQueryKeys(Request::create('/', 'GET', ['focus' => 'traffic']));

    expect($keys)->toContain('department', 'category', 'device_type');
});

test('catalog constraints apply on non catalog focus when department is set', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'devices',
        'department' => 'Women',
    ]);

    expect(EcomActivityFocus::shouldApplyCatalogConstraintsInIndexQuery('devices', $request))->toBeTrue();
});

test('session and catalog filters exclude facet dimension when computing option counts', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'categories',
        'department' => 'Women',
        'category' => 'Jumpsuits',
        'device_type' => 'mobile',
        'logged_in' => '1',
    ]);

    expect(EcomActivityFocus::sessionFiltersFromRequest($request, ['device_type']))
        ->not->toHaveKey('device_type')
        ->toHaveKeys(['logged_in']);

    expect(EcomActivityFocus::productCatalogFiltersFromRequest($request, ['category']))
        ->not->toHaveKey('category')
        ->toHaveKey('department');
});

test('conversion summary totals use matched payment funnel metrics', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'conversion',
        'has_order' => '1',
        'period' => '7d',
    ]);

    $metrics = EcomActivityFocus::summaryForFocus(
        'conversion',
        2,
        [
            'session-a' => ['qty' => 1, 'value' => 16.0],
            'session-b' => ['qty' => 2, 'value' => 24.99],
        ],
        $request,
        Carbon::parse('2026-08-17'),
        Carbon::parse('2026-08-23'),
        '7d',
    );

    $values = collect($metrics)->pluck('value', 'label');

    expect($values['Matching sessions'])->toBe(2)
        ->and($values['Sold qty'])->toBe('3')
        ->and($values['Sale'])->toBe('£40.99');
});

test('payment success summary totals use matched payment funnel metrics', function () {
    $metrics = EcomActivityFocus::summaryForFocus(
        'payment_success',
        2,
        [
            'session-a' => ['qty' => 1, 'value' => 10.0],
            'session-b' => ['qty' => 3, 'value' => 30.5],
        ],
    );

    $values = collect($metrics)->pluck('value', 'label');

    expect($values['Matching sessions'])->toBe(2)
        ->and($values['Orders'])->toBe('2')
        ->and($values['Sold qty'])->toBe('4')
        ->and($values['Sale'])->toBe('£40.50');
});

test('abandonment summary items in cart use matched funnel metrics', function () {
    $metrics = EcomActivityFocus::summaryForFocus(
        'cart_abandonment',
        3,
        [
            'session-a' => ['qty' => 2, 'value' => 40.0],
            'session-b' => ['qty' => 1, 'value' => 15.0],
            'session-c' => ['qty' => 4, 'value' => 80.0],
        ],
    );

    $values = collect($metrics)->pluck('value', 'label');

    expect($values['Matching sessions'])->toBe(3)
        ->and($values['At stake'])->toBe('£135.00')
        ->and($values['Items in cart'])->toBe('7');
});
