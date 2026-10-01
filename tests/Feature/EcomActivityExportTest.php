<?php

use App\Models\User;
use App\Models\UserExport;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Permission;

beforeEach(function () {
    Permission::findOrCreate('ecom_tracker.activity.index', 'web');
});

test('ecom activity export normalizes bracket notation funnel filters', function () {

    $response->assertAccepted()
        ->assertJsonStructure(['export_id', 'status', 'total_rows']);

    $export = UserExport::query()->first();

    expect($export)->not->toBeNull()
        ->and($export->type)->toBe(UserExport::TYPE_ECOM_ACTIVITY_REPORT)
        ->and($export->format)->toBe('xlsx')
        ->and($export->status)->toBe(UserExport::STATUS_QUEUED)
        ->and($export->filters['query']['funnel'] ?? null)->toBe(['payment_success'])
        ->and($export->filters['query']['period'] ?? null)->toBe('7d')
        ->and($export->notify_browser)->toBeFalse();
});

test('ecom activity export can be queued with current filters', function () {
    Queue::fake();

    $user = User::factory()->create();
    $user->givePermissionTo('ecom_tracker.activity.index');

    $response = $this->actingAs($user)->postJson(route('admin.exports.ecom-activity.start'), [
        'format' => 'xlsx',
        'query' => [
            'period' => '7d',
            'focus' => 'audience',
        ],
    ]);

    $response->assertAccepted()
        ->assertJsonStructure(['export_id', 'status', 'total_rows']);

    $export = UserExport::query()->first();

    expect($export)->not->toBeNull()
        ->and($export->type)->toBe(UserExport::TYPE_ECOM_ACTIVITY_REPORT)
        ->and($export->format)->toBe('xlsx')
        ->and($export->status)->toBe(UserExport::STATUS_QUEUED)
        ->and($export->filters['query']['period'] ?? null)->toBe('7d')
        ->and($export->notify_browser)->toBeFalse();
});

test('ecom activity export requires permission', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->postJson(route('admin.exports.ecom-activity.start'), [
        'format' => 'xlsx',
        'query' => ['period' => '7d'],
    ])->assertForbidden();
});
