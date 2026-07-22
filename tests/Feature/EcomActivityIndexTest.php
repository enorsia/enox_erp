<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\ActivityEcomUserBotContext;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
    Permission::findOrCreate('ecom_tracker.activity.show', 'web');
});

test('ecom activity index searches by user name and email', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    ActivityEcomUser::query()->create([
        'session_id' => Str::uuid()->toString(),
        'user_name' => 'Jane Shopper',
        'user_email' => 'jane@example.com',
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    $this->get(route('admin.ecom-activity.index', ['search' => 'jane@example.com']))
        ->assertOk()
        ->assertSee('jane@example.com');

    $this->get(route('admin.ecom-activity.index', ['search' => 'Jane Shopper']))
        ->assertOk()
        ->assertSee('Jane Shopper');
});

test('ecom activity index filters sessions with orders', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $orderedSession = Str::uuid()->toString();
    $guestSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $orderedSession,
        'device_type' => 'mobile',
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $guestSession,
        'device_type' => 'mobile',
        'last_active_at' => now()->subMinute(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $orderedSession,
        'action_type' => 'payment_success',
        'payment_success' => ['amount_paid' => 50],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->get(route('admin.ecom-activity.index', ['has_order' => '1']))
        ->assertOk()
        ->assertSee(Str::limit($orderedSession, 14))
        ->assertDontSee(Str::limit($guestSession, 14));

    $this->get(route('admin.ecom-activity.index', ['has_order' => '0']))
        ->assertOk()
        ->assertSee(Str::limit($guestSession, 14))
        ->assertDontSee(Str::limit($orderedSession, 14));
});

test('ecom activity index labels guest checkout sessions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    ActivityEcomUser::query()->create([
        'session_id' => Str::uuid()->toString(),
        'user_name' => 'Alex Guest',
        'user_email' => 'alex.guest@example.com',
        'is_logged_in' => false,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => Str::uuid()->toString(),
        'user_id' => 99,
        'user_name' => 'Registered Shopper',
        'user_email' => 'registered@example.com',
        'is_logged_in' => true,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('Alex Guest')
        ->assertSee('alex.guest@example.com')
        ->assertSee('Registered Shopper')
        ->assertSee('registered@example.com');
});

test('ecom activity index shows order count for sessions with payment success', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $orderedSession = Str::uuid()->toString();
    $guestSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $orderedSession,
        'device_type' => 'mobile',
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $guestSession,
        'device_type' => 'mobile',
        'last_active_at' => now(),
    ]);

    foreach ([1, 2] as $index) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $orderedSession,
            'action_type' => 'payment_success',
            'payment_success' => ['amount_paid' => 50 * $index],
            'created_at' => now(),
            'start_time' => now(),
            'end_time' => now(),
        ]);
    }

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('2', false)
        ->assertSee(Str::limit($orderedSession, 14))
        ->assertSee(Str::limit($guestSession, 14));
});

test('ecom activity index defaults to last 24 hours', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $recentSession = Str::uuid()->toString();
    $oldSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $recentSession,
        'device_type' => 'desktop',
        'last_active_at' => now(),
        'created_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $oldSession,
        'device_type' => 'desktop',
        'last_active_at' => now()->subDays(10),
        'created_at' => now()->subDays(10),
    ]);

    $this->get(route('admin.ecom-activity.index'))
        ->assertOk()
        ->assertSee('Last 24 hours')
        ->assertSee(Str::limit($recentSession, 14))
        ->assertDontSee(Str::limit($oldSession, 14));

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('All sessions')
        ->assertSee(Str::limit($recentSession, 14))
        ->assertSee(Str::limit($oldSession, 14));
});

test('ecom activity index filters by visitor type bot human and unclassified', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $humanSession = Str::uuid()->toString();
    $botSession = Str::uuid()->toString();
    $unclassifiedSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $humanSession,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $botSession,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $unclassifiedSession,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    ActivityEcomUserBotContext::query()->create([
        'session_id' => $humanSession,
        'client_ip' => '203.0.113.1',
        'ip_country' => 'GB',
        'is_bot' => false,
        'bot_confidence' => 'low',
        'bot_reason' => 'no bot signals detected',
    ]);

    ActivityEcomUserBotContext::query()->create([
        'session_id' => $botSession,
        'client_ip' => '203.0.113.2',
        'ip_country' => 'US',
        'is_bot' => true,
        'bot_confidence' => 'high',
        'bot_reason' => 'known crawler/script UA',
    ]);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all', 'visitor_type' => 'human']))
        ->assertOk()
        ->assertSee('Human')
        ->assertSee(Str::limit($humanSession, 14))
        ->assertDontSee(Str::limit($botSession, 14))
        ->assertDontSee(Str::limit($unclassifiedSession, 14));

    $this->get(route('admin.ecom-activity.index', ['period' => 'all', 'visitor_type' => 'bot']))
        ->assertOk()
        ->assertSee('Bot')
        ->assertSee(Str::limit($botSession, 14))
        ->assertDontSee(Str::limit($humanSession, 14))
        ->assertDontSee(Str::limit($unclassifiedSession, 14));

    $this->get(route('admin.ecom-activity.index', ['period' => 'all', 'visitor_type' => 'unclassified']))
        ->assertOk()
        ->assertSee('Unclassified')
        ->assertSee(Str::limit($unclassifiedSession, 14))
        ->assertDontSee(Str::limit($humanSession, 14))
        ->assertDontSee(Str::limit($botSession, 14));
});
