<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('ecom tracker dashboard resolves 24h rolling range', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $range = $service->resolveDateRange(['period' => '24h']);

    expect($range['label'])->toBe('Last 24 hours');
    expect($range['period'])->toBe('24h');
    expect($range['days'])->toBe(1);
    expect((int) TrackerTime::toLocal($range['from'])?->diffInHours(TrackerTime::toLocal($range['to'])))->toBe(24);

    Carbon::setTestNow();
});

test('ecom tracker dashboard sale excludes incomplete payment events and sums checkout line items', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-20 00:00:00');
    $to = Carbon::parse('2026-07-20 23:59:59');

    foreach ([
        Str::uuid()->toString(),
        Str::uuid()->toString(),
        Str::uuid()->toString(),
    ] as $index => $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);

        if ($index < 2) {
            ActivityEcomUserAction::query()->create([
                'event_id' => Str::uuid()->toString(),
                'session_id' => $sessionId,
                'action_type' => 'payment_success',
                'payment_success' => [
                    'order_id' => 'ORDER-'.$index,
                    'amount_paid' => $index === 0 ? 45 : 35,
                    'checkout_info' => [
                        'order_pk' => (string) (4801 + $index),
                        'items' => [[
                            'product_code' => 'SKU-'.$index,
                            'qty' => 1,
                            'price' => $index === 0 ? 45 : 35,
                        ]],
                        'totals' => ['grand_total' => $index === 0 ? 45 : 35],
                    ],
                ],
                'created_at' => $from->copy()->addHours(2 + $index),
                'start_time' => $from->copy()->addHours(2 + $index),
                'end_time' => $from->copy()->addHours(2 + $index)->addSeconds(10),
            ]);

            continue;
        }

        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'payment_success',
            'payment_success' => [
                'order_id' => 'ORD-E2E-1',
                'amount_paid' => 99.99,
                'payment_method' => 'card',
                'currency' => 'GBP',
            ],
            'created_at' => $from->copy()->addHours(5),
            'start_time' => $from->copy()->addHours(5),
            'end_time' => $from->copy()->addHours(5)->addSeconds(10),
        ]);
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-20',
        'date_to' => '2026-07-20',
    ]);

    expect(collect($data['kpis'])->firstWhere('label', 'Sale')['value'])->toBe(80.0);
    expect(collect($data['kpis'])->firstWhere('label', 'Average sale')['value'])->toBe(40.0);

    Carbon::setTestNow();
});

test('ecom tracker dashboard resolves custom date range', function () {
    $service = app(EcomTrackerDashboardService::class);

    $range = $service->resolveDateRange([
        'period' => 'custom',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-10',
    ]);

    expect(TrackerTime::toLocal($range['from'])?->toDateString())->toBe('2026-07-01');
    expect(TrackerTime::toLocal($range['to'])?->toDateString())->toBe('2026-07-10');
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
        ['action_type' => 'begin_checkout', 'begin_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
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

test('ecom tracker dashboard matches product views and purchases by product name', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-12 00:00:00');
    $productName = 'Womens Pink Coloured Slim Fit Stretch Jeans';

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
        'product_code' => 'GS-PARENT-SKU',
        'product_name' => $productName,
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 22.99,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'WJEPI10009400',
                    'product_name' => $productName,
                    'qty' => 1,
                    'price' => 22.99,
                    'color_name' => 'Pink',
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute()->addSeconds(10),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-12',
        'date_to' => '2026-07-12',
    ]);

    expect($data['products'])->not->toBeEmpty();

    $product = collect($data['products'])->first(fn (array $row) => $row['purchases'] > 0);

    expect($product)->not->toBeNull();
    expect($product['code'])->toBe('WJEPI10009400');
    expect($product['views'])->toBeGreaterThanOrEqual(1);
    expect($product['purchases'])->toBe(1);
    expect($product['revenue'])->toBe(22.99);
});

test('ecom tracker dashboard trend uses full filtered range with purchase qty', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-01 00:00:00');
    $to = Carbon::parse('2026-07-05 23:59:59');

    foreach (range(1, 5) as $day) {
        $sessionId = Str::uuid()->toString();
        $createdAt = $from->copy()->addDays($day - 1)->addHours(10);

        Carbon::setTestNow($createdAt);

        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'updated_at' => $createdAt,
            'last_active_at' => $createdAt,
        ]);

        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'payment_success',
            'payment_success' => ['amount_paid' => 50],
            'created_at' => $createdAt->copy()->addHour(),
            'start_time' => $createdAt->copy()->addHour(),
            'end_time' => $createdAt->copy()->addHours(2),
        ]);
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-05',
    ]);

    expect($data['trend']['total_days'])->toBe(5);
    expect($data['trend']['bucket'])->toBe('day');
    expect($data['trend']['labels'])->toHaveCount(5);
    expect($data['trend']['sessions'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['purchases'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['conversion_rates'])->toBe([100.0, 100.0, 100.0, 100.0, 100.0]);
    expect($data['trend']['use_log_scale'])->toBeFalse();

    Carbon::setTestNow();
});

test('ecom tracker dashboard counts all three funnel abandonment stages', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-15 00:00:00');
    $to = Carbon::parse('2026-07-15 23:59:59');

    $cartAbandonedId = Str::uuid()->toString();
    $beginCheckoutAbandonedId = Str::uuid()->toString();
    $proceedCheckoutAbandonedId = Str::uuid()->toString();
    $completedId = Str::uuid()->toString();

    foreach ([$cartAbandonedId, $beginCheckoutAbandonedId, $proceedCheckoutAbandonedId, $completedId] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $cartAbandonedId,
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['cart_total' => 50, 'product_id' => '1'],
        'created_at' => $from->copy()->addHour(),
        'start_time' => $from->copy()->addHour(),
        'end_time' => $from->copy()->addHour()->addSeconds(30),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $beginCheckoutAbandonedId,
        'action_type' => 'begin_checkout',
        'begin_checkout' => ['cart_total' => 120, 'coupon_code' => 'SAVE20'],
        'created_at' => $from->copy()->addHours(2),
        'start_time' => $from->copy()->addHours(2),
        'end_time' => $from->copy()->addHours(2)->addSeconds(30),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $proceedCheckoutAbandonedId,
        'action_type' => 'proceed_checkout',
        'proceed_to_checkout' => ['cart_total' => 90, 'coupon_code' => 'SAVE10'],
        'created_at' => $from->copy()->addHours(3),
        'start_time' => $from->copy()->addHours(3),
        'end_time' => $from->copy()->addHours(3)->addSeconds(30),
    ]);

    foreach ([
        ['action_type' => 'add_to_cart', 'add_to_cart' => ['cart_total' => 80]],
        ['action_type' => 'begin_checkout', 'begin_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'proceed_checkout', 'proceed_to_checkout' => ['cart_total' => 80, 'coupon_code' => 'SAVE10']],
        ['action_type' => 'payment_success', 'payment_success' => ['amount_paid' => 80]],
    ] as $index => $payload) {
        ActivityEcomUserAction::query()->create(array_merge([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $completedId,
            'created_at' => $from->copy()->addHours(4 + $index),
            'start_time' => $from->copy()->addHours(4 + $index),
            'end_time' => $from->copy()->addHours(4 + $index)->addSeconds(30),
        ], $payload));
    }

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-15',
        'date_to' => '2026-07-15',
    ]);

    expect($data['cart_abandonment']['session_count'])->toBe(1);
    expect($data['cart_abandonment']['at_stake'])->toBe(50.0);
    expect($data['cart_abandonment']['rows'][0]['session_id'] ?? null)->toBe($cartAbandonedId);

    expect($data['begin_checkout_abandonment']['session_count'])->toBe(1);
    expect($data['begin_checkout_abandonment']['at_stake'])->toBe(120.0);
    expect($data['begin_checkout_abandonment']['rows'][0]['session_id'] ?? null)->toBe($beginCheckoutAbandonedId);

    expect($data['proceed_checkout_abandonment']['session_count'])->toBe(1);
    expect($data['proceed_checkout_abandonment']['at_stake'])->toBe(90.0);
    expect($data['proceed_checkout_abandonment']['rows'][0]['session_id'] ?? null)->toBe($proceedCheckoutAbandonedId);

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
