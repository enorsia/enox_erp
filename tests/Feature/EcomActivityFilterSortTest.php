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
