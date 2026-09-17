<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
});

test('activity index supports multiple device filters', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $desktop = Str::uuid()->toString();
    $mobile = Str::uuid()->toString();
    $tablet = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $desktop,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $mobile,
        'device_type' => 'mobile',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $tablet,
        'device_type' => 'tablet',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'device_type' => ['desktop', 'mobile'],
        ]))
        ->assertOk();

    $content = $response->getContent();

    expect($content)
        ->toContain(substr($desktop, 0, 8))
        ->toContain(substr($mobile, 0, 8))
        ->not->toContain(substr($tablet, 0, 8));
});

test('activity index supports multiple funnel stage filters', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $abandoned = Str::uuid()->toString();
    $sold = Str::uuid()->toString();
    $viewOnly = Str::uuid()->toString();

    foreach ([$abandoned, $sold, $viewOnly] as $sessionId) {
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
        'session_id' => $sold,
        'action_type' => 'payment_success',
        'payment_success' => ['amount_paid' => 99, 'qty' => 1],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $viewOnly,
        'action_type' => 'product_view',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-activity.index', [
            'period' => 'all',
            'funnel' => ['cart_abandonment', 'payment_success'],
        ]))
        ->assertOk();

    $content = $response->getContent();

    expect($content)
        ->toContain(substr($abandoned, 0, 8))
        ->toContain(substr($sold, 0, 8))
        ->not->toContain(substr($viewOnly, 0, 8));
});