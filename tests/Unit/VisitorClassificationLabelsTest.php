<?php

use App\Support\VisitorClassificationLabels;

uses(Tests\TestCase::class);

test('reason maps cloudflare bot score to automated traffic', function () {
    $result = VisitorClassificationLabels::reason('cloudflare bot score', true);

    expect($result['headline'])->toBe('Automated traffic');
    expect($result['help'])->toContain('Cloudflare');
});

test('reason maps borderline score to likely real visitor', function () {
    $result = VisitorClassificationLabels::reason('cloudflare bot score borderline', false);

    expect($result['headline'])->toBe('Likely real visitor');
});

test('reason maps missing ua to automated traffic', function () {
    $result = VisitorClassificationLabels::reason('missing UA', true);

    expect($result['headline'])->toBe('Automated traffic');
});

test('reason maps crawler ua to crawler or bot', function () {
    $result = VisitorClassificationLabels::reason('known crawler/script UA', true);

    expect($result['headline'])->toBe('Crawler or bot');
});

test('reason maps no signals to real visitor', function () {
    $result = VisitorClassificationLabels::reason('no bot signals detected', false);

    expect($result['headline'])->toBe('Real visitor');
});

test('type label returns marketer friendly names', function () {
    expect(VisitorClassificationLabels::typeLabel('human'))->toBe('Real visitor');
    expect(VisitorClassificationLabels::typeLabelPlural('human'))->toBe('Real visitors');
    expect(VisitorClassificationLabels::typeLabel('bot'))->toBe('Automated traffic');
    expect(VisitorClassificationLabels::typeLabel('unclassified'))->toBe('Not classified');
});

test('country breakdown label formats common country codes', function () {
    expect(VisitorClassificationLabels::countryBreakdownLabel('gb'))->toBe('United Kingdom (GB)');
    expect(VisitorClassificationLabels::countryBreakdownLabel('US'))->toBe('United States (US)');
    expect(VisitorClassificationLabels::countryBreakdownLabel('DE'))->toBe('DE');
});

test('trust score label formats scores', function () {
    config(['bot-detection.cloudflare_bot_score_threshold' => 30]);

    expect(VisitorClassificationLabels::trustScoreLabel(null))->toBe('Not available');
    expect(VisitorClassificationLabels::trustScoreLabel(10))->toContain('likely automated');
    expect(VisitorClassificationLabels::trustScoreLabel(40))->toContain('borderline');
    expect(VisitorClassificationLabels::trustScoreLabel(85))->toContain('likely a real visitor');
});

test('user agent hint detects googlebot', function () {
    expect(VisitorClassificationLabels::userAgentHint('Mozilla/5.0 (compatible; Googlebot/2.1)'))
        ->toBe('Google crawler');
});
