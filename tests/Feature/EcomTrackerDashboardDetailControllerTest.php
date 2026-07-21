<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
});

test('visitor analytics detail pages are accessible for authorized users', function () {
    Permission::findOrCreate('ecom_tracker.visitors.index', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.visitors.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.visitors'))
        ->assertOk()
        ->assertSee('Visitor analytics');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.visitors.details', ['section' => 'trend', 'window' => '7d']))
        ->assertOk()
        ->assertSee('Unique visitors vs sessions');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.visitors.details', ['section' => 'visitors', 'window' => '7d']))
        ->assertOk()
        ->assertSee('All visitors')
        ->assertSee('Sort by')
        ->assertSee('Last active · newest first');
});

test('dashboard detail pages are accessible for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'funnel', 'period' => '7d']))
        ->assertOk()
        ->assertSee('Conversion funnel');
});

test('dashboard chart detail pages expose chart payload keys', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'devices', 'period' => '7d']))
        ->assertOk()
        ->assertSee('"devices":', false);

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'engagement', 'period' => '7d']))
        ->assertOk()
        ->assertSee('"engagement":', false);
});
