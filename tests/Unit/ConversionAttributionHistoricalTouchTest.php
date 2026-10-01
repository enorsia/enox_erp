<?php

use App\Support\AttributionRules;
use App\Support\SessionTrafficAttribution;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

uses(TestCase::class);

test('last marketing touch for visitor uses newest qualifying action before payment', function () {
    $paymentAt = Carbon::parse('2026-09-25 19:17:35', 'UTC');

    $touch = SessionTrafficAttribution::lastMarketingTouchForVisitorBefore(
        'a5190fb2-ade5-405b-82dc-8dc22fb458cf',
        $paymentAt,
    );

    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL fixture visitor required.');
    }

    expect($touch)->not->toBeNull()
        ->and($touch['utm_source'])->toBe('google')
        ->and($touch['utm_medium'])->toBe('paid')
        ->and($touch['captured_at']->lessThanOrEqualTo($paymentAt))->toBeTrue()
        ->and($touch['captured_at']->greaterThanOrEqualTo(
            $paymentAt->copy()->subDays(AttributionRules::attributionWindowDays()),
        ))->toBeTrue();
})->group('mysql');
