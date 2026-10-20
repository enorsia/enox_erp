<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Stable ordering for session timeline and ingest batches (time first, then funnel stage).
 * {@see displayActionCount()} mirrors admin session timeline grouping in EcomActivityController.
 * {@see TIMELINE_HIDDEN_ACTION_TYPES} are stored for CTR/grid analytics but not shown on session detail.
 */
final class TrackerActionSequence
{
    /** @var list<string> */
    public const TIMELINE_HIDDEN_ACTION_TYPES = [
        'product_click',
    ];

    /** @var array<string, int> */
    private const FUNNEL_RANK = [
        'landing_page' => 5,
        'category_view' => 10,
        'grid_impression' => 15,
        'grid_click' => 16,
        'product_click' => 17,
        'product_view' => 20,
        'product_view_popup' => 20,
        'add_to_cart' => 30,
        'begin_checkout' => 40,
        'proceed_checkout' => 50,
        'payment_success' => 60,
    ];

    public static function funnelRank(?string $actionType): int
    {
        return self::FUNNEL_RANK[$actionType] ?? 0;
    }

    public static function isVisibleInSessionTimeline(?string $actionType): bool
    {
        return ! in_array((string) $actionType, self::TIMELINE_HIDDEN_ACTION_TYPES, true);
    }

    /**
     * @param  Collection<int, object>  $actions
     * @return Collection<int, object>
     */
    public static function filterForSessionTimeline(Collection $actions): Collection
    {
        return $actions
            ->filter(fn (object $action) => self::isVisibleInSessionTimeline($action->action_type ?? null))
            ->values();
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    public static function compare(int $leftTs, string $leftType, int $leftId, int $rightTs, string $rightType, int $rightId): int
    {
        if ($leftTs !== $rightTs) {
            return $leftTs <=> $rightTs;
        }

        $rank = self::funnelRank($leftType) <=> self::funnelRank($rightType);

        if ($rank !== 0) {
            return $rank;
        }

        return $leftId <=> $rightId;
    }

    /**
     * @param  Collection<int, object>  $actions
     */
    public static function displayActionCount(Collection $actions): int
    {
        $sorted = self::filterForSessionTimeline($actions)
            ->sort(fn (object $left, object $right) => self::compareDisplayActions($left, $right))
            ->values();

        $count = 0;
        $index = 0;

        while ($index < $sorted->count()) {
            $action = $sorted[$index];

            if ((string) $action->action_type === 'grid_impression') {
                $pageKey = self::timelineGridPageKey($action);
                $index++;

                while ($index < $sorted->count()) {
                    $next = $sorted[$index];

                    if ((string) $next->action_type !== 'grid_impression' || self::timelineGridPageKey($next) !== $pageKey) {
                        break;
                    }

                    $index++;
                }

                $count++;

                continue;
            }

            if ((string) $action->action_type === 'grid_click') {
                $count++;
                $index++;

                continue;
            }

            if (! in_array((string) $action->action_type, ['product_view', 'product_view_popup'], true)) {
                $count++;
                $index++;

                continue;
            }

            $productKey = self::timelineProductKey($action);
            $actionType = (string) $action->action_type;
            $index++;

            while ($index < $sorted->count()) {
                $next = $sorted[$index];

                if (
                    ! in_array((string) $next->action_type, ['product_view', 'product_view_popup'], true)
                    || self::timelineProductKey($next) !== $productKey
                    || (string) $next->action_type !== $actionType
                ) {
                    break;
                }

                $index++;
            }

            $count++;
        }

        return $count;
    }

    private static function compareDisplayActions(object $left, object $right): int
    {
        return self::compare(
            self::displayActionTimestamp($left),
            (string) ($left->action_type ?? ''),
            (int) ($left->id ?? 0),
            self::displayActionTimestamp($right),
            (string) ($right->action_type ?? ''),
            (int) ($right->id ?? 0),
        );
    }

    private static function displayActionTimestamp(object $action): int
    {
        $created = $action->created_at ?? null;
        $start = $action->start_time ?? null;

        if ($created instanceof \DateTimeInterface) {
            return $created->getTimestamp();
        }

        if (is_string($created) && $created !== '') {
            return strtotime($created) ?: 0;
        }

        if ($start instanceof \DateTimeInterface) {
            return $start->getTimestamp();
        }

        if (is_string($start) && $start !== '') {
            return strtotime($start) ?: 0;
        }

        return 0;
    }

    private static function timelineGridPageKey(object $action): string
    {
        $category = trim((string) ($action->category_code ?? $action->category_name ?? ''));

        return self::timelineProductPathKey($action->page_url ?? null).'|'.$category;
    }

    private static function timelineProductKey(object $action): string
    {
        $productCode = trim((string) ($action->product_code ?? ''));

        if ($productCode !== '') {
            return 'code:'.$productCode;
        }

        return 'url:'.self::timelineProductPathKey($action->page_url ?? null);
    }

    private static function timelineProductPathKey(?string $url): string
    {
        if (! $url) {
            return '';
        }

        $path = parse_url($url, PHP_URL_PATH) ?: '';

        return rtrim($path, '/');
    }
}
