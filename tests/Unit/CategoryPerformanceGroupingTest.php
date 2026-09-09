<?php

use App\Services\EcomTrackerDashboardService;

test('ecom tracker groups category performance by department with totals', function () {
    $service = app(EcomTrackerDashboardService::class);

    $grouped = $service->groupCategoryPerformanceByDepartment([
        [
            'department_name' => 'Women',
            'category_name' => 'Dresses',
            'category_views' => 4,
            'product_views' => 6,
            'views' => 10,
            'adds' => 4,
            'sale_items' => 2,
            'sale_amount' => 120.0,
        ],
        [
            'department_name' => 'Men',
            'category_name' => 'Jumpers',
            'category_views' => 2,
            'product_views' => 3,
            'views' => 5,
            'adds' => 1,
            'sale_items' => 0,
            'sale_amount' => 0.0,
        ],
        [
            'department_name' => 'Women',
            'category_name' => 'Tops',
            'category_views' => 1,
            'product_views' => 2,
            'views' => 3,
            'adds' => 0,
            'sale_items' => 1,
            'sale_amount' => 40.0,
        ],
    ]);

    expect(collect($grouped)->pluck('name')->all())->toBe(['Women', 'Men']);
    expect($grouped[0]['category_views'])->toBe(5);
    expect($grouped[0]['product_views'])->toBe(8);
    expect($grouped[0]['views'])->toBe(13);
    expect($grouped[0]['sale_items'])->toBe(3);
    expect($grouped[0]['categories'][0]['category_name'])->toBe('Dresses');
    expect($grouped[0]['categories'][0]['department_name'])->toBe('Women');
    expect($grouped[1]['categories'][0]['category_name'])->toBe('Jumpers');
    expect($grouped[1]['categories'][0]['department_name'])->toBe('Men');
    expect(collect($grouped)->pluck('name'))->not->toContain('Boys');
});
