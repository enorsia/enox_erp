<?php

use App\Support\EcomTrackerLogger;
use Illuminate\Support\Facades\Log;

uses(Tests\TestCase::class);

test('ecom tracker frontend logger writes to dedicated channel when enabled', function () {
    config(['tracker.logging_enabled' => true, 'tracker.log_channel' => 'ecom_tracker']);

    $channel = Mockery::mock();
    Log::shouldReceive('channel')->once()->with('ecom_tracker')->andReturn($channel);
    $channel->shouldReceive('info')
        ->once()
        ->with('[EcomTracker Frontend] Test message', [
            'step' => 'test.step',
            'flow' => 'frontend',
            'module' => 'ecom_tracker',
            'foo' => 'bar',
        ]);

    EcomTrackerLogger::frontend()->info('test.step', 'Test message', ['foo' => 'bar']);
});

test('ecom tracker backend logger writes with backend prefix', function () {
    config(['tracker.logging_enabled' => true, 'tracker.log_channel' => 'ecom_tracker']);

    $channel = Mockery::mock();
    Log::shouldReceive('channel')->once()->with('ecom_tracker')->andReturn($channel);
    $channel->shouldReceive('info')
        ->once()
        ->with('[EcomTracker Backend] Admin opened store dashboard', [
            'step' => 'analytics.dashboard',
            'flow' => 'backend',
            'module' => 'ecom_tracker',
        ]);

    EcomTrackerLogger::backend()->info('analytics.dashboard', 'Admin opened store dashboard');
});

test('ecom tracker logger is silent when disabled', function () {
    config(['tracker.logging_enabled' => false]);

    Log::shouldReceive('channel')->never();

    EcomTrackerLogger::frontend()->info('test.step', 'Test message');
    EcomTrackerLogger::backend()->info('test.step', 'Test message');
});
