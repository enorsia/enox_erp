<?php

use App\Support\TrackerProductCatalogIdentity;
use Illuminate\Support\Collection;
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

it('prefers product view identity over mis-tagged add to cart lines', function () {
    $sessionId = (string) Str::uuid();

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'product_view',
            'product_code' => 'WA520041218',
            'department_name' => 'Women',
            'category_name' => 'Jumpers',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => '2026-09-03 04:59:14',
            'created_at' => '2026-09-03 04:59:14',
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => $sessionId,
            'funnel_stage' => 'add_to_cart',
            'product_code' => 'WA520041218',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => '2026-09-03 04:59:54',
            'created_at' => '2026-09-03 04:59:54',
        ],
    ]);

    $canonical = TrackerProductCatalogIdentity::canonicalIdentitiesForProductCodes(['WA520041218']);

    expect($canonical)->toHaveKey('WA520041218')
        ->and($canonical['WA520041218']['department_name'])->toBe('Women')
        ->and($canonical['WA520041218']['category_name'])->toBe('Jumpers');
});

it('excludes mis-tagged cart lines from catalog filter matches', function () {
    $lines = collect([
        (object) [
            'product_code' => 'WA520041218',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'funnel_stage' => 'add_to_cart',
        ],
        (object) [
            'product_code' => 'MA31214415',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'funnel_stage' => 'add_to_cart',
        ],
    ]);

    DB::table('activity_ecom_commerce_line_items')->insert([
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'funnel_stage' => 'product_view',
            'product_code' => 'WA520041218',
            'department_name' => 'Women',
            'category_name' => 'Jumpers',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => '2026-09-03 04:59:14',
            'created_at' => '2026-09-03 04:59:14',
        ],
        [
            'event_id' => (string) Str::uuid(),
            'session_id' => (string) Str::uuid(),
            'funnel_stage' => 'product_view',
            'product_code' => 'MA31214415',
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'line_no' => 1,
            'qty' => 1,
            'staged_at' => '2026-09-03 04:59:51',
            'created_at' => '2026-09-03 04:59:51',
        ],
    ]);

    $filtered = TrackerProductCatalogIdentity::filterLinesMatchingCatalogOptions($lines, [
        'department' => 'Men',
        'category' => 'Jeans',
    ]);

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->product_code)->toBe('MA31214415');
});

it('keeps category view lines without product codes', function () {
    $lines = Collection::make([
        (object) [
            'product_code' => null,
            'department_name' => 'Men',
            'category_name' => 'Jeans',
            'funnel_stage' => 'category_view',
        ],
    ]);

    $filtered = TrackerProductCatalogIdentity::filterLinesMatchingCatalogOptions($lines, [
        'department' => 'Men',
        'category' => 'Jeans',
    ]);

    expect($filtered)->toHaveCount(1);
});
