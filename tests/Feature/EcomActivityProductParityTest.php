<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Str;

test('product catalog session ids match dashboard product identity', function () {
    $sessionA = Str::uuid()->toString();
    $sessionB = Str::uuid()->toString();

    foreach ([$sessionA, $sessionB] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    $productName = 'Red Ruched Side Seam T-Shirt';
    $productCode = 'RRSST-001';

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionA,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => '',
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionB,
        'action_type' => 'product_view',
        'product_name' => $productName,
        'product_code' => $productCode,
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $dashboard = app(EcomTrackerDashboardService::class);
    $range = $dashboard->resolveDateRange(['period' => 'all']);
    $ids = $dashboard->productCatalogSessionIds($range['from'], $range['to'], [
        'product_code' => $productCode,
        'product_name' => $productName,
    ], 'all');

    expect($ids)->toContain($sessionA, $sessionB);

    $metrics = $dashboard->countProductCatalogMetricsForSessions(
        collect([$sessionA, $sessionB]),
        $range['from'],
        $range['to'],
        [
            'product_code' => $productCode,
            'product_name' => $productName,
        ],
    );

    expect($metrics[$sessionA]['products_viewed'])->toBe(1)
        ->and($metrics[$sessionB]['products_viewed'])->toBe(1);
});

test('category catalog metrics count payment success actions once per order', function () {
    $sessionId = Str::uuid()->toString();
    $categoryName = 'Tops and T-Shirts';

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => now(),
        'last_active_at' => now(),
    ]);

    ActivityEcomUserAction::query()->create([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'action_type' => 'payment_success',
        'department_name' => 'Women',
        'payment_success' => [
            'amount_paid' => 45.50,
            'checkout_info' => [
                'items' => [
                    [
                        'product_name' => 'Summer Tee',
                        'product_code' => 'TEE-001',
                        'category_name' => $categoryName,
                        'qty' => 1,
                    ],
                    [
                        'product_name' => 'Classic Tee',
                        'product_code' => 'TEE-002',
                        'category_name' => $categoryName,
                        'qty' => 1,
                    ],
                ],
            ],
        ],
        'created_at' => now(),
        'start_time' => now(),
        'end_time' => now(),
    ]);

    $dashboard = app(EcomTrackerDashboardService::class);
    $range = $dashboard->resolveDateRange(['period' => 'all']);
    $metrics = $dashboard->countCategoryCatalogMetricsForSessions(
        collect([$sessionId]),
        $range['from'],
        $range['to'],
        [
            'department' => 'Women',
            'category' => $categoryName,
        ],
    );

    expect($metrics[$sessionId]['purchases'])->toBe(1);
});

test('product catalog metrics sum matches dashboard view totals across sessions', function () {
    $sessionA = Str::uuid()->toString();
    $sessionB = Str::uuid()->toString();

    foreach ([$sessionA, $sessionB] as $sessionId) {
        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'device_type' => 'desktop',
            'created_at' => now(),
            'last_active_at' => now(),
        ]);
    }

    $productName = 'Red Ruched Side Seam T-Shirt';
    $productCode = 'RRSST-001';

    foreach ([
        [$sessionA, ''],
        [$sessionA, $productCode],
        [$sessionB, $productCode],
        [$sessionB, $productCode],
    ] as [$sessionId, $code]) {
        ActivityEcomUserAction::query()->create([
            'event_id' => Str::uuid()->toString(),
            'session_id' => $sessionId,
            'action_type' => 'product_view',
            'product_name' => $productName,
            'product_code' => $code,
            'created_at' => now(),
            'start_time' => now(),
            'end_time' => now(),
        ]);
    }

    $dashboard = app(EcomTrackerDashboardService::class);
    $range = $dashboard->resolveDateRange(['period' => 'all']);
    $options = [
        'product_code' => $productCode,
        'product_name' => $productName,
    ];

    $metrics = $dashboard->countProductCatalogMetricsForSessions(
        collect([$sessionA, $sessionB]),
        $range['from'],
        $range['to'],
        $options,
    );

    expect(array_sum(array_column($metrics, 'products_viewed')))->toBe(4)
        ->and($metrics[$sessionA]['products_viewed'])->toBe(2)
        ->and($metrics[$sessionB]['products_viewed'])->toBe(2);
});
