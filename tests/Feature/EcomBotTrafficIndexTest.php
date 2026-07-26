<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserBotContext;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.bot_traffic.index', 'web');
    Permission::findOrCreate('ecom_tracker.activity.show', 'web');
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
});

test('bot traffic hub requires permission', function () {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.bot-traffic'))
        ->assertForbidden();
});

test('bot traffic hub shows only automated sessions and metrics', function () {
    $user = User::factory()->create();
    $user->givePermissionTo(['ecom_tracker.bot_traffic.index', 'ecom_tracker.activity.index']);
    $this->actingAs($user);

    $humanSessionId = Str::uuid()->toString();
    $botSessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $humanSessionId,
        'device_type' => 'desktop',
        'last_active_at' => now(),
        'created_at' => now(),
    ]);

    ActivityEcomUserBotContext::query()->create([
        'session_id' => $humanSessionId,
        'is_bot' => false,
        'bot_confidence' => 'low',
        'bot_reason' => 'no bot signals detected',
        'ip_country' => 'GB',
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $botSessionId,
        'device_type' => 'mobile',
        'last_active_at' => now(),
        'created_at' => now(),
    ]);

    ActivityEcomUserBotContext::query()->create([
        'session_id' => $botSessionId,
        'is_bot' => true,
        'bot_confidence' => 'high',
        'bot_reason' => 'known crawler/script UA',
        'ip_country' => 'US',
    ]);

    $this->get(route('admin.ecom-tracker.bot-traffic', [
        'period' => 'all',
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->toDateString(),
    ]))
        ->assertOk()
        ->assertSee('Bot traffic')
        ->assertSee('Automated sessions')
        ->assertSee('Countries detected')
        ->assertSee('Automated traffic trend')
        ->assertSee('View all')
        ->assertSee('Top detection reasons')
        ->assertSee('Automated traffic by country')
        ->assertSee('Automated traffic sessions')
        ->assertSee(Str::limit($botSessionId, 14))
        ->assertDontSee('Real visitors')
        ->assertDontSee('Not classified')
        ->assertDontSee(Str::limit($humanSessionId, 14))
        ->assertSee('visitor_type=bot', false);
});

test('bot traffic hub filters sessions by country and search', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.bot_traffic.index');
    $this->actingAs($user);

    $usBotId = Str::uuid()->toString();
    $gbBotId = Str::uuid()->toString();

    foreach ([$usBotId => 'US', $gbBotId => 'GB'] as $sessionId => $country) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'last_active_at' => now(),
            'created_at' => now(),
        ]);

        ActivityEcomUserBotContext::query()->create([
            'session_id' => $sessionId,
            'is_bot' => true,
            'bot_confidence' => 'high',
            'bot_reason' => 'known crawler/script UA',
            'ip_country' => $country,
        ]);
    }

    $this->get(route('admin.ecom-tracker.bot-traffic', [
        'period' => 'all',
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->toDateString(),
        'country' => 'GB',
    ]))
        ->assertOk()
        ->assertSee(Str::limit($gbBotId, 14))
        ->assertDontSee(Str::limit($usBotId, 14))
        ->assertSee('Country: GB', false);
});

test('bot traffic hub lists detected countries for automated traffic only', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.bot_traffic.index');
    $this->actingAs($user);

    foreach ([
        ['country' => 'GB', 'is_bot' => true],
        ['country' => 'US', 'is_bot' => true],
        ['country' => 'GB', 'is_bot' => false],
    ] as $entry) {
        $sessionId = Str::uuid()->toString();

        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'last_active_at' => now(),
            'created_at' => now(),
        ]);

        ActivityEcomUserBotContext::query()->create([
            'session_id' => $sessionId,
            'is_bot' => $entry['is_bot'],
            'bot_confidence' => $entry['is_bot'] ? 'high' : 'low',
            'bot_reason' => $entry['is_bot'] ? 'known crawler/script UA' : 'no bot signals detected',
            'ip_country' => $entry['country'],
        ]);
    }

    $this->get(route('admin.ecom-tracker.bot-traffic', [
        'period' => 'all',
        'date_from' => now()->subDay()->toDateString(),
        'date_to' => now()->toDateString(),
    ]))
        ->assertOk()
        ->assertSee('Automated traffic by country')
        ->assertSee('United Kingdom (GB)')
        ->assertSee('US');
});
