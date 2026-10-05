<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\TrackerDashboardSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\TrackingSyncTestSchema;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    TrackingSyncTestSchema::up();
});

test('dashboard sync copies session and actions once without duplicates', function () {
    $visitorId = (string) Str::uuid();
    $sessionId = (string) Str::uuid();
    $idleAt = now()->subMinutes(45)->format('Y-m-d H:i:s');
    $actionAt = now()->subMinutes(40)->format('Y-m-d H:i:s');

    DB::table('activity_ecom_user')->insert([
        'session_id' => $sessionId,
        'visitor_id' => $visitorId,
        'device_type' => 'mobile',
        'browser' => 'Chrome',
        'ip' => '127.0.0.1',
        'is_logged_in' => false,
        'session_duration_seconds' => 120,
        'last_active_at' => $idleAt,
        'has_payment_success' => false,
        'is_sync' => ActivityEcomUser::SYNC_PENDING,
        'sync_try' => 0,
        'created_at' => $idleAt,
        'updated_at' => $idleAt,
    ]);

    $sessionPk = (int) DB::table('activity_ecom_user')->where('session_id', $sessionId)->value('id');

    foreach ([1, 2] as $i) {
        DB::table('activity_ecom_user_actions')->insert([
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'action_type' => 'product_view',
            'department_name' => 'Men',
            'category_name' => 'Shirts',
            'category_code' => 'SHIRT',
            'product_code' => 'P'.$i,
            'product_name' => 'Product '.$i,
            'is_sync' => ActivityEcomUserAction::SYNC_PENDING,
            'created_at' => $actionAt,
        ]);
    }

    $service = app(TrackerDashboardSyncService::class);

    expect($service->processBatch(null, 5))->toBeNull();

    $session = DB::table('activity_ecom_user')->where('id', $sessionPk)->first();
    expect((int) $session->is_sync)->toBe(ActivityEcomUser::SYNC_DONE);

    expect(DB::table('tracking_main_visitor')->count())->toBe(1)
        ->and(DB::table('tracking_session')->count())->toBe(1)
        ->and(DB::table('tracking_session_p_cat')->count())->toBe(2);

    expect(DB::table('activity_ecom_user_actions')->where('is_sync', ActivityEcomUserAction::SYNC_DONE)->count())->toBe(2);

    $service->processBatch(null, 5);

    expect(DB::table('tracking_session_p_cat')->count())->toBe(2)
        ->and(DB::table('tracking_product')->count())->toBe(2);
});
