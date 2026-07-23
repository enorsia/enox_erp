<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserBotContext;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->apiKey = 'test-tracker-key-' . Str::random(16);
    config(['tracker.api_key_hash' => bcrypt($this->apiKey)]);
});

test('resolve visit with valid client context stores bot context on new session', function () {
    $visitorId = (string) Str::uuid();

    $response = $this->postJson('/api/tracker/resolve-visit', [
        'visitor_id' => $visitorId,
        'client_context' => [
            'client_ip' => '203.0.113.50',
            'user_agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)',
            'ip_country' => 'GB',
            'cf_ray' => '7abc123def456789-LHR',
            'cf_bot_score' => 88,
        ],
    ], [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'visitor_id',
            'session_id',
            'is_new_daily_visitor',
            'is_new_session',
        ]);

    $sessionId = $response->json('session_id');

    $session = ActivityEcomUser::where('session_id', $sessionId)->first();
    $botContext = ActivityEcomUserBotContext::where('session_id', $sessionId)->first();

    expect($session)->not->toBeNull();
    expect($session->ip)->toBe('203.0.113.50');
    expect($session->country)->toBe('GB');
    expect($botContext)->not->toBeNull();
    expect($botContext->is_bot)->toBeFalse();
    expect($botContext->cf_bot_score)->toBe(88);
    expect($botContext->cf_ray)->toBe('7abc123def456789-LHR');
});

test('resolve visit without client context creates session without bot row', function () {
    $visitorId = (string) Str::uuid();

    $response = $this->postJson('/api/tracker/resolve-visit', [
        'visitor_id' => $visitorId,
    ], [
        'Authorization' => 'Bearer ' . $this->apiKey,
    ]);

    $response->assertOk();

    $sessionId = $response->json('session_id');

    expect(ActivityEcomUser::where('session_id', $sessionId)->exists())->toBeTrue();
    expect(ActivityEcomUserBotContext::where('session_id', $sessionId)->exists())->toBeFalse();
});
