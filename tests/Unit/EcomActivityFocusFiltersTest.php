<?php

use App\Support\EcomActivityFocus;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

test('catalog scoped search is enabled for products and categories focus', function () {
    $productsRequest = Request::create('/', 'GET', ['focus' => 'products']);
    $categoriesRequest = Request::create('/', 'GET', ['focus' => 'categories']);
    $audienceRequest = Request::create('/', 'GET', ['focus' => 'audience']);

    expect(EcomActivityFocus::usesCatalogScopedSearch($productsRequest))->toBeTrue()
        ->and(EcomActivityFocus::usesCatalogScopedSearch($categoriesRequest))->toBeTrue()
        ->and(EcomActivityFocus::usesCatalogScopedSearch($audienceRequest))->toBeFalse();
});

test('drawer preserve params keep drill-down context but expose catalog filters in drawer', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'categories',
        'back' => 'http://example.test/dashboard',
        'department' => 'Boys',
        'category' => 'Hoodies',
        'color' => 'Red',
        'activity' => 'views',
        'device_type' => 'mobile',
        'search' => 'hoodie',
    ]);

    $preserved = EcomActivityFocus::drawerPreserveQueryParams($request);

    expect($preserved)->toHaveKeys(['focus', 'back', 'department'])
        ->and($preserved)->not->toHaveKey('category')
        ->and($preserved)->not->toHaveKey('color')
        ->and($preserved)->not->toHaveKey('activity')
        ->and($preserved)->not->toHaveKey('search')
        ->and($preserved)->not->toHaveKey('device_type');
});

test('active filter count includes session and catalog filters on catalog focus', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'device_type' => 'mobile',
        'category' => 'Tops',
        'activity' => 'views',
        'product_code' => 'TEE-1',
    ]);

    expect(EcomActivityFocus::activeFilterCount($request))->toBe(4);
});

test('active filter chips include catalog filters on catalog focus', function () {
    $request = Request::create('/', 'GET', [
        'focus' => 'products',
        'category' => 'Tops',
        'activity' => 'views',
        'device_type' => 'mobile',
    ]);

    $labels = collect(EcomActivityFocus::activeFilterChipsFromRequest($request))
        ->pluck('label')
        ->all();

    expect($labels)->toContain('Category: Tops')
        ->and($labels)->toContain('Activity: Views')
        ->and($labels)->toContain('Device: Mobile');
});
