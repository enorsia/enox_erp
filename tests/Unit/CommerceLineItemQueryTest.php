<?php

use App\Support\CommerceLineItemQuery;
use App\Support\CommerceReadSupport;

uses(Tests\TestCase::class);

test('boolean mode prefix query strips operators that break mysql full text', function () {
    expect(CommerceLineItemQuery::booleanModePrefixQuery('hoodie'))->toBe('hoodie*')
        ->and(CommerceLineItemQuery::booleanModePrefixQuery('hodgson21142@outlook.com'))
        ->toBe('hodgson21142* outlook.com*')
        ->and(CommerceLineItemQuery::booleanModePrefixQuery('size 10+'))->toBe('size*')
        ->and(CommerceLineItemQuery::booleanModePrefixQuery('@'))->toBeNull()
        ->and(CommerceLineItemQuery::booleanModePrefixQuery('ab'))->toBeNull();
});

test('catalog lines for action do not query when an event map is provided', function () {
    $action = (object) [
        'action_type' => 'add_to_cart',
        'event_id' => 'missing-event-id',
        'product_name' => 'Tee',
        'product_code' => 'TEE-1',
        'sku' => '',
        'category_name' => 'Tops',
        'department_name' => 'Men',
        'general_color_name' => '',
        'item_qty' => 2,
        'line_count' => 1,
        'commerce_total' => 20,
    ];

    $lines = CommerceReadSupport::catalogLinesForAction($action, collect());

    expect($lines)->toHaveCount(1)
        ->and($lines[0]['code'])->toBe('TEE-1')
        ->and($lines[0]['qty'])->toBe(2.0);
});
