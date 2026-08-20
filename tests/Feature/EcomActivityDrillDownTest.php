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
        ->assertSee('Commerce')
        ->assertSee('Cart ·')
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

test('activity index categories focus scopes row metrics to filtered category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $topsSession = Str::uuid()->toString();
    $shortsOnlySession = Str::uuid()->toString();

    foreach ([$topsSession, $shortsOnlySession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $topsSession,
        'action_type' => 'category_view',
        'category_name' => 'Tops and T-Shirts',
        'created_at' => now()->subMinutes(10),
        'start_time' => now()->subMinutes(10),
        'end_time' => now()->subMinutes(10),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $topsSession,
        'action_type' => 'category_view',
        'category_name' => 'Shorts',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $shortsOnlySession,
        'action_type' => 'category_view',
        'category_name' => 'Shorts',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
            'category' => 'Tops and T-Shirts',
        ]))
        ->assertOk()
        ->assertSee('Tops and T-Shirts')
        ->assertSee($topsSession)
        ->assertDontSee($shortsOnlySession);

    expect(substr_count($response->getContent(), 'Shorts'))->toBe(0);
});

test('activity index categories focus scopes sessions to department and category', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $womenSession = Str::uuid()->toString();
    $boysSession = Str::uuid()->toString();
    $categoryName = 'Tops and T-Shirts';

    foreach ([$womenSession, $boysSession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $womenSession,
        'action_type' => 'category_view',
        'department_name' => 'Women',
        'category_name' => $categoryName,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $boysSession,
        'action_type' => 'category_view',
        'department_name' => 'Boys',
        'category_name' => $categoryName,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
            'department' => 'Women',
            'category' => $categoryName,
        ]))
        ->assertOk()
        ->assertSee('Women -> '.$categoryName)
        ->assertSee($womenSession)
        ->assertDontSee($boysSession);
});

test('activity index categories focus includes product-view-only sessions in category drill-down', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();
    $categoryName = 'Sweatshirts and Hoodies';

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'department_name' => 'Boys',
        'category_name' => $categoryName,
        'product_name' => 'Boys Hoodie',
        'product_code' => 'BH-001',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
            'department' => 'Boys',
            'category' => $categoryName,
        ]))
        ->assertOk()
        ->assertSee('Boys -> '.$categoryName)
        ->assertSee('Matching sessions')
        ->assertSee('1')
        ->assertSee($sessionId);
});

test('activity index categories focus shows category performance totals in drill-down context', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();
    $categoryName = 'Tops and T-Shirts';

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
        'category_name' => $categoryName,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'category_name' => $categoryName,
        'product_name' => 'Summer Tee',
        'product_code' => 'TEE-001',
        'add_to_cart' => [
            'product_code' => 'TEE-001',
            'product_name' => 'Summer Tee',
            'qty' => 1,
            'category_name' => $categoryName,
        ],
        'created_at' => now()->addMinute(),
        'start_time' => now()->addMinute(),
        'end_time' => now()->addMinute(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
            'category' => $categoryName,
        ]))
        ->assertOk()
        ->assertSee('Category performance')
        ->assertSee($categoryName)
        ->assertSee('Views')
        ->assertSee('Adds')
        ->assertSee('Proceed')
        ->assertSee('Cart abandoned')
        ->assertSee('Sold')
        ->assertSee('Sold qty')
        ->assertSee('Sale');
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

test('activity index keeps dashboard drill-down filters when sidebar filters are applied', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $mobileSession = Str::uuid()->toString();
    $desktopSession = Str::uuid()->toString();
    $categoryName = 'Tops and T-Shirts';

    ActivityEcomUser::query()->create([
        'session_id' => $mobileSession,
        'device_type' => 'mobile',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $desktopSession,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    foreach ([$mobileSession, $desktopSession] as $sessionId) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'category_view',
            'department_name' => 'Women',
            'category_name' => $categoryName,
            'created_at' => now(),
            'start_time' => now(),
            'end_time' => now(),
        ]);
    }

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
            'department' => 'Women',
            'category' => $categoryName,
            'device_type' => 'mobile',
        ]))
        ->assertOk()
        ->assertSee('Category performance')
        ->assertSee('Women -> '.$categoryName)
        ->assertSee('Device: Mobile')
        ->assertSee($mobileSession)
        ->assertDontSee($desktopSession);
});

test('activity index category drill-down with order filter only includes category purchases', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $categoryBuyer = Str::uuid()->toString();
    $otherCategoryBuyer = Str::uuid()->toString();
    $categoryName = 'Tops and T-Shirts';

    foreach ([$categoryBuyer, $otherCategoryBuyer] as $sessionId) {
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
            'department_name' => 'Women',
            'category_name' => $categoryName,
            'created_at' => now(),
            'start_time' => now(),
            'end_time' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $categoryBuyer,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 25,
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Summer Tee',
                    'product_code' => 'TEE-001',
                    'category_name' => $categoryName,
                    'department_name' => 'Women',
                    'qty' => 1,
                    'price' => 25,
                ]],
            ],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $otherCategoryBuyer,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 38,
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Trouser',
                    'product_code' => 'TR-1',
                    'category_name' => 'Trousers',
                    'department_name' => 'Women',
                    'qty' => 1,
                    'price' => 38,
                ]],
            ],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'categories',
            'department' => 'Women',
            'category' => $categoryName,
            'has_order' => '1',
        ]))
        ->assertOk()
        ->assertSee($categoryBuyer)
        ->assertDontSee($otherCategoryBuyer);
});

test('activity index products focus applies activity filter to session list', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $viewerSession = Str::uuid()->toString();
    $buyerSession = Str::uuid()->toString();
    $productCode = 'PARITY-TEE';

    foreach ([$viewerSession, $buyerSession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $viewerSession,
        'action_type' => 'product_view',
        'product_name' => 'Parity Tee',
        'product_code' => $productCode,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $buyerSession,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 20,
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Parity Tee',
                    'product_code' => $productCode,
                    'qty' => 1,
                    'price' => 20,
                ]],
            ],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'products',
            'product_code' => $productCode,
            'activity' => 'views',
        ]))
        ->assertOk()
        ->assertSee($viewerSession)
        ->assertDontSee($buyerSession);
});

test('activity index products focus uses catalog search instead of session search', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $productSession = Str::uuid()->toString();
    $decoySession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $productSession,
        'device_type' => 'desktop',
        'visitor_id' => 'visitor-alpha',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $decoySession,
        'device_type' => 'desktop',
        'visitor_id' => 'hoodie-shopper',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $productSession,
        'action_type' => 'product_view',
        'product_name' => 'Blue Hoodie',
        'product_code' => 'BH-100',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'products',
            'search' => 'hoodie',
        ]))
        ->assertOk()
        ->assertSee($productSession)
        ->assertDontSee($decoySession);
});

test('activity index shows catalog filter sections for products focus', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'products',
        ]))
        ->assertOk()
        ->assertSee('Session filters')
        ->assertSee('Product / category filters')
        ->assertSee('Product name, code or SKU');
});

test('activity index products focus shows product performance totals in drill-down context', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();
    $productCode = 'MS4421735';
    $productName = 'Cream Design Pique Polo With Rib Detailing';

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'product_name' => $productName,
        'product_code' => $productCode,
        'add_to_cart' => [
            'product_code' => $productCode,
            'product_name' => $productName,
            'qty' => 1,
        ],
        'created_at' => now()->addMinute(),
        'start_time' => now()->addMinute(),
        'end_time' => now()->addMinute(),
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
        ->assertSee($productCode)
        ->assertSee('Views')
        ->assertSee('Adds')
        ->assertSee('Proceed')
        ->assertSee('Sold')
        ->assertSee('Sale');
});
