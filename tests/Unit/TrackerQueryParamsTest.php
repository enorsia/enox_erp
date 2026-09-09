<?php

use App\Support\TrackerQueryParams;

test('tracker query params normalize indexed and empty bracket keys', function () {
    expect(TrackerQueryParams::normalize([
        'funnel[0]' => 'payment_success',
        'funnel[1]' => 'cart_abandonment',
        'device_type[]' => 'mobile',
        'utm_source[0]' => 'google',
        'period' => '7d',
    ]))->toBe([
        'funnel' => ['payment_success', 'cart_abandonment'],
        'device_type' => ['mobile'],
        'utm_source' => ['google'],
        'period' => '7d',
    ]);
});

test('tracker query params request exposes normalized multi select filters', function () {
    $request = TrackerQueryParams::request([
        'funnel[0]' => 'payment_success',
        'period' => '7d',
    ]);

    expect($request->input('funnel'))->toBe(['payment_success'])
        ->and($request->input('period'))->toBe('7d');
});
