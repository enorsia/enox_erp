<?php

use App\Support\EcomTrackerCompareSupport;
use App\Support\TrackerTime;
use Carbon\Carbon;

uses(Tests\TestCase::class);

test('short range display label strips trailing time window', function () {
    expect(TrackerTime::shortRangeDisplayLabel('Today (00:00:01 to 23:59:59)'))->toBe('Today')
        ->and(TrackerTime::shortRangeDisplayLabel('Yesterday (00:00:01 to 23:59:59)'))->toBe('Yesterday')
        ->and(TrackerTime::shortRangeDisplayLabel('Last 7 days'))->toBe('Last 7 days');
});

test('range display label uses friendly preset and date formats', function () {
    expect(TrackerTime::todayPresetLabel())->toBe('Today')
        ->and(TrackerTime::yesterdayPresetLabel())->toBe('Yesterday')
        ->and(TrackerTime::rangeDisplayLabel(['period' => '24h', 'label' => 'legacy']))->toBe('Today')
        ->and(TrackerTime::rangeDisplayLabel(['period' => 'yesterday', 'label' => 'legacy']))->toBe('Yesterday')
        ->and(TrackerTime::rangeDisplayLabel(['label' => 'Last 7 days']))->toBe('Last 7 days');
});

test('comparison label parts split multi-day ranges for kpi cards', function () {
    expect(TrackerTime::comparisonLabelParts('8 Sep 2026 – 10 Sep 2026'))->toBe([
        'type' => 'range',
        'from' => '8 Sep 2026',
        'to' => '10 Sep 2026',
    ])
        ->and(TrackerTime::comparisonLabelParts('Yesterday'))->toBe([
            'type' => 'single',
            'label' => 'Yesterday',
        ])
        ->and(TrackerTime::comparisonLabelParts('11 Sep 2026 – 11 Sep 2026'))->toBe([
            'type' => 'single',
            'label' => '11 Sep 2026',
        ]);
});

test('same-day custom range shows a single date label', function () {
    $day = Carbon::parse('2026-09-11', TrackerTime::timezone());

    expect(TrackerTime::formatLocalDateRangeLabel(
        $day->copy()->startOfDay(),
        $day->copy()->endOfDay(),
    ))->toBe('11 Sep 2026')
        ->and(TrackerTime::rangeDisplayLabel([
            'period' => 'custom',
            'from' => $day->copy()->startOfDay()->utc(),
            'to' => $day->copy()->endOfDay()->utc(),
        ]))->toBe('11 Sep 2026')
        ->and(TrackerTime::rangeDisplayParts([
            'period' => 'custom',
            'from' => $day->copy()->startOfDay()->utc(),
            'to' => $day->copy()->endOfDay()->utc(),
        ]))->toBe(['type' => 'single', 'label' => '11 Sep 2026'])
        ->and(TrackerTime::formatLocalDateRangeLabel(
            $day->copy()->startOfDay(),
            $day->copy()->addDays(2)->endOfDay(),
        ))->toBe('11 Sep 2026 – 13 Sep 2026')
        ->and(TrackerTime::rangeDisplayParts([
            'period' => 'custom',
            'from' => $day->copy()->startOfDay()->utc(),
            'to' => $day->copy()->addDays(2)->endOfDay()->utc(),
        ]))->toBe([
            'type' => 'range',
            'from' => '11 Sep 2026',
            'to' => '13 Sep 2026',
        ]);
});

test('enrich dashboard with cross comparison adds kpi deltas', function () {
    $left = [
        'range' => ['label' => 'Today'],
        'kpis' => [
            ['label' => 'Sessions', 'value' => 200, 'formatted' => '200'],
        ],
        'sale_conversion' => [],
        'funnel_dropoff' => [],
    ];

    $right = [
        'range' => ['label' => 'Yesterday'],
        'kpis' => [
            ['label' => 'Sessions', 'value' => 100, 'formatted' => '100'],
        ],
        'sale_conversion' => [],
        'funnel_dropoff' => [],
    ];

    $enriched = EcomTrackerCompareSupport::enrichDashboardWithCrossComparison($left, $right, 'Yesterday');
    $sessions = collect($enriched['kpis'])->firstWhere('label', 'Sessions');

    expect($sessions['comparison']['delta_pct'] ?? null)->toBe(100.0)
        ->and($sessions['comparison']['previous_formatted'] ?? null)->toBe('100')
        ->and($sessions['comparison']['comparison_label'] ?? null)->toBe('Yesterday');
});

test('drop metric delta uses session count and inverts improvement to positive change', function () {
    $left = [
        'kpis' => [],
        'sale_conversion' => [],
        'funnel_dropoff' => [
            'cart_drop' => [
                'label' => 'Cart drop',
                'value' => 0.0,
                'count' => 0,
                'formatted' => '0.0% / 0',
            ],
        ],
    ];

    $right = [
        'kpis' => [],
        'sale_conversion' => [],
        'funnel_dropoff' => [
            'cart_drop' => [
                'label' => 'Cart drop',
                'value' => 40.0,
                'count' => 2,
                'formatted' => '40.0% / 2',
            ],
        ],
    ];

    $rows = EcomTrackerCompareSupport::buildExecutiveSummary($left, $right);
    $cartDrop = collect($rows)->firstWhere('key', 'cart_drop');

    expect($cartDrop['delta_pct'] ?? null)->toBe(100.0)
        ->and($cartDrop['delta_direction'] ?? null)->toBe('up')
        ->and($cartDrop['delta_sentiment'] ?? null)->toBe('good');
});

test('drop metric delta marks higher counts as negative change', function () {
    $left = [
        'kpis' => [],
        'sale_conversion' => [],
        'funnel_dropoff' => [
            'checkout_drop' => [
                'label' => 'Checkout drop',
                'value' => 40.0,
                'count' => 4,
                'formatted' => '40.0% / 4',
            ],
        ],
    ];

    $right = [
        'kpis' => [],
        'sale_conversion' => [],
        'funnel_dropoff' => [
            'checkout_drop' => [
                'label' => 'Checkout drop',
                'value' => 20.0,
                'count' => 2,
                'formatted' => '20.0% / 2',
            ],
        ],
    ];

    $rows = EcomTrackerCompareSupport::buildExecutiveSummary($left, $right);
    $checkoutDrop = collect($rows)->firstWhere('key', 'checkout_drop');

    expect($checkoutDrop['delta_pct'] ?? null)->toBe(-100.0)
        ->and($checkoutDrop['delta_direction'] ?? null)->toBe('down')
        ->and($checkoutDrop['delta_sentiment'] ?? null)->toBe('bad');
});

test('enrich dashboard applies inverted drop count comparison to funnel cards', function () {
    $left = [
        'range' => ['label' => 'Today'],
        'kpis' => [],
        'sale_conversion' => [],
        'funnel_dropoff' => [
            'proceed_drop' => [
                'label' => 'Proceed drop',
                'value' => 0.0,
                'count' => 0,
                'formatted' => '0.0% / 0',
            ],
        ],
    ];

    $right = [
        'range' => ['label' => 'Yesterday'],
        'kpis' => [],
        'sale_conversion' => [],
        'funnel_dropoff' => [
            'proceed_drop' => [
                'label' => 'Proceed drop',
                'value' => 25.0,
                'count' => 1,
                'formatted' => '25.0% / 1',
            ],
        ],
    ];

    $enriched = EcomTrackerCompareSupport::enrichDashboardWithCrossComparison($left, $right, 'Yesterday');
    $comparison = $enriched['funnel_dropoff']['proceed_drop']['comparison'] ?? [];

    expect($comparison['delta_pct'] ?? null)->toBe(100.0)
        ->and($comparison['delta_direction'] ?? null)->toBe('up')
        ->and($comparison['delta_sentiment'] ?? null)->toBe('good');
});

test('row delta map highlights top movers', function () {
    $left = [
        ['label' => 'Mobile', 'sessions' => 300],
        ['label' => 'Desktop', 'sessions' => 50],
    ];

    $right = [
        ['label' => 'Mobile', 'sessions' => 100],
        ['label' => 'Desktop', 'sessions' => 80],
    ];

    $map = EcomTrackerCompareSupport::rowDeltaMap($left, $right, fn (array $row) => $row['label'], 'sessions', true);

    expect($map['Mobile']['delta_pct'] ?? null)->toBe(200.0)
        ->and($map['Mobile']['highlight'] ?? null)->toBe('up')
        ->and($map['Desktop']['delta_sentiment'] ?? null)->toBe('bad');
});

test('category metric compare deltas compare each side against the other side', function () {
    $left = [
        'category_departments' => [
            [
                'name' => 'Mens',
                'category_views' => 15,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => 0,
                'sale_amount' => 0,
                'categories' => [],
            ],
        ],
    ];

    $right = [
        'category_departments' => [
            [
                'name' => 'Mens',
                'category_views' => 10,
                'product_views' => 0,
                'adds' => 0,
                'proceed_checkouts' => 0,
                'sale_items' => 0,
                'sale_amount' => 0,
                'categories' => [],
            ],
        ],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildCategoryMetricCompareDeltas($left, $right);
    $rightDeltas = EcomTrackerCompareSupport::buildCategoryMetricCompareDeltas($right, $left);

    expect($leftDeltas['dept:Mens']['category_views']['delta_pct'] ?? null)->toBe(50.0)
        ->and($rightDeltas['dept:Mens']['category_views']['delta_pct'] ?? null)->toBe(-33.3);
});

test('product metric compare deltas compare each side against the other side', function () {
    $left = [
        'products' => [
            [
                'code' => 'SKU-1',
                'name' => 'Blue Shirt',
                'views' => 30,
                'adds' => 6,
                'proceed_checkouts' => 3,
                'qty' => 2,
                'revenue' => 120,
            ],
        ],
    ];

    $right = [
        'products' => [
            [
                'code' => 'SKU-1',
                'name' => 'Blue Shirt',
                'views' => 20,
                'adds' => 4,
                'proceed_checkouts' => 2,
                'qty' => 1,
                'revenue' => 60,
            ],
        ],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildProductMetricCompareDeltas($left, $right);
    $rightDeltas = EcomTrackerCompareSupport::buildProductMetricCompareDeltas($right, $left);

    expect($leftDeltas['product:SKU-1']['views']['delta_pct'] ?? null)->toBe(50.0)
        ->and($rightDeltas['product:SKU-1']['views']['delta_pct'] ?? null)->toBe(-33.3)
        ->and($leftDeltas['product:SKU-1']['revenue']['delta_pct'] ?? null)->toBe(100.0);
});

test('build category metric compare deltas maps each metric for departments and categories', function () {
    $left = [
        'category_departments' => [
            [
                'name' => 'Mens',
                'category_views' => 20,
                'product_views' => 40,
                'adds' => 4,
                'proceed_checkouts' => 2,
                'sale_items' => 1,
                'sale_amount' => 100,
                'categories' => [
                    [
                        'category_name' => 'Shirts',
                        'category_views' => 10,
                        'product_views' => 20,
                        'adds' => 2,
                        'proceed_checkouts' => 1,
                        'sale_items' => 1,
                        'sale_amount' => 60,
                    ],
                ],
            ],
        ],
    ];

    $right = [
        'category_departments' => [
            [
                'name' => 'Mens',
                'category_views' => 10,
                'product_views' => 20,
                'adds' => 2,
                'proceed_checkouts' => 1,
                'sale_items' => 0,
                'sale_amount' => 50,
                'categories' => [
                    [
                        'category_name' => 'Shirts',
                        'category_views' => 5,
                        'product_views' => 10,
                        'adds' => 1,
                        'proceed_checkouts' => 0,
                        'sale_items' => 0,
                        'sale_amount' => 30,
                    ],
                ],
            ],
        ],
    ];

    $deltas = EcomTrackerCompareSupport::buildCategoryMetricCompareDeltas($left, $right);

    expect($deltas['dept:Mens']['category_views']['delta_pct'] ?? null)->toBe(100.0)
        ->and($deltas['dept:Mens']['sale_amount']['delta_pct'] ?? null)->toBe(100.0)
        ->and($deltas['cat:Mens/Shirts']['adds']['delta_pct'] ?? null)->toBe(100.0)
        ->and($deltas['cat:Mens/Shirts']['sale_items']['delta_direction'] ?? null)->toBe('up')
        ->and($deltas['cat:Mens/Shirts']['sale_items']['delta_pct'] ?? null)->toBeNull();
});

test('executive metric activity focus maps metrics to drill down focuses', function () {
    expect(EcomTrackerCompareSupport::executiveMetricActivityFocus('sale_amount'))->toBe('conversion')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('sessions'))->toBe('audience')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('cart_drop'))->toBe('cart_abandonment')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('payments'))->toBe('payment_success')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('unknown'))->toBeNull();
});

test('device metric compare deltas compare each side against the other side', function () {
    $left = [
        'devices' => [
            'by_device' => [
                ['label' => 'Mobile', 'sessions' => 150],
            ],
        ],
    ];

    $right = [
        'devices' => [
            'by_device' => [
                ['label' => 'Mobile', 'sessions' => 100],
            ],
        ],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildDeviceMetricCompareDeltas($left, $right);
    $rightDeltas = EcomTrackerCompareSupport::buildDeviceMetricCompareDeltas($right, $left);

    expect($leftDeltas['Mobile']['sessions']['delta_pct'] ?? null)->toBe(50.0)
        ->and($rightDeltas['Mobile']['sessions']['delta_pct'] ?? null)->toBe(-33.3);
});

test('browser metric compare deltas compare each side against the other side', function () {
    $left = [
        'devices' => [
            'by_browser' => [
                ['label' => 'Chrome', 'sessions' => 150],
            ],
        ],
    ];

    $right = [
        'devices' => [
            'by_browser' => [
                ['label' => 'Chrome', 'sessions' => 100],
            ],
        ],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildBrowserMetricCompareDeltas($left, $right);
    $rightDeltas = EcomTrackerCompareSupport::buildBrowserMetricCompareDeltas($right, $left);

    expect($leftDeltas['Chrome']['sessions']['delta_pct'] ?? null)->toBe(50.0)
        ->and($rightDeltas['Chrome']['sessions']['delta_pct'] ?? null)->toBe(-33.3);
});

test('traffic metric compare deltas compare revenue per source and medium key', function () {
    $left = [
        'traffic_sources' => [
            ['source' => 'google', 'medium' => 'cpc', 'revenue' => 200],
        ],
    ];

    $right = [
        'traffic_sources' => [
            ['source' => 'google', 'medium' => 'cpc', 'revenue' => 100],
        ],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildTrafficMetricCompareDeltas($left, $right);
    $rightDeltas = EcomTrackerCompareSupport::buildTrafficMetricCompareDeltas($right, $left);

    expect($leftDeltas['google|cpc']['revenue']['delta_pct'] ?? null)->toBe(100.0)
        ->and($rightDeltas['google|cpc']['revenue']['delta_pct'] ?? null)->toBe(-50.0);
});

test('recoverable metric compare deltas invert abandonment sentiment and keep payment success higher is better', function () {
    $left = [
        'cart_abandonment' => ['session_count' => 40, 'at_stake' => 400],
        'payment_success_events' => ['session_count' => 120, 'at_stake' => 0],
    ];

    $right = [
        'cart_abandonment' => ['session_count' => 80, 'at_stake' => 800],
        'payment_success_events' => ['session_count' => 60, 'at_stake' => 0],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildRecoverableMetricCompareDeltas($left, $right);

    expect($leftDeltas['cart_abandonment']['session_count']['delta_pct'] ?? null)->toBe(50.0)
        ->and($leftDeltas['cart_abandonment']['session_count']['delta_sentiment'] ?? null)->toBe('good')
        ->and($leftDeltas['payment_success_events']['session_count']['delta_pct'] ?? null)->toBe(100.0)
        ->and($leftDeltas['payment_success_events']['session_count']['delta_sentiment'] ?? null)->toBe('good');
});

test('audience metric compare deltas compare unique visitors', function () {
    $left = [
        'new_returning' => ['unique' => 300, 'returning' => 90],
        'duration_distribution' => ['median_seconds' => 180],
    ];

    $right = [
        'new_returning' => ['unique' => 200, 'returning' => 60],
        'duration_distribution' => ['median_seconds' => 120],
    ];

    $leftDeltas = EcomTrackerCompareSupport::buildAudienceMetricCompareDeltas($left, $right);
    $rightDeltas = EcomTrackerCompareSupport::buildAudienceMetricCompareDeltas($right, $left);

    expect($leftDeltas['unique']['delta_pct'] ?? null)->toBe(50.0)
        ->and($rightDeltas['unique']['delta_pct'] ?? null)->toBe(-33.3)
        ->and($leftDeltas['median_seconds']['delta_pct'] ?? null)->toBe(50.0);
});
