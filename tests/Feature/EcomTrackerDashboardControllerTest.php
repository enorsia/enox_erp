<?php

use App\Models\User;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.dashboard.index', 'web');
});

test('store dashboard requires permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard'))
        ->assertForbidden();
});

test('store dashboard strips legacy session filters from query string', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', [
            'period' => '7d',
            'device_type' => 'mobile',
            'visitor_type' => 'human',
        ]))
        ->assertRedirect(route('admin.ecom-tracker.dashboard', ['period' => '7d']));
});

test('store dashboard shows custom date picker only when period is custom', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', [
            'period' => 'custom',
            'date_from' => '2026-07-01',
            'date_to' => '2026-07-15',
        ]))
        ->assertOk()
        ->assertSee('presetKey: \'custom\'', false)
        ->assertSee('value="2026-07-01"', false)
        ->assertSee('value="2026-07-15"', false)
        ->assertDontSee('Session quality', false)
        ->assertSee('Session duration distribution', false)
        ->assertSee('etd-duration-buckets', false)
        ->assertDontSee('etdDurationDistChart', false)
        ->assertDontSee('Total time on site', false);
});

test('store dashboard kpi cards link to user activity drill down', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', ['period' => '7d']))
        ->assertOk();

    expect($response->getContent())
        ->toContain('focus=audience')
        ->toContain('focus=conversion')
        ->toContain('focus=cart_abandonment')
        ->toContain('focus=begin_checkout_abandonment')
        ->toContain('focus=proceed_checkout_abandonment')
        ->toContain('focus=payment_success')
        ->toContain('focus=categories')
        ->toContain('etd-kpi-drilldown-link');
});

test('store dashboard does not show floating filter drawer', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', ['period' => '7d']))
        ->assertOk()
        ->assertSee('Store performance')
        ->assertDontSee('etd-filter-drawer', false)
        ->assertDontSee('Sessions &amp; audience', false)
        ->assertSee('presetKey: \'7d\'', false);
});
