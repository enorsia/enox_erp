<?php

use App\Models\ActivityEcomUser;
use App\Services\Exports\Async\EcomActivityAsyncRowBuilder;
use App\Services\Exports\Async\EcomActivityExportSchema;
use Carbon\Carbon;
use Illuminate\Http\Request;

uses(Tests\TestCase::class);

test('export headings focus on management columns', function () {
    $headings = EcomActivityExportSchema::headings(['period' => '7d']);

    expect($headings)->toContain('User type', 'Name', 'Email', 'Phone')
        ->and($headings)->toContain(
            'UTM source',
            'Traffic type',
            'Paid click ID',
            'Commerce stage',
            'Order ID',
            'Product Code',
            'Product title',
            'Size',
            'Color',
            'Product qty',
            'Sum qty',
            'Unit price',
            'Line total',
            'Order total',
            'Duration',
        )
        ->and($headings)->not->toContain(
            'User',
            'Commerce',
            'Session ID',
            'User ID',
            'Visitor trust',
            'Event qty',
            'Event time',
            'Actions',
            'Last active',
        );
});

test('payment success filter omits redundant context columns', function () {
    $headings = EcomActivityExportSchema::headings([
        'period' => '30d',
        'funnel' => ['payment_success'],
    ]);

    expect($headings)->not->toContain(
        'Commerce detail',
        'Order value',
        'Cart qty',
        'Cart value',
        'Abandoned',
        'Qty',
        'Value',
    )->and($headings)->toContain('Sum qty', 'Order total');
});

test('cart abandonment filter omits redundant context columns', function () {
    $headings = EcomActivityExportSchema::headings([
        'period' => '7d',
        'funnel' => ['cart_abandonment'],
    ]);

    expect($headings)->not->toContain(
        'Commerce detail',
        'Order value',
        'Cart qty',
        'Cart value',
        'Abandoned',
        'Qty',
        'Value',
    )->and($headings)->toContain('Sum qty', 'Order total');
});

test('registered user columns are populated separately', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-registered',
        'user_id' => 42,
        'user_name' => 'Jane Doe',
        'user_email' => 'jane@example.com',
        'user_phone' => '447700900123',
        'is_logged_in' => true,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
        'actions_count' => 5,
    ]);
    $session->setRelation('botContext', null);

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        [
            'commerce_label' => 'View',
            'commerce_display' => 'View',
            'actions_count' => 5,
        ],
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    $rows = $expanded['rows'];

    expect($rows)->toHaveCount(1)
        ->and($rows[0][2])->toBe('Registered')
        ->and($rows[0][3])->toBe('Jane Doe')
        ->and($rows[0][4])->toBe('jane@example.com')
        ->and($rows[0][5])->toBe('447700900123')
        ->and($rows[0][6])->toBe('View')
        ->and($serial)->toBe(2);
});

test('guest checkout leaves user id blank', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-guest-checkout',
        'user_name' => 'Guest Shopper',
        'user_email' => 'guest@example.com',
        'is_logged_in' => false,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $session->setRelation('botContext', null);

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        ['commerce_display' => '—'],
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    expect($expanded['rows'][0][2])->toBe('Guest checkout')
        ->and($expanded['rows'][0][3])->toBe('Guest Shopper')
        ->and($expanded['rows'][0][4])->toBe('guest@example.com');
});

test('payment event expands to one row per product line', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-order',
        'user_id' => 7,
        'user_name' => 'Buyer',
        'user_email' => 'buyer@example.com',
        'is_logged_in' => true,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
        'actions_count' => 3,
    ]);
    $session->setRelation('botContext', null);

    $metrics = [
        'commerce_display' => 'Order · £89.99',
        'commerce_label' => 'Order',
        'commerce_value' => 89.99,
        'order_qty' => 2,
        'order_value' => 89.99,
        'actions_count' => 3,
        'commerce_events' => [
            [
                'id' => 'payment:104521',
                'stage' => 'payment_success',
                'stage_label' => 'Order',
                'occurred_at' => '01 Sep 2026 10:04',
                'cart_qty' => 2,
                'cart_total' => '£89.99',
                'info_groups' => [[
                    'title' => 'Order info',
                    'fields' => [
                        ['label' => 'Order ID', 'value' => '104521'],
                        ['label' => 'Total', 'value' => '£89.99'],
                        ['label' => 'Quantity', 'value' => '2'],
                    ],
                ]],
                'products' => [
                    [
                        'product_code' => 'MS34129',
                        'title' => 'Silk Blouse (SKU1)',
                        'size' => 'M',
                        'color_po' => 'Red',
                        'qty' => '1',
                        'price' => '£45.00',
                    ],
                    [
                        'product_code' => 'SKU2',
                        'title' => 'Trousers (SKU2)',
                        'size' => '12',
                        'color_po' => 'Navy',
                        'qty' => '1',
                        'price' => '£44.99',
                    ],
                ],
            ],
        ],
    ];

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        $metrics,
        Request::create('/', 'GET', ['period' => '30d', 'funnel' => ['payment_success']]),
        $serial,
    );

    $rows = $expanded['rows'];

    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe(1)
        ->and($rows[1][0])->toBe('')
        ->and($rows[0][7])->toBe('104521')
        ->and($rows[1][7])->toBe('')
        ->and($rows[0][8])->toBe('MS34129')
        ->and($rows[0][9])->toBe('Silk Blouse')
        ->and($rows[0][13])->toBe(2)
        ->and($rows[1][13])->toBe('')
        ->and($rows[0][14])->toBe(45.0)
        ->and($rows[0][15])->toBe(45.0)
        ->and($rows[0][16])->toBe(89.99)
        ->and($rows[1][9])->toBe('Trousers')
        ->and($rows[1][15])->toBe(44.99)
        ->and($serial)->toBe(2);
});

test('async row builder increments serial per session and builds merge ranges', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-lines',
        'is_logged_in' => false,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $session->setRelation('botContext', null);

    $metrics = [
        'commerce_events' => [[
            'stage_label' => 'Cart',
            'cart_qty' => 2,
            'cart_total' => '£30.00',
            'products' => [
                ['title' => 'Item A', 'size' => 'M', 'color_po' => 'Blue', 'qty' => '1', 'price' => '£10.00'],
                ['title' => 'Item B', 'size' => 'L', 'color_po' => 'Green', 'qty' => '1', 'price' => '£20.00'],
            ],
        ]],
    ];

    $serial = 1;
    $built = EcomActivityAsyncRowBuilder::fromSessions(
        collect([$session]),
        ['session-lines' => $metrics],
        ['period' => '7d'],
        $serial,
        7,
    );

    expect($built['rows'])->toHaveCount(2)
        ->and($built['rows'][0][0])->toBe(1)
        ->and($built['rows'][1][0])->toBe('')
        ->and($serial)->toBe(2)
        ->and($built['merge_ranges'])->not->toBeEmpty()
        ->and($built['order_last_indices'])->toBe([1]);

    $sessionMerge = collect($built['merge_ranges'])->first(
        fn (array $range) => $range['column'] === 0 && $range['start_row'] === 7 && $range['end_row'] === 8,
    );

    expect($sessionMerge)->not->toBeNull();
});

test('product view commerce event expands to export rows with product columns', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-view',
        'is_logged_in' => false,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $session->setRelation('botContext', null);

    $metrics = [
        'commerce_label' => 'View',
        'commerce_display' => 'View',
        'commerce_events' => [[
            'stage' => 'product_view',
            'stage_label' => 'View',
            'cart_total' => null,
            'products' => [[
                'product_code' => 'MS34129',
                'title' => 'Silk Blouse (SKU1)',
                'size' => 'M',
                'color_po' => 'Red',
                'qty' => '1',
                'price' => '£45.00',
            ]],
        ]],
    ];

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        $metrics,
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    expect($expanded['rows'])->toHaveCount(1)
        ->and($expanded['rows'][0][6])->toBe('View')
        ->and($expanded['rows'][0][8])->toBe('MS34129')
        ->and($expanded['rows'][0][9])->toBe('Silk Blouse')
        ->and($expanded['rows'][0][10])->toBe('M')
        ->and($expanded['rows'][0][11])->toBe('Red')
        ->and($expanded['rows'][0][14])->toBe(45.0)
        ->and($expanded['rows'][0][15])->toBe(45.0);
});

test('category view commerce event expands with category title in product column', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-category',
        'is_logged_in' => false,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $session->setRelation('botContext', null);

    $metrics = [
        'commerce_label' => 'View',
        'commerce_display' => 'View',
        'commerce_events' => [[
            'stage' => 'category_view',
            'stage_label' => 'Category view',
            'products' => [[
                'title' => 'Women → Dresses',
                'size' => '—',
                'color_po' => '—',
                'qty' => '1',
                'price' => '—',
            ]],
        ]],
    ];

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        $metrics,
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    expect($expanded['rows'])->toHaveCount(1)
        ->and($expanded['rows'][0][6])->toBe('Category view')
        ->and($expanded['rows'][0][8])->toBe('—')
        ->and($expanded['rows'][0][9])->toBe('Women → Dresses');
});

test('merge ranges include event level columns for multi product orders', function () {
    $headings = EcomActivityExportSchema::headings(['period' => '7d', 'funnel' => ['payment_success']]);
    $orderIdColumn = array_search('Order ID', $headings, true);

    $ranges = EcomActivityExportSchema::mergeRangesForSessionBlock(
        [],
        10,
        2,
        [2],
        $headings,
    );

    expect(collect($ranges)->contains(
        fn (array $range) => $range['column'] === $orderIdColumn
            && $range['start_row'] === 10
            && $range['end_row'] === 11,
    ))->toBeTrue();
});

test('duplicate product titles in one order merge vertically in export', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-duplicate-title',
        'is_logged_in' => false,
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $session->setRelation('botContext', null);

    $metrics = [
        'commerce_events' => [[
            'stage_label' => 'Order',
            'cart_total' => '£90.00',
            'products' => [
                ['title' => 'Silk Blouse (SKU1)', 'size' => 'M', 'color_po' => 'Red', 'qty' => '1', 'price' => '£45.00'],
                ['title' => 'Silk Blouse (SKU2)', 'size' => 'L', 'color_po' => 'Blue', 'qty' => '1', 'price' => '£45.00'],
            ],
        ]],
    ];

    $headings = EcomActivityExportSchema::headings(['period' => '7d']);
    $productTitleColumn = array_search('Product title', $headings, true);

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        $metrics,
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    expect($expanded['rows'])->toHaveCount(2)
        ->and($expanded['rows'][0][$productTitleColumn])->toBe('Silk Blouse')
        ->and($expanded['rows'][1][$productTitleColumn])->toBe('')
        ->and($expanded['product_title_merges'])->toMatchArray([[
            'column' => $productTitleColumn,
            'start_row' => 0,
            'end_row' => 1,
        ]]);
});

test('export includes utm source traffic type and paid click id for google ads', function () {
    $session = ActivityEcomUser::make([
        'session_id' => 'session-google-paid',
        'landing_page' => 'https://enorsia.com/style/test?gad_source=1&gad_campaignid=23588680250&gclid=abc123',
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $session->setRelation('botContext', null);

    $headings = EcomActivityExportSchema::headings(['period' => '7d']);
    $utmSourceColumn = array_search('UTM source', $headings, true);
    $trafficTypeColumn = array_search('Traffic type', $headings, true);
    $paidClickIdColumn = array_search('Paid click ID', $headings, true);

    $serial = 1;
    $expanded = EcomActivityExportSchema::expandSession(
        $session,
        ['commerce_display' => '—'],
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    expect($expanded['rows'][0][$utmSourceColumn])->toBe('Google')
        ->and($expanded['rows'][0][$trafficTypeColumn])->toBe('Paid')
        ->and($expanded['rows'][0][$paidClickIdColumn])->toBe('abc123');
});

test('export includes facebook paid click id and marks organic traffic without click id', function () {
    $paidSession = ActivityEcomUser::make([
        'session_id' => 'session-facebook-paid',
        'landing_page' => 'https://enorsia.com/?utm_source=facebook&utm_medium=paid&fbclid=fb-click-123',
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $paidSession->setRelation('botContext', null);

    $organicSession = ActivityEcomUser::make([
        'session_id' => 'session-google-organic',
        'landing_page' => 'https://enorsia.com/style/test',
        'utm_source' => 'google',
        'utm_medium' => 'organic',
        'created_at' => Carbon::parse('2026-09-01 10:00:00', 'UTC'),
        'updated_at' => Carbon::parse('2026-09-01 10:05:00', 'UTC'),
    ]);
    $organicSession->setRelation('botContext', null);

    $headings = EcomActivityExportSchema::headings(['period' => '7d']);
    $utmSourceColumn = array_search('UTM source', $headings, true);
    $trafficTypeColumn = array_search('Traffic type', $headings, true);
    $paidClickIdColumn = array_search('Paid click ID', $headings, true);

    $serial = 1;
    $paidExpanded = EcomActivityExportSchema::expandSession(
        $paidSession,
        ['commerce_display' => '—'],
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    $organicExpanded = EcomActivityExportSchema::expandSession(
        $organicSession,
        ['commerce_display' => '—'],
        Request::create('/', 'GET', ['period' => '7d']),
        $serial,
    );

    expect($paidExpanded['rows'][0][$utmSourceColumn])->toBe('Facebook')
        ->and($paidExpanded['rows'][0][$trafficTypeColumn])->toBe('Paid')
        ->and($paidExpanded['rows'][0][$paidClickIdColumn])->toBe('fb-click-123')
        ->and($organicExpanded['rows'][0][$utmSourceColumn])->toBe('Google')
        ->and($organicExpanded['rows'][0][$trafficTypeColumn])->toBe('Organic')
        ->and($organicExpanded['rows'][0][$paidClickIdColumn])->toBe('—');
});

test('traffic columns are placed on the far right after duration', function () {
    $headings = EcomActivityExportSchema::headings(['period' => '7d']);

    expect(array_slice($headings, -4))->toBe([
        'Duration',
        'UTM source',
        'Traffic type',
        'Paid click ID',
    ]);
});

test('center aligned columns include only requested export columns', function () {
    $headings = EcomActivityExportSchema::headings([
        'period' => '30d',
        'funnel' => ['payment_success'],
    ]);

    expect(EcomActivityExportSchema::centerAlignedColumnIndices($headings))->toContain(
        array_search('Size', $headings, true),
        array_search('Color', $headings, true),
        array_search('Product qty', $headings, true),
        array_search('Sum qty', $headings, true),
        array_search('Unit price', $headings, true),
        array_search('Line total', $headings, true),
        array_search('Order total', $headings, true),
        array_search('Duration', $headings, true),
        array_search('UTM source', $headings, true),
        array_search('Traffic type', $headings, true),
    );
});

test('quantity columns are excluded from decimal formatting', function () {
    $headings = EcomActivityExportSchema::headings([
        'period' => '30d',
        'funnel' => ['payment_success'],
    ]);

    $layout = \App\Services\Exports\Async\SpreadsheetExportLayout::fromProfile(
        \App\Services\Exports\Async\SpreadsheetWriterProfile::ecomActivity($headings),
        $headings,
    );

    expect($layout->shouldFormatAsQuantity(array_search('Product qty', $headings, true)))->toBeTrue()
        ->and($layout->shouldFormatAsQuantity(array_search('Sum qty', $headings, true)))->toBeTrue()
        ->and($layout->shouldFormatAsQuantity(array_search('Unit price', $headings, true)))->toBeFalse();
});

test('product columns are ordered qty sum price line total then order total', function () {
    $headings = EcomActivityExportSchema::headings(['period' => '7d']);

    expect(array_search('Product Code', $headings, true))
        ->toBeLessThan(array_search('Product title', $headings, true));

    $productQtyIndex = array_search('Product qty', $headings, true);

    expect(array_slice($headings, $productQtyIndex, 5))->toBe([
        'Product qty',
        'Sum qty',
        'Unit price',
        'Line total',
        'Order total',
    ]);
});
