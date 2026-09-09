<?php

use App\Support\TrackerCategoryIdentity;

test('tracker category identity normalizes display names for matching', function () {
    expect(TrackerCategoryIdentity::displayName('Jumpsuits and Playsuits'))->toBe('Jumpsuits');
});

test('tracker category identity resolves department from category page url', function () {
    expect(TrackerCategoryIdentity::departmentNameFromPageUrl('http://127.0.0.1:8000/c/men/jumpers-and-cardigans-1'))
        ->toBe('Men');
    expect(TrackerCategoryIdentity::departmentNameFromPageUrl('https://enorsia.com/c/women/jumpers-and-cardigans-1'))
        ->toBe('Women');
    expect(TrackerCategoryIdentity::departmentNameFromPageUrl('https://enorsia.com/women/dresses'))
        ->toBe('Women');
    expect(TrackerCategoryIdentity::departmentNameFromPageUrl('https://enorsia.com/women'))
        ->toBe('Women');
    expect(TrackerCategoryIdentity::resolveDepartmentName([
        'department_name' => '',
        'page_url' => 'https://enorsia.com/c/women/dresses',
    ]))->toBe('Women');
    expect(TrackerCategoryIdentity::resolveDepartmentName([
        'department_name' => 'Men',
        'page_url' => 'https://enorsia.com/c/women/dresses',
    ]))->toBe('Men');
});

test('tracker category identity does not merge same category name across departments', function () {
    $womenJumpers = TrackerCategoryIdentity::meta('Women', '', 'Jumpers');
    $legacyJumpers = TrackerCategoryIdentity::meta('', '', 'Jumpers');

    expect(TrackerCategoryIdentity::lineMatchesRow([
        'department_name' => 'Men',
        'category_name' => 'Jumpers',
    ], $womenJumpers))->toBeFalse();

    expect(TrackerCategoryIdentity::lineMatchesRow([
        'department_name' => 'Men',
        'category_name' => 'Jumpers',
    ], $legacyJumpers))->toBeFalse();

    expect(TrackerCategoryIdentity::lineMatchesRow([
        'department_name' => 'Women',
        'category_name' => 'Jumpers',
    ], $womenJumpers))->toBeTrue();
});

test('tracker category identity matches cart lines to category rows by code or normalized name', function () {
    $row = TrackerCategoryIdentity::meta('Women', 'DRS', 'Dresses');

    expect(TrackerCategoryIdentity::lineMatchesRow([
        'department_name' => 'Women',
        'category_code' => 'DRS',
        'category_name' => 'Dresses',
    ], $row))->toBeTrue();

    expect(TrackerCategoryIdentity::lineMatchesRow([
        'department_name' => 'Women',
        'category_id' => '55',
        'category_name' => 'Dresses',
    ], $row))->toBeTrue();

    $rowWithId = TrackerCategoryIdentity::meta('Women', '', 'Dresses', '55');

    expect(TrackerCategoryIdentity::lineMatchesRow([
        'department_name' => 'Women',
        'category_id' => '55',
        'category_name' => 'Dresses',
    ], $rowWithId))->toBeTrue();
});

test('tracker category identity normalizes department names', function () {
    expect(TrackerCategoryIdentity::normalizeDepartmentName('men'))->toBe('Men');
    expect(TrackerCategoryIdentity::normalizeDepartmentName('WOMEN'))->toBe('Women');
    expect(TrackerCategoryIdentity::normalizeDepartmentName('boys'))->toBe('Boys');
    expect(TrackerCategoryIdentity::DEPARTMENTS)->toBe(['Men', 'Women', 'Boys', 'Girls']);
});

test('tracker category identity builds department category label', function () {
    $meta = TrackerCategoryIdentity::meta('Women', 'DRS', 'Dresses');

    expect($meta['label'])->toBe('Women -> Dresses');
    expect($meta['key'])->toBe('women|drs');
});

test('tracker category identity expands dashboard category filter labels to stored names', function () {
    expect(TrackerCategoryIdentity::storedCategoryNamesForFilter('Jumpsuits'))
        ->toBe(['Jumpsuits', 'Jumpsuits and Playsuits'])
        ->and(TrackerCategoryIdentity::storedCategoryNamesForFilter('Jumpsuits and Playsuits'))
        ->toBe(['Jumpsuits and Playsuits', 'Jumpsuits']);
});

test('tracker category identity matches stored category names to dashboard filter labels', function () {
    expect(TrackerCategoryIdentity::categoryMatchesFilter('Jumpsuits and Playsuits', 'Jumpsuits'))->toBeTrue()
        ->and(TrackerCategoryIdentity::categoryMatchesFilter('Jumpsuits', 'Jumpsuits and Playsuits'))->toBeTrue()
        ->and(TrackerCategoryIdentity::categoryMatchesFilter('Dresses', 'Jumpsuits'))->toBeFalse();
});

test('tracker category identity builds dashboard department filter options from line pairs', function () {
    $options = TrackerCategoryIdentity::filterOptionsFromPairs([
        ['department_name' => 'women', 'category_name' => 'Dresses'],
        ['department_name' => 'Women', 'category_name' => 'Jumpsuits and Playsuits'],
        ['department_name' => 'Men', 'category_name' => 'Men'],
        ['department_name' => 'Other', 'category_name' => 'Outlet'],
        ['department_name' => 'Boys', 'category_name' => 'T-Shirts'],
    ]);

    expect($options['departments'])->toBe(['Women', 'Boys'])
        ->and($options['categories_by_department']['Women'])->toBe(['Dresses', 'Jumpsuits'])
        ->and($options['categories_by_department']['Boys'])->toBe(['T-Shirts'])
        ->and($options['categories_by_department'])->not->toHaveKey('Men')
        ->and($options['categories_by_department'])->not->toHaveKey('Girls');
});

test('tracker category identity builds filter options only from categories with activity', function () {
    $options = TrackerCategoryIdentity::filterOptionsFromCategoryPerformance([
        [
            'department_name' => 'Women',
            'category_name' => 'Dresses',
            'category_views' => 2,
            'product_views' => 0,
            'views' => 2,
            'adds' => 0,
            'proceed_checkouts' => 0,
            'purchases' => 0,
            'sale_items' => 0,
            'sale_amount' => 0.0,
        ],
        [
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'category_views' => 0,
            'product_views' => 0,
            'views' => 0,
            'adds' => 0,
            'proceed_checkouts' => 0,
            'purchases' => 0,
            'sale_items' => 0,
            'sale_amount' => 0.0,
        ],
    ]);

    expect($options['departments'])->toBe(['Women'])
        ->and($options['categories_by_department'])->toBe([
            'Women' => ['Dresses'],
        ]);
});

test('tracker category identity resolves departments for a category filter label', function () {
    $filterOptions = [
        'departments' => ['Men', 'Women'],
        'categories_by_department' => [
            'Men' => ['Chinos', 'Jeans'],
            'Women' => ['Dresses'],
        ],
    ];

    expect(TrackerCategoryIdentity::departmentsForCategoryInFilterOptions('Chinos', $filterOptions))
        ->toBe(['Men'])
        ->and(TrackerCategoryIdentity::categoryListedForDepartment('Chinos', 'Women', $filterOptions))
        ->toBeFalse()
        ->and(TrackerCategoryIdentity::categoryListedForDepartment('Chinos', 'Men', $filterOptions))
        ->toBeTrue();
});
