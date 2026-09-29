<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use Illuminate\Support\Collection;

final class EcomActivityRelatedSessions
{
    public const DEFAULT_LIMIT = 50;

    /**
     * Sessions for the same visitor (newest first), including the open session.
     *
     * @return Collection<int, ActivityEcomUser>
     */
    public static function forSession(ActivityEcomUser $session, int $limit = self::DEFAULT_LIMIT): Collection
    {
        $visitorId = trim((string) ($session->visitor_id ?? ''));

        if ($visitorId === '') {
            return collect();
        }

        $limit = max(1, $limit);
        $columns = ['session_id', 'created_at', 'last_active_at'];

        $related = ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->orderByDesc('last_active_at')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get($columns);

        $currentSessionId = (string) ($session->session_id ?? '');

        if ($currentSessionId !== '' && ! $related->contains('session_id', $currentSessionId)) {
            $currentRow = ActivityEcomUser::query()
                ->where('session_id', $currentSessionId)
                ->first($columns);

            if ($currentRow !== null) {
                $related = $related
                    ->push($currentRow)
                    ->sortByDesc(fn (ActivityEcomUser $row) => self::sortTimestamp($row))
                    ->values()
                    ->take($limit);
            }
        }

        return $related;
    }

    private static function sortTimestamp(ActivityEcomUser $row): string
    {
        $active = $row->last_active_at ?? $row->created_at;

        return $active !== null ? (string) $active : '';
    }
}
