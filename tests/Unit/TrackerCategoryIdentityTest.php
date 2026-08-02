<?php

use App\Support\TrackerCategoryIdentity;

test('tracker category identity normalizes display names for matching', function () {
    expect(TrackerCategoryIdentity::displayName('Jumpsuits and Playsuits'))->toBe('Jumpsuits');
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
