<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\User;
use App\Support\EcomActivityFocus;
use App\Support\EcomTrackerViewData;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

function drillDownUtcAt(string $london): string
{
    return TrackerTime::formatUtc(Carbon::parse($london, TrackerTime::timezone()));
}

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
    Permission::findOrCreate('ecom_tracker.activity.show', 'web');
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
});

test('activity drill down link carries dashboard period and focus', function () {
    $url = EcomTrackerViewData::activityDrillDownLink('cart_abandonment', [
        'period' => '7d',
        'device_type' => 'mobile',
    ], [], 'https://example.test/dashboard');

    expect($url)->toContain('focus=cart_abandonment')
        ->and($url)->toContain('period=7d')
        ->and($url)->toContain('device_type=mobile')
        ->and($url)->toContain('back=');

    parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

    expect(EcomTrackerViewData::resolveBackUrl($query['back'] ?? null))
        ->toBe('https://example.test/dashboard');
});

test('activity index shows dashboard back button when opened from store performance', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $dashboardUrl = route('admin.ecom-tracker.dashboard', ['period' => '24h']);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => '24h',
            'focus' => 'cart_abandonment',
            'back' => $dashboardUrl,
        ]))
        ->assertOk()
        ->assertSee('Dashboard', false)
        ->assertSee($dashboardUrl, false);
});

test('dashboard detail link points to user activity drill down', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', ['period' => '7d']));

    $response->assertOk();
    expect($response->getContent())->toContain('admin/ecom-activity')
        ->and($response->getContent())->toContain('focus=cart_abandonment')
        ->and($response->getContent())->not->toContain('ecom-tracker/dashboard/details/cart-abandonment');
});

test('activity index with cart abandonment focus shows only abandoned sessions', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $abandoned = Str::uuid()->toString();
    $converted = Str::uuid()->toString();

    foreach ([$abandoned, $converted] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $abandoned,
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['cart_total' => 40, 'qty' => 1],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $converted,
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['cart_total' => 80, 'qty' => 1],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $converted,
        'action_type' => 'begin_checkout',
        'begin_checkout' => ['cart_total' => 80],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'cart_abandonment',
        ]))
        ->assertOk()
        ->assertSee('Cart abandoned')
        ->assertSee('Cart value')
        ->assertSee(substr($abandoned, 0, 8))
        ->assertDontSee(substr($converted, 0, 8));
});

test('activity index traffic focus renders source and medium columns', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'utm_source' => 'google',
        'utm_medium' => 'cpc',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'traffic',
        ]))
        ->assertOk()
        ->assertSee('Traffic sources')
        ->assertSee('Source')
        ->assertSee('Medium');
});

test('activity focus chip can be cleared', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'devices',
        ]))
        ->assertOk()
        ->assertSee('Focus: Device &amp; browser', false);

    expect($response->getContent())->toContain('focus=');
    expect(EcomActivityFocus::label('devices'))->toBe('Device & browser');
});

test('activity show link preserves list filters in back url', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');
    $user->givePermissionTo('ecom_tracker.activity.show');

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    $indexUrl = route('admin.ecom-activity.index', [
        'period' => 'all',
        'focus' => 'devices',
    ]);

    $this->actingAs($user)
        ->get($indexUrl)
        ->assertOk()
        ->assertSee('View session');

    $showUrl = EcomTrackerViewData::activityShowUrlFromRequest(request(), $sessionId);
    parse_str((string) parse_url($showUrl, PHP_URL_QUERY), $query);

    expect($showUrl)->toContain($sessionId)
        ->and(urldecode((string) ($query['back'] ?? '')))->toBe(request()->fullUrl());
});

test('activity index products focus filters sessions by product code', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $matchingSession = Str::uuid()->toString();
    $otherSession = Str::uuid()->toString();

    foreach ([$matchingSession, $otherSession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $matchingSession,
        'action_type' => 'product_view',
        'product_name' => 'Summer Dress',
        'product_code' => 'DRS-001',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $otherSession,
        'action_type' => 'product_view',
        'product_name' => 'Winter Coat',
        'product_code' => 'COT-999',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'products',
            'product_code' => 'DRS-001',
            'product_name' => 'Summer Dress',
        ]))
        ->assertOk()
        ->assertSee('Product performance')
        ->assertSee('DRS-001')
        ->assertSee('Summer Dress')
        ->assertSee('Matching sessions')
        ->assertSee('1 session')
        ->assertDontSee('Section:')
        ->assertSee(substr($matchingSession, 0, 8))
        ->assertDontSee(substr($otherSession, 0, 8));
});

test('activity index products focus matches dashboard views when code is missing on early events', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionA = Str::uuid()->toString();
    $sessionB = Str::uuid()->toString();

    foreach ([$sessionA, $sessionB] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    $productName = 'Red Ruched Side Seam T-Shirt';
    $productCode = 'RRSST-001';

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionA,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => '',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionA,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionB,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionB,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'products',
            'product_code' => $productCode,
            'product_name' => $productName,
        ]))
        ->assertOk()
        ->assertSee('Product performance')
        ->assertSee($sessionA)
        ->assertSee($sessionB)
        ->assertSee('>2<', false)
        ->assertSee('>1<', false);
});

test('activity index product drill down counts views by action time not session last active', function () {
    Carbon::setTestNow(Carbon::parse('2026-08-20 12:00:00', TrackerTime::timezone()));

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $staleSession = Str::uuid()->toString();
    $freshSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $staleSession,
        'device_type' => 'desktop',
        'created_at' => now()->subDays(5),
        'last_active_at' => now()->subDays(5),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $freshSession,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    $productName = 'Red Ruched Side Seam T-Shirt';
    $productCode = 'WS312259';

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $staleSession,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now()->subHours(2),
        'start_time' => now()->subHours(2),
        'end_time' => now()->subHours(2),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $freshSession,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now()->subHour(),
        'start_time' => now()->subHour(),
        'end_time' => now()->subHour(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => '24h',
            'focus' => 'products',
            'product_code' => $productCode,
            'product_name' => $productName,
        ]))
        ->assertOk()
        ->assertSee('Product performance')
        ->assertSee($staleSession)
        ->assertSee($freshSession);

    Carbon::setTestNow();
});

test('activity index categories focus renders category columns from category_name', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
        ]))
        ->assertOk()
        ->assertSee('Category performance')
        ->assertSee('Top category')
        ->assertSee('Women');
});

test('activity index date scope matches dashboard session range for 7d', function () {
    Carbon::setTestNow(Carbon::parse('2026-07-16 15:00:00', TrackerTime::timezone()));

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $startedBeforeRange = Str::uuid()->toString();
    $startedInside = Str::uuid()->toString();
    $startedBeforeRangeActiveInside = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $startedBeforeRange,
        'device_type' => 'desktop',
        'created_at' => drillDownUtcAt('2026-07-08 09:00:00'),
        'last_active_at' => drillDownUtcAt('2026-07-08 10:00:00'),
        'updated_at' => drillDownUtcAt('2026-07-08 10:00:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $startedInside,
        'device_type' => 'desktop',
        'created_at' => drillDownUtcAt('2026-07-14 10:00:00'),
        'last_active_at' => drillDownUtcAt('2026-07-14 11:00:00'),
        'updated_at' => drillDownUtcAt('2026-07-14 11:00:00'),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $startedBeforeRangeActiveInside,
        'device_type' => 'desktop',
        'created_at' => drillDownUtcAt('2026-07-08 09:00:00'),
        'last_active_at' => drillDownUtcAt('2026-07-15 12:00:00'),
        'updated_at' => drillDownUtcAt('2026-07-15 12:00:00'),
    ]);

    $service = app(\App\Services\EcomTrackerDashboardService::class);
    $range = $service->resolveDateRange(['period' => '7d']);

    $expectedIds = ActivityEcomUser::query()
        ->tap(fn ($query) => TrackerTime::applyEcomActivitySessionScope($query, $range['from'], $range['to'], '7d'))
        ->pluck('session_id')
        ->all();

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', ['period' => '7d']))
        ->assertOk()
        ->assertSee(substr($startedInside, 0, 8))
        ->assertDontSee(substr($startedBeforeRange, 0, 8))
        ->assertDontSee(substr($startedBeforeRangeActiveInside, 0, 8));

    expect($expectedIds)->toContain($startedInside)
        ->not->toContain($startedBeforeRange, $startedBeforeRangeActiveInside);
});

test('conversion focus drill down shows revenue from payment success events', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-1001',
            'amount_paid' => 15.49,
            'qty' => 1,
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'conversion',
            'has_order' => '1',
        ]))
        ->assertOk()
        ->assertSee('Sale & conversion')
        ->assertSee('£15.49')
        ->assertSee('1 session')
        ->assertSee(substr($sessionId, 0, 8));
});
