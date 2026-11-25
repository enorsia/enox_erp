<?php

use App\Services\Shop\EcommerceDepartmentProductSalesService;
use App\Services\Shop\ShopDailySalesMetrics;

uses(Tests\TestCase::class);

test('shop daily sales metrics include all platform qty columns', function () {
    expect(ShopDailySalesMetrics::TOTAL_QTY_SQL)->toContain('enorsia_total_qty')
        ->and(ShopDailySalesMetrics::TOTAL_QTY_SQL)->toContain('temu_total_qty');
});

test('shop daily sales metrics include all platform sale columns', function () {
    expect(ShopDailySalesMetrics::TOTAL_SALE_SQL)->toContain('enorsia_total_sale')
        ->and(ShopDailySalesMetrics::TOTAL_SALE_SQL)->toContain('temu_total_sale');
});

test('department filter order is women men girls boys', function () {
    expect(EcommerceDepartmentProductSalesService::DEPARTMENT_ORDER)->toBe(['Women', 'Men', 'Girls', 'Boys']);
});