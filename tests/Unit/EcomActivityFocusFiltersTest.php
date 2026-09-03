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
    $keywordRequest = Request::create('/', 'GET', ['search' => 'hoodie']);
    $productCodeRequest = Request::create('/', 'GET', ['search' => 'MS31262181']);

    expect(EcomActivityFocus::usesCatalogScopedSearch($productsRequest))->toBeTrue()
        ->and(EcomActivityFocus::usesCatalogScopedSearch($categoriesRequest))->toBeTrue()
        ->and(EcomActivityFocus::usesCatalogScopedSearch($audienceRequest))->toBeFalse()
        ->and(EcomActivityFocus::productCatalogFiltersFromRequest($keywordRequest))->toBe([])
        ->and(EcomActivityFocus::searchFilterLabel($keywordRequest))->toBe('Search')
        ->and(EcomActivityFocus::looksLikeProductCodeSearch('MS31262181'))->toBeTrue()
        ->and(EcomActivityFocus::looksLikeProductCodeSearch('hoodie'))->toBeFalse()
        ->and(EcomActivityFocus::looksLikeIdentitySearch('hodgson21142@outlook.com'))->toBeTrue()
        ->and(EcomActivityFocus::looksLikeProductCodeSearch('hodgson21142@outlook.com'))->toBeFalse()
        ->and(EcomActivityFocus::shouldUseProductScopedSearchInIndex($productCodeRequest))->toBeTrue()
        ->and(EcomActivityFocus::indexCatalogFiltersFromRequest($productCodeRequest))->toBe(['search' => 'MS31262181']);
});

test('drawer preserve params keep drill-down context and preserve dashboard catalog params', function () {
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

    expect($preserved)->toHaveKeys(['focus', 'back', 'color', 'activity'])
        ->and($preserved)->not->toHaveKey('category')
        ->and($preserved)->not->toHaveKey('search')
        ->and($preserved)->not->toHaveKey('device_type');
});

test('active filter count includes session and sidebar filters on catalog focus', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'device_type' => 'mobile',
        'category' => 'Tops',
        'activity' => 'views',
        'product_code' => 'TEE-1',
    ]);

    expect(EcomActivityFocus::activeFilterCount($request))->toBe(3);
});

test('product drill-down filters use a single short product chip', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'product_code' => 'WS333217',
        'product_name' => 'White Cotton Stretch Jersey Shorts',
    ]);

    $criteria = EcomActivityFocus::filterCriteriaFromRequest($request);
    $labels = collect($criteria)->pluck('label')->all();
    $values = collect($criteria)->pluck('value', 'label')->all();

    expect($labels)->toBe(['Product'])
        ->and($values['Product'])->toBe('WS333217')
        ->and(EcomActivityFocus::activeFilterCount($request))->toBe(1);

    $chips = EcomActivityFocus::activeFilterChipsFromRequest($request);

    expect($chips)->toHaveCount(1)
        ->and($chips[0]['label'])->toBe('Product: WS333217');
});

test('product drill-down shows session keyword search in drawer and applies search chip', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'product_code' => 'WS333217',
        'search' => 'jane@example.com',
    ]);

    expect(EcomActivityFocus::showSessionKeywordSearchInDrawer($request))->toBeTrue()
        ->and(EcomActivityFocus::shouldApplySessionKeywordSearch($request))->toBeTrue()
        ->and(EcomActivityFocus::productCatalogFiltersFromRequest($request))->toBe(['product_code' => 'WS333217'])
        ->and(EcomActivityFocus::activeFilterCount($request))->toBe(2);

    $labels = collect(EcomActivityFocus::filterCriteriaFromRequest($request))->pluck('label')->all();

    expect($labels)->toContain('Product', 'Search');
});

test('active filter chips include sidebar filters on catalog focus', function () {
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
        ->and($labels)->toContain('Device: Mobile')
        ->and($labels)->not->toContain('Activity: Views');
});

test('format funnel summary metrics from normalized acquisition row', function () {
    $normalize = new ReflectionMethod(EcomActivityFocus::class, 'normalizeAcquisitionRow');
    $normalize->setAccessible(true);

    $format = new ReflectionMethod(EcomActivityFocus::class, 'formatFunnelSummaryMetrics');
    $format->setAccessible(true);

    $normalized = $normalize->invoke(null, [
        'views' => 11,
        'add_to_cart' => 3,
        'begin_checkout' => 2,
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
        'Checkout',
        'Proceed',
        'Cart abandoned',
        'Sold',
        'Sold qty',
        'Sale',
    ])
        ->and($values['Views'])->toBe('11')
        ->and($values['Adds'])->toBe('3')
        ->and($values['Checkout'])->toBe('2')
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
    $durationRequest = Request::create('/', 'GET', ['duration_bucket' => '0-1']);
    $plainRequest = Request::create('/', 'GET', ['period' => '7d']);

    expect(EcomActivityFocus::resolveFilterSummaryFocus($deviceRequest))->toBe('devices')
        ->and(EcomActivityFocus::resolveFilterSummaryFocus($trafficRequest))->toBe('traffic')
        ->and(EcomActivityFocus::resolveFilterSummaryFocus($durationRequest))->toBe('duration')
        ->and(EcomActivityFocus::resolveFilterSummaryFocus($plainRequest))->toBeNull();
});

test('duration bucket is shown as a duration filter criterion', function () {
    $request = Request::create('/', 'GET', ['duration_bucket' => '0-1']);
    $criteria = collect(EcomActivityFocus::filterCriteriaFromRequest($request))->pluck('value', 'label');

    expect($criteria['Duration'] ?? null)->toBe('0–1 min')
        ->and(EcomActivityFocus::label('duration'))->toBe('Session duration');
});

test('activity list context includes funnel metrics for visitor keyword search filters', function () {
    $request = Request::create('/', 'GET', [
        'search' => 'Michael Corbett',
        'period' => '30d',
    ]);

    $dashboard = Mockery::mock(EcomTrackerDashboardService::class);
    $dashboard->shouldReceive('productCatalogEventScenarioOptions')->andReturn([]);
    $dashboard->shouldReceive('productCatalogActivityFilterOptions')->andReturn([]);
    $dashboard->shouldReceive('activityFunnelSummaryForFilters')
        ->once()
        ->withArgs(function ($from, $to, array $filters) {
            return ($filters['keyword_search'] ?? null) === 'Michael Corbett'
                && ! array_key_exists('search', $filters);
        })
        ->andReturn([
            'views' => 4,
            'adds' => 2,
            'begin_checkouts' => 1,
            'proceed_checkouts' => 1,
            'purchases' => 1,
            'qty' => 2,
            'revenue' => 45.50,
        ]);
    $dashboard->shouldReceive('audienceSummaryForFilters')
        ->once()
        ->andReturn([
            'unique_visitors' => 1,
            'avg_stay_seconds' => 291,
        ]);

    app()->instance(EcomTrackerDashboardService::class, $dashboard);

    $context = EcomActivityFocus::activityListContext(
        $request,
        'Last 30 days',
        1,
        [],
        Carbon::parse('2026-07-26'),
        Carbon::parse('2026-08-25'),
        '30d',
    );

    $values = collect($context['metrics'] ?? [])->pluck('value', 'label')->all();

    expect(EcomActivityFocus::activitySummaryFiltersFromRequest($request))
        ->toMatchArray(['keyword_search' => 'Michael Corbett'])
        ->and($context['section'])->toBe('Audience & engagement')
        ->and($values['Views'] ?? null)->toBe('4')
        ->and($values['Sold qty'] ?? null)->toBe('2')
        ->and($values['Sale'] ?? null)->toBe('£45.50');
});

test('activity list context includes funnel metrics for keyword search filters', function () {
    $request = Request::create('/', 'GET', [
        'search' => 'MS31262181',
        'period' => '30d',
    ]);

    $dashboard = Mockery::mock(EcomTrackerDashboardService::class);
    $dashboard->shouldReceive('productCatalogEventScenarioOptions')->andReturn([]);
    $dashboard->shouldReceive('productCatalogActivityFilterOptions')->andReturn([]);
    $dashboard->shouldReceive('activityFunnelSummaryForFilters')
        ->once()
        ->andReturn([
            'views' => 120,
            'adds' => 18,
            'begin_checkouts' => 9,
            'proceed_checkouts' => 6,
            'purchases' => 3,
            'qty' => 4,
            'revenue' => 89.97,
        ]);
    $dashboard->shouldReceive('audienceSummaryForFilters')
        ->once()
        ->andReturn([
            'unique_visitors' => 14029,
            'avg_stay_seconds' => 250,
        ]);

    app()->instance(EcomTrackerDashboardService::class, $dashboard);

    $context = EcomActivityFocus::activityListContext(
        $request,
        'Last 30 days',
        14,
        [],
        Carbon::parse('2026-07-26'),
        Carbon::parse('2026-08-25'),
        '30d',
    );

    $labels = collect($context['metrics'] ?? [])->pluck('label')->all();

    expect($context['section'])->toBe('Audience & engagement')
        ->and($labels)->toContain('Views', 'Adds', 'Checkout', 'Proceed', 'Sold', 'Sold qty', 'Sale', 'Unique visitors', 'Avg stay');
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
            'begin_checkouts' => 2,
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

test('activity sidebar filter keys include visitor audience and funnel fields', function () {
    $request = Request::create('/', 'GET', ['focus' => 'traffic']);

    $keys = EcomActivityFocus::sidebarFilterQueryKeys($request);

    expect($keys)->toContain('device_type', 'utm_source', 'logged_in', 'funnel', 'visitor_type', 'duration_bucket')
        ->and($keys)->not->toContain('country', 'date_from', 'date_to');
});

test('drawer preserve params keep drill-down focus but expose visitor type in drawer', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'session_quality',
        'visitor_type' => 'human',
        'period' => '7d',
    ]);

    $preserved = EcomActivityFocus::drawerPreserveQueryParams($request);

    expect($preserved)->toHaveKeys(['focus', 'period'])
        ->and($preserved)->not->toHaveKey('visitor_type');
});

test('activity sidebar chips include visitor type but not country', function () {
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
        ->and($labels)->toContain('Visitor type: Real visitors')
        ->and($labels)->not->toContain('Country: GB');
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

test('activity drawer department options are not limited to products or categories focus', function () {
    $request = Request::create('/', 'GET', [
        'period' => '30d',
        'funnel' => 'cart_abandonment',
        'department' => 'Women',
        'device_type' => 'mobile',
    ]);

    expect(EcomActivityFocus::showCatalogFiltersInDrawer($request))->toBeFalse()
        ->and(EcomActivityFocus::sessionFiltersFromRequest($request))
        ->toHaveKey('device_type')
        ->not->toHaveKey('department')
        ->not->toHaveKey('category')
        ->and(EcomActivityFocus::sidebarFilterQueryKeys($request))->toContain('department', 'category');
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

test('catalog filters reconcile mismatched department and category combinations', function () {
    $request = Request::create('/', 'GET', [
        'department' => 'Women',
        'category' => 'Chinos',
    ]);

    $filterOptions = [
        'departments' => ['Men', 'Women'],
        'categories_by_department' => [
            'Men' => ['Chinos'],
            'Women' => ['Dresses'],
        ],
    ];

    expect(EcomActivityFocus::reconcileCatalogFilters($request, $filterOptions))
        ->toBe([
            'department' => 'Men',
            'category' => 'Chinos',
        ])
        ->and(EcomActivityFocus::reconcileCatalogFilters(
            Request::create('/', 'GET', ['department' => 'Men', 'category' => 'Chinos']),
            $filterOptions,
        ))->toBeNull();
});

test('categories focus omits top category column when category filter is active', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'categories',
        'department' => 'Women',
        'category' => 'Co-ords',
    ]);

    $columns = EcomActivityFocus::tableColumns('categories', $request);

    expect(collect($columns)->pluck('key')->all())->toBe(['purchases']);
});

test('categories focus keeps top category column when only department filter is active', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'categories',
        'department' => 'Women',
    ]);

    $columns = EcomActivityFocus::tableColumns('categories', $request);

    expect(collect($columns)->pluck('key')->all())->toBe(['top_category', 'purchases']);
});
