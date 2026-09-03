<?php

use App\Support\EcomActivityCatalogFilterActions;
use Illuminate\Support\Collection;

it('builds ordered catalog filter actions from commerce line items', function () {
    $lines = collect([
        (object) [
            'id' => 1,
            'funnel_stage' => 'category_view',
            'staged_at' => '2026-09-02 10:00:00',
            'product_code' => null,
            'sku' => null,
            'product_name' => null,
            'order_id' => null,
        ],
        (object) [
            'id' => 2,
            'funnel_stage' => 'product_view',
            'staged_at' => '2026-09-02 10:01:00',
            'product_code' => 'WS4281691',
            'sku' => null,
            'product_name' => null,
            'order_id' => null,
        ],
        (object) [
            'id' => 3,
            'funnel_stage' => 'add_to_cart',
            'staged_at' => '2026-09-02 10:02:00',
            'product_code' => 'WS4281691',
            'sku' => null,
            'product_name' => null,
            'order_id' => null,
        ],
    ]);

    $actions = EcomActivityCatalogFilterActions::fromLines($lines);

    expect($actions)->toHaveCount(3)
        ->and($actions[0]['label'])->toBe('Category view')
        ->and($actions[1]['label'])->toBe('Product view')
        ->and($actions[1]['detail'])->toBe('WS4281691')
        ->and($actions[2]['label'])->toBe('Add to cart')
        ->and($actions[2]['detail'])->toBe('WS4281691');
});

it('deduplicates repeated catalog filter actions in the same session', function () {
    $lines = Collection::make([
        (object) [
            'id' => 1,
            'funnel_stage' => 'product_view',
            'staged_at' => '2026-09-02 10:00:00',
            'product_code' => 'WS4281691',
            'sku' => null,
            'product_name' => null,
            'order_id' => null,
        ],
        (object) [
            'id' => 2,
            'funnel_stage' => 'product_view',
            'staged_at' => '2026-09-02 10:05:00',
            'product_code' => 'WS4281691',
            'sku' => null,
            'product_name' => null,
            'order_id' => null,
        ],
    ]);

    expect(EcomActivityCatalogFilterActions::fromLines($lines))->toHaveCount(1);
});
