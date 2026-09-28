<?php

use App\Models\TrackerUtmFilter;

test('session filter source bucket treats empty utm and clean landing as direct', function () {
    $session = (object) [
        'utm_source' => null,
        'utm_medium' => null,
        'landing_page' => 'https://shop.example.com/products/foo',
    ];

    expect(TrackerUtmFilter::sessionFilterTrafficBucket($session))->toBe([
        'source' => '(direct)',
        'medium' => 'none',
    ]);
});

test('session filter source bucket infers google from landing gclid', function () {
    $session = (object) [
        'utm_source' => null,
        'landing_page' => 'https://shop.example.com/?gclid=abc',
    ];

    expect(TrackerUtmFilter::sessionSourceBucket($session))->toBe('google');
});

test('session filter source bucket uses utm column over referer-style signals not on landing', function () {
    $session = (object) [
        'utm_source' => null,
        'landing_page' => 'https://shop.example.com/checkout',
    ];

    expect(TrackerUtmFilter::sessionSourceBucket($session))->toBe('(direct)');
});
