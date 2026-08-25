<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\ActivityEcomUserBotContext;
use App\Models\User;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
    Permission::findOrCreate('ecom_tracker.activity.show', 'web');
});

function activityUtcAt(string $london): string
{
    return TrackerTime::formatUtc(Carbon::parse($london, TrackerTime::timezone()));
}

test('ecom activity index searches by user name and email', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'user_name' => 'Jane Shopper',
        'user_email' => 'jane@example.com',
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    $this->get(route('admin.ecom-activity.index', ['search' => 'jane@example.com', 'period' => 'all']))
        ->assertOk()
        ->assertSee('jane@example.com');

    $this->get(route('admin.ecom-activity.index', ['search' => 'Jane Shopper', 'period' => 'all']))
        ->assertOk()
        ->assertSee('Jane Shopper');
});

test('ecom activity keyword search does not treat search as catalog-only filter', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $emailSession = Str::uuid()->toString();
    $decoySession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $emailSession,
        'user_email' => 'dumiemaunde@gmail.com',
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $decoySession,
        'user_email' => 'other@example.com',
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    $this->get(route('admin.ecom-activity.index', [
        'search' => 'dumiemaunde@gmail.com',
        'period' => 'all',
    ]))
        ->assertOk()
        ->assertSee('dumiemaunde@gmail.com')
        ->assertDontSee('other@example.com');
});

test('ecom activity keyword search finds sessions by product name sku and category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $productSession = Str::uuid()->toString();
    $categorySession = Str::uuid()->toString();
    $decoySession = Str::uuid()->toString();

    foreach ([$productSession, $categorySession, $decoySession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $productSession,
        'action_type' => 'product_view',
        'product_name' => 'Blue Hoodie',
        'product_code' => 'BH-100',
        'sku' => 'BH-100-RED-M',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $categorySession,
        'action_type' => 'category_view',
        'category_name' => 'Hoodies',
        'department_name' => 'Boys',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->get(route('admin.ecom-activity.index', ['search' => 'hoodie', 'period' => 'all']))
        ->assertOk()
        ->assertSee($productSession)
        ->assertSee($categorySession)
        ->assertDontSee($decoySession);

    $this->get(route('admin.ecom-activity.index', ['search' => 'BH-100-RED-M', 'period' => 'all']))
        ->assertOk()
        ->assertSee($productSession)
        ->assertDontSee($decoySession);

    $this->get(route('admin.ecom-activity.index', ['search' => 'Boys', 'period' => 'all']))
        ->assertOk()
        ->assertSee($categorySession)
        ->assertDontSee($decoySession);
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
        ->assertSee($orderedSession)
        ->assertDontSee($guestSession);

    $this->get(route('admin.ecom-activity.index', ['has_order' => '0']))
        ->assertOk()
        ->assertSee($guestSession)
        ->assertDontSee($orderedSession);
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
        ->assertSee($orderedSession)
        ->assertSee($guestSession);
});

test('ecom activity index defaults to today preset', function () {
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
        ->assertSee(\App\Support\TrackerTime::todayPresetLabel())
        ->assertSee($recentSession)
        ->assertDontSee($oldSession);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('All sessions')
        ->assertSee($recentSession)
        ->assertSee($oldSession);
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
        ->assertSee('Real visitor')
        ->assertSee('GB')
        ->assertSee($humanSession)
        ->assertDontSee($botSession)
        ->assertDontSee($unclassifiedSession);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all', 'visitor_type' => 'bot']))
        ->assertOk()
        ->assertSee('Automated traffic')
        ->assertSee($botSession)
        ->assertDontSee($humanSession)
        ->assertDontSee($unclassifiedSession);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all', 'visitor_type' => 'unclassified']))
        ->assertOk()
        ->assertSee('Not classified')
        ->assertSee($unclassifiedSession)
        ->assertDontSee($humanSession)
        ->assertDontSee($botSession);
});

test('ecom activity index orders by server updated_at when client last active is stale', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 15:00:00', TrackerTime::timezone()));
    config(['tracker.visitor_timezone' => 'Europe/London']);

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $importedSession = Str::uuid()->toString();
    $freshSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $importedSession,
        'device_type' => 'desktop',
        'last_active_at' => activityUtcAt('2026-07-16 14:30:00'),
        'created_at' => activityUtcAt('2026-07-16 09:00:00'),
        'updated_at' => activityUtcAt('2026-07-16 14:30:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $freshSession,
        'device_type' => 'desktop',
        'last_active_at' => activityUtcAt('2026-07-16 10:00:00'),
        'created_at' => activityUtcAt('2026-07-16 14:58:00'),
        'updated_at' => activityUtcAt('2026-07-16 14:59:45'),
    ]);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSeeInOrder([
            $freshSession,
            $importedSession,
        ]);
});

test('ecom activity index orders sessions by latest activity newest first', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 15:00:00', TrackerTime::timezone()));

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $newestSession = Str::uuid()->toString();
    $middleSession = Str::uuid()->toString();
    $staleSession = Str::uuid()->toString();
    $missingLastActiveSession = Str::uuid()->toString();
    $recentIngestSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $staleSession,
        'device_type' => 'desktop',
        'last_active_at' => activityUtcAt('2026-07-16 10:00:00'),
        'created_at' => activityUtcAt('2026-07-16 09:00:00'),
        'updated_at' => activityUtcAt('2026-07-16 10:00:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $newestSession,
        'device_type' => 'desktop',
        'last_active_at' => activityUtcAt('2026-07-16 14:58:00'),
        'created_at' => activityUtcAt('2026-07-16 13:00:00'),
        'updated_at' => activityUtcAt('2026-07-16 14:58:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $middleSession,
        'device_type' => 'desktop',
        'last_active_at' => activityUtcAt('2026-07-16 14:00:00'),
        'created_at' => activityUtcAt('2026-07-16 12:00:00'),
        'updated_at' => activityUtcAt('2026-07-16 14:00:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $missingLastActiveSession,
        'device_type' => 'desktop',
        'last_active_at' => null,
        'created_at' => activityUtcAt('2026-07-16 13:30:00'),
        'updated_at' => activityUtcAt('2026-07-16 13:30:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $recentIngestSession,
        'device_type' => 'desktop',
        'last_active_at' => activityUtcAt('2026-07-16 10:30:00'),
        'created_at' => activityUtcAt('2026-07-16 10:00:00'),
        'updated_at' => activityUtcAt('2026-07-16 14:59:30'),
    ]);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSeeInOrder([
            $recentIngestSession,
            $newestSession,
            $middleSession,
            $missingLastActiveSession,
            $staleSession,
        ]);
});

test('ecom activity index shows visitor quality summary strip', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    ActivityEcomUserBotContext::query()->create([
        'session_id' => $sessionId,
        'is_bot' => false,
        'bot_confidence' => 'low',
        'bot_reason' => 'no bot signals detected',
    ]);

    $this->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('real visitors')
        ->assertSee('automated')
        ->assertSee('not classified');
});

test('ecom activity product code search only shows sessions with matching product activity', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user);

    $viewedSession = Str::uuid()->toString();
    $crossSellSession = Str::uuid()->toString();
    $otherOrderSession = Str::uuid()->toString();
    $decoySession = Str::uuid()->toString();

    foreach ([$viewedSession, $crossSellSession, $otherOrderSession, $decoySession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $viewedSession,
        'action_type' => 'product_view',
        'product_name' => 'Target Tee',
        'product_code' => 'MS31262181',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $crossSellSession,
        'action_type' => 'product_view',
        'product_name' => 'Target Tee',
        'product_code' => 'MS31262181',
        'created_at' => now()->subMinutes(2),
        'start_time' => now()->subMinutes(2),
        'end_time' => now()->subMinutes(2),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $crossSellSession,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 45.0,
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Other Tee',
                    'product_code' => 'MS99999999',
                    'qty' => 1,
                    'price' => 45.0,
                ]],
            ],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $otherOrderSession,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 30.0,
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Other Tee',
                    'product_code' => 'MS99999999',
                    'qty' => 1,
                    'price' => 30.0,
                ]],
            ],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $response = $this->get(route('admin.ecom-activity.index', [
        'search' => 'MS31262181',
        'period' => 'all',
    ]));

    $response->assertOk()
        ->assertSee($viewedSession)
        ->assertSee($crossSellSession)
        ->assertDontSee($otherOrderSession)
        ->assertDontSee($decoySession);

    $html = $response->getContent();

    expect($html)->not->toContain('MS99999999');
});
