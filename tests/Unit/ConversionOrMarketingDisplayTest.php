<?php

use App\Models\ActivityEcomUser;
use App\Support\SessionTrafficAttribution;
use Tests\TestCase;

uses(TestCase::class);

test('conversion or marketing display shows visitor marketing when no purchase conversion', function () {
    $session = new ActivityEcomUser([
        'session_id' => 'test-session',
        'has_payment_success' => false,
        'list_traffic_utm_source' => 'google',
        'list_traffic_utm_medium' => 'paid',
    ]);

    $fields = SessionTrafficAttribution::conversionOrMarketingDisplayFields($session);

    expect($fields['Marketing source'])->toBe('Google')
        ->and($fields['Marketing medium'])->toBe('paid');
});

test('conversion or marketing display hides section data for direct only', function () {
    $session = new ActivityEcomUser([
        'session_id' => 'direct-session',
        'list_traffic_utm_source' => '(direct)',
        'list_traffic_utm_medium' => 'none',
    ]);

    expect(SessionTrafficAttribution::conversionOrMarketingDisplayFields($session))->toBe([]);
});
