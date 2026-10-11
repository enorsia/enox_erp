<?php

namespace App\Support;

/**
 * Stable ordering for session timeline and ingest batches (time first, then funnel stage).
 */
final class TrackerActionSequence
{
    /** @var array<string, int> */
    private const FUNNEL_RANK = [
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
}
