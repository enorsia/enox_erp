<?php

use App\Models\User;
use App\Services\EcomTrackerDashboardService;
use App\Support\EcomTrackerCompareSupport;
use App\Support\EcomTrackerViewData;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
});

test('compare page requires permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.compare', ['left_period' => '7d']))
        ->assertForbidden();
});

test('compare page renders period columns', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.compare', ['left_period' => '7d']))
        ->assertOk()
        ->assertSee('Store performance')
        ->assertSee('Compare')
        ->assertDontSee('Executive summary')
        ->assertSee('Period A')
        ->assertSee('Period B')
        ->assertSee('Merchandising')
        ->assertSee('Recoverable sale')
        ->assertSee('Acquisition &amp; audience', false);
});

test('dashboard shows comparison shortcut before user activity', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $html = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', ['period' => '7d']))
        ->assertOk()
        ->getContent();

    expect($html)
        ->toContain('Comparison')
        ->toContain(route('admin.ecom-tracker.dashboard.compare'));

    $comparisonPos = strpos($html, 'Comparison');
    $activityPos = strpos($html, 'User Activity');

    expect($comparisonPos)->toBeLessThan($activityPos);
});

test('compare shortcut preserves dashboard period and back url', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $dashboardUrl = route('admin.ecom-tracker.dashboard', [
        'period' => '7d',
        'device_type' => 'mobile',
    ]);

    $this->actingAs($user)
        ->get($dashboardUrl)
        ->assertOk()
        ->assertSee('left_period=7d', false)
        ->assertSee('left_device_type=mobile', false)
        ->assertSee('back=', false);
});

test('compare defaults period b to previous range when right params are absent', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $service = app(EcomTrackerDashboardService::class);
    $leftFilters = ['period' => '7d'];
    $left = $service->getDashboardData($leftFilters);
    $prevRange = $service->resolvePreviousPeriodRange($left['range']);

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.compare', ['left_period' => '7d']))
        ->assertOk();

    expect($response->getContent())
        ->toContain(\App\Support\TrackerTime::toLocal($prevRange['from'])?->toDateString() ?? '')
        ->toContain(\App\Support\TrackerTime::toLocal($prevRange['to'])?->toDateString() ?? '');
});

test('compare period arrows use side specific query keys', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.compare', [
            'left_period' => 'yesterday',
            'right_period' => '24h',
            'back' => route('admin.ecom-tracker.dashboard', ['period' => '7d']),
        ]))
        ->assertOk();

    $html = $response->getContent();

    expect($html)->toContain('left_period=custom')
        ->and($html)->not->toContain('period=custom&amp;date_from=')
        ->and($html)->not->toContain('period=custom&date_from=');
});

test('compare supports independent left and right session filters', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.compare', [
            'left_period' => '7d',
            'left_device_type' => 'mobile',
            'right_period' => '7d',
            'right_device_type' => 'desktop',
        ]))
        ->assertOk()
        ->assertSee('left_device_type=mobile', false)
        ->assertSee('right_device_type=desktop', false);
});

test('compare product order matches dashboard for identical filters', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $service = app(EcomTrackerDashboardService::class);
    $filters = ['period' => '7d'];
    $dashboardProducts = collect($service->getDashboardData($filters)['products'] ?? [])->pluck('name')->all();

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.compare', [
            'left_period' => '7d',
            'right_period' => '7d',
        ]))
        ->assertOk();

    $html = $response->getContent();

    foreach (array_slice($dashboardProducts, 0, 3) as $productName) {
        expect($html)->toContain($productName);
    }
});

test('executive summary marks revenue increase as improved sentiment', function () {
    $left = [
        'kpis' => [
            ['label' => 'Sessions', 'value' => 100, 'formatted' => '100'],
        ],
        'sale_conversion' => [
            'revenue' => ['label' => 'Sale amount', 'value' => 200, 'formatted' => '£200.00'],
            'item_qty' => ['label' => 'Items sold', 'value' => 10, 'formatted' => '10'],
        ],
        'funnel_dropoff' => [
            'payments' => ['label' => 'Payments', 'value' => 5, 'formatted' => '5.0% / 5'],
            'cart_drop' => ['label' => 'Cart drop', 'value' => 10, 'formatted' => '10.0% / 10'],
            'checkout_drop' => ['label' => 'Checkout drop', 'value' => 8, 'formatted' => '8.0% / 8'],
            'proceed_drop' => ['label' => 'Proceed drop', 'value' => 6, 'formatted' => '6.0% / 6'],
        ],
    ];

    $right = [
        'kpis' => [
            ['label' => 'Sessions', 'value' => 80, 'formatted' => '80'],
        ],
        'sale_conversion' => [
            'revenue' => ['label' => 'Sale amount', 'value' => 100, 'formatted' => '£100.00'],
            'item_qty' => ['label' => 'Items sold', 'value' => 5, 'formatted' => '5'],
        ],
        'funnel_dropoff' => [
            'payments' => ['label' => 'Payments', 'value' => 3, 'formatted' => '3.0% / 3'],
            'cart_drop' => ['label' => 'Cart drop', 'value' => 15, 'formatted' => '15.0% / 15'],
            'checkout_drop' => ['label' => 'Checkout drop', 'value' => 12, 'formatted' => '12.0% / 12'],
            'proceed_drop' => ['label' => 'Proceed drop', 'value' => 9, 'formatted' => '9.0% / 9'],
        ],
    ];

    $rows = EcomTrackerCompareSupport::buildExecutiveSummary($left, $right);
    $revenue = collect($rows)->firstWhere('key', 'sale_amount');
    $cartDrop = collect($rows)->firstWhere('key', 'cart_drop');

    expect($revenue['delta_sentiment'] ?? null)->toBe('good');
    expect($cartDrop['delta_sentiment'] ?? null)->toBe('good');
});

test('compare back url helper returns dashboard when back param is set', function () {
    $dashboardUrl = route('admin.ecom-tracker.dashboard', ['period' => '7d']);

    $request = \Illuminate\Http\Request::create('/admin/ecom-tracker/dashboard/compare', 'GET', [
        'back' => $dashboardUrl,
    ]);

    $back = EcomTrackerViewData::compareBackUrl($request);

    expect($back)->toBe($dashboardUrl);
});
