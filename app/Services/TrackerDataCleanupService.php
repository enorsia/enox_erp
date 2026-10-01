<?php

namespace App\Services;

use App\Models\ActivityEcomCommerceLineItem;
use App\Models\ActivityEcomOrder;
use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\ActivityEcomUserBotContext;
use App\Support\TrackerSessionClock;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class TrackerDataCleanupService
{
    public function __construct(
        private TrackIngestService $trackIngestService,
    ) {}

    /**
     * @return array{scanned: int, duplicate_groups: int, deleted_actions: int, kept_actions: int}
     */
    public function dedupePaymentSuccessActions(?Carbon $before = null, bool $dryRun = false): array
    {
        $query = ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->orderBy('id');

        if ($before !== null) {
            $query->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        /** @var Collection<int, Collection<int, ActivityEcomUserAction>> $groups */
        $groups = $query
            ->get(['id', 'event_id', 'session_id', 'created_at', 'payment_success'])
            ->groupBy(fn (ActivityEcomUserAction $action) => $this->paymentSuccessOrderId($action));

        $deleted = 0;
        $kept = 0;
        $duplicateGroups = 0;

        foreach ($groups as $orderId => $actions) {
            if ($orderId === '' || $actions->count() <= 1) {
                $kept += $actions->count();

                continue;
            }

            $duplicateGroups++;
            $keeper = $actions->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])->first();

            foreach ($actions as $action) {
                if ($action->id === $keeper->id) {
                    $kept++;

                    continue;
                }

                if (! $dryRun) {
                    $sessionId = (string) $action->session_id;
                    $action->delete();

                    ActivityEcomUser::query()
                        ->where('session_id', $sessionId)
                        ->where('actions_count', '>', 0)
                        ->decrement('actions_count');

                    if (ActivityEcomUserAction::query()->where('session_id', $sessionId)->doesntExist()) {
                        ActivityEcomUser::query()->where('session_id', $sessionId)->delete();
                    }
                }

                $deleted++;
            }
        }

        return [
            'scanned' => $groups->flatten(1)->count(),
            'duplicate_groups' => $duplicateGroups,
            'deleted_actions' => $deleted,
            'kept_actions' => $kept,
        ];
    }

    /**
     * @return array{scanned: int, deleted_sessions: int}
     */
    public function removePaymentOnlySessions(?Carbon $before = null, bool $dryRun = false): array
    {
        $query = ActivityEcomUser::query()
            ->with(['actions' => fn ($builder) => $builder->select('id', 'session_id', 'action_type')])
            ->orderBy('id');

        if ($before !== null) {
            $query->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        $deleted = 0;
        $scanned = 0;

        $query->chunkById(200, function (Collection $sessions) use (&$deleted, &$scanned, $dryRun) {
            foreach ($sessions as $session) {
                $scanned++;

                if (! $this->isPaymentOnlySession($session)) {
                    continue;
                }

                if (! $dryRun) {
                    $session->delete();
                }

                $deleted++;
            }
        });

        return [
            'scanned' => $scanned,
            'deleted_sessions' => $deleted,
        ];
    }

    /**
     * @return array{scanned: int, deleted_sessions: int}
     */
    public function removeEmptySessions(?Carbon $before = null, bool $dryRun = false): array
    {
        $query = ActivityEcomUser::query()
            ->orderBy('id');

        if ($before !== null) {
            $query->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        $deleted = 0;
        $scanned = 0;

        $query->chunkById(200, function (Collection $sessions) use (&$deleted, &$scanned, $dryRun) {
            foreach ($sessions as $session) {
                $scanned++;

                if (($session->actions_count ?? 0) > 0) {
                    continue;
                }

                if (! $dryRun) {
                    $session->delete();
                }

                $deleted++;
            }
        });

        return [
            'scanned' => $scanned,
            'deleted_sessions' => $deleted,
        ];
    }

    public function backfillSessionCustomerFields(int $chunkSize = 100): int
    {
        return $this->trackIngestService->backfillSessionCustomerFromCheckoutActions($chunkSize);
    }

    /**
     * Collapse multiple activity_ecom_user rows for one visitor when they would have been
     * a single visit under TrackerSessionClock (same local day, gap not expired).
     *
     * @return array{
     *     visitors_scanned: int,
     *     merge_groups: int,
     *     sessions_removed: int,
     *     actions_reassigned: int,
     *     examples: array<int, array{visitor_id: string, keeper_session_id: string, dropped_session_ids: array<int, string>}>
     * }
     */
    public function mergeDuplicateVisitorSessionsWithinGap(
        ?Carbon $from = null,
        ?Carbon $before = null,
        bool $dryRun = false,
        ?string $visitorId = null,
        int $visitorChunk = 200,
    ): array {
        $visitorsScanned = 0;
        $mergeGroups = 0;
        $sessionsRemoved = 0;
        $actionsReassigned = 0;
        $examples = [];

        $visitorQuery = ActivityEcomUser::query()
            ->whereNotNull('visitor_id')
            ->where('visitor_id', '!=', '')
            ->select('visitor_id')
            ->groupBy('visitor_id')
            ->havingRaw('COUNT(*) > 1');

        if ($visitorId !== null) {
            $visitorQuery->where('visitor_id', $visitorId);
        }

        if ($from !== null) {
            $visitorQuery->where('created_at', '>=', TrackerTime::formatUtc($from));
        }

        if ($before !== null) {
            $visitorQuery->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        $visitorQuery->orderBy('visitor_id')->chunk($visitorChunk, function (Collection $rows) use (
            $from,
            $before,
            $dryRun,
            &$visitorsScanned,
            &$mergeGroups,
            &$sessionsRemoved,
            &$actionsReassigned,
            &$examples,
        ) {
            foreach ($rows as $row) {
                $vid = (string) $row->visitor_id;
                $visitorsScanned++;

                $sessionQuery = ActivityEcomUser::query()
                    ->where('visitor_id', $vid)
                    ->orderBy('created_at')
                    ->orderBy('id');

                if ($before !== null) {
                    $sessionQuery->where('created_at', '<=', TrackerTime::formatUtc($before));
                }

                /** @var Collection<int, ActivityEcomUser> $sessions */
                $sessions = $sessionQuery->get();

                if ($sessions->count() < 2) {
                    continue;
                }

                $clusters = $this->clusterSessionsForSameVisit($sessions);

                foreach ($clusters as $cluster) {
                    if ($cluster->count() < 2) {
                        continue;
                    }

                    if (! $this->clusterOverlapsRange($cluster, $from, $before)) {
                        continue;
                    }

                    $keeper = $cluster->first();
                    $droppers = $cluster->slice(1)->values();

                    if ($keeper === null) {
                        continue;
                    }

                    $mergeGroups++;
                    $droppedIds = $droppers->pluck('session_id')->map(fn ($id) => (string) $id)->all();

                    if (count($examples) < 10) {
                        $examples[] = [
                            'visitor_id' => $vid,
                            'keeper_session_id' => (string) $keeper->session_id,
                            'dropped_session_ids' => $droppedIds,
                        ];
                    }

                    if ($dryRun) {
                        foreach ($droppers as $drop) {
                            $actionsReassigned += ActivityEcomUserAction::query()
                                ->where('session_id', $drop->session_id)
                                ->count();
                        }

                        $sessionsRemoved += $droppers->count();

                        continue;
                    }

                    DB::transaction(function () use ($keeper, $droppers, $cluster, &$actionsReassigned, &$sessionsRemoved) {
                        $keeperId = (string) $keeper->session_id;

                        foreach ($droppers as $drop) {
                            $fromId = (string) $drop->session_id;

                            $moved = ActivityEcomUserAction::query()
                                ->where('session_id', $fromId)
                                ->update(['session_id' => $keeperId]);

                            $actionsReassigned += $moved;

                            ActivityEcomOrder::query()
                                ->where('session_id', $fromId)
                                ->update(['session_id' => $keeperId]);

                            ActivityEcomCommerceLineItem::query()
                                ->where('session_id', $fromId)
                                ->update(['session_id' => $keeperId]);

                            if (Schema::hasTable('attribution_touch_log')) {
                                DB::table('attribution_touch_log')
                                    ->where('session_id', $fromId)
                                    ->update(['session_id' => $keeperId]);
                            }

                            $keeperBot = ActivityEcomUserBotContext::query()->where('session_id', $keeperId)->exists();
                            $loserBot = ActivityEcomUserBotContext::query()->where('session_id', $fromId)->first();

                            if ($loserBot !== null) {
                                if (! $keeperBot) {
                                    $loserBot->update(['session_id' => $keeperId]);
                                } else {
                                    $loserBot->delete();
                                }
                            }

                            $drop->delete();
                            $sessionsRemoved++;
                        }

                        $this->refreshMergedSessionRow($keeper, $cluster);
                    });
                }
            }
        });

        return [
            'visitors_scanned' => $visitorsScanned,
            'merge_groups' => $mergeGroups,
            'sessions_removed' => $sessionsRemoved,
            'actions_reassigned' => $actionsReassigned,
            'examples' => $examples,
        ];
    }

    /**
     * @param  Collection<int, ActivityEcomUser>  $sessions
     * @return array<int, Collection<int, ActivityEcomUser>>
     */
    private function clusterSessionsForSameVisit(Collection $sessions): array
    {
        $clusters = [];
        $current = collect();
        $clusterStart = null;
        $clusterEnd = null;

        foreach ($sessions as $session) {
            if ($current->isEmpty()) {
                $current->push($session);
                $clusterStart = $session;
                $clusterEnd = $this->sessionActivityEnd($session);

                continue;
            }

            if ($this->belongsToSameVisit($clusterStart, $clusterEnd, $session)) {
                $current->push($session);
                $clusterEnd = $this->maxActivityInstant($clusterEnd, $this->sessionActivityEnd($session));

                continue;
            }

            $clusters[] = $current;
            $current = collect([$session]);
            $clusterStart = $session;
            $clusterEnd = $this->sessionActivityEnd($session);
        }

        if ($current->isNotEmpty()) {
            $clusters[] = $current;
        }

        return $clusters;
    }

    private function belongsToSameVisit(
        ActivityEcomUser $clusterStart,
        ?Carbon $clusterEnd,
        ActivityEcomUser $next,
    ): bool {
        if ($clusterEnd === null) {
            return false;
        }

        if ($this->localVisitDate($clusterStart) !== $this->localVisitDate($next)) {
            return false;
        }

        $nextStart = TrackerTime::toUtc($next->getRawOriginal('created_at') ?? $next->created_at);

        if ($nextStart === null) {
            return false;
        }

        return ! TrackerSessionClock::gapExpired(
            TrackerTime::formatUtc($clusterEnd),
            $nextStart,
        );
    }

    private function sessionActivityEnd(ActivityEcomUser $session): ?Carbon
    {
        $last = TrackerTime::toUtc($session->getRawOriginal('last_active_at') ?? $session->last_active_at);
        $created = TrackerTime::toUtc($session->getRawOriginal('created_at') ?? $session->created_at);

        if ($last === null) {
            return $created;
        }

        if ($created === null) {
            return $last;
        }

        return $last->gt($created) ? $last : $created;
    }

    private function maxActivityInstant(?Carbon $left, ?Carbon $right): ?Carbon
    {
        if ($left === null) {
            return $right;
        }

        if ($right === null) {
            return $left;
        }

        return $left->gt($right) ? $left : $right;
    }

    private function localVisitDate(ActivityEcomUser $session): string
    {
        $created = TrackerTime::toUtc($session->getRawOriginal('created_at') ?? $session->created_at);

        if ($created === null) {
            return '';
        }

        return $created->copy()->timezone(TrackerTime::timezone())->toDateString();
    }

    /**
     * @param  Collection<int, ActivityEcomUser>  $cluster
     */
    private function clusterOverlapsRange(Collection $cluster, ?Carbon $from, ?Carbon $before): bool
    {
        if ($from === null && $before === null) {
            return true;
        }

        foreach ($cluster as $session) {
            $created = TrackerTime::toUtc($session->getRawOriginal('created_at') ?? $session->created_at);

            if ($created === null) {
                continue;
            }

            if ($from !== null && $created->lt($from)) {
                continue;
            }

            if ($before !== null && $created->gt($before)) {
                continue;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  Collection<int, ActivityEcomUser>  $cluster
     */
    private function refreshMergedSessionRow(ActivityEcomUser $keeper, Collection $cluster): void
    {
        $keeperId = (string) $keeper->session_id;

        $createdAt = null;
        $lastActiveAt = null;

        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $keeperId)
            ->orderBy('created_at')
            ->get();

        foreach ($actions as $action) {
            $at = $this->actionActivityAt($action);
            if ($createdAt === null || $at->lt($createdAt)) {
                $createdAt = $at->copy();
            }
            if ($lastActiveAt === null || $at->gt($lastActiveAt)) {
                $lastActiveAt = $at->copy();
            }
        }

        if ($createdAt === null || $lastActiveAt === null) {
            foreach ($cluster as $session) {
                $created = TrackerTime::toUtc($session->getRawOriginal('created_at') ?? $session->created_at);
                $last = TrackerTime::toUtc($session->getRawOriginal('last_active_at') ?? $session->last_active_at) ?? $created;

                if ($created !== null && ($createdAt === null || $created->lt($createdAt))) {
                    $createdAt = $created;
                }

                if ($last !== null && ($lastActiveAt === null || $last->gt($lastActiveAt))) {
                    $lastActiveAt = $last;
                }
            }
        }

        if ($createdAt === null || $lastActiveAt === null) {
            return;
        }

        $duration = max(0, (int) $createdAt->diffInSeconds($lastActiveAt, true));
        $actionsCount = $actions->count();

        $keeper->refresh();

        $keeper->update([
            'created_at' => TrackerTime::formatUtc($createdAt),
            'last_active_at' => TrackerTime::formatUtc($lastActiveAt),
            'session_duration_seconds' => $duration,
            'actions_count' => $actionsCount,
            'has_add_to_cart' => $cluster->contains(fn (ActivityEcomUser $s) => (bool) $s->has_add_to_cart),
            'has_begin_checkout' => $cluster->contains(fn (ActivityEcomUser $s) => (bool) $s->has_begin_checkout),
            'has_proceed_checkout' => $cluster->contains(fn (ActivityEcomUser $s) => (bool) $s->has_proceed_checkout),
            'has_payment_success' => $cluster->contains(fn (ActivityEcomUser $s) => (bool) $s->has_payment_success),
            'is_logged_in' => $cluster->contains(fn (ActivityEcomUser $s) => (bool) $s->is_logged_in),
            'updated_at' => TrackerTime::formatUtc($lastActiveAt),
        ]);
    }

    private function actionActivityAt(ActivityEcomUserAction $action): Carbon
    {
        $fromEvent = TrackerSessionClock::activityAt([
            'end_time' => $action->end_time,
            'created_at' => $action->created_at,
            'start_time' => $action->start_time,
        ]);

        return $fromEvent ?? TrackerTime::toUtc($action->created_at) ?? TrackerTime::nowUtc();
    }

    /**
     * Recount actions per session from activity_ecom_user_actions into actions_count.
     *
     * @return array{scanned: int, updated: int, skipped: bool}
     */
    public function backfillSessionActionsCounts(?Carbon $before = null, bool $dryRun = false, int $chunkSize = 1000): array
    {
        if (! Schema::hasColumn('activity_ecom_user', 'actions_count')) {
            return [
                'scanned' => 0,
                'updated' => 0,
                'skipped' => true,
            ];
        }

        $lastId = 0;
        $scanned = 0;
        $updated = 0;

        do {
            $query = ActivityEcomUser::query()
                ->where('id', '>', $lastId)
                ->orderBy('id')
                ->limit($chunkSize);

            if ($before !== null) {
                $query->where('created_at', '<=', TrackerTime::formatUtc($before));
            }

            $ids = $query->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $scanned += $ids->count();

            if (! $dryRun) {
                $idList = $ids->implode(',');

                DB::statement(<<<SQL
UPDATE activity_ecom_user u
SET actions_count = (
    SELECT COUNT(*)
    FROM activity_ecom_user_actions a
    WHERE a.session_id = u.session_id
)
WHERE u.id IN ({$idList})
SQL);

                $updated += $ids->count();
            }

            $lastId = (int) $ids->last();
        } while ($ids->count() === $chunkSize);

        return [
            'scanned' => $scanned,
            'updated' => $updated,
            'skipped' => false,
        ];
    }

    private function isPaymentOnlySession(ActivityEcomUser $session): bool
    {
        if (($session->actions_count ?? 0) !== 1) {
            return false;
        }

        $action = $session->relationLoaded('actions')
            ? $session->actions->first()
            : $session->actions()->first(['id', 'session_id', 'action_type']);

        return $action !== null && $action->action_type === 'payment_success';
    }

    private function paymentSuccessOrderId(ActivityEcomUserAction $action): string
    {
        $payload = is_array($action->payment_success) ? $action->payment_success : [];

        $orderId = trim((string) ($payload['order_id'] ?? ''));

        if ($orderId !== '') {
            return $orderId;
        }

        $checkoutInfo = $payload['checkout_info'] ?? [];

        if (! is_array($checkoutInfo)) {
            return '';
        }

        return trim((string) ($checkoutInfo['order_number'] ?? $checkoutInfo['order_pk'] ?? ''));
    }
}
