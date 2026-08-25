<?php

use App\Services\EcomTrackerDashboardService;

uses(Tests\TestCase::class);

test('purchase line revenue treats price as line total when checkout subtotal matches', function () {
    $service = app(EcomTrackerDashboardService::class);
    $resolve = new ReflectionMethod(EcomTrackerDashboardService::class, 'resolvePaymentLinePriceMode');
    $resolve->setAccessible(true);
    $revenue = new ReflectionMethod(EcomTrackerDashboardService::class, 'resolvePurchaseLineRevenue');
    $revenue->setAccessible(true);

    $items = [[
        'product_code' => 'MA44131120',
        'qty' => 2,
        'price' => 28,
    ]];

    $mode = $resolve->invoke($service, $items, 28.0);

    expect($mode)->toBe('line')
        ->and($revenue->invoke($service, $items[0], $mode))->toBe(28.0);
});

test('purchase line revenue treats price as unit price when checkout subtotal matches extended totals', function () {
    $service = app(EcomTrackerDashboardService::class);
    $resolve = new ReflectionMethod(EcomTrackerDashboardService::class, 'resolvePaymentLinePriceMode');
    $resolve->setAccessible(true);
    $revenue = new ReflectionMethod(EcomTrackerDashboardService::class, 'resolvePurchaseLineRevenue');
    $revenue->setAccessible(true);

    $items = [
        ['product_code' => 'SKU-1', 'qty' => 2, 'price' => 30],
        ['product_code' => 'SKU-2', 'qty' => 1, 'price' => 30],
    ];

    $mode = $resolve->invoke($service, $items, 90.0);

    expect($mode)->toBe('unit')
        ->and($revenue->invoke($service, $items[0], $mode))->toBe(60.0)
        ->and($revenue->invoke($service, $items[1], $mode))->toBe(30.0);
});

test('purchase line revenue prefers explicit line total fields', function () {
    $service = app(EcomTrackerDashboardService::class);
    $revenue = new ReflectionMethod(EcomTrackerDashboardService::class, 'resolvePurchaseLineRevenue');
    $revenue->setAccessible(true);

    expect($revenue->invoke($service, [
        'qty' => 2,
        'price' => 99,
        'line_total' => 28,
    ], 'unit'))->toBe(28.0);
});
