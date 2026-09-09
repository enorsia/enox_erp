<?php

use App\Models\ActivityEcomUser;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

function seedProductParityLineItem(
    string $sessionId,
    string $funnelStage,
    string $productName,
    string $productCode,
    string $categoryName = '',
    string $departmentName = '',
    float $qty = 1,
    float $lineTotal = 0,
): void {
    $now = now();

    DB::table('activity_ecom_commerce_line_items')->insert([
        'event_id' => Str::uuid()->toString(),
        'session_id' => $sessionId,
        'funnel_stage' => $funnelStage,
        'line_no' => 1,
        'product_name' => $productName,
        'product_code' => $productCode,
        'sku' => $productCode,
        'category_name' => $categoryName,
        'department_name' => $departmentName,
        'qty' => $qty,
        'line_total' => $lineTotal,
        'staged_at' => $now,
        'created_at' => $now,
    ]);
}

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

    seedProductParityLineItem($sessionA, 'add_to_cart', $productName, $productCode);
    seedProductParityLineItem($sessionB, 'add_to_cart', $productName, $productCode);

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

    expect($metrics[$sessionA]['adds'])->toBe(1)
        ->and($metrics[$sessionB]['adds'])->toBe(1)
        ->and($metrics[$sessionA]['products_viewed'])->toBe(0)
        ->and($metrics[$sessionB]['products_viewed'])->toBe(0);
});

test('category catalog metrics count payment success actions once per order', function () {
    $sessionId = Str::uuid()->toString();
    $categoryName = 'Tops and T-Shirts';
    $eventId = Str::uuid()->toString();
    $now = now();

    ActivityEcomUser::query()->create([
        'session_id' => $sessionId,
        'device_type' => 'desktop',
        'created_at' => $now,
        'last_active_at' => $now,
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'funnel_stage' => 'payment_success',
            'line_no' => 1,
            'product_name' => 'Summer Tee',
            'product_code' => 'TEE-001',
            'sku' => 'TEE-001',
            'category_name' => $categoryName,
            'department_name' => 'Women',
            'qty' => 1,
            'line_total' => 22.75,
            'staged_at' => $now,
            'created_at' => $now,
        ],
        [
            'event_id' => $eventId,
            'session_id' => $sessionId,
            'funnel_stage' => 'payment_success',
            'line_no' => 2,
            'product_name' => 'Classic Tee',
            'product_code' => 'TEE-002',
            'sku' => 'TEE-002',
            'category_name' => $categoryName,
            'department_name' => 'Women',
            'qty' => 1,
            'line_total' => 22.75,
            'staged_at' => $now,
            'created_at' => $now,
        ],
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

    foreach ([$sessionA, $sessionA, $sessionB, $sessionB] as $sessionId) {
        seedProductParityLineItem($sessionId, 'add_to_cart', $productName, $productCode);
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

    expect(array_sum(array_column($metrics, 'adds')))->toBe(4)
        ->and($metrics[$sessionA]['adds'])->toBe(2)
        ->and($metrics[$sessionB]['adds'])->toBe(2)
        ->and($metrics[$sessionA]['products_viewed'])->toBe(0)
        ->and($metrics[$sessionB]['products_viewed'])->toBe(0);
});
