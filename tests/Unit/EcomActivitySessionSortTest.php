<?php

use App\Models\ActivityEcomUser;
use App\Support\EcomActivitySessionSort;
use Carbon\Carbon;
use Tests\TestCase;

uses(TestCase::class);

test('default funnel sort uses session columns without line item subqueries', function () {
    $query = ActivityEcomUser::query();
    $scope = [
        'from' => Carbon::parse('2026-01-01'),
        'to' => Carbon::parse('2026-01-31'),
        'catalog_options' => [],
    ];

    $sql = EcomActivitySessionSort::apply($query, 'funnel_stage', 'desc', $scope)->toSql();

    expect($sql)->not->toContain('funnel_line_times')
        ->and($sql)->not->toContain('funnel_order_times')
        ->and($sql)->toContain('has_payment_success');
});
