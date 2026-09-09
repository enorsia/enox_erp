<?php

use App\Models\ActivityEcomUser;
use App\Support\SessionDurationBuckets;

uses(Tests\TestCase::class);

test('session duration buckets assign boundary seconds correctly', function () {
    expect(SessionDurationBuckets::bucketLabelForSeconds(0))->toBe('0–1 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(60))->toBe('0–1 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(61))->toBe('1–3 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(180))->toBe('1–3 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(181))->toBe('3–5 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(300))->toBe('3–5 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(301))->toBe('5–7 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(540))->toBe('7–9 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(900))->toBe('13–15 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(1800))->toBe('15–30 min')
        ->and(SessionDurationBuckets::bucketLabelForSeconds(1801))->toBe('30+ min');
});

test('session duration buckets compute counts percentages and median', function () {
    $result = SessionDurationBuckets::withCounts([30, 90, 120, 600, 1200]);

    expect($result['total_sessions'])->toBe(5)
        ->and($result['median_seconds'])->toBe(120)
        ->and($result['buckets'])->toHaveCount(10);

    $byLabel = collect($result['buckets'])->keyBy('label');

    expect($byLabel['0–1 min']['count'])->toBe(1)
        ->and($byLabel['0–1 min']['key'])->toBe('0-1')
        ->and($byLabel['1–3 min']['count'])->toBe(2)
        ->and($byLabel['9–11 min']['count'])->toBe(1)
        ->and($byLabel['15–30 min']['count'])->toBe(1)
        ->and($byLabel['30+ min']['count'])->toBe(0);

    $pctTotal = round(collect($result['buckets'])->sum('pct'), 1);

    expect($pctTotal)->toBe(100.0);
});

test('session duration buckets return empty distribution for no sessions', function () {
    $result = SessionDurationBuckets::withCounts([]);

    expect($result['total_sessions'])->toBe(0)
        ->and($result['median_seconds'])->toBe(0)
        ->and(collect($result['buckets'])->sum('count'))->toBe(0);
});

test('session duration bucket query filters the matching second range', function () {
    $short = ActivityEcomUser::query();
    SessionDurationBuckets::applyToQuery($short, '0-1');
    $shortSql = str_replace(['`', '"'], '', $short->toSql());

    expect($shortSql)->toContain('COALESCE(activity_ecom_user.session_duration_seconds, 0) between ? and ?')
        ->and($short->getBindings())->toBe([0, 60]);

    $long = ActivityEcomUser::query();
    SessionDurationBuckets::applyToQuery($long, '30-plus');
    $longSql = str_replace(['`', '"'], '', $long->toSql());

    expect($longSql)->toContain('COALESCE(activity_ecom_user.session_duration_seconds, 0) >= ?')
        ->and($long->getBindings())->toBe([1801]);
});
