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

test('abandoned rows ignore flagged sessions without an in-period add to cart line', function () {
    [$from, $to] = funnelWindow();

    $abandonedId = (string) Str::uuid();
    $outOfPeriodId = (string) Str::uuid();
    $unflaggedActionOnlyId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $abandonedId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $outOfPeriodId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => false,
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

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $abandonedId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 2,
            'line_total' => 40,
            'staged_at' => $from->copy()->addHour(),
            'created_at' => $from->copy()->addHour(),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $outOfPeriodId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 9,
            'line_total' => 999,
            'staged_at' => $from->copy()->subWeek(),
            'created_at' => $from->copy()->subWeek(),
        ],
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
        ->and($rows[0]['value'])->toBe(40.0)
        ->and($rows[0]['qty'])->toBe(2);
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

test('cart abandonment filter excludes sessions that proceeded without begin checkout', function () {
    [$from, $to] = funnelWindow();
    $proceededId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $proceededId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => false,
        'has_proceed_checkout' => true,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $proceededId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 20,
            'staged_at' => $from->copy()->addMinutes(10),
            'created_at' => $from->copy()->addMinutes(10),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $proceededId,
            'funnel_stage' => 'proceed_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 20,
            'staged_at' => $from->copy()->addMinutes(20),
            'created_at' => $from->copy()->addMinutes(20),
        ],
    ]);

    $cartIds = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'add_to_cart',
            'begin_checkout',
            $from,
            $to,
        ))
        ->pluck('session_id')
        ->all();

    $proceedIds = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'proceed_checkout',
            'payment_success',
            $from,
            $to,
        ))
        ->pluck('session_id')
        ->all();

    expect($cartIds)->not->toContain($proceededId)
        ->and($proceedIds)->toContain($proceededId)
        ->and(CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null))->toBe([]);
});

test('cart abandonment filter excludes add to cart that happened outside the period', function () {
    [$from, $to] = funnelWindow();
    $sessionId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'has_add_to_cart' => true,
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
        'line_total' => 15,
        'staged_at' => $from->copy()->subDays(10),
        'created_at' => $from->copy()->subDays(10),
    ]);

    $ids = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'add_to_cart',
            'begin_checkout',
            $from,
            $to,
        ))
        ->pluck('session_id')
        ->all();

    expect($ids)->not->toContain($sessionId)
        ->and(CommerceFunnelQuery::abandonedRows($from, $to, 'add_to_cart', 'begin_checkout', null))->toBe([]);
});

test('cart abandonment sql excludes later funnel flags and requires in-period cart lines', function () {
    [$from, $to] = funnelWindow();

    $sql = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'add_to_cart',
            'begin_checkout',
            $from,
            $to,
        ))
        ->toSql();

    expect($sql)
        ->toContain('has_begin_checkout')
        ->and($sql)->toContain('has_proceed_checkout')
        ->and($sql)->toContain('has_payment_success')
        ->and($sql)->toContain('activity_ecom_commerce_line_items')
        ->and($sql)->toContain('staged_at');
});

test('begin checkout abandonment excludes sessions that added to cart afterwards', function () {
    [$from, $to] = funnelWindow();
    $returnedToCartId = (string) Str::uuid();
    $stoppedAtCheckoutId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $returnedToCartId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => true,
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $stoppedAtCheckoutId,
        'has_add_to_cart' => true,
        'has_begin_checkout' => true,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $returnedToCartId,
            'funnel_stage' => 'begin_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 24,
            'staged_at' => $from->copy()->addMinutes(10),
            'created_at' => $from->copy()->addMinutes(10),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $returnedToCartId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 52,
            'staged_at' => $from->copy()->addMinutes(20),
            'created_at' => $from->copy()->addMinutes(20),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $stoppedAtCheckoutId,
            'funnel_stage' => 'add_to_cart',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 18,
            'staged_at' => $from->copy()->addMinutes(5),
            'created_at' => $from->copy()->addMinutes(5),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $stoppedAtCheckoutId,
            'funnel_stage' => 'begin_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 18,
            'staged_at' => $from->copy()->addMinutes(15),
            'created_at' => $from->copy()->addMinutes(15),
        ],
    ]);

    $beginIds = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'begin_checkout',
            'proceed_checkout',
            $from,
            $to,
        ))
        ->pluck('session_id')
        ->all();

    $rows = CommerceFunnelQuery::abandonedRows($from, $to, 'begin_checkout', 'proceed_checkout', null);

    expect($beginIds)->not->toContain($returnedToCartId)
        ->and($beginIds)->toContain($stoppedAtCheckoutId)
        ->and(collect($rows)->pluck('session_id')->all())->not->toContain($returnedToCartId)
        ->and(collect($rows)->pluck('session_id')->all())->toContain($stoppedAtCheckoutId);
});

test('payment success filter matches in-period orders even when first payment is older', function () {
    [$from, $to] = funnelWindow();
    $repeatBuyerId = (string) Str::uuid();
    $oldPaymentOnlyId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $repeatBuyerId,
        'has_payment_success' => true,
        'first_payment_at' => $from->copy()->subDays(10),
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $oldPaymentOnlyId,
        'has_payment_success' => true,
        'first_payment_at' => $from->copy()->subDays(10),
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_orders')->insert([
        [
            'order_id' => 'OLD-'.$repeatBuyerId,
            'event_id' => (string) Str::uuid(),
            'session_id' => $repeatBuyerId,
            'amount_paid' => 10,
            'item_qty' => 1,
            'ordered_at' => $from->copy()->subDays(10),
            'created_at' => $from->copy()->subDays(10),
            'updated_at' => $from->copy()->subDays(10),
        ],
        [
            'order_id' => 'NEW-'.$repeatBuyerId,
            'event_id' => (string) Str::uuid(),
            'session_id' => $repeatBuyerId,
            'amount_paid' => 80,
            'item_qty' => 2,
            'ordered_at' => $from->copy()->addHours(2),
            'created_at' => $from->copy()->addHours(2),
            'updated_at' => $from->copy()->addHours(2),
        ],
        [
            'order_id' => 'OLD-'.$oldPaymentOnlyId,
            'event_id' => (string) Str::uuid(),
            'session_id' => $oldPaymentOnlyId,
            'amount_paid' => 12,
            'item_qty' => 1,
            'ordered_at' => $from->copy()->subDays(10),
            'created_at' => $from->copy()->subDays(10),
            'updated_at' => $from->copy()->subDays(10),
        ],
    ]);

    $ids = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyPaymentSuccessSessionFilter($query, $from, $to))
        ->pluck('session_id')
        ->all();

    expect($ids)->toContain($repeatBuyerId)
        ->and($ids)->not->toContain($oldPaymentOnlyId);
});

test('proceed checkout abandonment excludes sessions that paid in the period', function () {
    [$from, $to] = funnelWindow();
    $paidId = (string) Str::uuid();
    $abandonedId = (string) Str::uuid();

    ActivityEcomUser::query()->create([
        'session_id' => $paidId,
        'has_proceed_checkout' => true,
        'has_payment_success' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);
    ActivityEcomUser::query()->create([
        'session_id' => $abandonedId,
        'has_proceed_checkout' => true,
        'has_payment_success' => false,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $paidId,
            'funnel_stage' => 'proceed_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 40,
            'staged_at' => $from->copy()->addMinutes(10),
            'created_at' => $from->copy()->addMinutes(10),
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $abandonedId,
            'funnel_stage' => 'proceed_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 25,
            'staged_at' => $from->copy()->addMinutes(10),
            'created_at' => $from->copy()->addMinutes(10),
        ],
    ]);

    DB::table('activity_ecom_orders')->insert([
        'order_id' => 'PAY-'.$paidId,
        'event_id' => (string) Str::uuid(),
        'session_id' => $paidId,
        'amount_paid' => 40,
        'item_qty' => 1,
        'ordered_at' => $from->copy()->addMinutes(20),
        'created_at' => $from->copy()->addMinutes(20),
        'updated_at' => $from->copy()->addMinutes(20),
    ]);

    $ids = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'proceed_checkout',
            'payment_success',
            $from,
            $to,
        ))
        ->pluck('session_id')
        ->all();

    expect($ids)->not->toContain($paidId)
        ->and($ids)->toContain($abandonedId);
});

test('proceed checkout abandonment keeps sessions whose last step is proceed at the same timestamp as begin', function () {
    [$from, $to] = funnelWindow();
    $sessionId = (string) Str::uuid();
    $at = $from->copy()->addMinutes(10);

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'has_begin_checkout' => true,
        'has_proceed_checkout' => true,
        'created_at' => $from,
        'updated_at' => $from,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'begin_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 29.99,
            'staged_at' => $at,
            'created_at' => $at,
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'proceed_checkout',
            'line_no' => 1,
            'qty' => 1,
            'line_total' => 29.99,
            'staged_at' => $at,
            'created_at' => $at,
        ],
    ]);

    $ids = ActivityEcomUser::query()
        ->tap(fn ($query) => CommerceFunnelQuery::applyAbandonedSessionFilter(
            $query,
            'proceed_checkout',
            'payment_success',
            $from,
            $to,
        ))
        ->pluck('session_id')
        ->all();

    expect($ids)->toContain($sessionId)
        ->and(collect(CommerceFunnelQuery::abandonedRows($from, $to, 'proceed_checkout', 'payment_success', null))->pluck('session_id')->all())
        ->toContain($sessionId);
});
