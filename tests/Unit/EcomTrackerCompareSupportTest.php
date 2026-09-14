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

test('executive metric activity focus maps metrics to drill down focuses', function () {
    expect(EcomTrackerCompareSupport::executiveMetricActivityFocus('sale_amount'))->toBe('conversion')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('sessions'))->toBe('audience')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('cart_drop'))->toBe('cart_abandonment')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('payments'))->toBe('payment_success')
        ->and(EcomTrackerCompareSupport::executiveMetricActivityFocus('unknown'))->toBeNull();
});
