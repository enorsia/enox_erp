<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\CommerceIngestWriter;
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

test('commerce ingest writer creates order and payment lines', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $action = ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $session->session_id,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-100',
            'amount_paid' => 20,
            'payment_method' => 'card',
            'checkout_info' => [
                'items' => [[
                    'product_name' => 'Item',
                    'product_code' => 'P1',
                    'qty' => 1,
                    'price' => 20,
                ]],
                'totals' => ['grand_total' => 20, 'subtotal' => 20],
            ],
        ],
        'created_at' => now(),
    ]);

    app(CommerceIngestWriter::class)->syncFromAction($action);

    expect(DB::table('activity_ecom_orders')->where('order_id', 'ORD-100')->exists())->toBeTrue()
        ->and(DB::table('activity_ecom_commerce_line_items')->where('event_id', $action->event_id)->count())->toBe(1);

    $session->refresh();
    expect($session->has_payment_success)->toBeTrue();
});

test('commerce ingest writer keeps earlier canonical order on duplicate payment', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $earlier = ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $session->session_id,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-DUP',
            'amount_paid' => 10,
            'checkout_info' => ['items' => [], 'totals' => ['grand_total' => 10]],
        ],
        'created_at' => now()->subHour(),
    ]);

    $later = ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $session->session_id,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-DUP',
            'amount_paid' => 99,
            'checkout_info' => ['items' => [], 'totals' => ['grand_total' => 99]],
        ],
        'created_at' => now(),
    ]);

    $writer = app(CommerceIngestWriter::class);
    $writer->syncFromAction($later);
    $writer->syncFromAction($earlier);

    $order = DB::table('activity_ecom_orders')->where('order_id', 'ORD-DUP')->first();
    expect((float) $order->amount_paid)->toBe(10.0)
        ->and($order->event_id)->toBe($earlier->event_id);
});

test('add to cart resume is idempotent', function () {
    $session = ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $action = ActivityEcomUserAction::query()->create([
        'event_id' => (string) Str::uuid(),
        'session_id' => $session->session_id,
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'cart_total' => 5,
            'items' => [['product_name' => 'X', 'product_code' => 'X1', 'qty' => 1, 'price' => 5]],
        ],
        'created_at' => now(),
    ]);

    $writer = app(CommerceIngestWriter::class);
    $writer->syncFromAction($action);
    $writer->syncFromAction($action);

    expect(DB::table('activity_ecom_commerce_line_items')->where('event_id', $action->event_id)->count())->toBe(1);
});
