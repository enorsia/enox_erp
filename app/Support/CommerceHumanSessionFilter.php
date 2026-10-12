<?php

namespace App\Support;

use Illuminate\Database\Query\Builder;

/**
 * Exclude bot-classified sessions from dashboard commerce aggregates.
 */
final class CommerceHumanSessionFilter
{
    public static function apply(Builder $query, string $sessionAlias = 's'): void
    {
        if (! config('tracker.dashboard_exclude_bots', true)) {
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
}
