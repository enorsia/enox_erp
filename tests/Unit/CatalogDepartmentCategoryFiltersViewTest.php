<?php

use Illuminate\Http\Request;

uses(Tests\TestCase::class);

test('category select only renders categories for the selected department', function () {
    $filterOptions = [
        'departments' => ['Women', 'Men'],
        'categories_by_department' => [
            'Women' => ['Co-ords', 'Dresses'],
            'Men' => ['Chinos', 'Jeans'],
        ],
    ];

    $request = Request::create('/admin/ecom-activity', 'GET', [
        'department' => 'Women',
        'category' => 'Co-ords',
    ]);

    app()->instance('request', $request);

    $html = view('ecom_tracker.partials.catalog-department-category-filters', [
        'filterOptions' => $filterOptions,
    ])->render();

    expect($html)
        ->toContain('data-department="Women"')
        ->toContain('>Co-ords</option>')
        ->toContain('>Dresses</option>')
        ->not->toContain('data-department="Men"')
        ->not->toContain('>Chinos</option>')
        ->toContain('"Men":["Chinos","Jeans"]');
});
