<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Str;

test('ecom tracker dashboard resolves yesterday preset range', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $range = $service->resolveDateRange(['period' => 'yesterday']);
    $compare = $service->resolvePreviousPeriodRange($range);

    expect($range['label'])->toBe(TrackerTime::yesterdayPresetLabel());
    expect($range['period'])->toBe('yesterday');
    expect(TrackerTime::toLocal($compare['from'])?->toDateString())->toBe('2026-07-18');
    expect(TrackerTime::toLocal($compare['to'])?->toDateString())->toBe('2026-07-18');

    Carbon::setTestNow();
});

test('ecom tracker dashboard resolves today preset range', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $range = $service->resolveDateRange(['period' => '24h']);

    expect($range['label'])->toBe(TrackerTime::todayPresetLabel());
    expect($range['period'])->toBe('24h');
    expect($range['days'])->toBe(1);
    expect(TrackerTime::toLocal($range['from'])?->format('Y-m-d H:i:s'))->toBe('2026-07-20 00:00:01');
    expect(TrackerTime::toLocal($range['to'])?->format('Y-m-d H:i:s'))->toBe('2026-07-20 23:59:59');

    Carbon::setTestNow();
});

test('ecom tracker dashboard sale amount sums payment_success amount_paid', function () {
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

    expect($data['sale_conversion']['revenue']['value'])->toBe(179.99);
    expect($data['sale_conversion']['item_qty']['value'])->toBeGreaterThan(0);

    Carbon::setTestNow();
});

test('ecom tracker dashboard sale amount uses amount_paid not checkout line totals', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-21 00:00:00');
    $to = Carbon::parse('2026-07-21 23:59:59');
    $sessionId = Str::uuid()->toString();

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
        'action_type' => 'payment_success',
        'payment_success' => [
            'currency' => 'GBP',
            'order_id' => '2721890745',
            'amount_paid' => 13.98,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-1',
                    'product_name' => 'Dress',
                    'qty' => 1,
                    'price' => 19.99,
                    'line_total' => 19.99,
                ]],
                'totals' => ['grand_total' => 19.99],
            ],
        ],
        'created_at' => $from->copy()->addHours(2),
        'start_time' => $from->copy()->addHours(2),
        'end_time' => $from->copy()->addHours(2)->addSeconds(10),
    ]);

    Carbon::setTestNow($to);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-21',
        'date_to' => '2026-07-21',
    ]);

    expect($data['sale_conversion']['revenue']['value'])->toBe(13.98);

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
    expect(collect($data['funnel'])->firstWhere('stage', 'Purchase')['count'])->toBe(1);
    expect($data['categories'][0]['label'] ?? null)->toBe('Women');
    expect($data['categories'][0]['views'] ?? null)->toBe(1);
    expect($data['categories'][0]['adds'] ?? null)->toBe(1);
    expect($data['categories'][0]['purchases'] ?? null)->toBe(1);
    expect($data['categories'][0]['sale_items'] ?? null)->toBe(1);
    expect($data['categories'][0]['sale_amount'] ?? null)->toBe(80.0);
    expect($data['categories'][0]['conversion_rate'] ?? null)->toBe(100.0);
    expect($data['products'][0]['code'] ?? null)->toBe('SKU-1');
    expect($data['products'][0]['variants'][0]['color'] ?? null)->toBe('Navy');
    expect($data['products'][0]['variants'][0]['purchases'] ?? null)->toBe(1);

    Carbon::setTestNow();
});

test('ecom tracker dashboard category performance uses department and category label', function () {
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
        'action_type' => 'category_view',
        'department_name' => 'Women',
        'category_name' => 'Dresses',
        'category_code' => 'DRS',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-11',
        'date_to' => '2026-07-11',
    ]);

    expect($data['categories'][0]['label'] ?? null)->toBe('Women -> Dresses');
    expect($data['categories'][0]['category_code'] ?? null)->toBe('DRS');
});

test('ecom tracker dashboard category performance counts product views with department and category', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-11 12:00:00');

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
        'department_name' => 'Women',
        'category_name' => 'Dresses',
        'category_code' => 'DRS',
        'product_name' => 'Summer Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-11',
        'date_to' => '2026-07-11',
    ]);

    expect($data['categories'][0]['views'] ?? null)->toBe(1);
    expect($data['category_departments'][0]['name'] ?? null)->toBe('Women');
    expect($data['category_departments'][0]['views'] ?? null)->toBe(1);
    expect($data['category_departments'][0]['categories'][0]['category_name'] ?? null)->toBe('Dresses');
});

test('ecom tracker dashboard category performance attributes purchases across visitor sessions', function () {
    $service = app(EcomTrackerDashboardService::class);
    $visitorId = Str::uuid()->toString();
    $browseSessionId = Str::uuid()->toString();
    $purchaseSessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-12 10:00:00');

    foreach ([$browseSessionId, $purchaseSessionId] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $browseSessionId,
        'action_type' => 'category_view',
        'department_name' => 'Women',
        'category_name' => 'Dresses',
        'category_code' => 'DRS',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $purchaseSessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 120,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-1',
                    'product_name' => 'Dress',
                    'qty' => 2,
                    'price' => 60,
                ]],
            ],
        ],
        'created_at' => $from->copy()->addHours(2),
        'start_time' => $from->copy()->addHours(2),
        'end_time' => $from->copy()->addHours(2)->addSeconds(10),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-12',
        'date_to' => '2026-07-12',
    ]);

    expect($data['categories'][0]['label'] ?? null)->toBe('Women -> Dresses')
        ->and($data['categories'][0]['purchases'] ?? null)->toBe(1)
        ->and($data['categories'][0]['sale_items'] ?? null)->toBe(2)
        ->and($data['categories'][0]['sale_amount'] ?? null)->toBe(120.0);
});

test('ecom tracker dashboard category performance attributes add to cart from line item category fields', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-12 10:00:00');

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
        'action_type' => 'category_view',
        'department_name' => 'Women',
        'category_name' => 'Dresses',
        'category_code' => 'DRS',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(20),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'product_id' => '1183',
            'qty' => 2,
            'items' => [[
                'product_id' => '1183',
                'product_code' => 'WJKDNL000003',
                'product_name' => 'Dress',
                'qty' => 2,
                'price' => 28,
                'category_id' => '55',
                'category_code' => 'DRS',
                'category_name' => 'Dresses',
                'department_id' => '12',
                'department_name' => 'Women',
            ]],
        ],
        'created_at' => $from->copy()->addMinutes(5),
        'start_time' => $from->copy()->addMinutes(5),
        'end_time' => $from->copy()->addMinutes(5)->addSeconds(10),
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-12',
        'date_to' => '2026-07-12',
    ]);

    expect($data['categories'][0]['label'] ?? null)->toBe('Women -> Dresses')
        ->and($data['categories'][0]['adds'] ?? null)->toBe(2);
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
            'visitor_id' => 'visitor-'.$day,
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
    expect($data['trend']['series'])->toHaveCount(10);
    expect(collect($data['trend']['series'])->pluck('key')->all())->toBe([
        'unique_visitors',
        'sessions',
        'category_views',
        'product_views',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'purchases',
        'items_sold_qty',
        'conversion_rate',
    ]);
    expect($data['trend']['unique_visitors'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['sessions'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['items_sold_qty'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['conversion_rates'])->toBe([100.0, 100.0, 100.0, 100.0, 100.0]);
    expect(collect($data['trend']['series'])->firstWhere('key', 'purchases')['data'])->toBe([1, 1, 1, 1, 1]);
    expect($data['trend']['use_log_scale'])->toBeFalse();

    Carbon::setTestNow();
});

test('ecom tracker dashboard trend uses twenty four hourly buckets for today', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => 'visitor-hourly',
        'device_type' => 'desktop',
        'created_at' => '2026-07-20 10:00:00',
        'updated_at' => '2026-07-20 10:00:00',
        'last_active_at' => '2026-07-20 10:00:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'created_at' => '2026-07-20 10:15:00',
        'start_time' => '2026-07-20 10:15:00',
        'end_time' => '2026-07-20 10:15:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => ['amount_paid' => 50],
        'created_at' => '2026-07-20 10:30:00',
        'start_time' => '2026-07-20 10:30:00',
        'end_time' => '2026-07-20 10:30:00',
    ]);

    $data = $service->getDashboardData(['period' => '24h']);

    expect($data['trend']['bucket'])->toBe('hour');
    expect($data['trend']['labels'])->toHaveCount(24);
    expect($data['trend']['labels'][0])->toBe('00:00');
    expect($data['trend']['labels'][23])->toBe('23:00');
    expect(collect($data['trend']['series'])->firstWhere('key', 'unique_visitors')['data'][10])->toBe(1);
    expect(collect($data['trend']['series'])->firstWhere('key', 'sessions')['data'][10])->toBe(1);
    expect(collect($data['trend']['series'])->firstWhere('key', 'category_views')['data'][10])->toBe(1);
    expect(collect($data['trend']['series'])->firstWhere('key', 'purchases')['data'][10])->toBe(1);
    expect(collect($data['trend']['series'])->firstWhere('key', 'items_sold_qty')['data'][10])->toBe(1);
    expect(collect($data['trend']['series'])->firstWhere('key', 'conversion_rate')['data'][10])->toBe(100.0);

    Carbon::setTestNow();
});

test('ecom tracker dashboard trend uses twenty four hourly buckets for custom single day', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-31 16:00:00', TrackerTime::timezone()));

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'visitor_id' => 'visitor-custom-day',
        'device_type' => 'desktop',
        'created_at' => '2026-07-30 10:00:00',
        'updated_at' => '2026-07-30 10:00:00',
        'last_active_at' => '2026-07-30 10:00:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'category_view',
        'category_name' => 'Women',
        'created_at' => '2026-07-30 10:15:00',
        'start_time' => '2026-07-30 10:15:00',
        'end_time' => '2026-07-30 10:15:00',
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-30',
        'date_to' => '2026-07-30',
    ]);

    expect($data['trend']['bucket'])->toBe('hour');
    expect($data['trend']['labels'])->toHaveCount(24);
    expect($data['trend']['labels'][0])->toBe('00:00');
    expect($data['trend']['labels'][23])->toBe('23:00');
    expect($data['trend']['range_label'])->toBe('30 Jul 2026 · hourly');
    expect(collect($data['trend']['series'])->firstWhere('key', 'sessions')['data'][10])->toBe(1);
    expect(collect($data['trend']['series'])->firstWhere('key', 'category_views')['data'][10])->toBe(1);

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

    expect($data['payment_success_events']['session_count'])->toBe(1);
    expect($data['payment_success_events']['at_stake'])->toBe(80.0);
    expect($data['payment_success_events']['rows'][0]['session_id'] ?? null)->toBe($completedId);
    expect($data['payment_success_events']['rows'][0]['occurred_ago'] ?? null)->not->toBeEmpty();
    expect($data['payment_success_events']['rows'][0]['qty'] ?? null)->toBeGreaterThan(0);

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

    expect($data['products'][0]['variants'][0]['views'] ?? null)->toBe(1);
    expect($data['products'][0]['variants'][0]['purchases'] ?? null)->toBe(1);
});

test('product catalog filters apply funnel scenario and activity events with or logic', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-12 00:00:00');
    $to = Carbon::parse('2026-07-12 23:59:59');

    $createSession = function (string $suffix) use ($from) {
        $sessionId = "session-{$suffix}";
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);

        return $sessionId;
    };

    $viewOnly = $createSession('view');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $viewOnly,
        'action_type' => 'product_view',
        'product_code' => 'SKU-VIEW',
        'product_name' => 'View Only Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    $cartOnly = $createSession('cart');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $cartOnly,
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'product_code' => 'SKU-CART',
            'product_id' => '2',
        ],
        'product_name' => 'Cart Only Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    $purchased = $createSession('buy');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $purchased,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 120,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BUY',
                    'product_name' => 'Purchased Dress',
                    'qty' => 1,
                    'price' => 120,
                ]],
            ],
        ],
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    $catalog = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'event_scenario' => 'viewed_not_purchased',
    ]);

    expect(collect($catalog['products'])->pluck('name')->all())->toBe(['View Only Dress']);

    $activityCatalog = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'activity' => 'purchases',
    ]);

    expect(collect($activityCatalog['products'])->pluck('name')->all())
        ->toBe(['Purchased Dress']);

    $sorted = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'sort_by' => 'top_purchases',
    ]);

    expect($sorted['products'][0]['name'])->toBe('Purchased Dress');
    expect($sorted['sort_by'])->toBe('top_purchases');
});

test('product catalog counts purchases as orders and qty as units with full sale value', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-13 00:00:00');
    $to = Carbon::parse('2026-07-13 23:59:59');

    $createSession = function (string $suffix) use ($from) {
        $sessionId = "purchase-session-{$suffix}";
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);

        return $sessionId;
    };

    $bulkOrder = $createSession('bulk');
    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $bulkOrder,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-BULK',
            'amount_paid' => 75,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BULK',
                    'product_name' => 'Bulk Dress',
                    'quantity' => 3,
                    'price' => 25,
                ]],
            ],
        ],
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    foreach (['A', 'B'] as $suffix) {
        $sessionId = $createSession($suffix);
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'payment_success',
            'payment_success' => [
                'order_id' => "ORD-{$suffix}",
                'amount_paid' => 40,
                'checkout_info' => [
                    'items' => [[
                        'product_code' => 'SKU-MULTI',
                        'product_name' => 'Multi Order Dress',
                        'qty' => 1,
                        'price' => 40,
                    ]],
                ],
            ],
            'created_at' => $from->copy()->addMinutes(5),
            'start_time' => $from->copy()->addMinutes(5),
            'end_time' => $from->copy()->addMinutes(5),
        ]);
    }

    $catalog = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'sort_by' => 'top_purchases',
    ]);

    $bulk = collect($catalog['products'])->firstWhere('name', 'Bulk Dress');
    expect($bulk['purchases'])->toBe(1);
    expect($bulk['qty'])->toBe(3);
    expect($bulk['revenue'])->toBe(75.0);

    $multi = collect($catalog['products'])->firstWhere('name', 'Multi Order Dress');
    expect($multi['purchases'])->toBe(2);
    expect($multi['qty'])->toBe(2);
    expect($multi['revenue'])->toBe(80.0);

    expect($catalog['products'][0]['name'])->toBe('Multi Order Dress');
});

test('product catalog with has order session filter shows only purchased products', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-14 00:00:00');
    $to = Carbon::parse('2026-07-14 23:59:59');
    $sessionId = Str::uuid()->toString();

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
        'product_code' => 'SKU-VIEWED',
        'product_name' => 'Only Viewed Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'product_view',
        'product_code' => 'SKU-BOUGHT',
        'product_name' => 'Purchased Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-ONLY-ONE',
            'amount_paid' => 55,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BOUGHT',
                    'product_name' => 'Purchased Dress',
                    'qty' => 1,
                    'price' => 55,
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute(),
    ]);

    $allProducts = $service->buildProductCatalogPerformance($from, $to, null, [], []);
    expect(collect($allProducts['products'])->pluck('name')->all())
        ->toContain('Only Viewed Dress', 'Purchased Dress');

    $orderedOnly = $service->buildProductCatalogPerformance($from, $to, null, ['has_order' => '1'], []);
    expect(collect($orderedOnly['products'])->pluck('name')->all())
        ->toBe(['Purchased Dress']);
    expect($orderedOnly['products'][0]['purchases'])->toBe(1);
});

test('product catalog funnel and activity filters only show purchased products when required', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-15 00:00:00');
    $to = Carbon::parse('2026-07-15 23:59:59');
    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $from,
        'updated_at' => $from,
        'last_active_at' => $from,
    ]);

    foreach ([
        ['SKU-VIEW', 'Only Viewed Dress'],
        ['SKU-BUY', 'Purchased Dress'],
    ] as [$code, $name]) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'product_view',
            'product_code' => $code,
            'product_name' => $name,
            'created_at' => $from,
            'start_time' => $from,
            'end_time' => $from,
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORD-FUNNEL-1',
            'amount_paid' => 60,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-BUY',
                    'product_name' => 'Purchased Dress',
                    'qty' => 1,
                    'price' => 60,
                ]],
            ],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute(),
    ]);

    $purchasedOnly = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'event_scenario' => 'purchased_only',
    ]);
    expect(collect($purchasedOnly['products'])->pluck('name')->all())->toBe(['Purchased Dress']);

    $activityPurchases = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'activity' => 'purchases',
    ]);
    expect(collect($activityPurchases['products'])->pluck('name')->all())->toBe(['Purchased Dress']);

    $viewedNotPurchased = $service->buildProductCatalogPerformance($from, $to, null, [], [
        'event_scenario' => 'viewed_not_purchased',
    ]);
    expect(collect($viewedNotPurchased['products'])->pluck('name')->all())->toBe(['Only Viewed Dress']);
});

test('product catalog empty period defaults to today preset', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-15 16:00:00', TrackerTime::timezone()));

    $range = $service->resolveDateRange(['period' => '']);

    expect($range['period'])->toBe('24h');
    expect($range['label'])->toBe(TrackerTime::todayPresetLabel());

    Carbon::setTestNow();
});

test('dashboard product table defaults to today preset and applies session filters', function () {
    $service = app(EcomTrackerDashboardService::class);
    $now = Carbon::parse('2026-07-15 16:00:00', TrackerTime::timezone());

    Carbon::setTestNow($now);

    $recentSession = Str::uuid()->toString();
    $oldSession = Str::uuid()->toString();
    $recentFrom = $now->copy()->subHours(6)->utc();
    $oldFrom = $now->copy()->subDays(3)->utc();

    foreach ([[$recentSession, $recentFrom], [$oldSession, $oldFrom]] as [$sessionId, $createdAt]) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => $createdAt,
            'updated_at' => $createdAt,
            'last_active_at' => $createdAt,
        ]);

        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'product_view',
            'product_code' => "SKU-{$sessionId}",
            'product_name' => $sessionId === $recentSession ? 'Recent Dress' : 'Old Dress',
            'created_at' => $createdAt,
            'start_time' => $createdAt,
            'end_time' => $createdAt,
        ]);
    }

    $defaultData = $service->getDashboardData([]);
    expect($defaultData['range']['period'])->toBe('24h');
    expect(collect($defaultData['products'])->pluck('name')->all())->toBe(['Recent Dress']);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $recentSession,
        'action_type' => 'payment_success',
        'payment_success' => [
            'amount_paid' => 50,
            'checkout_info' => [
                'items' => [[
                    'product_code' => 'SKU-RECENT',
                    'product_name' => 'Recent Dress',
                    'qty' => 1,
                    'price' => 50,
                ]],
            ],
        ],
        'created_at' => $recentFrom,
        'start_time' => $recentFrom,
        'end_time' => $recentFrom,
    ]);

    $orderedData = $service->getDashboardData(['has_order' => '1']);
    expect(collect($orderedData['products'])->pluck('name')->all())->toBe(['Recent Dress']);

    $searchData = $service->getDashboardData(['search' => 'Old']);
    expect(collect($searchData['products'])->pluck('name')->all())->toBe([]);

    Carbon::setTestNow();
});

test('ecom tracker dashboard includes visitor quality summary', function () {
    $service = app(EcomTrackerDashboardService::class);

    $data = $service->getDashboardData(['period' => '7d']);

    expect($data)->toHaveKey('visitor_quality');
    expect($data['visitor_quality'])->toHaveKeys(['real_shoppers', 'automated_traffic', 'not_classified', 'uk_shoppers']);
});

test('ecom tracker dashboard audience kpis align with user activity session scope', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $activeOldSession = Str::uuid()->toString();
    $startedToday = Str::uuid()->toString();
    $startedYesterday = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $activeOldSession,
        'visitor_id' => 'visitor-old-active',
        'device_type' => 'desktop',
        'created_at' => '2026-07-10 10:00:00',
        'updated_at' => '2026-07-20 14:00:00',
        'last_active_at' => '2026-07-20 14:00:00',
        'session_duration_seconds' => 600,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $startedToday,
        'visitor_id' => 'visitor-today',
        'device_type' => 'desktop',
        'created_at' => '2026-07-20 09:00:00',
        'updated_at' => '2026-07-20 10:00:00',
        'last_active_at' => '2026-07-20 10:00:00',
        'session_duration_seconds' => 300,
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $startedYesterday,
        'visitor_id' => 'visitor-yesterday',
        'device_type' => 'desktop',
        'created_at' => '2026-07-19 09:00:00',
        'updated_at' => '2026-07-20 08:00:00',
        'last_active_at' => '2026-07-20 08:00:00',
        'session_duration_seconds' => 400,
    ]);

    $kpiValue = fn (array $data, string $label) => collect($data['kpis'])->firstWhere('label', $label)['value'] ?? null;

    $today = $service->getDashboardData(['period' => '24h']);

    expect($kpiValue($today, 'Sessions'))->toBe(3);
    expect($kpiValue($today, 'Unique visitors'))->toBe(3);
    expect($kpiValue($today, 'Total stay time'))->toBe(1300);

    $yesterday = $service->getDashboardData(['period' => 'yesterday']);

    expect($kpiValue($yesterday, 'Sessions'))->toBe(1);
    expect($kpiValue($yesterday, 'Unique visitors'))->toBe(1);
    expect($kpiValue($yesterday, 'Total stay time'))->toBe(400);

    Carbon::setTestNow();
});

test('ecom tracker dashboard payments count sessions with payment success', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $singleOrderSession = Str::uuid()->toString();
    $doubleOrderSession = Str::uuid()->toString();
    $noOrderSession = Str::uuid()->toString();

    foreach ([$singleOrderSession, $doubleOrderSession, $noOrderSession] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => '2026-07-18 10:00:00',
            'updated_at' => '2026-07-20 10:00:00',
            'last_active_at' => '2026-07-20 10:00:00',
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $singleOrderSession,
        'action_type' => 'payment_success',
        'payment_success' => ['amount_paid' => 25],
        'created_at' => '2026-07-10 12:00:00',
        'start_time' => '2026-07-10 12:00:00',
        'end_time' => '2026-07-10 12:00:00',
    ]);

    foreach ([1, 2] as $index) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $doubleOrderSession,
            'action_type' => 'payment_success',
            'payment_success' => ['amount_paid' => 40 * $index],
            'created_at' => '2026-07-19 1'.$index.':00:00',
            'start_time' => '2026-07-19 1'.$index.':00:00',
            'end_time' => '2026-07-19 1'.$index.':00:00',
        ]);
    }

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-18',
        'date_to' => '2026-07-20',
    ]);

    expect($data['funnel_dropoff']['payments']['formatted'])->toBe('33.3% / 1');
    expect($data['sale_conversion']['item_qty']['value'])->toBe(2);

    Carbon::setTestNow();
});

test('ecom tracker items sold sums product line quantities per payment', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-28 16:00:00', TrackerTime::timezone()));

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => '2026-07-27 10:00:00',
        'updated_at' => '2026-07-27 11:00:00',
        'last_active_at' => '2026-07-27 11:00:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORDER-1',
            'amount_paid' => 90,
            'checkout_info' => [
                'items' => [
                    ['product_code' => 'SKU-1', 'qty' => 2, 'price' => 30],
                    ['product_code' => 'SKU-2', 'qty' => 1, 'price' => 30],
                ],
            ],
        ],
        'created_at' => '2026-07-27 10:30:00',
        'start_time' => '2026-07-27 10:30:00',
        'end_time' => '2026-07-27 10:30:00',
    ]);

    $data = $service->getDashboardData(['period' => 'yesterday']);

    expect($data['sale_conversion']['item_qty']['value'])->toBe(3);
    expect($data['funnel_dropoff']['payments']['formatted'])->toBe('100.0% / 1');

    Carbon::setTestNow();
});

test('ecom tracker funnel stages only count actions inside the selected date range', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $sessionId = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => '2026-07-18 10:00:00',
        'updated_at' => '2026-07-20 10:00:00',
        'last_active_at' => '2026-07-20 10:00:00',
        'session_duration_seconds' => 120,
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'add_to_cart' => ['product_code' => 'SKU-1'],
        'created_at' => '2026-06-01 12:00:00',
        'start_time' => '2026-06-01 12:00:00',
        'end_time' => '2026-06-01 12:00:00',
    ]);

    $data = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-18',
        'date_to' => '2026-07-20',
    ]);

    expect($data['funnel_dropoff']['cart_drop']['formatted'])->toBe('0.0% / 0');
    expect(collect($data['kpis'])->firstWhere('label', 'Total stay time')['value'])->toBe(120);

    Carbon::setTestNow();
});

test('ecom tracker today sale items views and adds align across dashboard sections', function () {
    $service = app(EcomTrackerDashboardService::class);

    Carbon::setTestNow(Carbon::parse('2026-07-28 16:00:00', TrackerTime::timezone()));

    $todaySession = Str::uuid()->toString();
    $staleSession = Str::uuid()->toString();

    ActivityEcomUser::query()->create([
        'session_id' => $todaySession,
        'device_type' => 'desktop',
        'created_at' => '2026-07-28 09:00:00',
        'updated_at' => '2026-07-28 10:00:00',
        'last_active_at' => '2026-07-28 10:00:00',
    ]);

    ActivityEcomUser::query()->create([
        'session_id' => $staleSession,
        'device_type' => 'desktop',
        'created_at' => '2026-07-20 09:00:00',
        'updated_at' => '2026-07-28 11:00:00',
        'last_active_at' => '2026-07-28 11:00:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $todaySession,
        'action_type' => 'product_view',
        'product_name' => 'Today Shirt',
        'product_code' => 'TODAY-1',
        'category_name' => 'Shirts',
        'department_name' => 'Men',
        'created_at' => '2026-07-28 09:30:00',
        'start_time' => '2026-07-28 09:30:00',
        'end_time' => '2026-07-28 09:30:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $todaySession,
        'action_type' => 'add_to_cart',
        'product_name' => 'Today Shirt',
        'product_code' => 'TODAY-1',
        'add_to_cart' => ['product_code' => 'TODAY-1', 'qty' => 1],
        'category_name' => 'Shirts',
        'department_name' => 'Men',
        'created_at' => '2026-07-28 09:40:00',
        'start_time' => '2026-07-28 09:40:00',
        'end_time' => '2026-07-28 09:40:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $todaySession,
        'action_type' => 'payment_success',
        'product_name' => 'Today Shirt',
        'product_code' => 'TODAY-1',
        'payment_success' => [
            'amount_paid' => 50,
            'checkout_info' => [
                'items' => [
                    ['product_code' => 'TODAY-1', 'qty' => 2, 'price' => 25],
                ],
            ],
        ],
        'created_at' => '2026-07-28 09:50:00',
        'start_time' => '2026-07-28 09:50:00',
        'end_time' => '2026-07-28 09:50:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $staleSession,
        'action_type' => 'product_view',
        'product_name' => 'Old Shirt',
        'product_code' => 'OLD-1',
        'category_name' => 'Shirts',
        'department_name' => 'Men',
        'created_at' => '2026-07-20 09:30:00',
        'start_time' => '2026-07-20 09:30:00',
        'end_time' => '2026-07-20 09:30:00',
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $staleSession,
        'action_type' => 'payment_success',
        'product_name' => 'Old Shirt',
        'product_code' => 'OLD-1',
        'payment_success' => [
            'amount_paid' => 30,
            'checkout_info' => [
                'items' => [
                    ['product_code' => 'OLD-1', 'qty' => 5, 'price' => 6],
                ],
            ],
        ],
        'created_at' => '2026-07-20 10:00:00',
        'start_time' => '2026-07-20 10:00:00',
        'end_time' => '2026-07-20 10:00:00',
    ]);

    $data = $service->getDashboardData(['period' => '24h']);

    expect($data['sale_conversion']['item_qty']['value'])->toBe(2);

    $productQty = collect($data['products'])->sum('qty');
    expect($productQty)->toBe(2);

    $category = collect($data['categories'])->firstWhere('category_name', 'Shirts');
    expect($category['views'] ?? 0)->toBe(1);
    expect($category['adds'] ?? 0)->toBe(1);
    expect($category['sale_items'] ?? 0)->toBe(2);

    Carbon::setTestNow();
});

test('ecom tracker product catalog merges views and adds for the same product code', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-15 10:00:00');

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
        'product_code' => 'BS1001',
        'product_name' => 'Blue Dress',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(10),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'product_code' => 'BS1001',
            'qty' => 1,
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute()->addSeconds(5),
    ]);

    $catalog = $service->buildProductCatalogPerformance($from, $from, null, [], []);

    expect($catalog['products'])->toHaveCount(1);
    expect($catalog['products'][0]['views'])->toBe(1);
    expect($catalog['products'][0]['adds'])->toBe(1);
    expect($catalog['products'][0]['code'])->toBe('BS1001');
});

test('ecom tracker product catalog merges variant views without sku into add rows with sku', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-15 11:00:00');

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
        'product_code' => 'BS2002',
        'product_name' => 'Red Shirt',
        'general_color_name' => 'Red',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(10),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'cart_items' => [[
                'product_code' => 'BS2002',
                'product_name' => 'Red Shirt',
                'color_name' => 'Red',
                'size_name' => 'M',
                'sku' => 'WSHGR14009988',
                'qty' => 1,
            ]],
        ],
        'created_at' => $from->copy()->addMinute(),
        'start_time' => $from->copy()->addMinute(),
        'end_time' => $from->copy()->addMinute()->addSeconds(5),
    ]);

    $catalog = $service->buildProductCatalogPerformance($from, $from, null, [], []);
    $product = $catalog['products'][0];

    expect($product['views'])->toBe(1);
    expect($product['adds'])->toBe(1);
    expect($product['variants'])->toHaveCount(1);
    expect($product['variants'][0]['views'])->toBe(1);
    expect($product['variants'][0]['adds'])->toBe(1);
    expect($product['variants'][0]['sku'])->toBe('WSHGR14009988');
});

test('ecom tracker product catalog allows add to cart without a product view in the period', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-15 12:00:00');

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
        'action_type' => 'add_to_cart',
        'add_to_cart' => [
            'product_code' => 'BS3003',
            'product_name' => 'Quick Add Tee',
            'qty' => 1,
        ],
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(5),
    ]);

    $catalog = $service->buildProductCatalogPerformance($from, $from, null, [], []);

    expect($catalog['products'][0]['views'])->toBe(0);
    expect($catalog['products'][0]['adds'])->toBe(1);
});

test('ecom tracker product catalog merges view parent code with add variant sku product code', function () {
    $service = app(EcomTrackerDashboardService::class);
    $sessionId = Str::uuid()->toString();
    $from = Carbon::parse('2026-07-15 13:00:00');

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
        'product_code' => 'MTVYEXXL',
        'product_name' => 'Yellow Stripe T-Shirt',
        'general_color_name' => 'Yellow',
        'sku' => 'MTVYEXXL000755',
        'created_at' => $from,
        'start_time' => $from,
        'end_time' => $from->copy()->addSeconds(10),
    ]);

    foreach ([1, 2] as $index) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'add_to_cart',
            'add_to_cart' => [
                'cart_items' => [[
                    'product_code' => 'MTVYEXXL000755',
                    'product_name' => 'Yellow Stripe T-Shirt',
                    'color_name' => 'Yellow',
                    'size_name' => 'XXL',
                    'qty' => 1,
                ]],
            ],
            'created_at' => $from->copy()->addMinutes($index),
            'start_time' => $from->copy()->addMinutes($index),
            'end_time' => $from->copy()->addMinutes($index)->addSeconds(5),
        ]);
    }

    $catalog = $service->buildProductCatalogPerformance($from, $from, null, [], []);
    $product = $catalog['products'][0];

    expect($catalog['products'])->toHaveCount(1);
    expect($product['views'])->toBe(1);
    expect($product['adds'])->toBe(2);
    expect($product['code'])->toBe('MTVYEXXL');
    expect($product['variants'][0]['views'])->toBe(1);
    expect($product['variants'][0]['adds'])->toBe(2);
    expect($product['variants'][0]['size'])->toBe('XXL');
    expect($product['variants'][0]['sku'])->toBe('MTVYEXXL000755');
});

test('ecom tracker dashboard device breakdown includes funnel metrics by device and browser', function () {
    $service = app(EcomTrackerDashboardService::class);
    $from = Carbon::parse('2026-07-20 10:00:00');
    $to = Carbon::parse('2026-07-20 23:59:59');
    $mobileSessionId = Str::uuid()->toString();
    $desktopSessionId = Str::uuid()->toString();

    foreach ([
        [$mobileSessionId, 'mobile', 'Safari'],
        [$desktopSessionId, 'desktop', 'Chrome'],
    ] as [$sessionId, $deviceType, $browser]) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => $deviceType,
            'browser' => $browser,
            'created_at' => $from,
            'updated_at' => $from,
            'last_active_at' => $from,
        ]);
    }

    foreach ([
        [$mobileSessionId, 'begin_checkout'],
        [$mobileSessionId, 'proceed_checkout'],
        [$desktopSessionId, 'begin_checkout'],
    ] as [$sessionId, $actionType]) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => $actionType,
            'created_at' => $from->copy()->addHour(),
            'start_time' => $from->copy()->addHour(),
            'end_time' => $from->copy()->addHour()->addSeconds(5),
        ]);
    }

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $mobileSessionId,
        'action_type' => 'payment_success',
        'payment_success' => [
            'order_id' => 'ORDER-100',
            'amount_paid' => 80,
            'checkout_info' => [
                'items' => [
                    ['qty' => 2, 'price' => 40],
                ],
            ],
        ],
        'created_at' => $from->copy()->addHours(2),
        'start_time' => $from->copy()->addHours(2),
        'end_time' => $from->copy()->addHours(2)->addSeconds(10),
    ]);

    Carbon::setTestNow(Carbon::parse('2026-07-20 16:00:00', TrackerTime::timezone()));

    $dashboard = $service->getDashboardData([
        'period' => 'custom',
        'date_from' => '2026-07-20',
        'date_to' => '2026-07-20',
    ]);

    $mobileDevice = collect($dashboard['devices']['by_device'])->firstWhere('label', 'Mobile');
    $desktopDevice = collect($dashboard['devices']['by_device'])->firstWhere('label', 'Desktop');
    $safariBrowser = collect($dashboard['devices']['by_browser'])->firstWhere('label', 'Safari');
    $chromeBrowser = collect($dashboard['devices']['by_browser'])->firstWhere('label', 'Chrome');

    expect($mobileDevice)->not->toBeNull();
    expect($mobileDevice['sessions'])->toBe(1);
    expect($mobileDevice['begin_checkout'])->toBe(1);
    expect($mobileDevice['proceed_checkout'])->toBe(1);
    expect($mobileDevice['sold_qty'])->toBe(2);
    expect($mobileDevice['conversion_rate'])->toBe(100.0);

    expect($desktopDevice)->not->toBeNull();
    expect($desktopDevice['sessions'])->toBe(1);
    expect($desktopDevice['begin_checkout'])->toBe(1);
    expect($desktopDevice['proceed_checkout'])->toBe(0);
    expect($desktopDevice['sold_qty'])->toBe(0);
    expect($desktopDevice['conversion_rate'])->toBe(0.0);

    expect($safariBrowser['sold_qty'])->toBe(2);
    expect($chromeBrowser['begin_checkout'])->toBe(1);

    Carbon::setTestNow();
});
