<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\TrackerDataCleanupService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('tracker cleanup removes duplicate payment_success rows and payment-only sessions', function () {
    $service = app(TrackerDataCleanupService::class);
    $before = Carbon::parse('2026-08-04', TrackerTime::timezone())->endOfDay();

    $keeperSession = Str::uuid()->toString();
    $ghostSession = Str::uuid()->toString();

    foreach ([$keeperSession, $ghostSession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => '2026-08-01 10:00:00',
            'last_active_at' => '2026-08-01 10:00:00',
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $keeperSession,
        'action_type' => 'product_view',
        'product_name' => 'Dress',
        'created_at' => '2026-08-01 10:00:00',
    ]);

    foreach ([
        ['session_id' => $keeperSession, 'created_at' => '2026-08-01 10:05:00'],
        ['session_id' => $ghostSession, 'created_at' => '2026-08-01 10:06:00'],
    ] as $index => $row) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $row['session_id'],
            'action_type' => 'payment_success',
            'payment_success' => [
                'order_id' => 'ORD-CLEANUP-1',
                'amount_paid' => 25.00,
                'checkout_info' => [
                    'customer' => [
                        'first_name' => 'Case',
                        'last_name' => 'Study',
                        'email' => 'case.study@example.com',
                        'phone' => '07111111111',
                    ],
                ],
            ],
            'created_at' => $row['created_at'],
        ]);
    }

    $orphans = $service->removePaymentOnlySessions($before);
    $payments = $service->dedupePaymentSuccessActions($before);

    expect($orphans['deleted_sessions'])->toBe(1)
        ->and($payments['deleted_actions'])->toBe(0)
        ->and(ActivityEcomUserAction::query()->where('action_type', 'payment_success')->count())->toBe(1)
        ->and(ActivityEcomUser::query()->where('session_id', $ghostSession)->exists())->toBeFalse()
        ->and(ActivityEcomUser::query()->where('session_id', $keeperSession)->exists())->toBeTrue();
});
