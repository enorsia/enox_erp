<?php

use App\Support\Enox;

uses(Tests\TestCase::class);

test('enox config defines the shop replica database connection name', function () {
    expect(config('enox.database_connection'))->toBe('enox');
    expect(Enox::connectionName())->toBe('enox');
});

test('enox mysql connection is registered separately from the erp default', function () {
    expect(config('database.connections.enox.driver'))->toBe('mysql');
    expect(config('database.connections.enox'))->toHaveKeys([
        'host',
        'database',
        'username',
        'password',
    ]);
    expect(config('database.default'))->not->toBe('enox');
});

test('enox config still exposes the internal shop api settings', function () {
    expect(config('enox.endpoints.tracking_products_total_sold'))->toBe('tracking/products/total-sold');
});