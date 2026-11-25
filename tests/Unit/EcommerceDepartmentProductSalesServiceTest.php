<?php

use App\Services\Shop\EcommerceDepartmentProductSalesService;
use App\Support\Enox;

uses(Tests\TestCase::class);

test('department product sales service can aggregate from enorsia replica when configured', function () {
    try {
        Enox::connection()->getPdo();
    } catch (Throwable) {
        $this->markTestSkipped('ENOX_DB_* replica is not configured for this environment.');
    }

    $service = app(EcommerceDepartmentProductSalesService::class);
    $from = now()->subDays(30)->toDateString();
    $to = now()->toDateString();

    $paginator = $service->paginate(
        $from,
        $to,
        '',
        [],
        'total_sale',
        'desc',
        5,
        1,
        '/test',
        [],
    );

    expect($paginator->total())->toBeGreaterThanOrEqual(0);

    $first = $paginator->items()[0] ?? null;
    if ($first !== null) {
        expect($first)->toHaveKeys(['department_name', 'product_code', 'total_qty', 'total_sale']);
    }
});