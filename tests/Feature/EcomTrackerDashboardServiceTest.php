<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('ecom tracker dashboard resolves custom date range', function () {
    $service = app(EcomTrackerDashboardService::class);

    $range = $service->resolveDateRange([
        'period' => 'custom',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-10',
    ]);

    expect($range['from']->toDateString())->toBe('2026-07-01');
    expect($range['to']->toDateString())->toBe('2026-07-10');
    expect($range['days'])->toBe(10);
});

test('ecom tracker dashboard builds funnel and kpis from tracked actions', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-10 00:00:00');
    $to = Carbon::parse('2026-07-10 23:59:59');

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'mobile',
        'utm_source' => 'google',
        'utm_medium' => 'organic',
        'country' => 'UK',
        'city' => 'London',
        'is_logged_in' => false,
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    foreach ([
        ['action_type' => 'category_view', 'category_name' => 'Women'],
        ['action_type' => 'product_view', 'product_code' => 'SKU-1', 'product_name' => 'Dress', 'general_color_name' => 'Navy'],
        ['action_type' => 'add_to_cart', 'add_to_cart' => ['cart_total' => 80, 'product_id' => '1']],
        ['action_type' => 'proceed_checkout', 'proceed_to_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'payment_success', 'payment_success' => ['amount_paid' => 80, 'checkout_info' => ['items' => [['product_code' => 'SKU-1', 'product_name' => 'Dress', 'qty' => 1, 'price' => 80, 'color_name' => 'Navy']]]]],
    ] as $index => $payload) {
        ActivityEcomUserAction::query()->create(array_merge([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'created_at' => $from->copy()->addMinutes($index),
            'start_time' => $from->copy()->addMinutes($index),
            'end_time' => $from->copy()->addMinutes($index)->addSeconds(30),
        ], $payload));
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-10',
        'date_to' => '2026-07-10',
    ]);

    expect($data['funnel'])->not->toBeEmpty();
    expect(collect($data['funnel'])->firstWhere('stage', 'Payment success')['count'])->toBe(1);
    expect($data['categories'][0]['name'] ?? null)->toBe('Women');
    expect($data['products'][0]['code'] ?? null)->toBe('SKU-1');
    expect($data['colors']['products'][0]['product'] ?? null)->toBe('Dress');
    expect($data['colors']['products'][0]['variants'][0]['color'] ?? null)->toBe('Navy');
    expect($data['colors']['products'][0]['variants'][0]['purchased'] ?? null)->toBe(1);

    Carbon::setTestNow();
});

test('ecom tracker dashboard matches purchased variants when checkout only has product id', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-11 00:00:00');

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_code' => 'SKU-9',
        'product_name' => 'Polo Shirt',
        'general_color_name' => 'Beige',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 45,
            'checkout_info' => [
                'items' => [[
                    'product_id' => '101',
                    'product_name' => 'Polo Shirt',
                    'qty' => 1,
                    'price' => 45,
                    'color_name' => 'Beige',
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute()->addSeconds(10),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-11',
        'date_to' => '2026-07-11',
    ]);

    expect($data['colors']['products'][0]['variants'][0]['viewed'] ?? null)->toBe(1);
    expect($data['colors']['products'][0]['variants'][0]['purchased'] ?? null)->toBe(1);
});
