<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('general.ecom_tracker_dashboard.index', 'web');
});

test('dashboard detail pages are accessible for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('general.ecom_tracker_dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'funnel', 'period' => '7d']))
        ->assertOk()
        ->assertSee('Conversion funnel');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.visitors.details', ['section' => 'trend', 'window' => '7d']))
        ->assertOk()
        ->assertSee('Visitors & sessions over time');
});

test('dashboard chart detail pages expose chart payload keys', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('general.ecom_tracker_dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'devices', 'period' => '7d']))
        ->assertOk()
        ->assertSee('"devices":', false);

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'engagement', 'period' => '7d']))
        ->assertOk()
        ->assertSee('"engagement":', false);
});
