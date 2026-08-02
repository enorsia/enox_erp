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

test('tracker category identity builds department category label', function () {
    $meta = TrackerCategoryIdentity::meta('Women', 'DRS', 'Dresses');

    expect($meta['label'])->toBe('Women -> Dresses');
    expect($meta['key'])->toBe('women|drs');
});
