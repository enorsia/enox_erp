<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use Illuminate\Support\Collection;

final class EcomActivityRelatedSessions
{
    public const DEFAULT_LIMIT = 20;

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

        return ActivityEcomUser::query()
            ->where('visitor_id', $visitorId)
            ->orderByDesc('last_active_at')
            ->orderByDesc('id')
            ->limit(max(1, $limit))
            ->get([
                'session_id',
                'created_at',
                'last_active_at',
            ]);
    }
}
