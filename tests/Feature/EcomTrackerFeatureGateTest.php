<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
});

test('ecom tracker admin routes return 404 when feature is disabled', function () {
    config(['tracker.enabled' => false]);

    $user = User::factory()->create();
    $user->givePermissionTo([
        'ecom_tracker.dashboard.index',
        'ecom_tracker.activity.index',
    ]);

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard'))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('admin.ecom-activity.index'))
        ->assertNotFound();
});

test('ecom tracker admin routes are reachable when feature is enabled', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard'))
        ->assertOk();
});
