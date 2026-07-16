<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('general.ecom_activity.show', 'web');
});

test('ecom activity show paginates action timeline', function () {
    $user = User::factory()->create();
    $user->givePermissionTo('general.ecom_activity.show');

    $this->actingAs($user);

    $sessionId = '209cb6e7-b31e-4eea-a866-983bfcb15a39';

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'last_active_at' => now(),
    ]);

    for ($i = 1; $i <= 20; $i++) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'category_view',
            'category_name' => "Category {$i}",
            'category_code' => "CAT-{$i}",
            'created_at' => now()->subMinutes(20 - $i),
            'start_time' => now()->subMinutes(20 - $i),
            'end_time' => now()->subMinutes(20 - $i),
        ]);
    }

    $this->get(route('admin.ecom-activity.show', ['session' => $sessionId]))
        ->assertOk()
        ->assertSee('Showing 1–15 of 20')
        ->assertSee('Category 20')
        ->assertSee('Category 6')
        ->assertDontSee('Category 5');

    $this->get(route('admin.ecom-activity.show', ['session' => $sessionId, 'timeline_page' => 2]))
        ->assertOk()
        ->assertSee('Showing 16–20 of 20')
        ->assertSee('Category 5')
        ->assertSee('Category 1')
        ->assertDontSee('Category 6');
});
