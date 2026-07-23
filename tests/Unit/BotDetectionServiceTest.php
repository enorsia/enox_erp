<?php

use App\Services\BotDetectionService;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new BotDetectionService();
});

test('isLikelyBotFromContext classifies low cf bot score as bot', function () {
    config(['bot-detection.cloudflare_bot_score_threshold' => 30]);

    $result = $this->service->isLikelyBotFromContext([
        'cf_bot_score' => 10,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);

    expect($result['is_bot'])->toBeTrue();
    expect($result['confidence'])->toBe('high');
    expect($result['reason'])->toBe('cloudflare bot score');
});

test('isLikelyBotFromContext skips cf bot score rule when score is null', function () {
    config(['bot-detection.cloudflare_bot_score_threshold' => 30]);

    $result = $this->service->isLikelyBotFromContext([
        'cf_bot_score' => null,
        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    ]);

    expect($result['is_bot'])->toBeFalse();
    expect($result['confidence'])->toBe('low');
    expect($result['reason'])->toBe('no bot signals detected');
});

test('isLikelyBotFromContext classifies missing user agent as bot', function () {
    $result = $this->service->isLikelyBotFromContext([
        'user_agent' => null,
    ]);

    expect($result['is_bot'])->toBeTrue();
    expect($result['confidence'])->toBe('high');
    expect($result['reason'])->toBe('missing UA');
});

test('isLikelyBotFromContext classifies known crawler user agent as bot', function () {
    $result = $this->service->isLikelyBotFromContext([
        'user_agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    ]);

    expect($result['is_bot'])->toBeTrue();
    expect($result['confidence'])->toBe('high');
    expect($result['reason'])->toBe('known crawler/script UA');
});

test('isLikelyBotFromContext classifies borderline cf bot score as human with medium confidence', function () {
    config(['bot-detection.cloudflare_bot_score_threshold' => 30]);

    $result = $this->service->isLikelyBotFromContext([
        'cf_bot_score' => 40,
        'user_agent' => 'Mozilla/5.0',
    ]);

    expect($result['is_bot'])->toBeFalse();
    expect($result['confidence'])->toBe('medium');
    expect($result['reason'])->toBe('cloudflare bot score borderline');
});
