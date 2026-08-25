<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\User;
use App\Support\EcomActivityFocus;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
});

test('activity index funnel drawer filter shows only cart abandoned sessions', function () {
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
            'funnel' => 'cart_abandonment',
        ]))
        ->assertOk()
        ->assertSee('Funnel: Cart abandoned', false)
        ->assertSee(substr($abandoned, 0, 8))
        ->assertDontSee(substr($converted, 0, 8));
});

test('activity index does not double apply funnel when focus already matches', function () {
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
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['cart_total' => 40, 'qty' => 1],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    expect(EcomActivityFocus::shouldApplyDrawerFunnelFilter(
        request()->duplicate([
            'focus' => 'cart_abandonment',
            'funnel' => 'cart_abandonment',
        ]),
    ))->toBeFalse();
});

test('activity index sorts by funnel stage with payment success first', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $cartOnly = Str::uuid()->toString();
    $sold = Str::uuid()->toString();

    foreach ([$cartOnly, $sold] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $cartOnly,
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['cart_total' => 10, 'qty' => 1],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sold,
        'action_type' => 'payment_success',
        'payment_success' => ['amount_paid' => 99.5, 'qty' => 1],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'sort_by' => 'funnel_stage',
            'sort_dir' => 'desc',
        ]))
        ->assertOk();

    $content = $response->getContent();
    $soldPos = strpos($content, substr($sold, 0, 8));
    $cartPos = strpos($content, substr($cartOnly, 0, 8));

    expect($soldPos)->not->toBeFalse()
        ->and($cartPos)->not->toBeFalse()
        ->and($soldPos)->toBeLessThan($cartPos);
});

test('activity table fragment returns panel and pagination markup', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'fragment' => 'table',
        ]))
        ->assertOk()
        ->assertSee('etd-panel', false)
        ->assertSee('etd-activity-pagination', false)
        ->assertSee('Sort by')
        ->assertSee(substr($sessionId, 0, 8));
});

test('activity index shows sort toolbar and sortable headers', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('Sort by')
        ->assertSee('data-etd-activity-sort', false)
        ->assertSee('Funnel stage (sold first)');
});

test('activity filter drawer includes visitor trust and country fields', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', ['period' => 'all']))
        ->assertOk()
        ->assertSee('Visitor trust')
        ->assertSee('Funnel stage')
        ->assertSee('etd-filter-drawer--wide', false);
});

test('activity index product drill-down sorts by funnel stage sold proceed checkout cart view', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $viewOnly = Str::uuid()->toString();
    $cartOnly = Str::uuid()->toString();
    $checkoutOnly = Str::uuid()->toString();
    $proceedOnly = Str::uuid()->toString();
    $sold = Str::uuid()->toString();

    foreach ([$viewOnly, $cartOnly, $checkoutOnly, $proceedOnly, $sold] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    $productCode = 'WS333217';
    $productName = 'White Cotton Stretch Jersey Shorts';

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $viewOnly,
        'action_type' => 'product_view',
        'product_code' => $productCode,
        'product_name' => $productName,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $cartOnly,
        'action_type' => 'add_to_cart',
        'product_code' => $productCode,
        'product_name' => $productName,
        'add_to_cart' => ['items' => [['product_code' => $productCode, 'product_name' => $productName, 'qty' => 1]], 'cart_total' => 25],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $checkoutOnly,
        'action_type' => 'begin_checkout',
        'product_code' => $productCode,
        'product_name' => $productName,
        'begin_checkout' => ['items' => [['product_code' => $productCode, 'product_name' => $productName, 'qty' => 1]], 'cart_total' => 25],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $proceedOnly,
        'action_type' => 'proceed_checkout',
        'product_code' => $productCode,
        'product_name' => $productName,
        'proceed_to_checkout' => ['items' => [['product_code' => $productCode, 'product_name' => $productName, 'qty' => 1]], 'cart_total' => 25],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sold,
        'action_type' => 'payment_success',
        'product_code' => $productCode,
        'product_name' => $productName,
        'payment_success' => [
            'amount_paid' => 25,
            'qty' => 1,
            'items' => [['product_code' => $productCode, 'product_name' => $productName, 'qty' => 1, 'line_total' => 25]],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'focus' => 'products',
            'product_code' => $productCode,
            'product_name' => $productName,
        ]))
        ->assertOk();

    $content = $response->getContent();
    $positions = collect([$sold, $proceedOnly, $checkoutOnly, $cartOnly, $viewOnly])
        ->mapWithKeys(fn (string $sessionId) => [$sessionId => strpos($content, substr($sessionId, 0, 8))])
        ->all();

    expect($positions[$sold])->not->toBeFalse()
        ->and($positions[$proceedOnly])->not->toBeFalse()
        ->and($positions[$checkoutOnly])->not->toBeFalse()
        ->and($positions[$cartOnly])->not->toBeFalse()
        ->and($positions[$viewOnly])->not->toBeFalse()
        ->and($positions[$sold])->toBeLessThan($positions[$proceedOnly])
        ->and($positions[$proceedOnly])->toBeLessThan($positions[$checkoutOnly])
        ->and($positions[$checkoutOnly])->toBeLessThan($positions[$cartOnly])
        ->and($positions[$cartOnly])->toBeLessThan($positions[$viewOnly]);
});
