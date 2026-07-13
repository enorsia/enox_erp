<?php

use App\Models\ActivityEcomUserAction;
use App\Services\EcomActivityTimelinePresenter;
use Carbon\Carbon;
use Illuminate\Support\Str;

uses(Tests\TestCase::class);

test('consecutive product views for same product are grouped with summed dwell and color timeline', function () {
    $presenter = new EcomActivityTimelinePresenter();

    $actions = collect([
        makeAction([
            'action_type' => 'product_view',
            'product_code' => 'SKU-1',
            'product_name' => 'Dress',
            'general_color_name' => 'Brown',
            'start_time' => '2026-07-13 10:00:00',
            'end_time' => '2026-07-13 10:00:01',
            'created_at' => '2026-07-13 10:00:00',
        ]),
        makeAction([
            'action_type' => 'product_view',
            'product_code' => 'SKU-1',
            'product_name' => 'Dress',
            'general_color_name' => 'Silver',
            'start_time' => '2026-07-13 10:00:01',
            'end_time' => '2026-07-13 10:00:03',
            'created_at' => '2026-07-13 10:00:01',
        ]),
        makeAction([
            'action_type' => 'product_view',
            'product_code' => 'SKU-1',
            'product_name' => 'Dress',
            'general_color_name' => 'Brown',
            'start_time' => '2026-07-13 10:00:03',
            'end_time' => '2026-07-13 10:00:06',
            'created_at' => '2026-07-13 10:00:03',
        ]),
    ]);

    $timeline = $presenter->present($actions);

    expect($timeline)->toHaveCount(1);
    expect($timeline->first()->is_grouped_product_view)->toBeTrue();
    expect($timeline->first()->dwell_seconds)->toBe(6);
    expect($timeline->first()->color_timeline)->toBe('Brown (1s) → Silver (2s) → Brown (3s)');
    expect($timeline->first()->actions->first()->general_color_name)->toBe('Brown');
    expect($timeline->first()->actions->last()->general_color_name)->toBe('Brown');
    expect((int) $timeline->first()->actions->first()->start_time->format('s'))->toBe(3);
});

test('product views separated by another action stay as separate timeline items', function () {
    $presenter = new EcomActivityTimelinePresenter();

    $actions = collect([
        makeAction([
            'action_type' => 'product_view',
            'product_code' => 'SKU-1',
            'general_color_name' => 'Brown',
            'start_time' => '2026-07-13 10:00:00',
            'end_time' => '2026-07-13 10:00:05',
            'created_at' => '2026-07-13 10:00:00',
        ]),
        makeAction([
            'action_type' => 'category_view',
            'category_name' => 'Women',
            'created_at' => '2026-07-13 10:01:00',
        ]),
        makeAction([
            'action_type' => 'product_view',
            'product_code' => 'SKU-1',
            'general_color_name' => 'Silver',
            'start_time' => '2026-07-13 10:02:00',
            'end_time' => '2026-07-13 10:02:08',
            'created_at' => '2026-07-13 10:02:00',
        ]),
    ]);

    $timeline = $presenter->present($actions);

    expect($timeline)->toHaveCount(3);
    expect($timeline->pluck('action_type')->all())->toBe([
        'product_view',
        'category_view',
        'product_view',
    ]);
    expect($timeline->first()->color_timeline)->toBe('Silver (8s)');
    expect($timeline->last()->color_timeline)->toBe('Brown (5s)');
});

/**
 * @param  array<string, mixed>  $attributes
 */
function makeAction(array $attributes): ActivityEcomUserAction
{
    static $id = 1;

    $action = new ActivityEcomUserAction;
    $action->forceFill(array_merge([
        'event_id' => Str::uuid()->toString(),
        'session_id' => 'session-1',
        'action_type' => 'product_view',
    ], $attributes));
    $action->id = $attributes['id'] ?? $id++;

    foreach (['start_time', 'end_time', 'created_at'] as $field) {
        if (isset($attributes[$field]) && is_string($attributes[$field])) {
            $action->{$field} = Carbon::parse($attributes[$field]);
        }
    }

    return $action;
}
