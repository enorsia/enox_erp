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

test('store dashboard filter drawer preserves period when session filters are active', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', [
            'period' => '7d',
            'device_type' => 'mobile',
            'visitor_type' => 'human',
        ]))
        ->assertOk()
        ->assertSee('Store performance')
        ->assertSee('name="period" value="7d"', false)
        ->assertSee('Sessions &amp; audience', false)
        ->assertDontSee('Product catalog', false)
        ->assertDontSee('product-catalog-search', false)
        ->assertSee('Device: Mobile', false)
        ->assertSee('Real visitors', false)
        ->assertSee('presetKey: \'7d\'', false)
        ->assertSee('etd-custom-dates', false)
        ->assertDontSee('presetKey: \'custom\'', false);
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
        ->assertSee('Session quality', false)
        ->assertSee('Returning visitors', false)
        ->assertSee('Avg session duration', false)
        ->assertSee('Total time on site', false);
});

test('store dashboard period preset links keep active drawer filters', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.dashboard.index');

    $response = $this->actingAs($user)
        ->get(route('admin.ecom-tracker.dashboard', [
            'period' => '7d',
            'country' => 'GB',
        ]));

    $response
        ->assertOk()
        ->assertSee('period=30d', false)
        ->assertSee('country=GB', false)
        ->assertSee('Country: GB', false)
        ->assertDontSee('search=jacket', false);
});
