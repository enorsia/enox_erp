<?php

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\CommerceIngestWriter;
use App\Support\CommerceLineItemParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\CommerceTestSchema;

uses(Tests\TestCase::class);

beforeEach(function () {
    CommerceTestSchema::up();
});

afterEach(function () {
    CommerceTestSchema::down();
});

function createCatalogSession(): ActivityEcomUser
{
    return ActivityEcomUser::query()->create([
        'session_id' => (string) Str::uuid(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

test('ingest writes category view line items without sync command', function () {
    $session = createCatalogSession();
    $eventId = (string) Str::uuid();

    $action = ActivityEcomUserAction::query()->create([
        'event_id' => $eventId,
        'session_id' => $session->session_id,
        'action_type' => 'category_view',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'created_at' => now(),
    ]);

    app(CommerceIngestWriter::class)->syncFromAction($action);

    $line = DB::table('activity_ecom_commerce_line_items')->where('event_id', $eventId)->first();

    expect($line)->not->toBeNull()
        ->and($line->funnel_stage)->toBe('category_view')
        ->and($line->department_name)->toBe('Men')
        ->and($line->category_name)->toBe('Jeans');
});

test('ingest writes product view line items without sync command', function () {
    $session = createCatalogSession();
    $eventId = (string) Str::uuid();

    $action = ActivityEcomUserAction::query()->create([
        'event_id' => $eventId,
        'session_id' => $session->session_id,
        'action_type' => 'product_view',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'product_code' => 'MA31214415',
        'product_name' => 'Enorsia Washed Black Skinny Fit Jeans',
        'sku' => 'MJEBL30005993',
        'created_at' => now(),
    ]);

    app(CommerceIngestWriter::class)->syncFromAction($action);

    $line = DB::table('activity_ecom_commerce_line_items')->where('event_id', $eventId)->first();

    expect($line)->not->toBeNull()
        ->and($line->funnel_stage)->toBe('product_view')
        ->and($line->product_code)->toBe('MA31214415')
        ->and($line->department_name)->toBe('Men')
        ->and($line->category_name)->toBe('Jeans');
});

test('ingest stores per cart item department and category from frontend payload', function () {
    $session = createCatalogSession();
    $eventId = (string) Str::uuid();

    $action = ActivityEcomUserAction::query()->create([
        'event_id' => $eventId,
        'session_id' => $session->session_id,
        'action_type' => 'add_to_cart',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'product_code' => 'MA31214415',
        'product_name' => 'Enorsia Washed Black Skinny Fit Jeans',
        'sku' => 'MJEBL30005993',
        'add_to_cart' => [
            'product_id' => '339',
            'qty' => 1,
            'unit_price' => 39.99,
            'cart_total' => 79.99,
            'items' => [
                [
                    'product_id' => '1184',
                    'product_code' => 'WA520041218',
                    'sku' => 'WJKOFS000001',
                    'product_name' => "Women's Crochet Front Tie Knit Cardigan",
                    'qty' => 1,
                    'price' => 40,
                    'department_name' => 'Women',
                    'category_name' => 'Jumpers',
                ],
                [
                    'product_id' => '339',
                    'product_code' => 'MA31214415',
                    'sku' => 'MJEBL30005993',
                    'product_name' => 'Enorsia Washed Black Skinny Fit Jeans',
                    'qty' => 1,
                    'price' => 39.99,
                    'department_name' => 'Men',
                    'category_name' => 'Jeans',
                ],
            ],
        ],
        'created_at' => now(),
    ]);

    app(CommerceIngestWriter::class)->syncFromAction($action);

    $lines = DB::table('activity_ecom_commerce_line_items')
        ->where('event_id', $eventId)
        ->orderBy('line_no')
        ->get();

    expect($lines)->toHaveCount(2)
        ->and($lines[0]->product_code)->toBe('WA520041218')
        ->and($lines[0]->department_name)->toBe('Women')
        ->and($lines[0]->category_name)->toBe('Jumpers')
        ->and($lines[1]->product_code)->toBe('MA31214415')
        ->and($lines[1]->department_name)->toBe('Men')
        ->and($lines[1]->category_name)->toBe('Jeans');
});

test('commerce line item parser keeps each cart line category separate', function () {
    $action = new ActivityEcomUserAction([
        'event_id' => (string) Str::uuid(),
        'session_id' => 'sess-cart',
        'action_type' => 'add_to_cart',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'add_to_cart' => [
            'cart_total' => 79.99,
            'items' => [
                [
                    'product_code' => 'WA520041218',
                    'product_name' => "Women's Crochet Front Tie Knit Cardigan",
                    'qty' => 1,
                    'price' => 40,
                    'department_name' => 'Women',
                    'category_name' => 'Jumpers',
                ],
                [
                    'product_code' => 'MA31214415',
                    'product_name' => 'Enorsia Washed Black Skinny Fit Jeans',
                    'qty' => 1,
                    'price' => 39.99,
                    'department_name' => 'Men',
                    'category_name' => 'Jeans',
                ],
            ],
        ],
        'created_at' => now(),
    ]);
    $action->setRelation('session', new ActivityEcomUser(['visitor_id' => 'visitor-1']));

    $lines = CommerceLineItemParser::parseFromAction($action);

    expect($lines)->toHaveCount(2)
        ->and($lines[0]['product_code'])->toBe('WA520041218')
        ->and($lines[0]['department_name'])->toBe('Women')
        ->and($lines[0]['category_name'])->toBe('Jumpers')
        ->and($lines[1]['product_code'])->toBe('MA31214415')
        ->and($lines[1]['department_name'])->toBe('Men')
        ->and($lines[1]['category_name'])->toBe('Jeans');
});

test('full catalog funnel sequence writes line items on each ingest step', function () {
    $session = createCatalogSession();
    $writer = app(CommerceIngestWriter::class);

    $categoryEventId = (string) Str::uuid();
    $writer->syncFromAction(ActivityEcomUserAction::query()->create([
        'event_id' => $categoryEventId,
        'session_id' => $session->session_id,
        'action_type' => 'category_view',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'created_at' => now()->subMinutes(2),
    ]));

    $productEventId = (string) Str::uuid();
    $writer->syncFromAction(ActivityEcomUserAction::query()->create([
        'event_id' => $productEventId,
        'session_id' => $session->session_id,
        'action_type' => 'product_view',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'product_code' => 'MA31214415',
        'product_name' => 'Enorsia Washed Black Skinny Fit Jeans',
        'created_at' => now()->subMinute(),
    ]));

    $cartEventId = (string) Str::uuid();
    $writer->syncFromAction(ActivityEcomUserAction::query()->create([
        'event_id' => $cartEventId,
        'session_id' => $session->session_id,
        'action_type' => 'add_to_cart',
        'department_name' => 'Men',
        'category_name' => 'Jeans',
        'product_code' => 'MA31214415',
        'add_to_cart' => [
            'cart_total' => 39.99,
            'items' => [[
                'product_code' => 'MA31214415',
                'product_name' => 'Enorsia Washed Black Skinny Fit Jeans',
                'qty' => 1,
                'price' => 39.99,
                'department_name' => 'Men',
                'category_name' => 'Jeans',
            ]],
        ],
        'created_at' => now(),
    ]));

    $lines = DB::table('activity_ecom_commerce_line_items')
        ->where('session_id', $session->session_id)
        ->orderBy('staged_at')
        ->get();

    expect($lines)->toHaveCount(3)
        ->and($lines->pluck('funnel_stage')->all())->toBe([
            'category_view',
            'product_view',
            'add_to_cart',
        ])
        ->and($lines->every(
            fn ($line) => $line->department_name === 'Men' && $line->category_name === 'Jeans',
        ))->toBeTrue();
});
