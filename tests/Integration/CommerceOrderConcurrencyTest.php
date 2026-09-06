<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\CommerceIngestWriter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

/**
 * @group mysql
 */
test('concurrent canonical order upsert keeps single row', function () {
    if (DB::connection()->getDriverName() !== 'mysql') {
        $this->markTestSkipped('MySQL-only concurrency test.');
    }

    CommerceTestSchema::up();

    $session = ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $first = ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $session->session_id,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-RACE',
            'amount_paid' => 40,
            'checkout_info' => ['items' => [], 'totals' => ['grand_total' => 40]],
        ],
        'created_at' => now()->subMinutes(5),
    ]);

    $second = ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $session->session_id,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-RACE',
            'amount_paid' => 99,
            'checkout_info' => ['items' => [], 'totals' => ['grand_total' => 99]],
        ],
        'created_at' => now(),
    ]);

    $writer = app(CommerceIngestWriter::class);
    $writer->syncFromAction($second);
    $writer->syncFromAction($first);

    expect(DB::table('activity_ecom_orders')->where('order_id', 'ORD-RACE')->count())->toBe(1);

    CommerceTestSchema::down();
})->group('mysql');
