<?php

namespace App\Services;

use App\Models\ActivityEcomUserAction;
use Illuminate\Support\Collection;

class EcomActivityTimelinePresenter
{
    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     */
    public function countEvents(Collection $actions): int
    {
        return $this->present($actions)->count();
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $actions
     * @return Collection<int, object>
     */
    public function present(Collection $actions): Collection
    {
        $sorted = $actions
            ->sortBy(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->start_time?->timestamp ?? 0,
                $action->id,
            ])
            ->values();

        $timeline = collect();
        $index = 0;

        while ($index < $sorted->count()) {
            /** @var ActivityEcomUserAction $action */
            $action = $sorted[$index];

            if (! in_array($action->action_type, ['product_view', 'product_view_popup'], true)) {
                $timeline->push($this->wrapSingle($action));
                $index++;

                continue;
            }

            $group = collect([$action]);
            $productKey = $this->productKey($action);
            $index++;

            while ($index < $sorted->count()) {
                /** @var ActivityEcomUserAction $next */
                $next = $sorted[$index];

                if (
                    ! in_array($next->action_type, ['product_view', 'product_view_popup'], true)
                    || $this->productKey($next) !== $productKey
                    || $action->action_type !== $next->action_type
                ) {
                    break;
                }

                $group->push($next);
                $index++;
            }

            $timeline->push($group->count() === 1
                ? $this->wrapSingle($group->first())
                : $this->wrapProductViewGroup($group));
        }

        return $timeline
            ->sortByDesc(fn (object $item) => [
                $item->created_at?->timestamp ?? 0,
                $item->id ?? 0,
            ])
            ->values();
    }

    private function wrapSingle(ActivityEcomUserAction $action): object
    {
        $dwellSeconds = $this->dwellSeconds($action);
        $colorName = $action->general_color_name ?: 'Unknown';

        return (object) [
            'id' => $action->id,
            'is_grouped_product_view' => false,
            'action_type' => $action->action_type,
            'action' => $action,
            'actions' => collect([$action]),
            'category_name' => $action->category_name,
            'category_code' => $action->category_code,
            'product_name' => $action->product_name,
            'product_code' => $action->product_code,
            'product_price' => $action->product_price,
            'referer' => $action->referer,
            'page_url' => $action->page_url,
            'start_time' => $action->start_time,
            'end_time' => $action->end_time,
            'created_at' => $action->created_at ?? $action->start_time,
            'dwell_seconds' => $dwellSeconds,
            'color_timeline' => $dwellSeconds !== null
                ? sprintf('%s (%ds)', $colorName, $dwellSeconds)
                : $colorName,
            'color_segments' => [[
                'name' => $colorName,
                'seconds' => $dwellSeconds,
            ]],
            'add_to_cart' => $action->add_to_cart,
            'begin_checkout' => $action->begin_checkout,
            'proceed_to_checkout' => $action->proceed_to_checkout,
            'payment_success' => $action->payment_success,
        ];
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>  $group
     */
    private function wrapProductViewGroup(Collection $group): object
    {
        $segments = $group->map(function (ActivityEcomUserAction $action) {
            return [
                'name' => $action->general_color_name ?: 'Unknown',
                'seconds' => $this->dwellSeconds($action),
            ];
        });

        $colorTimeline = $segments
            ->map(function (array $segment) {
                if ($segment['seconds'] === null) {
                    return $segment['name'];
                }

                return sprintf('%s (%ds)', $segment['name'], $segment['seconds']);
            })
            ->join(' → ');

        $displaySegments = $segments->reverse()->values();

        $totalDwell = $segments
            ->pluck('seconds')
            ->filter(fn ($seconds) => $seconds !== null)
            ->sum();

        $first = $group->first();
        $last = $group->last();

        return (object) [
            'id' => $first->id,
            'is_grouped_product_view' => true,
            'action_type' => $first->action_type,
            'action' => $first,
            'actions' => $group->sortByDesc(fn (ActivityEcomUserAction $action) => [
                $action->created_at?->timestamp ?? 0,
                $action->start_time?->timestamp ?? 0,
                $action->id,
            ])->values(),
            'category_name' => null,
            'category_code' => null,
            'product_name' => $first->product_name,
            'product_code' => $first->product_code,
            'product_price' => $last->product_price,
            'referer' => $first->referer,
            'page_url' => $last->page_url,
            'start_time' => $first->start_time,
            'end_time' => $last->end_time,
            'created_at' => $first->created_at ?? $first->start_time,
            'dwell_seconds' => $totalDwell > 0 ? $totalDwell : null,
            'color_timeline' => $colorTimeline,
            'color_segments' => $displaySegments->all(),
            'add_to_cart' => null,
            'begin_checkout' => null,
            'proceed_to_checkout' => null,
            'payment_success' => null,
        ];
    }

    private function productKey(ActivityEcomUserAction $action): string
    {
        if (! empty($action->product_code)) {
            return 'code:' . $action->product_code;
        }

        return 'url:' . $this->productPathKey($action->page_url);
    }

    private function productPathKey(?string $url): string
    {
        if (! $url) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return rtrim($path, '/');
    }

    private function dwellSeconds(ActivityEcomUserAction $action): ?int
    {
        if (! $action->start_time || ! $action->end_time) {
            return null;
        }

        return (int) $action->start_time->diffInSeconds($action->end_time);
    }
}
