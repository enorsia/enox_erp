<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
});

test('ecom tracker dashboard is reachable with permission', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard'))
        ->assertOk();
});
