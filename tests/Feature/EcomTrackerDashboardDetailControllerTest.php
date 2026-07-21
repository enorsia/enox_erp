<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
});

test('visitor analytics export downloads excel for authorized users', function () {
    Permission::findOrCreate('ecom_tracker.visitors.index', 'web');

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.visitors.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.visitors.export', ['window' => '7d']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
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

test('dashboard export downloads excel for authorized users', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.export', ['period' => '7d']))
        ->assertOk()
        ->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
});

test('product catalog event scenario filters products on detail page', function () {
    $service = app(\App\Services\EcomTrackerDashboardService::class);

    expect($service->productMatchesEventScenario(['views' => 5, 'adds' => 2, 'purchases' => 0], 'viewed_not_purchased'))->toBeTrue();
    expect($service->productMatchesEventScenario(['views' => 5, 'adds' => 2, 'purchases' => 1], 'viewed_not_purchased'))->toBeFalse();
    expect($service->productMatchesEventScenario(['views' => 0, 'adds' => 3, 'purchases' => 0], 'added_not_purchased'))->toBeTrue();
    expect($service->productMatchesEventScenario(['views' => 2, 'adds' => 1, 'purchases' => 1], 'full_funnel'))->toBeTrue();
});

test('dashboard product catalog detail supports sort and variant drill-down', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'products', 'period' => '7d', 'sort_by' => 'top_views']))
        ->assertOk()
        ->assertSee('Product & variant performance')
        ->assertSee('Funnel')
        ->assertSee('Purchases');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'products', 'period' => '7d', 'sort_by' => 'insight_engagement']))
        ->assertOk()
        ->assertSee('Views + cart adds');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'products', 'period' => '7d', 'event_scenario' => 'added_not_purchased']))
        ->assertOk()
        ->assertSee('Added to cart · not purchased');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard.details', ['section' => 'colors', 'period' => '7d']))
        ->assertRedirect(route('admin.ecom-tracker.dashboard.details', ['section' => 'products', 'period' => '7d']));
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
