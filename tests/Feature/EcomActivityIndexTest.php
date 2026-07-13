<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('general.ecom_activity.index', 'web');
    Permission::findOrCreate('general.ecom_activity.show', 'web');
});

test('ecom activity index searches by user name and email', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('general.ecom_activity.index');

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
    $user->givePermissionTo('general.ecom_activity.index');

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
