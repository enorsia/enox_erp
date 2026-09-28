<?php

use App\Models\ActivityEcomUser;
use App\Support\EcomActivityRelatedSessions;
use Illuminate\Support\Str;

test('related sessions includes current session and orders by last active', function () {
    $visitorId = Str::uuid()->toString();
    $currentId = Str::uuid()->toString();
    $olderId = Str::uuid()->toString();
    $newerId = Str::uuid()->toString();

    $current = ActivityEcomUser::query()->create([
        'session_id' => $currentId,
        'visitor_id' => $visitorId,
        'last_active_at' => now()->subMinutes(10),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $olderId,
        'visitor_id' => $visitorId,
        'last_active_at' => now()->subDay(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $newerId,
        'visitor_id' => $visitorId,
        'last_active_at' => now(),
    ]);

    $related = EcomActivityRelatedSessions::forSession($current);

    expect($related)->toHaveCount(3);
    expect($related->first()->session_id)->toBe($newerId);
    expect($related->pluck('session_id')->all())->toContain($currentId);
});

test('related sessions returns empty when visitor id is blank', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => Str::uuid()->toString(),
        'last_active_at' => now(),
    ]);

    expect(EcomActivityRelatedSessions::forSession($session))->toBeEmpty();
});
