<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Bot filtering for commerce rollups (traffic funnel only — not revenue).
 */
final class CommerceRollupSessionScope
{
    /**
     * Exclude sessions classified as bots. Unclassified sessions (no bot row) count as human.
     */
    public static function applyHumanSessionFilter(Builder $query, string $sessionAlias = 's'): void
    {
        if (! config('tracker.rollups_exclude_bots', true)) {
            return;
        }

        $sessionId = $sessionAlias.'.session_id';
        $query->whereNotExists(function (Builder $sub) use ($sessionId) {
            $sub->selectRaw('1')
                ->from('activity_ecom_user_bot_context as bc_filter')
                ->whereColumn('bc_filter.session_id', $sessionId)
                ->where('bc_filter.is_bot', true);
        });
    }

    public static function countLineItemsWhere(string $conditionSql): string
    {
        if (! config('tracker.rollups_exclude_bots', true)) {
            return "SUM(CASE WHEN {$conditionSql} THEN 1 ELSE 0 END)";
        }

        return "SUM(CASE WHEN {$conditionSql} AND COALESCE(bc_li.is_bot, 0) = 0 THEN 1 ELSE 0 END)";
    }

    public static function countPaymentSuccessLines(): string
    {
        return "SUM(CASE WHEN li.funnel_stage = 'payment_success' THEN 1 ELSE 0 END)";
    }

    public static function sumPaymentQty(): string
    {
        return 'COALESCE(SUM(CASE WHEN li.funnel_stage = \'payment_success\' THEN li.qty ELSE 0 END), 0)';
    }

    public static function sumPaymentRevenue(): string
    {
        return 'COALESCE(SUM(CASE WHEN li.funnel_stage = \'payment_success\' THEN li.line_total ELSE 0 END), 0)';
    }
}
