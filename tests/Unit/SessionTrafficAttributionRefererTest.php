<?php

use App\Support\SessionTrafficAttribution;
use Tests\TestCase;

uses(TestCase::class);

test('session traffic attribution resolves awin affiliate from same site referer query string', function () {
    $referer = 'https://enorsia.com/shop/clearance-sale?source=aw&utm_source=awin&sv1=affiliate&sv_campaign_id=80338&awc=118905_1790326895_b0cb967922f7ef15871e672b564b1178';

    expect(SessionTrafficAttribution::sessionAttributesFromIngest(
        ['landing_page' => 'https://enorsia.com/order/tracking'],
        'https://enorsia.com/style/womens-butterfly-print-sweatshirt?color=navy',
        $referer,
    ))->toMatchArray([
        'utm_source' => 'awin',
        'utm_medium' => 'affiliate',
        'utm_campaign' => '80338',
        'landing_page' => 'https://enorsia.com/style/womens-butterfly-print-sweatshirt?color=navy',
    ]);
});
