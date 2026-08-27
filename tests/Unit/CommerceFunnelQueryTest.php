<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\CommerceFunnelQuery;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

beforeEach(function () {
    CommerceTestSchema::up();
});

afterEach(function () {
    CommerceTestSchema::down();
});

function funnelWindow(): array
{
    $from = Carbon::parse('2026-08-01 00:00:00');
    $to = Carbon::parse('2026-08-01 23:59:59');

    return [$from, $to];
}

test('abandoned rows prefer session flags over scanning all add to cart actions', function () {
    [$from, $to] = funnelWindow();

    $abandonedId = (string) Str::uuid();
    $checkedOutId = (string) Str::uuid();
    $unflaggedActionOnlyId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $abandonedId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $checkedOutId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => true,
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $unflaggedActionOnlyId,
        'has_add_to_cart' => false,
        'has_begin_checkout' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $abandonedId,
        'action_type' => 'add_to_cart',
        'commerce_total' => 40,
        'item_qty' => 2,
        'created_at' => $from->copy()->addHour(),
    ]);
    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $unflaggedActionOnlyId,
        'action_type' => 'add_to_cart',
        'commerce_total' => 999,
        'item_qty' => 9,
        'created_at' => $from->copy()->addHour(),
    ]);

    $rows = CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['session_id'])->toBe($abandonedId)
        ->and($rows[0]['value'])->toBe(0.0)
        ->and($rows[0]['qty'])->toBe(0);
});

test('abandoned rows take qty and value from commerce line items', function () {
    [$from, $to] = funnelWindow();
    $sessionId = (string) Str::uuid();
    $eventId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 2,
            'line_total' => 30,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
        [
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 2,
            'qty' => 1,
            'line_total' => 15,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
    ]);

    $rows = CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['qty'])->toBe(3)
        ->and($rows[0]['value'])->toBe(45.0);
});

test('abandoned rows use line items when session flags are not backfilled', function () {
    [$from, $to] = funnelWindow();
    $sessionId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'has_add_to_cart' => false,
        'has_begin_checkout' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        'event_id' => (string) Str::uuid(),
        'session_id' => $sessionId,
        'funnel_stage' => 'add_to_cart',
        'line_no' => 1,
        'qty' => 1,
        'line_total' => 22.5,
        'staged_at' => $from->copy()->addMinutes(10),
        'created_at' => $from->copy()->addMinutes(10),
    ]);

    $rows = CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['session_id'])->toBe($sessionId)
        ->and($rows[0]['value'])->toBe(22.5);
});

test('abandoned rows ignore actions when flags and line items are empty', function () {
    [$from, $to] = funnelWindow();
    $abandonedId = (string) Str::uuid();
    $checkedOutId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $abandonedId,
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $checkedOutId,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $abandonedId,
        'action_type' => 'add_to_cart',
        'commerce_total' => 50,
        'item_qty' => 1,
        'created_at' => $from->copy()->addHour(),
    ]);
    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $checkedOutId,
        'action_type' => 'add_to_cart',
        'commerce_total' => 80,
        'item_qty' => 1,
        'created_at' => $from->copy()->addHour(),
    ]);
    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $checkedOutId,
        'action_type' => 'begin_checkout',
        'commerce_total' => 80,
        'item_qty' => 1,
        'created_at' => $from->copy()->addHours(2),
    ]);

    $rows = CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null);

    expect($rows)->toBe([]);
});

test('payment rows prefer activity ecom orders over payment success actions', function () {
    [$from, $to] = funnelWindow();
    $sessionId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'has_payment_success' => true,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_orders')->insert([
        'order_id' => 'ORD-1',
        'event_id' => (string) Str::uuid(),
        'session_id' => $sessionId,
        'amount_paid' => 75.5,
        'item_qty' => 3,
        'ordered_at' => $from->copy()->addHours(3),
        'created_at' => $from->copy()->addHours(3),
        'updated_at' => $from->copy()->addHours(3),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'amount_paid' => 1,
        'commerce_total' => 1,
        'item_qty' => 1,
        'created_at' => $from->copy()->addHours(3),
    ]);

    $rows = CommerceFunnelQuery::paymentRows($from, $to, null);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['session_id'])->toBe($sessionId)
        ->and($rows[0]['value'])->toBe(75.5)
        ->and($rows[0]['qty'])->toBe(3);
});

test('abandoned rows from loaded data match sql hydrate for latest event lines', function () {
    [$from, $to] = funnelWindow();
    $sessionId = (string) Str::uuid();
    $oldEvent = (string) Str::uuid();
    $latestEvent = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => $oldEvent,
            'session_id' => $sessionId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 9,
            'line_total' => 90,
            'staged_at' => $from->copy()->addMinutes(5),
            'created_at' => $from->copy()->addMinutes(5),
        ],
        [
            'event_id' => $latestEvent,
            'session_id' => $sessionId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 2,
            'line_total' => 20,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
        [
            'event_id' => $latestEvent,
            'session_id' => $sessionId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 2,
            'qty' => 1,
            'line_total' => 10,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
    ]);

    $sessions = DB::table('activity_ecom_user')->where('session_id', $sessionId)->get()->keyBy('session_id');
    $lines = DB::table('activity_ecom_commerce_line_items')->where('session_id', $sessionId)->get();

    $fromSql = CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null);
    $fromMemory = CommerceFunnelQuery::abandonedRowsFromLoadedData($sessions, $lines, 'add_to_cart', 'begin_checkout');

    expect($fromMemory)->toHaveCount(1)
        ->and($fromMemory[0]['qty'])->toBe(3)
        ->and($fromMemory[0]['value'])->toBe(30.0)
        ->and($fromMemory)->toEqual($fromSql);
});
