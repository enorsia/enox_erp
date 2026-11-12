<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackingDailyAudience;
use App\Models\TrackingDailyDuration;
use App\Models\TrackingOrderAddress;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerActionSequence;
use App\Support\TrackerCategoryIdentity;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies closed sessions from activity_ecom_user (+ actions) into tracking_* tables.
 *
 * Flow: main visitor (visitor_id) → daily visitor (calendar day) → session (session_id)
 * → session details (ip, device, browser, …) → action dimensions → tracking_session_p_cat
 * → dashboard tables (daily counters, orders, recoverable sales); touched days are recalculated after each batch.
 *
 * activity_ecom_user.is_sync: 0 = pending, 1 = done, 2 = failed (retry until sync_try >= MAX).
 * Action rows use the same flags; only ids loaded in the batch are marked done.
 *
 * Ingest safety: never DELETE or TRUNCATE activity_ecom_user / activity_ecom_user_actions /
 * activity_ecom_user_bot_context. Invalid or missing visitor_id sessions are skipped (not pruned).
 * Use tracker:cleanup-historical-data only when you intend to remove rows (requires --force).
 */
class TrackerDashboardSyncService
{
    private const MAX_SYNC_TRY = 5;

    private const FAILED_RETRY_COOLDOWN_MINUTES = 5;

    private const NO_DEPARTMENT_LABEL = '(no department)';

    /**
     * activity_ecom_user_actions.action_type => JSON column name on the same row.
     *
     * @var array<string, string>
     */
    private const ACTION_JSON_COLUMNS = [
        'add_to_cart' => 'add_to_cart',
        'begin_checkout' => 'begin_checkout',
        'proceed_checkout' => 'proceed_to_checkout',
        'proceed_to_checkout' => 'proceed_to_checkout', // alias if action_type matches JSON key name
        'payment_success' => 'payment_success',
    ];

    /**
     * Action counters added per synced action (table => unique key columns + counter columns).
     * Only actions on the session's own visit day count (the master dashboard needs both in the range).
     * Session totals, funnel sessions, durations and sold figures are recalculated per day instead (refreshDay).
     * Audience views / cart funnel columns are only written in refreshProductFunnelCounts (not per synced action).
     */
    private const ACTION_COUNTERS = [
        'tracking_daily_summaries' => [
            'unique' => ['metric_date'],
            'counters' => ['category_views', 'product_views'],
        ],
        'tracking_daily_categories' => [
            'unique' => ['metric_date', 'tracking_category_id'],
            'counters' => ['category_views', 'product_views', 'add_to_carts', 'proceed_checkouts'],
        ],
        'tracking_daily_products' => [
            'unique' => ['metric_date', 'product_code'],
            'counters' => ['product_views', 'add_to_carts', 'begin_checkouts', 'proceed_checkouts'],
        ],
        'tracking_daily_audiences' => [
            'unique' => ['metric_date', 'type', 'dimension_id', 'source', 'medium'],
            'counters' => ['views', 'add_to_carts', 'begin_checkouts', 'proceed_checkouts'],
        ],
    ];

    /** @var array<string, int|null> */
    private array $cache = [];

    /** @var array<string, true> metric dates whose daily totals are recalculated after the batch */
    private array $touchedDates = [];

    /**
     * Product-performance units for the day being refreshed, split by store-local hour.
     * Day totals match SUM of tracking_daily_products. A session's view/add/begin/proceed
     * lands on the hour of its last action that day so the hours still add up to the day.
     *
     * @var array<int, array{views: int, adds: int, begins: int, proceeds: int, paid: int, sale: float}>
     */
    private array $productJourneyByHour = [];

    /**
     * Category views that category performance counts: one per session per category.
     * The hour is the hour of the action that counted, so the hours add up to the day.
     *
     * @var array<int, int>
     */
    private array $categoryViewsByHour = [];

    /**
     * PLANNER: pending/failed activity_ecom_user_actions → session_id → activity_ecom_user.id chunks.
     *
     * @return list<list<int>>
     */
    public function planChunks(int $chunkSize = 25): array
    {
        try {
            $this->requeueUsersWithUnsyncedActions();

            $ids = $this->pluckPlannerUserIdsFromPendingActions();

            if ($ids === []) {
                return [];
            }

            return collect($ids)
                ->chunk(max(1, $chunkSize))
                ->map(fn ($chunk) => $chunk->values()->all())
                ->values()
                ->all();
        } catch (Throwable $e) {
            Log::error('tracker.dashboard.sync: planChunks failed', [
                'step' => 'planChunks',
                'chunk_size' => $chunkSize,
                'exception' => $e,
            ]);

            throw $e;
        }
    }

    /**
     * CHUNK JOB: sync ONLY these session ids (same filters as the queue, so a retry is safe).
     *
     * @param  list<int>  $ids
     */
    /**
     * Push one storefront session into tracking_* (same work as a sync chunk of size 1).
     */
    public function syncPendingBySessionId(string $sessionId): void
    {
        $activityUserId = (int) TrackerDatabase::table('activity_ecom_user')
            ->where('session_id', $sessionId)
            ->value('id');

        if ($activityUserId <= 0) {
            return;
        }

        $this->syncSessionIds([$activityUserId]);
    }

    public function syncSessionIds(array $ids): void
    {
        $this->cache = [];
        $this->touchedDates = [];

        if ($ids === []) {
            return;
        }

        $sessions = $this->fetchPendingSessions(0, count($ids), $ids);

        if ($sessions->isEmpty()) {
            Log::debug('tracker.dashboard.sync: chunk empty (already synced?)', ['ids' => count($ids)]);

            return;
        }

        $this->syncSessions($sessions);
    }

    /**
     * Old loop style (tinker, tests, manual runs).
     *
     * @return int|null Next activity_ecom_user id cursor, or null when no more work.
     */
    public function processBatch(?int $afterId = null, int $batchSize = 25): ?int
    {
        $this->cache = [];
        $this->touchedDates = [];
        $limit = max(1, $batchSize);
        $afterId = max(0, (int) $afterId);

        $sessions = $this->fetchPendingSessions($afterId, $limit);

        if ($sessions->isEmpty()) {
            Log::debug('tracker.dashboard.sync: batch empty', ['after_id' => $afterId]);

            return null;
        }

        $this->syncSessions($sessions);

        return $sessions->count() === $limit ? (int) $sessions->last()->id : null;
    }

    /**
     * @param  Collection<int, object>  $sessions
     */
    private function syncSessions(Collection $sessions): void
    {
        $sessionIds = $sessions->pluck('session_id')->filter()->values()->all();
        $actionsBySession = $this->loadUnsyncedActionsForSessions($sessionIds);

        $ok = 0;
        $failed = 0;

        foreach ($sessions as $row) {
            $sessionRow = (array) $row;
            $rawSessionId = (string) ($sessionRow['session_id'] ?? '');
            $actions = $actionsBySession->get($rawSessionId, collect());

            if ($this->syncOneSession($sessionRow, $actions)) {
                $ok++;
            } else {
                $failed++;
            }
        }

        $this->refreshTouchedDays();

        Log::info('tracker.dashboard.sync: batch done', [
            'first_id' => (int) $sessions->first()->id,
            'last_id' => (int) $sessions->last()->id,
            'ok' => $ok,
            'failed' => $failed,
            'count' => $sessions->count(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function syncOneSession(array $sessionRow, Collection $actions): bool
    {
        $sessionPk = (int) $sessionRow['id'];
        $rawSessionId = (string) ($sessionRow['session_id'] ?? '');
        $visitorId = trim((string) ($sessionRow['visitor_id'] ?? ''));
        if ($visitorId === '') {
            Log::debug('tracker.dashboard.sync: skip session without visitor_id', [
                'activity_ecom_user_id' => $sessionPk,
                'session_id' => $rawSessionId,
            ]);

            return true;
        }

        $cacheBefore = $this->cache;
        $sessionUpdatedAt = $sessionRow['updated_at'] ?? null;

        try {
            DB::transaction(function () use ($sessionRow, $actions, $sessionPk, $rawSessionId, $cacheBefore, $sessionUpdatedAt): void {
                // Restored on each attempt so rolled-back dimension ids are not reused after deadlock.
                $this->cache = $cacheBefore;
                $sessionRow = $this->upsertTrackingFromSession($sessionRow, $actions);
                // Loaded actions are counted into the daily tables now, so they must be done even if the
                // session row changed meanwhile (it stays pending and only its new actions are synced next time).
                $this->markActionsSynced($actions);
                $this->markSessionSynced($sessionPk, $sessionUpdatedAt, $sessionRow);
                // After markSessionSynced (matches updated_at). Refreshing actions_count first used to bump
                // updated_at and leave the session stuck pending while the new payment_success was already synced.
                $this->refreshTrackingSessionActionsCount($rawSessionId);
            }, 3);

            return true;
        } catch (Throwable $e) {
            $this->cache = $cacheBefore;

            Log::error('tracker.dashboard.sync: session failed', [
                'step' => 'syncOneSession',
                'activity_ecom_user_id' => $sessionPk,
                'session_id' => $rawSessionId,
                'exception' => $e,
            ]);

            try {
                $this->markSessionFailed($sessionPk);
                $this->markActionsFailed($actions);
            } catch (Throwable $inner) {
                Log::error('tracker.dashboard.sync: could not mark session failed', [
                    'step' => 'syncOneSession.markFailed',
                    'activity_ecom_user_id' => $sessionPk,
                    'exception' => $inner,
                ]);
            }

            return false;
        }
    }

    // ===== 1. Queue =====

    private function fetchPendingSessions(int $afterId, int $limit, ?array $onlyIds = null): Collection
    {
        $retryAfter = now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES);

        $query = TrackerDatabase::table('activity_ecom_user as u')
            ->select('u.*')
            ->where('u.id', '>', $afterId);

        // Fan-out: a chunk job passes its own ids, so it only works on those sessions.
        if ($onlyIds !== null) {
            $query->whereIn('u.id', $onlyIds);
        }

        $query->where('u.sync_try', '<', self::MAX_SYNC_TRY);
        $this->applySyncableSessionFilter($query, 'u');

        // Planner queue: only sessions with pending actions. Chunk / explicit ids: always allow (rebuild visit day, etc.).
        if ($onlyIds === null) {
            $this->applyHasUnsyncedActionsFilter($query, 'u');
            $this->applyPendingOrRetryFilter($query, $retryAfter, 'u');
        }

        return $query->orderBy('u.id')->limit($limit)->get();
    }

    /** Session marked done but new/changed actions still pending — put user back on the sync queue. */
    private function requeueUsersWithUnsyncedActions(): void
    {
        $updated = TrackerDatabase::table('activity_ecom_user as u')
            ->where('u.sync_try', '<', self::MAX_SYNC_TRY)
            ->where('u.is_sync', ActivityEcomUser::SYNC_DONE)
            ->whereNotNull('u.visitor_id')
            ->where('u.visitor_id', '!=', '')
            ->whereExists(function (Builder $exists): void {
                $exists->selectRaw('1')
                    ->from('activity_ecom_user_actions as a')
                    ->whereColumn('a.session_id', 'u.session_id')
                    ->whereIn('a.is_sync', [
                        ActivityEcomUserAction::SYNC_PENDING,
                        ActivityEcomUserAction::SYNC_FAILED,
                    ]);
            })
            ->update([
                'is_sync' => ActivityEcomUser::SYNC_PENDING,
                'updated_at' => now(),
            ]);

        if ($updated > 0) {
            Log::info('tracker.dashboard.sync: requeued users with unsynced actions', [
                'count' => $updated,
            ]);
        }
    }

    /**
     * activity_ecom_user rows that never got a tracking_session row (e.g. zero-action sessions finalized earlier).
     */
    private function requeueSessionsMissingTrackingRow(): void
    {
        $ids = TrackerDatabase::table('activity_ecom_user as u')
            ->where('u.sync_try', '<', self::MAX_SYNC_TRY)
            ->whereNotNull('u.visitor_id')
            ->where('u.visitor_id', '!=', '')
            ->whereNotExists(function (Builder $exists): void {
                $exists->selectRaw('1')
                    ->from('tracking_session as ts')
                    ->whereColumn('ts.session_id', 'u.session_id');
            })
            ->pluck('u.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($ids === []) {
            return;
        }

        TrackerDatabase::table('activity_ecom_user')
            ->whereIn('id', $ids)
            ->update([
                'is_sync' => ActivityEcomUser::SYNC_PENDING,
                'updated_at' => now(),
            ]);

        Log::info('tracker.dashboard.sync: requeued sessions missing tracking_session', [
            'count' => count($ids),
        ]);
    }

    /**
     * Pending/failed actions → session_id → syncable activity_ecom_user.id (ordered by user id).
     *
     * @return list<int>
     */
    private function pluckPlannerUserIdsFromPendingActions(): array
    {
        $sessionIds = TrackerDatabase::table('activity_ecom_user_actions')
            ->whereIn('is_sync', [
                ActivityEcomUserAction::SYNC_PENDING,
                ActivityEcomUserAction::SYNC_FAILED,
            ])
            ->distinct()
            ->pluck('session_id');

        $fromActions = [];
        if ($sessionIds->isNotEmpty()) {
            $query = TrackerDatabase::table('activity_ecom_user as u')
                ->whereIn('u.session_id', $sessionIds)
                ->where('u.sync_try', '<', self::MAX_SYNC_TRY);

            $this->applySyncableSessionFilter($query, 'u');

            $fromActions = $query->orderBy('u.id')
                ->pluck('u.id')
                ->map(fn ($id) => (int) $id)
                ->all();
        }

        return $fromActions;
    }

    /**
     * Pending sessions with no unsynced actions still need a tracking_session row (including 0-action visits).
     * Not used by {@see planChunks()} (action-only planner); manual/tinker only.
     */
    private function finalizePendingSessionsWithoutUnsyncedActions(): void
    {
        $retryAfter = now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES);

        $query = TrackerDatabase::table('activity_ecom_user as u')
            ->select('u.*')
            ->where('u.sync_try', '<', self::MAX_SYNC_TRY);

        $this->applySyncableSessionFilter($query, 'u');
        $this->applyPendingOrRetryFilter($query, $retryAfter, 'u');
        $this->applyHasNoUnsyncedActionsFilter($query, 'u');

        $idle = $query->get();

        if ($idle->isEmpty()) {
            return;
        }

        $ok = 0;
        $failed = 0;

        foreach ($idle as $row) {
            if ($this->syncOneSession((array) $row, collect())) {
                $ok++;
            } else {
                $failed++;
            }
        }

        Log::info('tracker.dashboard.sync: materialized idle pending sessions', [
            'count' => $idle->count(),
            'ok' => $ok,
            'failed' => $failed,
        ]);
    }

    private function applySyncableSessionFilter(Builder $query, string $alias): void
    {
        $query->whereNotNull("{$alias}.visitor_id")
            ->where("{$alias}.visitor_id", '!=', '');
    }

    private function applyHasUnsyncedActionsFilter(Builder $query, string $alias): void
    {
        $query->whereExists(function (Builder $exists) use ($alias): void {
            $exists->selectRaw('1')
                ->from('activity_ecom_user_actions as a')
                ->whereColumn('a.session_id', "{$alias}.session_id")
                ->whereIn('a.is_sync', [
                    ActivityEcomUserAction::SYNC_PENDING,
                    ActivityEcomUserAction::SYNC_FAILED,
                ]);
        });
    }

    private function applyHasNoUnsyncedActionsFilter(Builder $query, string $alias): void
    {
        $query->whereNotExists(function (Builder $exists) use ($alias): void {
            $exists->selectRaw('1')
                ->from('activity_ecom_user_actions as a')
                ->whereColumn('a.session_id', "{$alias}.session_id")
                ->whereIn('a.is_sync', [
                    ActivityEcomUserAction::SYNC_PENDING,
                    ActivityEcomUserAction::SYNC_FAILED,
                ]);
        });
    }

    private function applyPendingOrRetryFilter(Builder $query, Carbon $retryAfter, string $alias): void
    {
        $query->where(function ($status) use ($retryAfter, $alias): void {
            $status->where("{$alias}.is_sync", ActivityEcomUser::SYNC_PENDING)
                ->orWhere(function ($q) use ($retryAfter, $alias): void {
                    $q->where("{$alias}.is_sync", ActivityEcomUser::SYNC_FAILED)
                        ->where(function ($cooldown) use ($retryAfter, $alias): void {
                            $cooldown->whereNull("{$alias}.sync_at")
                                ->orWhere("{$alias}.sync_at", '<', $retryAfter);
                        });
                });
        });
    }

    /**
     * --force: every session in the date window is marked pending again and replayed, pending or already done.
     * Window = session started or was last active in range ({@see TrackerTime::applySessionActivityWindow()}).
     */
    public function requeueSyncedSessionsForDashboard(Carbon $from, ?Carbon $to = null): int
    {
        $query = $this->sessionsForDashboardForceRequeueQuery($from, $to);
        $sessionIds = $query->pluck('u.session_id')
            ->map(fn ($id) => (string) $id)
            ->filter()
            ->values()
            ->all();

        if ($sessionIds === []) {
            return 0;
        }

        $now = now();
        $count = TrackerDatabase::table('activity_ecom_user')
            ->whereIn('session_id', $sessionIds)
            ->update([
                'is_sync' => ActivityEcomUser::SYNC_PENDING,
                'updated_at' => $now,
            ]);

        TrackerDatabase::table('activity_ecom_user_actions')
            ->whereIn('session_id', $sessionIds)
            ->where('is_sync', ActivityEcomUserAction::SYNC_DONE)
            ->update(['is_sync' => ActivityEcomUserAction::SYNC_PENDING]);

        return $count;
    }

    private function sessionsForDashboardForceRequeueQuery(Carbon $from, ?Carbon $to): Builder
    {
        $fromDay = $from->copy()->startOfDay();
        $toDay = ($to ?? $from)->copy()->endOfDay();

        $query = TrackerDatabase::table('activity_ecom_user as u');

        TrackerTime::applySessionActivityWindow($query, $fromDay, $toDay, 'u');

        return $query;
    }

    /**
     * @param  list<string>  $sessionIds
     * @return Collection<string, Collection<int, object>>
     */
    private function loadUnsyncedActionsForSessions(array $sessionIds): Collection
    {
        if ($sessionIds === []) {
            return collect();
        }

        return TrackerDatabase::table('activity_ecom_user_actions')
            ->whereIn('session_id', $sessionIds)
            ->whereIn('is_sync', [
                ActivityEcomUserAction::SYNC_PENDING,
                ActivityEcomUserAction::SYNC_FAILED,
            ])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get()
            ->groupBy('session_id');
    }

    // ===== 2. Visitor / session =====

    /**
     * IP / UA for tracking_session_details live on bot_context (not activity_ecom_user).
     *
     * @param  array<string, mixed>  $sessionRow
     * @return array<string, mixed>
     */
    private function enrichSessionRowWithBotContext(array $sessionRow): array
    {
        $sessionId = (string) ($sessionRow['session_id'] ?? '');

        if ($sessionId === '') {
            return $sessionRow;
        }

        $bot = TrackerDatabase::table('activity_ecom_user_bot_context')
            ->where('session_id', $sessionId)
            ->first(['client_ip', 'user_agent', 'ip_country']);

        if ($bot === null) {
            return $sessionRow;
        }

        $sessionRow['ip'] = $bot->client_ip;
        $sessionRow['user_agent'] = $bot->user_agent;
        $sessionRow['ip_country'] = $bot->ip_country;

        return $sessionRow;
    }

    /**
     * @return array<string, mixed>
     */
    private function upsertTrackingFromSession(array $sessionRow, Collection $actions): array
    {
        $sessionId = $this->normalizeSessionId((string) ($sessionRow['session_id'] ?? ''));
        $visitorId = trim((string) ($sessionRow['visitor_id'] ?? ''));

        if ($sessionId === '') {
            throw new \InvalidArgumentException('Missing session_id');
        }

        // activity_ecom_user: session + visitor/contact; activity_ecom_user_actions: every event → tracking_*.
        $allActions = $this->loadAllActionsForSession($sessionId);
        $applyActions = $actions->isNotEmpty() ? $actions : $allActions;
        $rebuildFromFullHistory = $actions->isEmpty() && $allActions->isNotEmpty();

        $sessionRow = $this->enrichSessionRowWithBotContext($sessionRow);
        $sessionRow = $this->withCheckoutCustomer($sessionRow, $allActions);
        $sessionRow = $this->mergeFunnelIntoSessionRow($sessionRow, $allActions);

        $deviceId = $this->remember('device:'.($sessionRow['device_type'] ?? ''), fn () => $this->findOrCreateDevice((string) ($sessionRow['device_type'] ?? '')));
        $browserId = $this->remember('browser:'.($sessionRow['browser'] ?? ''), fn () => $this->findOrCreateBrowser((string) ($sessionRow['browser'] ?? '')));

        $mainVisitorId = $this->findOrCreateMainVisitor($visitorId, $sessionRow);
        $previousVisitDate = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('ts.session_id', $sessionId)
            ->value('dv.visit_date');
        $visitDate = $this->visitDateForSession($sessionRow, $allActions, $sessionId);
        $dailyVisitorRowId = $this->findOrCreateDailyVisitor($mainVisitorId, $visitorId, $visitDate);

        $trackingSessionId = $this->upsertTrackingSession($sessionRow, $sessionId, $dailyVisitorRowId, $allActions);
        $this->upsertSessionDetails($trackingSessionId, $sessionRow, $allActions, $deviceId, $browserId);
        $this->applyVisitorDayTrafficBucket($visitorId, $visitDate, $trackingSessionId);
        if (filled($previousVisitDate) && (string) $previousVisitDate !== $visitDate && $visitorId !== '') {
            $this->applyVisitorDayTrafficBucket($visitorId, (string) $previousVisitDate, $trackingSessionId);
        }

        $this->syncActionsForSession($trackingSessionId, $visitDate, $applyActions, $visitorId, $rebuildFromFullHistory);
        $this->upsertRecoverableSale($trackingSessionId, $visitDate, $sessionRow, $allActions);
        $this->touchedDates[$visitDate] = true;
        if (filled($previousVisitDate) && $previousVisitDate !== $visitDate) {
            $this->touchedDates[(string) $previousVisitDate] = true;
        }

        return $sessionRow;
    }

    /**
     * @return Collection<int, object>
     */
    private function loadAllActionsForSession(string $sessionId): Collection
    {
        return TrackerDatabase::table('activity_ecom_user_actions')
            ->where('session_id', $sessionId)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $sessionRow
     * @return array<string, mixed>
     */
    private function mergeFunnelIntoSessionRow(array $sessionRow, Collection $actions): array
    {
        $hasCart = false;
        $hasBegin = false;
        $hasProceed = false;
        $hasPayment = false;

        foreach ($actions as $action) {
            match ($this->metricActionType($action)) {
                'add_to_cart' => $hasCart = true,
                'begin_checkout' => $hasBegin = true,
                'proceed_checkout' => $hasProceed = true,
                'payment_success' => $hasPayment = true,
                default => null,
            };
        }

        $sessionRow['has_add_to_cart'] = (int) $hasCart;
        $sessionRow['has_begin_checkout'] = (int) $hasBegin;
        $sessionRow['has_proceed_checkout'] = (int) $hasProceed;
        $sessionRow['has_payment_success'] = (int) $hasPayment;
        $sessionRow['latest_funnel_stage'] = match (true) {
            $hasPayment => 6,
            $hasProceed => 5,
            $hasBegin => 4,
            $hasCart => 3,
            default => null,
        };

        return $sessionRow;
    }

    /**
     * Customer typed at proceed checkout / payment success is the most accurate contact for the session.
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     * @return array<string, mixed>
     */
    private function withCheckoutCustomer(array $sessionRow, Collection $actions): array
    {
        foreach ($actions as $action) {
            $type = $this->metricActionType($action);
            if ($type !== 'proceed_checkout' && $type !== 'payment_success') {
                continue;
            }

            $json = $this->actionJsonPayload($action);
            $customer = $type === 'payment_success' ? ($json['checkout_info']['customer'] ?? null) : ($json['customer'] ?? null);
            if (! is_array($customer)) {
                continue;
            }

            $name = trim((string) ($customer['full_name'] ?? ''));
            if ($name === '') {
                $name = trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? ''));
            }

            $values = [
                'user_name' => Str::limit($name, 255, ''),
                'user_email' => Str::limit(trim((string) ($customer['email'] ?? '')), 255, ''),
                'user_phone' => Str::limit(trim((string) ($customer['phone'] ?? '')), 50, ''),
            ];

            foreach ($values as $column => $value) {
                if ($value !== '') {
                    $sessionRow[$column] = $value;
                }
            }
        }

        return $sessionRow;
    }

    // ===== 3. Actions → p_cat =====

    /**
     * @param  Collection<int, object>  $actions
     */
    private function syncActionsForSession(
        int $trackingSessionId,
        string $visitDate,
        Collection $actions,
        string $visitorId,
        bool $replaceSessionProductLinks = false,
    ): void {
        if ($replaceSessionProductLinks) {
            TrackerDatabase::table('tracking_session_p_cat')->where('tracking_session_id', $trackingSessionId)->delete();
        }

        $pCatRows = [];
        $counters = [];
        $funnelDedupe = [];

        foreach ($actions as $action) {
            $type = $this->metricActionType($action);
            $actionDate = $this->localDate($action->created_at ?? null);
            $this->touchedDates[$actionDate] = true;
            $metricHour = $this->localHour($action->created_at ?? null);
            $orderLines = [];

            $sessionId = $this->normalizeSessionId((string) ($action->session_id ?? ''));
            $this->recordFunnelAction($counters, $action, $type, $actionDate, $sessionId, $funnelDedupe);

            foreach ($this->actionCommerceLines($action) as $line) {
                $productId = $this->findOrCreateProduct(
                    $line['product_code'],
                    $line['sku'],
                    $line['product_name'],
                );
                $categoryId = $this->resolveCategoryIdFromLine($line);

                if ($productId === null) {
                    continue;
                }

                $colorId = $this->findOrCreateColor($line['color_code'], $line['color_name']);
                $sizeId = $this->findOrCreateSize($line['size_code'], $line['size_name']);

                $orderLines[] = [
                    'tracking_product_id' => $productId,
                    'tracking_category_id' => $categoryId,
                    'tracking_color_id' => $colorId,
                    'tracking_size_id' => $sizeId,
                    'item_variant' => $line['item_variant'] !== '' ? Str::limit($line['item_variant'], 100, '') : null,
                    'qty' => $line['qty'],
                    'unit_price' => $line['price'],
                    'line_total' => $line['line_total'],
                ];

                $key = implode('|', [
                    $trackingSessionId,
                    $productId,
                    $categoryId ?? 0,
                    $colorId ?? 0,
                    $sizeId ?? 0,
                ]);
                $pCatRows[$key] ??= [
                    'tracking_session_id' => $trackingSessionId,
                    'tracking_product_id' => $productId,
                    'tracking_category_id' => $categoryId,
                    'tracking_color_id' => $colorId,
                    'tracking_size_id' => $sizeId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            if ($type === 'payment_success') {
                $this->upsertOrder($trackingSessionId, $visitorId, $action, $orderLines);
            }
        }

        if ($pCatRows !== []) {
            TrackerDatabase::table('tracking_session_p_cat')->insert(array_values($pCatRows));
        }

        $this->addCounters($counters);
    }

    /**
     * @return list<array{qty: int, price: float, line_total: float, item_variant: string, product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}>
     */
    private function actionCommerceLines(object $action): array
    {
        $type = (string) ($action->action_type ?? '');
        $json = $this->actionJsonPayload($action);

        if (isset(self::ACTION_JSON_COLUMNS[$type])) {
            $items = $this->cartItemsFromPayload($type, $json);
            if ($items !== []) {
                $lines = [];
                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }
                    $line = $this->commerceLineFromItem($action, $item, $json);
                    if ($line !== null) {
                        $lines[] = $line;
                    }
                }

                if ($lines !== []) {
                    return $lines;
                }
            }
        }

        $line = $this->commerceLineFromScalars($action, $json);

        // Same fallback as the master line parser: one line for the whole cart.
        if ($type === 'add_to_cart') {
            $line['qty'] = max(1, (int) ($json['qty'] ?? 1));
            $line['line_total'] = round((float) ($json['cart_total'] ?? $line['price']), 2);
        }

        return [$line];
    }

    /**
     * One action row normally has at most one commerce JSON column populated.
     *
     * @return array<string, mixed>
     */
    private function actionJsonPayload(object $action): array
    {
        $type = (string) ($action->action_type ?? '');
        $column = self::ACTION_JSON_COLUMNS[$type] ?? null;
        if ($column === null) {
            return [];
        }

        $raw = $action->{$column} ?? null;
        if ($raw === null || $raw === '') {
            return [];
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return list<mixed>
     */
    private function cartItemsFromPayload(string $actionType, array $json): array
    {
        if ($actionType === 'payment_success') {
            $checkout = is_array($json['checkout_info'] ?? null) ? $json['checkout_info'] : [];
            $items = $checkout['items'] ?? [];
        } else {
            $items = $json['items'] ?? $json['cart_items'] ?? [];
        }

        if (! is_array($items)) {
            return [];
        }

        return array_values(array_filter($items, 'is_array'));
    }

    /**
     * @param  array<string, mixed>  $item
     * @param  array<string, mixed>  $json
     * @return array{qty: int, price: float, line_total: float, item_variant: string, product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}|null
     */
    private function commerceLineFromItem(object $action, array $item, array $json): ?array
    {
        $productCode = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
        if ($productCode === '') {
            return null;
        }

        $scalar = $this->commerceLineFromScalars($action, $json);

        $productName = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
        $sku = trim((string) ($item['sku'] ?? ''));
        $department = trim((string) ($item['department_name'] ?? ''));
        $departmentCode = trim((string) ($item['department_id'] ?? $item['department_code'] ?? ''));
        $category = trim((string) ($item['category_name'] ?? ''));
        $categoryCode = trim((string) ($item['category_code'] ?? $item['category_id'] ?? ''));
        $colorCode = trim((string) ($item['color_id'] ?? $item['product_color_id'] ?? ''));
        $colorName = trim((string) ($item['color_name'] ?? $item['general_color_name'] ?? ''));
        $sizeCode = trim((string) ($item['size_id'] ?? ''));
        $sizeName = trim((string) ($item['size_name'] ?? ''));
        $qty = max(1, (int) ($item['qty'] ?? 1));
        $price = round((float) ($item['unit_price'] ?? $item['price'] ?? 0), 2);
        $lineTotal = round((float) ($item['line_total'] ?? 0), 2);

        return [
            'qty' => $qty,
            'price' => $price,
            'line_total' => $lineTotal > 0 ? $lineTotal : round($price * $qty, 2),
            'item_variant' => trim((string) ($item['item_variant'] ?? '')),
            'product_code' => $productCode,
            'sku' => $sku !== '' ? $sku : $scalar['sku'],
            'product_name' => $productName !== '' ? $productName : $scalar['product_name'],
            'category_code' => $categoryCode !== '' ? $categoryCode : $scalar['category_code'],
            'category_name' => $category !== '' ? $category : $scalar['category_name'],
            'department_name' => $department !== '' ? $department : $scalar['department_name'],
            'department_code' => $departmentCode !== '' ? $departmentCode : $scalar['department_code'],
            'color_code' => $colorCode !== '' ? $colorCode : $scalar['color_code'],
            'color_name' => $colorName !== '' ? $colorName : $scalar['color_name'],
            'size_code' => $sizeCode !== '' ? $sizeCode : $scalar['size_code'],
            'size_name' => $sizeName !== '' ? $sizeName : $scalar['size_name'],
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{qty: int, price: float, line_total: float, item_variant: string, product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}
     */
    private function commerceLineFromScalars(object $action, array $json = []): array
    {
        $qty = max(1, (int) ($action->item_qty ?? 1));
        $price = round((float) ($action->product_price ?? 0), 2);

        return [
            'qty' => $qty,
            'price' => $price,
            'line_total' => round($price * $qty, 2),
            'item_variant' => '',
            'product_code' => trim((string) (($action->product_code ?? '') ?: ($json['product_code'] ?? ''))),
            'sku' => trim((string) (($action->sku ?? '') ?: ($json['sku'] ?? ''))),
            'product_name' => trim((string) (($action->product_name ?? '') ?: ($json['product_name'] ?? ''))),
            'category_code' => trim((string) (($action->category_code ?? '') ?: ($json['category_code'] ?? ($json['category_id'] ?? '')))),
            'category_name' => trim((string) (($action->category_name ?? '') ?: ($json['category_name'] ?? ''))),
            'department_name' => trim((string) (($action->department_name ?? '') ?: ($json['department_name'] ?? ''))),
            'department_code' => trim((string) (($action->department_id ?? '') ?: ($json['department_id'] ?? ($json['department_code'] ?? '')))),
            'color_code' => trim((string) (($action->product_color_id ?? '') ?: ($json['color_id'] ?? ''))),
            'color_name' => trim((string) (($action->general_color_name ?? '') ?: ($json['color_name'] ?? ''))),
            'size_code' => '',
            'size_name' => '',
        ];
    }

    /**
     * @param  array{category_code: string, category_name: string, department_name: string}  $line
     */
    private function resolveCategoryIdFromLine(array $line): ?int
    {
        $categoryName = trim($line['category_name']);
        $categoryCode = trim($line['category_code']);

        if ($categoryName !== '') {
            return $this->resolveCategoryId(
                $categoryCode,
                $categoryName,
                $line['department_name'],
                trim($line['department_code'] ?? ''),
            );
        }

        if ($categoryCode === '') {
            return null;
        }

        $existing = TrackerDatabase::table('tracking_category')
            ->where(function ($query) use ($categoryCode) {
                $query->where('code', $categoryCode);
                if (ctype_digit($categoryCode)) {
                    $query->orWhere('id', (int) $categoryCode);
                }
            })
            ->first();

        return $existing !== null ? (int) $existing->id : null;
    }

    /**
     * @param  array<string, mixed>  $sessionRow
     */
    private function findOrCreateMainVisitor(string $visitorId, array $sessionRow): int
    {
        $visitorId = Str::limit($visitorId, 36, '');
        $cacheKey = 'main_visitor:'.$visitorId;

        $name = $sessionRow['user_name'] ?? null;
        $email = $sessionRow['user_email'] ?? null;
        $phone = $sessionRow['user_phone'] ?? null;

        if (array_key_exists($cacheKey, $this->cache)) {
            $this->updateMainVisitorById((int) $this->cache[$cacheKey], $name, $email, $phone);

            return (int) $this->cache[$cacheKey];
        }

        $existing = TrackerDatabase::table('tracking_main_visitor')->where('visitor_id', $visitorId)->first();

        if ($existing) {
            $this->updateMainVisitorById((int) $existing->id, $name, $email, $phone, $existing);
            $this->cache[$cacheKey] = (int) $existing->id;

            return (int) $existing->id;
        }

        $now = now();

        // Parallel chunk jobs may create the same visitor_id at once; upsert is idempotent.
        TrackerDatabase::table('tracking_main_visitor')->upsert(
            [[
                'visitor_id' => $visitorId,
                'name' => $name,
                'email' => $email,
                'phone' => $phone,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['visitor_id'],
            ['updated_at'],
        );

        $id = $this->lookupRowId('tracking_main_visitor', ['visitor_id' => $visitorId], true);

        if ($id === null) {
            throw new \RuntimeException('Failed to resolve main visitor after upsert');
        }

        $this->updateMainVisitorById($id, $name, $email, $phone);
        $this->cache[$cacheKey] = $id;

        return $id;
    }

    private function updateMainVisitorById(
        int $mainVisitorId,
        mixed $name,
        mixed $email,
        mixed $phone,
        ?object $existing = null,
    ): void {
        $existing ??= TrackerDatabase::table('tracking_main_visitor')->where('id', $mainVisitorId)->first();

        if ($existing === null) {
            return;
        }

        $this->applyTableUpdates('tracking_main_visitor', $existing, [
            'name' => $name ?? $existing->name,
            'email' => $email ?? $existing->email,
            'phone' => $phone ?? $existing->phone,
        ]);
    }

    /**
     * Visit / activity-list day: earliest real activity (e.g. payment_success on 17 Oct), not a later landing_page on 21 Oct.
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function visitDateForSession(array $sessionRow, Collection $actions, string $sessionId): string
    {
        $earliestUtc = $this->earliestStoredInstant($actions, $sessionRow, $sessionId);

        if ($earliestUtc !== null) {
            return $earliestUtc->copy()->timezone(TrackerTime::timezone())->toDateString();
        }

        return $this->localDate($sessionRow['last_active_at'] ?? $sessionRow['created_at'] ?? null);
    }

    /**
     * @param  Collection<int, object>  $actions
     * @param  array<string, mixed>  $sessionRow
     */
    private function earliestStoredInstant(Collection $actions, array $sessionRow, string $sessionId): ?Carbon
    {
        $earliest = null;

        foreach ($actions as $action) {
            $earliest = $this->minUtcInstant($earliest, TrackerTime::toUtc($action->created_at ?? null));
        }

        foreach (['first_payment_at', 'created_at'] as $field) {
            $earliest = $this->minUtcInstant($earliest, TrackerTime::toUtc($sessionRow[$field] ?? null));
        }

        $existingSessionStart = TrackerDatabase::table('tracking_session')
            ->where('session_id', $sessionId)
            ->value('created_at');
        $earliest = $this->minUtcInstant($earliest, TrackerTime::toUtc($existingSessionStart));

        return $earliest;
    }

    private function minUtcInstant(?Carbon $current, ?Carbon $candidate): ?Carbon
    {
        if ($candidate === null) {
            return $current;
        }

        if ($current === null || $candidate->lessThan($current)) {
            return $candidate;
        }

        return $current;
    }

    private function localDate(mixed $at): string
    {
        return Carbon::parse($at ?? now())->timezone(TrackerTime::timezone())->toDateString();
    }

    private function localHour(mixed $at): int
    {
        return (int) Carbon::parse($at ?? now())->timezone(TrackerTime::timezone())->format('G');
    }

    private function findOrCreateDailyVisitor(int $mainVisitorId, string $visitorId, string $visitDate): int
    {
        $cacheKey = 'daily_visitor:'.$mainVisitorId.'|'.$visitDate;

        return $this->remember($cacheKey, function () use ($mainVisitorId, $visitorId, $visitDate): int {
            $where = [
                'tracking_main_visitor_id' => $mainVisitorId,
                'visit_date' => $visitDate,
            ];

            $existingId = $this->lookupRowId('tracking_daily_visitor', $where);

            if ($existingId !== null) {
                return $existingId;
            }

            $now = now();
            $dailyVisitorId = md5($visitorId.'|'.$visitDate);

            // Upsert avoids duplicate-key races when parallel chunk jobs sync the same visitor/day.
            TrackerDatabase::table('tracking_daily_visitor')->upsert(
                [[
                    'tracking_main_visitor_id' => $mainVisitorId,
                    'daily_visitor_id' => $dailyVisitorId,
                    'visit_date' => $visitDate,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]],
                ['tracking_main_visitor_id', 'visit_date'],
                ['updated_at'],
            );

            $resolvedId = $this->lookupRowId('tracking_daily_visitor', $where, true);

            if ($resolvedId === null) {
                throw new \RuntimeException('Failed to resolve daily visitor after upsert');
            }

            return $resolvedId;
        }) ?? throw new \RuntimeException('Failed to resolve daily visitor');
    }

    /**
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions  Unsynced actions included in this sync pass.
     */
    private function upsertTrackingSession(array $sessionRow, string $sessionId, int $dailyVisitorId, Collection $actions): int
    {
        $existing = TrackerDatabase::table('tracking_session')->where('session_id', $sessionId)->first();

        $payload = array_merge([
            'tracking_daily_visitor_id' => $dailyVisitorId,
            'name' => $sessionRow['user_name'] ?? $existing?->name,
            'email' => $sessionRow['user_email'] ?? $existing?->email,
            'phone' => $sessionRow['user_phone'] ?? $existing?->phone,
            'is_logged_in' => (int) (bool) ($sessionRow['is_logged_in'] ?? false),
            'last_active_at' => $sessionRow['last_active_at'] ?? null,
            'has_add_to_cart' => $this->stickyFlag($sessionRow, 'has_add_to_cart', $existing, 'has_add_to_cart'),
            'has_begin_checkout' => $this->stickyFlag($sessionRow, 'has_begin_checkout', $existing, 'has_begin_checkout'),
            'has_proceed_checkout' => $this->stickyFlag($sessionRow, 'has_proceed_checkout', $existing, 'has_proceed_checkout'),
            'has_order' => $this->stickyFlag($sessionRow, 'has_payment_success', $existing, 'has_order'),
        ], $this->mergedSessionRollups($existing, $sessionRow, $actions));

        $sessionStartedAt = TrackerTime::formatUtc($sessionRow['created_at'] ?? null) ?? now()->toDateTimeString();
        $sessionStartedAt = $this->earliestSessionTimestamp($sessionStartedAt, $actions);

        if ($existing) {
            $trackingStart = Carbon::parse($existing->created_at);
            $earliestStart = Carbon::parse($sessionStartedAt);

            if ($earliestStart->lessThan($trackingStart)) {
                $payload['created_at'] = $sessionStartedAt;
            }

            $this->applyTableUpdates('tracking_session', $existing, $payload);

            return (int) $existing->id;
        }

        $payload['session_id'] = $sessionId;
        $payload['created_at'] = $sessionStartedAt;
        $payload['updated_at'] = now();

        return $this->insertOrFetch('tracking_session', $payload, ['session_id' => $sessionId]);
    }

    /**
     * A funnel flag never goes back to 0 once a sync has seen it.
     *
     * @param  array<string, mixed>  $sessionRow
     */
    private function stickyFlag(array $sessionRow, string $sourceColumn, ?object $existing, string $column): int
    {
        return (int) ((bool) ($sessionRow[$sourceColumn] ?? false) || (bool) ($existing->{$column} ?? false));
    }

    /**
     * Roll up session metrics on each sync. actions_count is set separately via
     * {@see refreshTrackingSessionActionsCount()} (timeline-style count, same as admin session detail).
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     * @return array{duration_seconds: int, latest_funnel_stage: ?int}
     */
    private function mergedSessionRollups(?object $existing, array $sessionRow, Collection $actions): array
    {
        $incomingDuration = (int) ($sessionRow['session_duration_seconds'] ?? 0);
        $incomingFunnel = $this->funnelStageCode($sessionRow['latest_funnel_stage'] ?? null);

        if ($existing === null) {
            return [
                'duration_seconds' => $incomingDuration,
                'latest_funnel_stage' => $incomingFunnel,
            ];
        }

        return [
            'duration_seconds' => max((int) ($existing->duration_seconds ?? 0), $incomingDuration),
            'latest_funnel_stage' => $this->maxFunnelStage($existing->latest_funnel_stage ?? null, $incomingFunnel),
        ];
    }

    /**
     * @param  list<string>  $sessionIds
     */
    public function reconcileActionCountsForSessions(array $sessionIds): void
    {
        foreach ($sessionIds as $sessionId) {
            $this->refreshTrackingSessionActionsCount((string) $sessionId);
        }
    }

    /**
     * One row per session on tracking_session: timeline display action count (grid rows grouped, product_click excluded)
     * for events in the session window — same rules as admin session detail.
     */
    private function refreshTrackingSessionActionsCount(string $sessionId): void
    {
        $sessionId = trim($sessionId);

        if ($sessionId === '') {
            return;
        }

        $tracking = TrackerDatabase::table('tracking_session')
            ->where('session_id', $sessionId)
            ->first(['id', 'created_at', 'last_active_at']);

        if ($tracking === null) {
            return;
        }

        $activity = TrackerDatabase::table('activity_ecom_user')
            ->where('session_id', $sessionId)
            ->first(['created_at', 'last_active_at']);

        $windowStart = $this->sessionActionsWindowStart(
            $tracking->created_at,
            $activity?->created_at,
            $sessionId,
        );
        $windowEnd = $this->sessionActionsWindowEnd(
            $tracking->last_active_at,
            $activity?->last_active_at,
        );

        $actionsQuery = TrackerDatabase::table('activity_ecom_user_actions')
            ->where('session_id', $sessionId)
            ->where('created_at', '>=', $windowStart)
            ->orderBy('created_at')
            ->orderBy('id');

        if ($windowEnd !== null) {
            $actionsQuery->where('created_at', '<=', $windowEnd);
        }

        $count = TrackerActionSequence::displayActionCount($actionsQuery->get([
            'id',
            'action_type',
            'created_at',
            'start_time',
            'page_url',
            'category_code',
            'category_name',
            'product_code',
            'sku',
        ]));

        TrackerDatabase::table('tracking_session')
            ->where('id', $tracking->id)
            ->update([
                'actions_count' => $count,
                'updated_at' => now(),
            ]);

        TrackerDatabase::table('activity_ecom_user')
            ->where('session_id', $sessionId)
            ->update([
                'actions_count' => $count,
            ]);
    }

    /**
     * @param  Collection<int, object>  $actions
     */
    private function earliestSessionTimestamp(string $sessionStartedAt, Collection $actions): string
    {
        $earliest = Carbon::parse($sessionStartedAt);

        foreach ($actions as $action) {
            if (! filled($action->created_at ?? null)) {
                continue;
            }

            $at = Carbon::parse($action->created_at);

            if ($at->lessThan($earliest)) {
                $earliest = $at;
            }
        }

        return TrackerTime::formatUtc($earliest) ?? $sessionStartedAt;
    }

    private function sessionActionsWindowStart(
        mixed $trackingCreatedAt,
        mixed $activityCreatedAt,
        string $sessionId,
    ): string {
        $start = filled($trackingCreatedAt)
            ? Carbon::parse($trackingCreatedAt)
            : now();

        if (filled($activityCreatedAt)) {
            $activityStart = Carbon::parse($activityCreatedAt);

            if ($activityStart->lessThan($start)) {
                $start = $activityStart;
            }
        }

        $earliestActionAt = TrackerDatabase::table('activity_ecom_user_actions')
            ->where('session_id', $sessionId)
            ->min('created_at');

        if (filled($earliestActionAt)) {
            $actionStart = Carbon::parse($earliestActionAt);

            if ($actionStart->lessThan($start)) {
                $start = $actionStart;
            }
        }

        return TrackerTime::formatUtc($start) ?? $start->toDateTimeString();
    }

    private function sessionActionsWindowEnd(mixed $trackingLastActiveAt, mixed $activityLastActiveAt): ?string
    {
        $end = null;

        if (filled($trackingLastActiveAt)) {
            $end = Carbon::parse($trackingLastActiveAt);
        }

        if (filled($activityLastActiveAt)) {
            $activityEnd = Carbon::parse($activityLastActiveAt);

            if ($end === null || $activityEnd->greaterThan($end)) {
                $end = $activityEnd;
            }
        }

        if ($end === null) {
            return null;
        }

        return TrackerTime::formatUtc($end) ?? $end->toDateTimeString();
    }

    private function maxFunnelStage(mixed $current, mixed $incoming): ?int
    {
        $currentCode = $current === null || $current === '' ? null : (int) $current;
        $incomingCode = $incoming === null ? null : (int) $incoming;

        if ($currentCode === null) {
            return $incomingCode;
        }

        if ($incomingCode === null) {
            return $currentCode;
        }

        return max($currentCode, $incomingCode);
    }

    /**
     * utm_source / utm_medium store this session's own touches. The calendar-day bucket is applied after this, across the visitor's sessions that day.
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     * @return array{0: ?int, 1: string, 2: string} traffic source id, source, medium
     */
    private function upsertSessionDetails(
        int $trackingSessionId,
        array $sessionRow,
        Collection $actions,
        ?int $deviceId,
        ?int $browserId,
    ): array {
        $referer = $this->earliestRefererFromActions($actions);
        $touches = SessionTrafficAttribution::collectSessionTrafficTouches($sessionRow, $actions);

        if ($touches === []) {
            $bucket = SessionTrafficAttribution::resolvedTrafficBucket((object) $sessionRow, [''], (string) $referer);
            $touches = [[
                'source' => $bucket['source'],
                'medium' => $bucket['medium'],
                'campaign' => $sessionRow['utm_campaign'] ?? null,
                'landing_page' => $sessionRow['landing_page'] ?? null,
                'referer' => $referer,
                'captured_at' => TrackerTime::formatUtc($sessionRow['created_at'] ?? now()) ?? now()->toDateTimeString(),
            ]];
        }

        $touches = SessionTrafficAttribution::normalizeTouchesForAttributionStorage($touches);

        $touchSignature = static function (array $touch): string {
            $source = (string) ($touch['source'] ?? '');
            $medium = (string) ($touch['medium'] ?? '');
            $capturedAt = (string) ($touch['captured_at'] ?? '');

            return "{$source}|{$medium}|{$capturedAt}";
        };

        $existingTouches = TrackerDatabase::table('tracking_session_details')
            ->where('tracking_session_id', $trackingSessionId)
            ->orderBy('touch_sequence')
            ->get(['utm_source', 'utm_medium', 'captured_at']);

        $existingSignature = $existingTouches
            ->map(fn (object $row) => "{$row->utm_source}|{$row->utm_medium}|{$row->captured_at}")
            ->values()
            ->all();
        $newSignature = array_map($touchSignature, $touches);

        if ($existingSignature !== $newSignature) {
            TrackerDatabase::table('tracking_session_details')
                ->where('tracking_session_id', $trackingSessionId)
                ->delete();

            foreach ($touches as $index => $touch) {
                [$source, $medium] = $this->trafficSourceMedium($touch['source'], $touch['medium']);
                $trafficId = $this->remember("traffic:{$source}|{$medium}", fn () => $this->findOrCreateTrafficSource($source, $medium));

                try {
                    TrackerDatabase::table('tracking_session_details')->insert([
                        'tracking_session_id' => $trackingSessionId,
                        'touch_sequence' => $index + 1,
                        'captured_at' => $touch['captured_at'],
                        'ip' => $sessionRow['ip'] ?? null,
                        'tracking_device_id' => $deviceId,
                        'tracking_browser_id' => $browserId,
                        'tracking_traffic_source_id' => $trafficId,
                        'utm_source' => $source,
                        'utm_medium' => $medium,
                        'utm_campaign' => $touch['campaign'] ?? null,
                        'landing_page' => $touch['landing_page'] ?? null,
                        'referer' => $touch['referer'] ?? $referer,
                        'user_agent' => $sessionRow['user_agent'] ?? null,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } catch (QueryException $e) {
                    Log::debug('tracker.dashboard.sync: touch_sequence insert race', [
                        'step' => 'tracking_session_details.insert',
                        'tracking_session_id' => $trackingSessionId,
                        'exception' => $e,
                    ]);
                }
            }
        }

        $latest = TrackerDatabase::table('tracking_session_details')
            ->where('tracking_session_id', $trackingSessionId)
            ->orderByDesc('touch_sequence')
            ->first();

        if ($latest === null) {
            return [null, '(direct)', 'none'];
        }

        $trafficId = $latest->tracking_traffic_source_id !== null ? (int) $latest->tracking_traffic_source_id : null;

        return [
            $trafficId,
            (string) $latest->utm_source,
            (string) $latest->utm_medium,
        ];
    }

    /**
     * One source and one medium for this visitor on this calendar day, written onto every session that day.
     * Direct and none do not count. The next calendar day is resolved on its own.
     */
    private function applyVisitorDayTrafficBucket(string $visitorId, string $visitDate, int $trackingSessionId): void
    {
        $visitorId = trim($visitorId);

        $query = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('dv.visit_date', $visitDate);

        if ($visitorId === '') {
            $query->where('ts.id', $trackingSessionId);
        } else {
            $mainId = TrackerDatabase::table('tracking_main_visitor')
                ->where('visitor_id', Str::limit($visitorId, 36, ''))
                ->value('id');

            if ($mainId === null) {
                $query->where('ts.id', $trackingSessionId);
            } else {
                $query->where('dv.tracking_main_visitor_id', (int) $mainId);
            }
        }

        $sessions = $query->get(['ts.id as tracking_session_id', 'ts.session_id']);
        if ($sessions->isEmpty()) {
            return;
        }

        $sessionIds = $sessions->pluck('session_id')->map(fn ($id) => (string) $id)->all();
        $activityRows = TrackerDatabase::table('activity_ecom_user')
            ->whereIn('session_id', $sessionIds)
            ->get(['session_id', 'landing_page', 'created_at'])
            ->keyBy('session_id');
        $actions = TrackerDatabase::table('activity_ecom_user_actions')
            ->whereIn('session_id', $sessionIds)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['session_id', 'id', 'page_url', 'referer', 'created_at'])
            ->groupBy('session_id');

        $touches = [];
        foreach ($sessions as $session) {
            $activity = $activityRows->get($session->session_id);
            if ($activity === null) {
                continue;
            }

            $sessionActions = $actions->get($session->session_id, collect());
            $sessionTouches = SessionTrafficAttribution::normalizeTouchesForAttributionStorage(
                SessionTrafficAttribution::collectSessionTrafficTouches((array) $activity, $sessionActions),
            );
            if ($sessionTouches === []) {
                $referer = (string) ($sessionActions->first(fn (object $action): bool => filled($action->referer ?? null))?->referer ?? '');
                $sessionTouches = [SessionTrafficAttribution::resolvedTrafficBucket($activity, [''], $referer)];
            }

            foreach ($sessionTouches as $touch) {
                $touches[] = $touch;
            }
        }

        $bucket = SessionTrafficAttribution::resolveVisitorDayBucket($touches);
        [$source, $medium] = $this->trafficSourceMedium($bucket['source'], $bucket['medium']);
        $trafficId = $this->findOrCreateTrafficSource($source, $medium);
        $trackingIds = $sessions->pluck('tracking_session_id')->map(fn ($id) => (int) $id)->all();

        TrackerDatabase::table('tracking_session_details')
            ->whereIn('tracking_session_id', $trackingIds)
            ->where(function (Builder $details) use ($source, $medium, $trafficId): void {
                $details->where('utm_source', '!=', $source)
                    ->orWhere('utm_medium', '!=', $medium)
                    ->orWhere('tracking_traffic_source_id', '!=', $trafficId)
                    ->orWhereNull('tracking_traffic_source_id');
            })
            ->update([
                'tracking_traffic_source_id' => $trafficId,
                'utm_source' => $source,
                'utm_medium' => $medium,
                'updated_at' => now(),
            ]);
    }

    // ===== 4. Lookup tables =====

    private function findOrCreateDevice(string $name): ?int
    {
        $name = $this->deviceBrowserName($name);

        return $this->insertOrFetch('tracking_device', [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['name' => $name]);
    }

    private function findOrCreateBrowser(string $name): ?int
    {
        $name = $this->deviceBrowserName($name);

        return $this->insertOrFetch('tracking_browser', [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['name' => $name]);
    }

    /** Blank and any spelling of unknown are one Unknown row. */
    private function deviceBrowserName(string $name): string
    {
        $name = trim($name);
        if ($name === '' || strcasecmp($name, 'unknown') === 0) {
            $name = 'Unknown';
        }

        return Str::limit($name, 50, '');
    }

    /**
     * @return array{0: string, 1: string} source and medium with the "(direct)" / "none" defaults
     */
    private function trafficSourceMedium(?string $source, ?string $medium): array
    {
        $source = trim((string) $source);
        $medium = trim((string) $medium);
        $source = SessionTrafficAttribution::normalizeSource($source !== '' ? $source : '(direct)') ?? '(direct)';

        return [
            Str::limit($source, 100, ''),
            Str::limit($medium !== '' ? $medium : 'none', 100, ''),
        ];
    }

    private function findOrCreateTrafficSource(string $source, string $medium): ?int
    {
        [$source, $medium] = $this->trafficSourceMedium($source, $medium);

        $existing = TrackerDatabase::table('tracking_traffic_source')->where('name', $source)->first();
        if ($existing !== null && (string) ($existing->medium ?? '') !== $medium && $medium !== SessionTrafficAttribution::MULTI_MEDIUM) {
            TrackerDatabase::table('tracking_traffic_source')->where('id', $existing->id)->update([
                'medium' => $medium,
                'updated_at' => now(),
            ]);
        }

        return $this->insertOrFetch('tracking_traffic_source', [
            'name' => $source,
            'medium' => $medium,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['name' => $source]);
    }

    /** Code as stored in tracking_product.code (also the tracking_daily_products key). */
    private function productCode(string $code): string
    {
        return Str::limit(trim($code), 100, '');
    }

    private function findOrCreateProduct(string $code, string $sku, string $title): ?int
    {
        $code = $this->productCode($code);
        if ($code === '') {
            return null;
        }

        $skuVal = $sku !== '' ? Str::limit($sku, 100, '') : null;
        $titleVal = $title !== '' ? Str::limit($title, 500, '') : null;
        $where = $this->productIdentityWhere($code, $skuVal);
        $cacheKey = 'product:'.$code.'|'.($skuVal ?? '');

        return $this->remember($cacheKey, function () use ($code, $skuVal, $titleVal, $where): int {
            $existing = $this->lookupProductRow($code, $skuVal);

            if ($existing !== null) {
                $updates = $this->diffUpdates((array) $existing, [
                    'title' => $titleVal ?? $existing->title,
                ]);
                if ($updates !== []) {
                    $updates['updated_at'] = now();
                    TrackerDatabase::table('tracking_product')->where('id', $existing->id)->update($updates);
                }

                return (int) $existing->id;
            }

            return $this->insertOrFetch('tracking_product', [
                'code' => $code,
                'sku' => $skuVal,
                'title' => $titleVal,
                'created_at' => now(),
                'updated_at' => now(),
            ], $where);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function productIdentityWhere(string $code, ?string $sku): array
    {
        return [
            'code' => $code,
            'sku' => $sku,
        ];
    }

    private function lookupProductRow(string $code, ?string $sku): ?object
    {
        return $this->lookupRowQuery('tracking_product', $this->productIdentityWhere($code, $sku))->first();
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function lookupRowQuery(string $table, array $where): Builder
    {
        $query = TrackerDatabase::table($table);

        foreach ($where as $column => $value) {
            if ($value === null) {
                $query->whereNull($column);
            } else {
                $query->where($column, $value);
            }
        }

        return $query;
    }

    private function findOrCreateColor(string $code, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $nameVal = Str::limit($name, 255, '');
        $codeVal = trim($code) !== '' ? Str::limit(trim($code), 100, '') : null;
        $cacheKey = 'color:'.$nameVal;

        return $this->remember($cacheKey, function () use ($codeVal, $nameVal): int {
            $existing = TrackerDatabase::table('tracking_color')->where('name', $nameVal)->first();
            if ($existing !== null && $codeVal !== null && (string) ($existing->code ?? '') !== $codeVal) {
                TrackerDatabase::table('tracking_color')->where('id', $existing->id)->update([
                    'code' => $codeVal,
                    'updated_at' => now(),
                ]);
            }

            return $this->insertOrFetch('tracking_color', [
                'code' => $codeVal,
                'name' => $nameVal,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['name' => $nameVal]);
        });
    }

    private function findOrCreateSize(string $code, string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $nameVal = Str::limit($name, 255, '');
        $codeVal = trim($code) !== '' ? Str::limit(trim($code), 100, '') : null;
        $cacheKey = 'size:'.$nameVal;

        return $this->remember($cacheKey, function () use ($codeVal, $nameVal): int {
            $existing = TrackerDatabase::table('tracking_size')->where('name', $nameVal)->first();
            if ($existing !== null && $codeVal !== null && (string) ($existing->code ?? '') !== $codeVal) {
                TrackerDatabase::table('tracking_size')->where('id', $existing->id)->update([
                    'code' => $codeVal,
                    'updated_at' => now(),
                ]);
            }

            return $this->insertOrFetch('tracking_size', [
                'code' => $codeVal,
                'name' => $nameVal,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['name' => $nameVal]);
        });
    }

    private function normalizeDepartmentName(string $departmentName): string
    {
        $departmentName = trim($departmentName);

        return $departmentName !== '' ? $departmentName : self::NO_DEPARTMENT_LABEL;
    }

    private function findOrCreateDepartment(string $departmentName, string $departmentCode = ''): int
    {
        $departmentName = Str::limit($this->normalizeDepartmentName($departmentName), 255, '');
        $codeVal = trim($departmentCode) !== '' ? Str::limit(trim($departmentCode), 100, '') : null;
        $cacheKey = 'department:'.$departmentName;

        return $this->remember($cacheKey, function () use ($departmentName, $codeVal): int {
            $existing = TrackerDatabase::table('tracking_category')
                ->whereNull('parent_id')
                ->where('name', $departmentName)
                ->first();

            if ($existing !== null) {
                if ($codeVal !== null && (string) ($existing->code ?? '') !== $codeVal) {
                    TrackerDatabase::table('tracking_category')->where('id', $existing->id)->update([
                        'code' => $codeVal,
                        'updated_at' => now(),
                    ]);
                }

                return (int) $existing->id;
            }

            return $this->insertOrFetch('tracking_category', [
                'parent_id' => null,
                'code' => $codeVal,
                'name' => $departmentName,
                'created_at' => now(),
                'updated_at' => now(),
            ], [
                'parent_id' => null,
                'name' => $departmentName,
            ]);
        }) ?? throw new \RuntimeException('Failed to resolve department');
    }

    private function resolveCategoryId(string $code, string $categoryName, string $departmentName, string $departmentCode = ''): ?int
    {
        $categoryName = Str::limit(trim($categoryName), 255, '');
        if ($categoryName === '') {
            return null;
        }

        $codeVal = trim($code) !== '' ? Str::limit(trim($code), 100, '') : null;
        $departmentId = $this->findOrCreateDepartment($departmentName, $departmentCode);

        return $this->findOrCreateCategoryUnderParent($departmentId, $codeVal, $categoryName);
    }

    private function findOrCreateCategoryUnderParent(?int $parentId, ?string $code, string $name): int
    {
        $name = Str::limit(trim($name), 255, '');
        $cacheKey = 'category:'.($parentId ?? 0).'|'.$name;

        return $this->remember($cacheKey, function () use ($parentId, $name, $code): int {
            $existing = TrackerDatabase::table('tracking_category')
                ->where('parent_id', $parentId)
                ->where('name', $name)
                ->first();

            if ($existing !== null) {
                if ($code !== null && (string) ($existing->code ?? '') !== $code) {
                    TrackerDatabase::table('tracking_category')->where('id', $existing->id)->update([
                        'code' => $code,
                        'updated_at' => now(),
                    ]);
                }

                return (int) $existing->id;
            }

            return $this->insertOrFetch('tracking_category', [
                'parent_id' => $parentId,
                'code' => $code,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ], [
                'parent_id' => $parentId,
                'name' => $name,
            ]);
        }) ?? throw new \RuntimeException('Failed to resolve category');
    }

    /**
     * @param  Collection<int, object>  $actions
     */
    private function earliestRefererFromActions(Collection $actions): ?string
    {
        $referer = null;
        $minId = PHP_INT_MAX;

        foreach ($actions as $action) {
            $candidate = trim((string) ($action->referer ?? ''));
            if ($candidate === '') {
                continue;
            }

            $id = (int) ($action->id ?? PHP_INT_MAX);
            if ($id < $minId) {
                $minId = $id;
                $referer = Str::limit($candidate, 2048, '');
            }
        }

        return $referer;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function insertOrFetch(string $table, array $row, array $where): int
    {
        $existingId = $this->lookupRowId($table, $where, true);

        if ($existingId !== null) {
            return $existingId;
        }

        $uniqueBy = array_keys($where);

        if ($uniqueBy !== []) {
            $row['created_at'] = $row['created_at'] ?? now();
            $row['updated_at'] = $row['updated_at'] ?? now();

            $updateColumns = array_values(array_filter(
                array_keys($row),
                static fn (string $column): bool => ! in_array($column, [...$uniqueBy, 'created_at'], true),
            ));

            if ($updateColumns === []) {
                $updateColumns = ['updated_at'];
            }

            TrackerDatabase::table($table)->upsert([$row], $uniqueBy, $updateColumns);

            $resolvedId = $this->lookupRowId($table, $where, true);

            if ($resolvedId !== null) {
                return $resolvedId;
            }
        }

        try {
            return (int) TrackerDatabase::table($table)->insertGetId($row);
        } catch (QueryException $exception) {
            if (! $this->isUniqueConstraintViolation($exception)) {
                throw $exception;
            }

            for ($attempt = 0; $attempt < 5; $attempt++) {
                $existingId = $this->lookupRowId($table, $where, true);

                if ($existingId !== null) {
                    return $existingId;
                }

                usleep(20_000);
            }

            throw $exception;
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        if ($exception instanceof UniqueConstraintViolationException) {
            return true;
        }

        return (int) ($exception->errorInfo[1] ?? 0) === 1062;
    }

    /**
     * @param  array<string, mixed>  $where
     */
    /**
     * @param  array<string, mixed>  $where
     */
    private function lookupRowId(string $table, array $where, bool $useWritePdo = false): ?int
    {
        $query = $this->lookupRowQuery($table, $where);

        if ($useWritePdo) {
            $query->useWritePdo();
        }

        $id = $query->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function remember(string $key, Closure $resolve): ?int
    {
        if (array_key_exists($key, $this->cache)) {
            return $this->cache[$key];
        }

        $value = $resolve();

        $this->cache[$key] = $value;

        return $value;
    }

    /**
     * @param  array<string, mixed>  $desired
     */
    private function applyTableUpdates(string $table, object $existing, array $desired): void
    {
        $updates = $this->diffUpdates((array) $existing, $desired);

        if ($updates === []) {
            return;
        }

        $updates['updated_at'] = now();
        TrackerDatabase::table($table)->where('id', $existing->id)->update($updates);
    }

    /**
     * @param  array<string, mixed>  $existing
     * @param  array<string, mixed>  $desired
     * @return array<string, mixed>
     */
    private function diffUpdates(array $existing, array $desired): array
    {
        $updates = [];

        foreach ($desired as $column => $value) {
            if (! array_key_exists($column, $existing)) {
                continue;
            }

            if ($this->diffValue($existing[$column]) !== $this->diffValue($value)) {
                $updates[$column] = $value;
            }
        }

        return $updates;
    }

    private function diffValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return (string) (int) $value;
        }

        return (string) $value;
    }

    private function normalizeSessionId(string $sessionId): string
    {
        return Str::limit(trim($sessionId), 36, '');
    }

    private function funnelStageCode(mixed $stage): ?int
    {
        if ($stage === null || $stage === '') {
            return null;
        }

        if (is_numeric($stage)) {
            return (int) $stage;
        }

        return match (strtolower((string) $stage)) {
            'category_view', 'view' => 1,
            'product_view' => 2,
            'add_to_cart' => 3,
            'begin_checkout' => 4,
            'proceed_checkout' => 5,
            'payment_success' => 6,
            default => null,
        };
    }

    // ===== 5. Dashboard tables =====

    private function metricActionType(object $action): string
    {
        $type = (string) ($action->action_type ?? '');

        return match ($type) {
            'proceed_to_checkout' => 'proceed_checkout',
            'product_view_popup' => 'product_view',
            default => $type,
        };
    }

    /**
     * @return list<array{type: int, dimension_id: int, source: string, medium: string}>
     */
    private function audienceKeys(?int $deviceId, ?int $browserId, string $source, string $medium): array
    {
        return [
            ['type' => TrackingDailyAudience::TYPE_DEVICE, 'dimension_id' => $deviceId ?: (int) $this->findOrCreateDevice('Unknown'), 'source' => '', 'medium' => ''],
            ['type' => TrackingDailyAudience::TYPE_BROWSER, 'dimension_id' => $browserId ?: (int) $this->findOrCreateBrowser('Unknown'), 'source' => '', 'medium' => ''],
            ['type' => TrackingDailyAudience::TYPE_TRAFFIC_SOURCE, 'dimension_id' => $this->trafficDimensionId($source, $medium), 'source' => $source, 'medium' => $medium],
        ];
    }

    /**
     * One lookup id per source name. A missing or stale id must not open a second row for that source and medium.
     */
    private function trafficDimensionId(string $source, string $medium): int
    {
        [$source, $medium] = $this->trafficSourceMedium($source, $medium);

        return (int) ($this->remember(
            'traffic-id:'.$source,
            function () use ($source, $medium): ?int {
                $existing = TrackerDatabase::table('tracking_traffic_source')->where('name', $source)->value('id');
                if ($existing !== null) {
                    return (int) $existing;
                }

                return $this->findOrCreateTrafficSource($source, $medium);
            },
        ) ?? 0);
    }

    /**
     * @return list<array{type: int, dimension_id: int, source: string, medium: string}>
     */
    private function audienceKeysFromDetails(object $row): array
    {
        [$source, $medium] = $this->trafficSourceMedium($row->utm_source, $row->utm_medium);

        return $this->audienceKeys(
            $row->tracking_device_id !== null ? (int) $row->tracking_device_id : null,
            $row->tracking_browser_id !== null ? (int) $row->tracking_browser_id : null,
            $source,
            $medium,
        );
    }

    /**
     * Device, browser, and traffic sold units follow the order placed time.
     * One second into the next day counts on that day, not on the session visit date.
     *
     * @param  array<string, array<string, mixed>>  $audience
     * @param  array<string, int|float>  $emptyAudience
     */
    private function addVisitDateDeviceBrowserSold(array &$audience, string $visitDate, array $emptyAudience): void
    {
        $ordersQuery = TrackerDatabase::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->where('o.metric_date', $visitDate);
        SessionTrafficAttribution::joinLatestSessionDetails($ordersQuery, 'o.tracking_session_id');

        $orders = $ordersQuery->get([
            'o.tracking_session_id',
            'o.items_qty',
            'o.amount_paid',
            'sd.tracking_device_id',
            'sd.tracking_browser_id',
            'sd.tracking_traffic_source_id',
            'sd.utm_source',
            'sd.utm_medium',
        ]);

        $paidSessions = [];
        foreach ($orders as $order) {
            $sessionId = (int) ($order->tracking_session_id ?? 0);
            $countPayment = $sessionId === 0 || ! isset($paidSessions[$sessionId]);
            if ($sessionId !== 0) {
                $paidSessions[$sessionId] = true;
            }
            [$source, $medium] = $this->trafficSourceMedium($order->utm_source, $order->utm_medium);
            foreach ($this->audienceKeys(
                $order->tracking_device_id !== null ? (int) $order->tracking_device_id : null,
                $order->tracking_browser_id !== null ? (int) $order->tracking_browser_id : null,
                $source,
                $medium,
            ) as $key) {
                $id = implode('|', $key);
                $audience[$id] ??= $key + $emptyAudience;
                if ($countPayment) {
                    $audience[$id]['payments']++;
                }
                $audience[$id]['sold_qty'] += (int) $order->items_qty;
                $audience[$id]['sale_amount'] += (float) $order->amount_paid;
            }
        }
    }

    /**
     * Checkout session device/browser; order last non-direct traffic for revenue rollups.
     */
    private function audienceKeysForOrder(object $orderRow): array
    {
        [$source, $medium] = $this->trafficSourceMedium(
            (string) ($orderRow->traffic_utm_source ?? '(direct)'),
            (string) ($orderRow->traffic_utm_medium ?? 'none'),
        );

        return $this->audienceKeys(
            $orderRow->tracking_device_id !== null ? (int) $orderRow->tracking_device_id : null,
            $orderRow->tracking_browser_id !== null ? (int) $orderRow->tracking_browser_id : null,
            $source,
            $medium,
        );
    }

    /**
     * Product performance funnel: views & proceed once per session per product; adds sum line qty.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     * @param  array<string, true>  $funnelDedupe
     */
    private function recordFunnelAction(
        array &$counters,
        object $action,
        string $type,
        string $date,
        string $sessionId,
        array &$funnelDedupe,
    ): void {
        foreach ($this->funnelContributionsForAction($action, $type) as $contribution) {
            if ($contribution['dedupe'] !== null) {
                $key = $sessionId.'|'.$contribution['dedupe'];
                if (isset($funnelDedupe[$key])) {
                    continue;
                }
                $funnelDedupe[$key] = true;
            }

            $this->applyFunnelContribution(
                $counters,
                $date,
                $contribution['column'],
                $contribution['product_code'],
                $contribution['category_id'],
                $contribution['amount'],
            );

            if ($contribution['column'] === 'category_views') {
                $hour = $this->localHour($action->created_at ?? null);
                $this->categoryViewsByHour[$hour] = ($this->categoryViewsByHour[$hour] ?? 0) + (int) $contribution['amount'];
            }
        }
    }

    /**
     * @return list<array{column: string, product_code: ?string, category_id: ?int, amount: int, dedupe: ?string}>
     */
    private function funnelContributionsForAction(object $action, string $type): array
    {
        if ($type === 'category_view') {
            $contributions = [];
            foreach ($this->actionCommerceLines($action) as $line) {
                $categoryId = $this->resolveCategoryIdFromLine($line);
                if ($categoryId === null) {
                    continue;
                }
                $contributions[] = [
                    'column' => 'category_views',
                    'product_code' => null,
                    'category_id' => $categoryId,
                    'amount' => 1,
                    'dedupe' => 'category_view|'.$categoryId,
                ];
            }

            return $contributions;
        }

        return [];
    }

    /**
     * @return list<array{qty: int, price: float, line_total: float, item_variant: string, product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}>
     */
    private function addToCartMetricLines(object $action): array
    {
        $json = $this->actionJsonPayload($action);
        $added = $this->addedCartLineFromAddToCartPayload($json);
        if ($added !== null) {
            $line = $this->commerceLineFromItem($action, $added, $json);
            if ($line !== null) {
                return [$line];
            }
        }

        $line = $this->commerceLineFromScalars($action, $json);
        if ($this->productCode($line['product_code']) === '') {
            return [];
        }

        $line['qty'] = max(1, (int) ($json['qty'] ?? $line['qty']));

        return [$line];
    }

    /**
     * Line added in this click (not every line in the cart snapshot).
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function addedCartLineFromAddToCartPayload(array $payload): ?array
    {
        $items = $payload['items'] ?? $payload['cart_items'] ?? [];
        if (! is_array($items)) {
            return null;
        }

        $addedProductId = trim((string) ($payload['product_id'] ?? ''));
        if ($addedProductId !== '') {
            foreach ($items as $item) {
                if (! is_array($item)) {
                    continue;
                }
                if (trim((string) ($item['product_id'] ?? '')) === $addedProductId) {
                    return $item;
                }
            }
        }

        $lines = array_values(array_filter($items, 'is_array'));
        if (count($lines) === 1) {
            return $lines[0];
        }

        return null;
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     */
    private function applyFunnelContribution(
        array &$counters,
        string $date,
        string $column,
        ?string $productCode,
        ?int $categoryId,
        int $amount,
    ): void {
        if ($amount <= 0) {
            return;
        }

        if ($categoryId !== null) {
            $this->bump($counters, 'tracking_daily_categories', ['metric_date' => $date, 'tracking_category_id' => $categoryId], $column, $amount);

            if ($column === 'category_views' || $column === 'product_views') {
                $this->bump($counters, 'tracking_daily_summaries', ['metric_date' => $date], $column, $amount);
            }
        }

        if ($productCode !== null && $column !== 'category_views') {
            $this->bump($counters, 'tracking_daily_products', ['metric_date' => $date, 'product_code' => $productCode], $column, $amount);
        }
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     * @param  array<string, mixed>  $key
     */
    private function bump(array &$counters, string $table, array $key, string $column, int $amount = 1): void
    {
        $id = implode('|', $key);
        $counters[$table][$id] ??= $key;
        $counters[$table][$id][$column] = ($counters[$table][$id][$column] ?? 0) + $amount;
    }

    /**
     * One upsert per table that adds the counts to the existing day row.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     */
    private function addCounters(array $counters): void
    {
        $now = now();

        foreach ($counters as $table => $rows) {
            ['unique' => $unique, 'counters' => $columns] = self::ACTION_COUNTERS[$table];

            $values = [];
            foreach ($rows as $row) {
                $value = [];
                foreach ($unique as $column) {
                    $value[$column] = $row[$column];
                }
                foreach ($columns as $column) {
                    $value[$column] = $row[$column] ?? 0;
                }
                $values[] = $value + ['created_at' => $now, 'updated_at' => $now];
            }

            $update = ['updated_at' => $now];
            foreach ($columns as $column) {
                $update[$column] = DB::raw("`{$column}` + VALUES(`{$column}`)");
            }

            TrackerDatabase::table($table)->upsert($values, $unique, $update);
        }
    }

    /**
     * payment_success → tracking_orders (+ details, addresses). Keyed by order number, so a re-sync replaces it.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function upsertOrder(int $trackingSessionId, string $visitorId, object $action, array $lines): void
    {
        $json = $this->actionJsonPayload($action);
        $checkout = is_array($json['checkout_info'] ?? null) ? $json['checkout_info'] : [];
        $orderNumber = Str::limit(trim((string) ($checkout['order_number'] ?? $json['order_id'] ?? $action->order_id ?? '')), 50, '');

        if ($orderNumber === '') {
            Log::warning('tracker.dashboard.sync: payment success without order number', ['action_id' => $action->id ?? null]);

            return;
        }

        $totals = is_array($checkout['totals'] ?? null) ? $checkout['totals'] : [];
        $customer = is_array($checkout['customer'] ?? null) ? $checkout['customer'] : [];
        $money = fn (string $key): float => round((float) ($totals[$key] ?? 0), 2);
        $text = fn (mixed $value, int $limit): ?string => trim((string) $value) !== '' ? Str::limit(trim((string) $value), $limit, '') : null;
        $orderedAt = $action->created_at ?? now();
        $paymentAt = TrackerTime::toUtc($orderedAt) ?? TrackerTime::nowUtc();
        $metricDate = $this->localDate($orderedAt);
        $now = now();

        $orderTraffic = SessionTrafficAttribution::resolveOrderTrafficBucket($visitorId, $paymentAt);
        [$trafficSource, $trafficMedium] = $this->trafficSourceMedium($orderTraffic['source'], $orderTraffic['medium']);
        $orderTrafficId = $this->remember(
            "traffic:{$trafficSource}|{$trafficMedium}",
            fn () => $this->findOrCreateTrafficSource($trafficSource, $trafficMedium),
        );

        $discount = round((float) ($action->commerce_discount ?? 0), 2)
            ?: $money('coupon_discount') + $money('scs_discount') + $money('sms_discount');

        $order = [
            'tracking_session_id' => $trackingSessionId,
            'order_number' => $orderNumber,
            'order_pk' => is_numeric($checkout['order_pk'] ?? null) ? (int) $checkout['order_pk'] : null,
            'metric_date' => $metricDate,
            'ordered_at' => $orderedAt,
            'first_name' => $text($customer['first_name'] ?? '', 100),
            'last_name' => $text($customer['last_name'] ?? '', 100),
            'email' => $text($customer['email'] ?? '', 255),
            'phone' => $text($customer['phone'] ?? '', 50),
            'currency' => strtoupper(Str::limit(trim((string) ($json['currency'] ?? '')) ?: 'GBP', 3, '')),
            'payment_method' => $text($json['payment_method'] ?? '', 50),
            'coupon_code' => $text($checkout['coupon_code'] ?? $action->coupon_code ?? '', 100),
            'items_qty' => array_sum(array_column($lines, 'qty')),
            'subtotal' => $money('subtotal'),
            'shipping_cost' => $money('shipping_cost'),
            'delivery_charge' => $money('delivery_charge'),
            'service_charge' => $money('service_charge'),
            'priority_charge' => $money('priority_charge'),
            'extra_handling_cost' => $money('extra_handling_cost'),
            'extra_charges_total' => $money('extra_charges_total'),
            'discount_total' => $discount,
            'grand_total' => $money('grand_total'),
            'amount_paid' => round((float) ($json['amount_paid'] ?? $action->amount_paid ?? $money('grand_total')), 2),
            'traffic_utm_source' => $trafficSource,
            'traffic_utm_medium' => $trafficMedium,
            'tracking_traffic_source_id' => $orderTrafficId,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        TrackerDatabase::table('tracking_orders')->upsert(
            [$order],
            ['order_number'],
            array_keys(Arr::except($order, ['order_number', 'created_at'])),
        );

        $orderId = (int) TrackerDatabase::table('tracking_orders')->where('order_number', $orderNumber)->value('id');

        TrackerDatabase::table('tracking_order_details')->where('tracking_order_id', $orderId)->delete();

        if ($lines !== []) {
            TrackerDatabase::table('tracking_order_details')->insert(array_map(fn (array $line) => $line + [
                'tracking_order_id' => $orderId,
                'created_at' => $now,
                'updated_at' => $now,
            ], $lines));
        }

        $addresses = [];
        foreach ([TrackingOrderAddress::TYPE_SHIPPING => 'shipping', TrackingOrderAddress::TYPE_BILLING => 'billing'] as $type => $key) {
            $address = $checkout[$key] ?? null;
            if (! is_array($address)) {
                continue;
            }

            $addresses[] = [
                'tracking_order_id' => $orderId,
                'type' => $type,
                'line_1' => $text($address['line_1'] ?? '', 255),
                'line_2' => $text($address['line_2'] ?? '', 255),
                'town_city' => $text($address['town_city'] ?? '', 100),
                'postcode' => $text($address['postcode'] ?? '', 20),
                'country' => $text($address['country'] ?? '', 100),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($addresses !== []) {
            TrackerDatabase::table('tracking_order_addresses')->upsert(
                $addresses,
                ['tracking_order_id', 'type'],
                ['line_1', 'line_2', 'town_city', 'postcode', 'country', 'updated_at'],
            );
        }

        $this->touchedDates[$metricDate] = true;
    }

    /**
     * One recoverable row per session, on the visit day, at tracking_session.latest_funnel_stage.
     * That is the same session set as User activity. Qty and value come from the latest lines at
     * that step (or the order, for payment), including a step that happened after midnight.
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function upsertRecoverableSale(int $trackingSessionId, string $visitDate, array $sessionRow, Collection $actions): void
    {
        $stage = (int) TrackerDatabase::table('tracking_session')
            ->where('id', $trackingSessionId)
            ->value('latest_funnel_stage');

        $existing = TrackerDatabase::table('tracking_recoverable_sales')
            ->where('tracking_session_id', $trackingSessionId);

        if (! in_array($stage, [3, 4, 5, 6], true)) {
            $existing->delete();

            return;
        }

        $orders = $stage === 6
            ? TrackerDatabase::table('tracking_orders')
                ->where('tracking_session_id', $trackingSessionId)
                ->get(['items_qty', 'amount_paid', 'ordered_at'])
            : collect();

        $snapshot = $this->recoverableSaleSnapshot($stage, $actions, $orders);
        $now = now();

        $existing->where('metric_date', '!=', $visitDate)->delete();

        TrackerDatabase::table('tracking_recoverable_sales')->upsert([[
            'metric_date' => $visitDate,
            'type' => $stage,
            'tracking_session_id' => $trackingSessionId,
            'qty' => $snapshot['qty'],
            'sale_value' => $snapshot['sale_value'],
            'occurred_at' => $snapshot['occurred_at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['metric_date', 'tracking_session_id'], ['type', 'qty', 'sale_value', 'occurred_at', 'updated_at']);
    }

    /**
     * @param  Collection<int, object>  $actions
     * @param  Collection<int, object>  $orders
     * @return array{qty: int, sale_value: float, occurred_at: ?string}
     */
    private function recoverableSaleSnapshot(int $stage, Collection $actions, Collection $orders): array
    {
        if ($stage === 6 && $orders->isNotEmpty()) {
            $qty = 0;
            $value = 0.0;
            $at = null;
            foreach ($orders as $order) {
                $qty += max(1, (int) ($order->items_qty ?? 0));
                $value += (float) ($order->amount_paid ?? 0);
                $orderedAt = (string) ($order->ordered_at ?? '');
                if ($orderedAt !== '' && ($at === null || $orderedAt > $at)) {
                    $at = $orderedAt;
                }
            }

            return ['qty' => $qty, 'sale_value' => round($value, 2), 'occurred_at' => $at];
        }

        $lines = $this->latestCommerceLines($actions, $stage)
            ?? $this->latestCommerceLines($actions, null);

        if ($lines === null) {
            return ['qty' => 0, 'sale_value' => 0.0, 'occurred_at' => null];
        }

        return [
            'qty' => (int) array_sum(array_column($lines['lines'], 'qty')),
            'sale_value' => round((float) array_sum(array_column($lines['lines'], 'line_total')), 2),
            'occurred_at' => $lines['at'] !== '' ? $lines['at'] : null,
        ];
    }

    /**
     * Latest action at $stage that still has product lines. Null $stage accepts any commerce step.
     * Not limited to the visit calendar day: the session is already tied to that visit.
     *
     * @param  Collection<int, object>  $actions
     * @return array{at: string, lines: list<array<string, mixed>>}|null
     */
    private function latestCommerceLines(Collection $actions, ?int $stage): ?array
    {
        $latest = null;

        foreach ($actions as $action) {
            $code = (int) $this->funnelStageCode($this->metricActionType($action));
            if ($code < 3 || ($stage !== null && $code !== $stage)) {
                continue;
            }

            $lines = array_values(array_filter(
                $this->actionCommerceLines($action),
                fn (array $line) => ($line['product_code'] ?? '') !== '' || ($line['product_name'] ?? '') !== '',
            ));
            if ($lines === []) {
                continue;
            }

            $at = (string) ($action->created_at ?? '');
            $id = (int) ($action->id ?? 0);
            if ($latest === null || [$at, $id] >= [$latest['at'], $latest['id']]) {
                $latest = ['at' => $at, 'id' => $id, 'lines' => $lines];
            }
        }

        if ($latest === null) {
            return null;
        }

        return ['at' => $latest['at'], 'lines' => $latest['lines']];
    }

    /**
     * Rebuild recoverable rows for one visit day from tracking_session.latest_funnel_stage
     * so the panel lists the same sessions as User activity.
     */
    private function rebuildRecoverableSales(string $visitDate, Carbon $now): void
    {
        $visiting = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('dv.visit_date', $visitDate)
            ->get(['ts.id', 'ts.session_id', 'ts.latest_funnel_stage']);

        $keep = $visiting->filter(fn (object $session) => in_array((int) $session->latest_funnel_stage, [3, 4, 5, 6], true))->values();
        $keepIds = $keep->map(fn (object $session) => (int) $session->id)->all();
        $visitingIds = $visiting->map(fn (object $session) => (int) $session->id)->all();

        TrackerDatabase::table('tracking_recoverable_sales')
            ->where('metric_date', $visitDate)
            ->whereNotIn('tracking_session_id', $keepIds ?: [0])
            ->delete();

        if ($visitingIds !== []) {
            TrackerDatabase::table('tracking_recoverable_sales')
                ->whereIn('tracking_session_id', $visitingIds)
                ->where('metric_date', '!=', $visitDate)
                ->delete();
        }

        if ($keep->isNotEmpty()) {
            $actions = TrackerDatabase::table('activity_ecom_user_actions')
                ->whereIn('session_id', $keep->pluck('session_id')->all())
                ->orderBy('created_at')
                ->orderBy('id')
                ->get()
                ->groupBy('session_id');

            $orders = TrackerDatabase::table('tracking_orders')
                ->whereIn('tracking_session_id', $keepIds)
                ->get(['tracking_session_id', 'items_qty', 'amount_paid', 'ordered_at'])
                ->groupBy(fn (object $order) => (int) $order->tracking_session_id);

            $values = [];
            foreach ($keep as $session) {
                $sessionId = (int) $session->id;
                $snapshot = $this->recoverableSaleSnapshot(
                    (int) $session->latest_funnel_stage,
                    $actions->get($session->session_id, collect()),
                    $orders->get($sessionId, collect()),
                );
                $values[] = [
                    'metric_date' => $visitDate,
                    'type' => (int) $session->latest_funnel_stage,
                    'tracking_session_id' => $sessionId,
                    'qty' => $snapshot['qty'],
                    'sale_value' => $snapshot['sale_value'],
                    'occurred_at' => $snapshot['occurred_at'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($values, 200) as $chunk) {
                TrackerDatabase::table('tracking_recoverable_sales')->upsert(
                    $chunk,
                    ['metric_date', 'tracking_session_id'],
                    ['type', 'qty', 'sale_value', 'occurred_at', 'updated_at'],
                );
            }
        }

        $drops = TrackerDatabase::table('tracking_recoverable_sales')
            ->where('metric_date', $visitDate)
            ->whereIn('type', [3, 4, 5])
            ->groupBy('type')
            ->pluck(DB::raw('COUNT(*)'), 'type');

        TrackerDatabase::table('tracking_daily_summaries')
            ->where('metric_date', $visitDate)
            ->update([
                'cart_drops' => (int) ($drops[3] ?? 0),
                'checkout_drops' => (int) ($drops[4] ?? 0),
                'proceed_drops' => (int) ($drops[5] ?? 0),
                'updated_at' => $now,
            ]);
    }

    /**
     * Recalculate the touched days. One failing day is logged and the others still run;
     * it is recalculated again by the next sync that touches that day.
     */
    private function refreshTouchedDays(): void
    {
        foreach (array_keys($this->touchedDates) as $date) {
            try {
                DB::transaction(fn () => $this->refreshDay($date), 3);
            } catch (Throwable $e) {
                Log::error('tracker.dashboard.sync: daily totals failed', [
                    'step' => 'refreshTouchedDays',
                    'date' => $date,
                    'exception' => $e,
                ]);
            }
        }

        $this->touchedDates = [];
    }

    /**
     * Session totals, duration buckets and sold figures for one day, rebuilt from the tracking tables
     * (safe to run any number of times).
     */
    private function refreshDay(string $date): void
    {
        $now = now();
        $this->resetProductJourneyHours();

        $sessionsQuery = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('dv.visit_date', $date);
        SessionTrafficAttribution::joinLatestSessionDetails($sessionsQuery);
        $sessions = $sessionsQuery->get([
            'ts.tracking_daily_visitor_id', 'ts.duration_seconds',
            'ts.has_add_to_cart', 'ts.has_begin_checkout', 'ts.has_proceed_checkout', 'ts.has_order',
            'sd.tracking_device_id', 'sd.tracking_browser_id', 'sd.tracking_traffic_source_id', 'sd.utm_source', 'sd.utm_medium',
        ]);

        // same_day: the order's session started that day too (device / traffic / payment panel need both, like master).
        $ordersQuery = TrackerDatabase::table('tracking_orders as o')
            ->leftJoin('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->leftJoin('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('o.metric_date', $date);
        SessionTrafficAttribution::joinLatestSessionDetails($ordersQuery, 'o.tracking_session_id');
        $orders = $ordersQuery
            ->selectRaw('dv.visit_date = ? as same_day', [$date])
            ->addSelect([
                'o.tracking_session_id', 'o.items_qty', 'o.amount_paid', 'o.ordered_at',
                'o.traffic_utm_source', 'o.traffic_utm_medium', 'o.tracking_traffic_source_id as order_tracking_traffic_source_id',
                'sd.tracking_device_id', 'sd.tracking_browser_id',
            ])
            ->get();

        $summary = [
            'unique_visitors' => $sessions->pluck('tracking_daily_visitor_id')->unique()->count(),
            'sessions' => $sessions->count(),
            'stay_seconds' => 0,
            'cart_sessions' => 0,
            'checkout_sessions' => 0,
            'proceed_sessions' => 0,
            'cart_drops' => 0,
            'checkout_drops' => 0,
            'proceed_drops' => 0,
            'payments' => 0,
            'items_sold' => 0,
            'sale_amount' => 0,
        ];
        $durations = array_fill_keys(array_keys(TrackingDailyDuration::BUCKETS), ['sessions' => 0, 'duration_seconds' => 0]);
        $audience = [];
        $emptyAudience = ['sessions' => 0, 'add_to_carts' => 0, 'begin_checkouts' => 0, 'proceed_checkouts' => 0, 'payments' => 0, 'sold_qty' => 0, 'sale_amount' => 0];

        foreach ($sessions as $session) {
            $seconds = (int) $session->duration_seconds;
            $funnel = [
                'add_to_carts' => (int) (bool) $session->has_add_to_cart,
                'begin_checkouts' => (int) (bool) $session->has_begin_checkout,
                'proceed_checkouts' => (int) (bool) $session->has_proceed_checkout,
            ];

            $summary['stay_seconds'] += $seconds;
            $summary['cart_sessions'] += $funnel['add_to_carts'];
            $summary['checkout_sessions'] += $funnel['begin_checkouts'];
            $summary['proceed_sessions'] += $funnel['proceed_checkouts'];

            $bucket = TrackingDailyDuration::bucketFor($seconds);
            $durations[$bucket]['sessions']++;
            $durations[$bucket]['duration_seconds'] += $seconds;

            foreach ($this->audienceKeysFromDetails($session) as $key) {
                $id = implode('|', $key);
                $audience[$id] ??= $key + $emptyAudience;
                $audience[$id]['sessions']++;
            }
        }

        $paidSessions = [];
        foreach ($orders as $order) {
            $sessionId = (int) ($order->tracking_session_id ?? 0);
            if ($sessionId === 0 || ! isset($paidSessions[$sessionId])) {
                $summary['payments']++;
                if ($sessionId !== 0) {
                    $paidSessions[$sessionId] = true;
                }
            }
            $summary['items_sold'] += (int) $order->items_qty;
            $summary['sale_amount'] += (float) $order->amount_paid;

            if (! $order->same_day) {
                continue;
            }

            foreach ($this->audienceKeysForOrder($order) as $key) {
                if (in_array($key['type'], [
                    TrackingDailyAudience::TYPE_DEVICE,
                    TrackingDailyAudience::TYPE_BROWSER,
                    TrackingDailyAudience::TYPE_TRAFFIC_SOURCE,
                ], true)) {
                    continue;
                }

                $id = implode('|', $key);
                $audience[$id] ??= $key + $emptyAudience;
                $audience[$id]['payments']++;
                $audience[$id]['sold_qty'] += (int) $order->items_qty;
                $audience[$id]['sale_amount'] += (float) $order->amount_paid;
            }
        }

        $this->addVisitDateDeviceBrowserSold($audience, $date, $emptyAudience);
        $audience = $this->mergeTrafficAudienceRows($audience);

        $this->rebuildRecoverableSales($date, $now);

        $drops = TrackerDatabase::table('tracking_recoverable_sales')
            ->where('metric_date', $date)
            ->whereIn('type', [3, 4, 5])
            ->groupBy('type')
            ->pluck(DB::raw('COUNT(*)'), 'type');
        $summary['cart_drops'] = (int) ($drops[3] ?? 0);
        $summary['checkout_drops'] = (int) ($drops[4] ?? 0);
        $summary['proceed_drops'] = (int) ($drops[5] ?? 0);

        $summary['sale_amount'] = round($summary['sale_amount'], 2);

        TrackerDatabase::table('tracking_daily_summaries')->upsert(
            [['metric_date' => $date] + $summary + ['created_at' => $now, 'updated_at' => $now]],
            ['metric_date'],
            [...array_keys($summary), 'updated_at'],
        );

        $durationRows = [];
        foreach ($durations as $bucket => $values) {
            $durationRows[] = ['metric_date' => $date, 'bucket' => $bucket] + $values + ['created_at' => $now, 'updated_at' => $now];
        }
        TrackerDatabase::table('tracking_daily_durations')->upsert($durationRows, ['metric_date', 'bucket'], ['sessions', 'duration_seconds', 'updated_at']);

        $audienceColumns = [...array_keys($emptyAudience), 'conversion_rate'];
        TrackerDatabase::table('tracking_daily_audiences')
            ->where('metric_date', $date)
            ->update(array_fill_keys($audienceColumns, 0) + ['updated_at' => $now]);

        if ($audience !== []) {
            $audienceRows = array_map(fn (array $row) => ['metric_date' => $date] + [
                ...$row,
                'sale_amount' => round($row['sale_amount'], 2),
                'conversion_rate' => $row['sessions'] > 0 ? round($row['payments'] / $row['sessions'] * 100, 2) : 0,
                'created_at' => $now,
                'updated_at' => $now,
            ], array_values($audience));

            TrackerDatabase::table('tracking_daily_audiences')->upsert(
                $audienceRows,
                ['metric_date', 'type', 'dimension_id', 'source', 'medium'],
                [...$audienceColumns, 'updated_at'],
            );
        }

        $this->refreshProductFunnelCounts($date, $now);
        $this->writeAllocatedProductSold($date, $now);
        $this->storePrimaryCategoriesForDate($date);
        $this->rollCategorySoldFromProducts($date, $now);
        $this->alignAudienceSaleToProductSale($date);

        $this->purgeEmptyDailyCategoryRows($date);
        $this->purgeEmptyTrafficAudienceRows($date);
        $this->purgeEmptyDeviceBrowserAudienceRows($date);

        $this->backfillTrackingSessionStartedAt($date);

        $this->refreshHourlySummaries($date, $now);
    }

    /** Align tracking_session.created_at with the real session start from activity (not sync time). */
    private function backfillTrackingSessionStartedAt(string $visitDate): void
    {
        TrackerDatabase::table('tracking_session as ts')
            ->join('activity_ecom_user as u', 'u.session_id', '=', 'ts.session_id')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('dv.visit_date', $visitDate)
            ->whereColumn('u.created_at', '<', 'ts.created_at')
            ->update(['ts.created_at' => DB::raw('u.created_at')]);
    }

    /**
     * @return array{views: int, adds: int, begins: int, proceeds: int, paid: int}
     */
    private function emptyProductJourneyHour(): array
    {
        return ['views' => 0, 'adds' => 0, 'begins' => 0, 'proceeds' => 0, 'paid' => 0, 'sale' => 0.0];
    }

    private function resetProductJourneyHours(): void
    {
        $this->productJourneyByHour = [];
        $this->categoryViewsByHour = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $this->productJourneyByHour[$hour] = $this->emptyProductJourneyHour();
            $this->categoryViewsByHour[$hour] = 0;
        }
    }

    /**
     * Write the product-performance sums (view, add, begin, proceed, paid) onto the day row.
     */
    private function storeProductJourneyDayTotals(string $date, Carbon $now): void
    {
        $totals = $this->emptyProductJourneyHour();
        foreach ($this->productJourneyByHour as $bucket) {
            $totals['views'] += $bucket['views'];
            $totals['adds'] += $bucket['adds'];
            $totals['begins'] += $bucket['begins'];
            $totals['proceeds'] += $bucket['proceeds'];
            $totals['paid'] += $bucket['paid'];
            $totals['sale'] += $bucket['sale'];
        }

        TrackerDatabase::table('tracking_daily_summaries')->where('metric_date', $date)->update([
            'product_view_units' => $totals['views'],
            'product_add_units' => $totals['adds'],
            'product_begin_units' => $totals['begins'],
            'product_proceed_units' => $totals['proceeds'],
            'product_paid_units' => $totals['paid'],
            'product_sale_amount' => round($totals['sale'], 2),
            'updated_at' => $now,
        ]);
    }

    /**
     * Rebuild hourly dashboard trend rows (action/event counts + session + order totals).
     */
    private function refreshHourlySummaries(string $date, Carbon $now): void
    {
        $hours = [];
        for ($h = 0; $h < 24; $h++) {
            $journey = $this->productJourneyByHour[$h] ?? $this->emptyProductJourneyHour();
            $hours[$h] = [
                'metric_date' => $date,
                'metric_hour' => $h,
                'category_views' => (int) ($this->categoryViewsByHour[$h] ?? 0),
                'product_views' => 0,
                'product_view_units' => $journey['views'],
                'product_add_units' => $journey['adds'],
                'product_begin_units' => $journey['begins'],
                'product_proceed_units' => $journey['proceeds'],
                'product_paid_units' => $journey['paid'],
                'product_sale_amount' => round((float) $journey['sale'], 2),
                'unique_visitors' => 0,
                'sessions' => 0,
            ];
        }

        $this->applyHourlyTrendActionCounts($date, $hours);

        $sessions = TrackerDatabase::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->join('activity_ecom_user as u', 'u.session_id', '=', 'ts.session_id')
            ->where('dv.visit_date', $date)
            ->get([
                'ts.tracking_daily_visitor_id', 'u.created_at as session_started_at',
            ]);

        $visitorHour = [];
        foreach ($sessions as $session) {
            $hour = $this->localHour($session->session_started_at);
            $visitorId = (int) $session->tracking_daily_visitor_id;
            $hours[$hour]['sessions']++;
            if (! isset($visitorHour[$visitorId]) || $hour < $visitorHour[$visitorId]) {
                $visitorHour[$visitorId] = $hour;
            }
        }

        foreach ($visitorHour as $hour) {
            $hours[$hour]['unique_visitors']++;
        }

        $rows = [];
        foreach ($hours as $row) {
            if ($this->hourlySummaryRowIsEmpty($row)) {
                continue;
            }
            $rows[] = $row + ['created_at' => $now, 'updated_at' => $now];
        }

        TrackerDatabase::table('tracking_hourly_summaries')
            ->where('metric_date', $date)
            ->whereNotIn('metric_hour', array_column($rows, 'metric_hour') ?: [-1])
            ->delete();

        if ($rows === []) {
            return;
        }

        $columns = [
            'unique_visitors', 'sessions', 'category_views', 'product_views',
            'product_view_units', 'product_add_units', 'product_begin_units', 'product_proceed_units', 'product_paid_units',
            'product_sale_amount',
        ];

        TrackerDatabase::table('tracking_hourly_summaries')->upsert(
            $rows,
            ['metric_date', 'metric_hour'],
            [...$columns, 'updated_at'],
        );
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function hourlySummaryRowIsEmpty(array $row): bool
    {
        foreach ([
            'unique_visitors', 'sessions', 'category_views', 'product_views',
            'product_view_units', 'product_add_units', 'product_begin_units', 'product_proceed_units', 'product_paid_units',
        ] as $column) {
            if ((int) ($row[$column] ?? 0) !== 0) {
                return false;
            }
        }

        return round((float) ($row['product_sale_amount'] ?? 0), 2) === 0.0;
    }

    /**
     * @param  array<int, array<string, mixed>>  $hours
     */
    private function applyHourlyTrendActionCounts(string $visitDate, array &$hours): void
    {
        [$fromBound, $toBound] = $this->actionStorageBounds($visitDate);
        $actions = TrackerDatabase::table('activity_ecom_user_actions')
            ->whereBetween('created_at', [$fromBound, $toBound])
            ->orderBy('id')
            ->get();

        foreach ($actions as $action) {
            if ($this->localDate($action->created_at ?? null) !== $visitDate) {
                continue;
            }

            $hour = $this->localHour($action->created_at ?? null);
            $type = $this->metricActionType($action);
            $lines = $this->actionCommerceLines($action);

            if ($lines === []) {
                $this->bumpHourlyTrendMetric($hours, $hour, $type);

                continue;
            }

            foreach ($lines as $line) {
                if ($this->resolveCategoryIdFromLine($line) === null) {
                    continue;
                }

                $this->bumpHourlyTrendMetric($hours, $hour, $type);
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $hours
     */
    private function bumpHourlyTrendMetric(array &$hours, int $hour, string $type): void
    {
        $column = match ($type) {
            'product_view' => 'product_views',
            default => null,
        };

        if ($column === null) {
            return;
        }

        $hours[$hour][$column]++;
    }

    /**
     * @param  list<string>  $sessionIds
     * @return array<string, object>
     */
    private function activityUsersBySessionId(array $sessionIds): array
    {
        $map = [];
        foreach (array_chunk($sessionIds, 500) as $chunk) {
            $rows = TrackerDatabase::table('activity_ecom_user')
                ->whereIn('session_id', $chunk)
                ->get(['session_id', 'visitor_id', 'created_at']);

            foreach ($rows as $row) {
                $map[$this->normalizeSessionId((string) $row->session_id)] = $row;
            }
        }

        return $map;
    }

    /**
     * Sessions passed in are already one calendar day. Views stay per session.
     * Add, begin, and proceed for the same visitor use the max qty from the start
     * of that day to the end of that day. A later session only adds the increase.
     *
     * @param  list<array{id: string, visitor: string, started_at: ?Carbon, credit: bool, units: array<string, array<string, int>>}>  $sessions
     * @return array<string, array<string, array{views: int, add_to_cart: int, begin_checkout: int, proceed_checkout: int}>>
     */
    private function creditSameVisitorDayFunnel(array $sessions): array
    {
        $maxSteps = ['add_to_cart', 'begin_checkout', 'proceed_checkout'];
        $groups = [];
        foreach ($sessions as $session) {
            $key = $session['visitor'] !== '' ? $session['visitor'] : 'session:'.$session['id'];
            $groups[$key][] = $session;
        }

        $credits = [];
        foreach ($groups as $group) {
            usort($group, function (array $left, array $right): int {
                $leftStart = $left['started_at'];
                $rightStart = $right['started_at'];
                if ($leftStart === null && $rightStart === null) {
                    return strcmp($left['id'], $right['id']);
                }
                if ($leftStart === null) {
                    return 1;
                }
                if ($rightStart === null) {
                    return -1;
                }

                $byTime = $leftStart->getTimestamp() <=> $rightStart->getTimestamp();

                return $byTime !== 0 ? $byTime : strcmp($left['id'], $right['id']);
            });

            $running = [];
            foreach ($group as $session) {
                foreach ($session['units'] as $productCode => $unitSteps) {
                    $views = (int) ($unitSteps['views'] ?? 0);
                    if ($session['credit'] && $views > 0) {
                        $credits[$session['id']][$productCode]['views'] = ($credits[$session['id']][$productCode]['views'] ?? 0) + $views;
                    }

                    foreach ($maxSteps as $step) {
                        $qty = (int) ($unitSteps[$step] ?? 0);
                        $already = (int) ($running[$productCode][$step] ?? 0);
                        if ($qty > $already) {
                            $running[$productCode][$step] = $qty;
                        }
                        $gain = max(0, $qty - $already);
                        if ($session['credit'] && $gain > 0) {
                            $credits[$session['id']][$productCode][$step] = $gain;
                        }
                    }
                }
            }
        }

        return $credits;
    }

    /**
     * Rebuild product / category / audience funnel columns for one calendar day.
     * Each action counts on its own time, the same way payment_success does.
     * Commerce units: {@see EcomActivityListService::productFunnelUnitsForSessionActions()}
     * (catalog product_click views; add / begin / proceed / paid cycle rules).
     * Views stay per session. Add, begin, and proceed for the same visitor use the max
     * qty from the start of this calendar day to the end of this day.
     */
    private function refreshProductFunnelCounts(string $date, Carbon $now): void
    {
        TrackerDatabase::table('tracking_daily_products')->where('metric_date', $date)->update([
            'tracking_category_id' => null,
            'product_views' => 0,
            'add_to_carts' => 0,
            'begin_checkouts' => 0,
            'proceed_checkouts' => 0,
            'updated_at' => $now,
        ]);

        TrackerDatabase::table('tracking_daily_categories')->where('metric_date', $date)->update([
            'category_views' => 0,
            'product_views' => 0,
            'add_to_carts' => 0,
            'proceed_checkouts' => 0,
            'updated_at' => $now,
        ]);

        TrackerDatabase::table('tracking_daily_summaries')->where('metric_date', $date)->update([
            'category_views' => 0,
            'product_views' => 0,
            'product_view_units' => 0,
            'product_add_units' => 0,
            'product_begin_units' => 0,
            'product_proceed_units' => 0,
            'updated_at' => $now,
        ]);

        TrackerDatabase::table('tracking_daily_audiences')->where('metric_date', $date)->update([
            'views' => 0,
            'add_to_carts' => 0,
            'begin_checkouts' => 0,
            'proceed_checkouts' => 0,
            'updated_at' => $now,
        ]);

        [$fromBound, $toBound] = $this->actionStorageBounds($date);
        $actions = TrackerDatabase::table('activity_ecom_user_actions')
            ->whereBetween('created_at', [$fromBound, $toBound])
            ->orderBy('id')
            ->get();

        if ($actions->isEmpty()) {
            return;
        }

        $sessionIds = $actions
            ->pluck('session_id')
            ->map(fn ($id) => $this->normalizeSessionId((string) $id))
            ->unique()
            ->values()
            ->all();
        $sessionAudienceKeys = $this->sessionAudienceKeysForSessionIds($sessionIds);
        $this->fillAudienceKeysFromActivityUsers($sessionAudienceKeys, $sessionIds);
        $productCategoryByCode = $this->productCategoryIdsByCodeForVisitDate($date);
        $this->fillProductCategoriesFromSessions($productCategoryByCode, $sessionIds);

        $skuToParent = TrackerDatabase::table('tracking_product')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->pluck('code', 'sku')
            ->all();

        $activityList = app(EcomActivityListService::class);
        $counters = [];
        $funnelDedupe = [];
        $funnelSessions = [];
        $usersBySession = $this->activityUsersBySessionId($sessionIds);

        foreach ($actions->groupBy('session_id') as $rawSessionId => $sessionActions) {
            $sessionId = $this->normalizeSessionId((string) $rawSessionId);
            $dayActions = $sessionActions->filter(
                fn (object $action) => $this->localDate($action->created_at ?? null) === $date,
            );

            if ($dayActions->isEmpty()) {
                continue;
            }

            foreach ($dayActions as $action) {
                $type = $this->metricActionType($action);
                $this->recordFunnelAction($counters, $action, $type, $date, $sessionId, $funnelDedupe);
            }

            $user = $usersBySession[$sessionId] ?? null;
            $funnelSessions[] = [
                'id' => $sessionId,
                'visitor' => trim((string) ($user->visitor_id ?? '')),
                'started_at' => TrackerTime::toUtc($user->created_at ?? null),
                'credit' => true,
                'hour' => $this->localHour($dayActions->last()->created_at ?? null),
                'audience' => $sessionAudienceKeys[$sessionId] ?? [],
                'day_actions' => $dayActions,
                'units' => $activityList->productFunnelUnitsForSessionActions($dayActions, $skuToParent),
            ];
        }

        $credits = $this->creditSameVisitorDayFunnel($funnelSessions);

        foreach ($funnelSessions as $session) {
            $units = $credits[$session['id']] ?? [];
            $sessionViews = 0;
            $sessionAdds = 0;
            $sessionBegins = 0;
            $sessionProceeds = 0;

            foreach ($units as $productCode => $steps) {
                $productCode = $this->productCode($productCode);
                if ($productCode === '') {
                    continue;
                }

                $key = ['metric_date' => $date, 'product_code' => $productCode];
                if (($steps['views'] ?? 0) > 0) {
                    $this->bump($counters, 'tracking_daily_products', $key, 'product_views', (int) $steps['views']);
                }
                if (($steps['add_to_cart'] ?? 0) > 0) {
                    $this->bump($counters, 'tracking_daily_products', $key, 'add_to_carts', (int) $steps['add_to_cart']);
                }
                if (($steps['begin_checkout'] ?? 0) > 0) {
                    $this->bump($counters, 'tracking_daily_products', $key, 'begin_checkouts', (int) $steps['begin_checkout']);
                }
                if (($steps['proceed_checkout'] ?? 0) > 0) {
                    $this->bump($counters, 'tracking_daily_products', $key, 'proceed_checkouts', (int) $steps['proceed_checkout']);
                }

                $categoryId = $productCategoryByCode[$productCode] ?? $this->categoryIdFromActions($session['day_actions'], $productCode);
                if ($categoryId !== null) {
                    $productCategoryByCode[$productCode] = (int) $categoryId;
                    $this->bumpCategoryFunnelSteps($counters, $date, (int) $categoryId, $steps);
                }

                $sessionViews += (int) ($steps['views'] ?? 0);
                $sessionAdds += (int) ($steps['add_to_cart'] ?? 0);
                $sessionBegins += (int) ($steps['begin_checkout'] ?? 0);
                $sessionProceeds += (int) ($steps['proceed_checkout'] ?? 0);
            }

            $hour = $session['hour'];
            $this->productJourneyByHour[$hour]['views'] += $sessionViews;
            $this->productJourneyByHour[$hour]['adds'] += $sessionAdds;
            $this->productJourneyByHour[$hour]['begins'] += $sessionBegins;
            $this->productJourneyByHour[$hour]['proceeds'] += $sessionProceeds;

            foreach ($session['audience'] as $audienceKey) {
                $this->bumpAudienceFunnelSteps($counters, $date, $audienceKey, $sessionViews, $sessionAdds, $sessionBegins, $sessionProceeds);
            }
        }

        $this->addCounters($counters);
        $this->storeProductJourneyDayTotals($date, $now);
    }

    /**
     * @return array<string, list<array{type: int, dimension_id: int, source: string, medium: string}>>
     */
    /**
     * @param  list<string>  $sessionIds
     * @return array<string, list<array{type: int, dimension_id: int, source: string, medium: string}>>
     */
    private function sessionAudienceKeysForSessionIds(array $sessionIds): array
    {
        $map = [];
        foreach (array_chunk($sessionIds, 500) as $chunk) {
            $query = TrackerDatabase::table('tracking_session as ts')
                ->whereIn('ts.session_id', $chunk);
            SessionTrafficAttribution::joinLatestSessionDetails($query);

            foreach ($query->get(['ts.session_id', 'sd.tracking_device_id', 'sd.tracking_browser_id', 'sd.tracking_traffic_source_id', 'sd.utm_source', 'sd.utm_medium']) as $row) {
                $sessionId = $this->normalizeSessionId((string) $row->session_id);
                $map[$sessionId] = $this->audienceKeysFromDetails($row);
            }
        }

        return $map;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function actionStorageBounds(string $date): array
    {
        $start = Carbon::parse($date, TrackerTime::timezone())->startOfDay();

        return TrackerTime::storageRange($start, $start->copy()->endOfDay());
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $sessionIds
     */
    /**
     * A session can have actions before its tracking_session row exists. Use the visitor row so the view still lands on device, browser, and traffic.
     *
     * @param  array<string, list<array{type: int, dimension_id: int, source: string, medium: string}>>  $map
     * @param  list<string>  $sessionIds
     */
    private function fillAudienceKeysFromActivityUsers(array &$map, array $sessionIds): void
    {
        $missing = [];
        foreach ($sessionIds as $sessionId) {
            if (! isset($map[$sessionId])) {
                $missing[] = $sessionId;
            }
        }

        if ($missing === []) {
            return;
        }

        foreach (array_chunk($missing, 500) as $chunk) {
            $users = TrackerDatabase::table('activity_ecom_user')
                ->whereIn('session_id', $chunk)
                ->get(['session_id', 'device_type', 'browser']);

            foreach ($users as $user) {
                $sessionId = $this->normalizeSessionId((string) $user->session_id);
                [$source, $medium] = $this->trafficSourceMedium(null, null);
                $map[$sessionId] = $this->audienceKeys(
                    $this->findOrCreateDevice((string) ($user->device_type ?? '')),
                    $this->findOrCreateBrowser((string) ($user->browser ?? '')),
                    $source,
                    $medium,
                );
            }
        }
    }

    /**
     * @param  iterable<int, object>  $actions
     */
    private function categoryIdFromActions(iterable $actions, string $productCode): ?int
    {
        foreach ($actions as $action) {
            if ($this->productCode((string) ($action->product_code ?? '')) !== $productCode) {
                continue;
            }

            $categoryId = $this->resolveCategoryIdFromLine([
                'category_name' => (string) ($action->category_name ?? ''),
                'category_code' => (string) ($action->category_code ?? ''),
                'department_name' => (string) ($action->department_name ?? ''),
                'department_code' => (string) ($action->department_id ?? ''),
            ]);

            if ($categoryId !== null) {
                return $categoryId;
            }
        }

        return null;
    }

    /**
     * Keep device, browser, and traffic sale equal to the rounded product sale. Row rounding can leave one penny out.
     */
    private function alignAudienceSaleToProductSale(string $date): void
    {
        $target = round((float) TrackerDatabase::table('tracking_daily_products')->where('metric_date', $date)->sum('sale_amount'), 2);

        foreach ([TrackingDailyAudience::TYPE_DEVICE, TrackingDailyAudience::TYPE_BROWSER, TrackingDailyAudience::TYPE_TRAFFIC_SOURCE] as $type) {
            $rows = TrackerDatabase::table('tracking_daily_audiences')
                ->where('metric_date', $date)
                ->where('type', $type)
                ->get(['id', 'sale_amount']);

            if ($rows->isEmpty()) {
                continue;
            }

            $sum = round((float) $rows->sum('sale_amount'), 2);
            $diff = round($target - $sum, 2);
            if ($diff === 0.0) {
                continue;
            }

            $largest = $rows->sortByDesc(fn (object $row) => (float) $row->sale_amount)->first();
            TrackerDatabase::table('tracking_daily_audiences')->where('id', $largest->id)->update([
                'sale_amount' => round((float) $largest->sale_amount + $diff, 2),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * @param  array<string, int>  $map
     * @param  list<string>  $sessionIds
     */
    private function fillProductCategoriesFromSessions(array &$map, array $sessionIds): void
    {
        if ($sessionIds === []) {
            return;
        }

        $canonical = TrackerCategoryIdentity::canonicalCategoryIds();
        foreach (array_chunk($sessionIds, 500) as $chunk) {
            $rows = TrackerDatabase::table('tracking_session_p_cat as sp')
                ->join('tracking_session as ts', 'ts.id', '=', 'sp.tracking_session_id')
                ->join('tracking_product as p', 'p.id', '=', 'sp.tracking_product_id')
                ->whereIn('ts.session_id', $chunk)
                ->whereNotNull('sp.tracking_category_id')
                ->get(['p.code', 'sp.tracking_category_id']);

            foreach ($rows as $row) {
                $code = $this->productCode((string) $row->code);
                if ($code === '' || isset($map[$code])) {
                    continue;
                }

                $categoryId = (int) $row->tracking_category_id;
                $map[$code] = $canonical[$categoryId] ?? $categoryId;
            }
        }
    }

    /**
     * @return array<string, int> product code => tracking_category_id
     */
    private function productCategoryIdsByCodeForVisitDate(string $visitDate): array
    {
        return TrackerCategoryIdentity::primaryCategoryIdByProductCodeForVisitDate($visitDate);
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     * @param  array{views?: int, add_to_cart?: int, begin_checkout?: int, proceed_checkout?: int}  $steps
     */
    private function bumpCategoryFunnelSteps(array &$counters, string $date, int $categoryId, array $steps): void
    {
        $key = ['metric_date' => $date, 'tracking_category_id' => $categoryId];

        if (($steps['views'] ?? 0) > 0) {
            $amount = (int) $steps['views'];
            $this->bump($counters, 'tracking_daily_categories', $key, 'product_views', $amount);
            $this->bump($counters, 'tracking_daily_summaries', ['metric_date' => $date], 'product_views', $amount);
        }
        if (($steps['add_to_cart'] ?? 0) > 0) {
            $this->bump($counters, 'tracking_daily_categories', $key, 'add_to_carts', (int) $steps['add_to_cart']);
        }
        if (($steps['proceed_checkout'] ?? 0) > 0) {
            $this->bump($counters, 'tracking_daily_categories', $key, 'proceed_checkouts', (int) $steps['proceed_checkout']);
        }
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     * @param  array{type: int, dimension_id: int, source: string, medium: string}  $audienceKey
     */
    private function bumpAudienceFunnelSteps(
        array &$counters,
        string $date,
        array $audienceKey,
        int $views,
        int $adds,
        int $begins,
        int $proceeds,
    ): void {
        $key = ['metric_date' => $date] + $audienceKey;

        if ($views > 0) {
            $this->bump($counters, 'tracking_daily_audiences', $key, 'views', $views);
        }
        if ($adds > 0) {
            $this->bump($counters, 'tracking_daily_audiences', $key, 'add_to_carts', $adds);
        }
        if ($begins > 0) {
            $this->bump($counters, 'tracking_daily_audiences', $key, 'begin_checkouts', $begins);
        }
        if ($proceeds > 0) {
            $this->bump($counters, 'tracking_daily_audiences', $key, 'proceed_checkouts', $proceeds);
        }
    }

    /**
     * Save the sync category on each product row: link-count primary category, otherwise the order-line category.
     */
    private function storePrimaryCategoriesForDate(string $date): void
    {
        $this->writeProductCategoryIds($date, $this->productCategoryIdsByCodeForVisitDate($date));

        $canonical = TrackerCategoryIdentity::canonicalCategoryIds();
        $best = [];
        $lines = TrackerDatabase::table('tracking_order_details as od')
            ->join('tracking_orders as o', 'o.id', '=', 'od.tracking_order_id')
            ->join('tracking_product as p', 'p.id', '=', 'od.tracking_product_id')
            ->where('o.metric_date', $date)
            ->whereNotNull('od.tracking_category_id')
            ->groupBy('p.code', 'od.tracking_category_id')
            ->selectRaw('p.code as product_code, od.tracking_category_id as category_id, SUM(od.qty) as qty')
            ->get();

        foreach ($lines as $line) {
            $code = $this->productCode((string) $line->product_code);
            if ($code === '') {
                continue;
            }

            $categoryId = $canonical[(int) $line->category_id] ?? (int) $line->category_id;
            $qty = (int) $line->qty;
            $current = $best[$code] ?? null;
            if ($current === null || $qty > $current['qty'] || ($qty === $current['qty'] && $categoryId < $current['id'])) {
                $best[$code] = ['id' => $categoryId, 'qty' => $qty];
            }
        }

        if ($best === []) {
            return;
        }

        $open = TrackerDatabase::table('tracking_daily_products')
            ->where('metric_date', $date)
            ->whereNull('tracking_category_id')
            ->whereIn('product_code', array_keys($best))
            ->pluck('product_code');

        $fallback = [];
        foreach ($open as $code) {
            $code = (string) $code;
            if (isset($best[$code])) {
                $fallback[$code] = $best[$code]['id'];
            }
        }

        $this->writeProductCategoryIds($date, $fallback);
    }

    /**
     * @param  array<string, int>  $categoryIdByCode
     */
    private function writeProductCategoryIds(string $date, array $categoryIdByCode): void
    {
        if ($categoryIdByCode === []) {
            return;
        }

        $pdo = TrackerDatabase::connection()->getPdo();
        foreach (array_chunk($categoryIdByCode, 300, true) as $chunk) {
            $case = 'CASE product_code';
            foreach ($chunk as $code => $categoryId) {
                $case .= ' WHEN '.$pdo->quote((string) $code).' THEN '.(int) $categoryId;
            }
            $case .= ' ELSE tracking_category_id END';

            TrackerDatabase::table('tracking_daily_products')
                ->where('metric_date', $date)
                ->whereIn('product_code', array_keys($chunk))
                ->update(['tracking_category_id' => DB::raw($case)]);
        }
    }

    /**
     * Product paid and sale for orders placed on this day. Same split as the product page: order amount_paid shared by line_total.
     */
    private function writeAllocatedProductSold(string $date, Carbon $now): void
    {
        TrackerDatabase::table('tracking_daily_products')->where('metric_date', $date)->update([
            'sold_qty' => 0,
            'sale_amount' => 0,
            'updated_at' => $now,
        ]);
        TrackerDatabase::table('tracking_daily_summaries')->where('metric_date', $date)->update([
            'product_paid_units' => 0,
            'product_sale_amount' => 0,
            'updated_at' => $now,
        ]);
        foreach ($this->productJourneyByHour as $hour => $bucket) {
            $this->productJourneyByHour[$hour]['paid'] = 0;
            $this->productJourneyByHour[$hour]['sale'] = 0.0;
        }

        $orders = TrackerDatabase::table('tracking_orders as o')
            ->where('o.metric_date', $date)
            ->get(['o.id', 'o.ordered_at', 'o.amount_paid']);

        if ($orders->isEmpty()) {
            return;
        }

        $details = TrackerDatabase::table('tracking_order_details as od')
            ->join('tracking_product as p', 'p.id', '=', 'od.tracking_product_id')
            ->whereIn('od.tracking_order_id', $orders->pluck('id'))
            ->get(['od.tracking_order_id', 'p.code as product_code', 'od.qty', 'od.line_total']);

        $linesByOrder = [];
        foreach ($details as $line) {
            $linesByOrder[(int) $line->tracking_order_id][] = $line;
        }

        $totals = [];
        foreach ($orders as $order) {
            $lines = $linesByOrder[(int) $order->id] ?? [];
            if ($lines === []) {
                continue;
            }

            $amountPaid = (float) $order->amount_paid;
            $weightSum = 0.0;
            foreach ($lines as $line) {
                $weight = (float) $line->line_total;
                if ($weight <= 0) {
                    $weight = (float) max(1, (int) $line->qty);
                }
                $weightSum += $weight;
            }
            if ($weightSum <= 0) {
                $weightSum = (float) count($lines);
            }

            $paidHour = $this->localHour($order->ordered_at);

            foreach ($lines as $line) {
                $code = $this->productCode((string) $line->product_code);
                if ($code === '') {
                    continue;
                }

                $weight = (float) $line->line_total;
                if ($weight <= 0) {
                    $weight = (float) max(1, (int) $line->qty);
                }

                $qty = (int) $line->qty;
                $totals[$code] ??= ['qty' => 0, 'revenue' => 0.0];
                $share = $amountPaid * ($weight / $weightSum);
                $totals[$code]['qty'] += $qty;
                $totals[$code]['revenue'] += $share;
                $this->productJourneyByHour[$paidHour]['paid'] += $qty;
                $this->productJourneyByHour[$paidHour]['sale'] += $share;
            }
        }

        if ($totals === []) {
            return;
        }

        $rows = [];
        foreach ($totals as $code => $row) {
            $rows[] = [
                'metric_date' => $date,
                'product_code' => $code,
                'sold_qty' => (int) $row['qty'],
                'sale_amount' => round((float) $row['revenue'], 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        TrackerDatabase::table('tracking_daily_products')->upsert(
            $rows,
            ['metric_date', 'product_code'],
            ['sold_qty', 'sale_amount', 'updated_at'],
        );

        $roundedDay = 0.0;
        foreach ($totals as $row) {
            $roundedDay += round((float) $row['revenue'], 2);
        }
        $roundedDay = round($roundedDay, 2);
        $roundedHours = 0.0;
        $adjustHour = 0;
        $largestHourSale = -1.0;
        foreach ($this->productJourneyByHour as $hour => $bucket) {
            $rounded = round((float) $bucket['sale'], 2);
            $this->productJourneyByHour[$hour]['sale'] = $rounded;
            $roundedHours += $rounded;
            if ($rounded > $largestHourSale) {
                $largestHourSale = $rounded;
                $adjustHour = $hour;
            }
        }
        $diff = round($roundedDay - $roundedHours, 2);
        if ($diff !== 0.0) {
            $this->productJourneyByHour[$adjustHour]['sale'] = round($this->productJourneyByHour[$adjustHour]['sale'] + $diff, 2);
        }

        $this->storeProductJourneyDayTotals($date, $now);
    }

    /**
     * Category paid and sale are the sum of the product rows stored for that category.
     */
    private function rollCategorySoldFromProducts(string $date, Carbon $now): void
    {
        TrackerDatabase::table('tracking_daily_categories')->where('metric_date', $date)->update([
            'sold_qty' => 0,
            'orders' => 0,
            'sale_amount' => 0,
            'updated_at' => $now,
        ]);

        $sold = TrackerDatabase::table('tracking_daily_products')
            ->where('metric_date', $date)
            ->whereNotNull('tracking_category_id')
            ->groupBy('tracking_category_id')
            ->selectRaw('tracking_category_id, SUM(sold_qty) as sold_qty, SUM(sale_amount) as sale_amount')
            ->get();

        if ($sold->isEmpty()) {
            return;
        }

        $rows = [];
        foreach ($sold as $row) {
            if ((int) $row->sold_qty === 0 && (float) $row->sale_amount == 0.0) {
                continue;
            }

            $rows[] = [
                'metric_date' => $date,
                'tracking_category_id' => (int) $row->tracking_category_id,
                'sold_qty' => (int) $row->sold_qty,
                'orders' => 0,
                'sale_amount' => round((float) $row->sale_amount, 2),
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        if ($rows === []) {
            return;
        }

        TrackerDatabase::table('tracking_daily_categories')->upsert(
            $rows,
            ['metric_date', 'tracking_category_id'],
            ['sold_qty', 'orders', 'sale_amount', 'updated_at'],
        );
    }

    /**
     * Same source and medium is one traffic row, for every source. The id is the lookup row for that source name.
     *
     * @param  array<string, array<string, mixed>>  $audience
     * @return array<string, array<string, mixed>>
     */
    private function mergeTrafficAudienceRows(array $audience): array
    {
        $merged = [];
        $traffic = [];

        foreach ($audience as $row) {
            if ((int) $row['type'] !== TrackingDailyAudience::TYPE_TRAFFIC_SOURCE) {
                $id = $row['type'].'|'.$row['dimension_id'].'|'.$row['source'].'|'.$row['medium'];
                if (! isset($merged[$id])) {
                    $merged[$id] = $row;

                    continue;
                }

                foreach (['sessions', 'add_to_carts', 'begin_checkouts', 'proceed_checkouts', 'payments', 'sold_qty', 'sale_amount'] as $column) {
                    $merged[$id][$column] += $row[$column];
                }

                continue;
            }

            [$source, $medium] = $this->trafficSourceMedium((string) $row['source'], (string) $row['medium']);
            $bucket = $source."\0".$medium;
            $dimensionId = $this->trafficDimensionId($source, $medium);

            if (! isset($traffic[$bucket])) {
                $row['source'] = $source;
                $row['medium'] = $medium;
                $row['dimension_id'] = $dimensionId;
                $traffic[$bucket] = $row;

                continue;
            }

            if ($dimensionId > 0) {
                $traffic[$bucket]['dimension_id'] = $dimensionId;
            }

            foreach (['sessions', 'add_to_carts', 'begin_checkouts', 'proceed_checkouts', 'payments', 'sold_qty', 'sale_amount'] as $column) {
                $traffic[$bucket][$column] += $row[$column];
            }
        }

        foreach ($traffic as $row) {
            $merged[$row['type'].'|'.$row['dimension_id'].'|'.$row['source'].'|'.$row['medium']] = $row;
        }

        return $merged;
    }

    /** Remove category day rows left with no metrics after funnel + sold refresh. */
    private function purgeEmptyDailyCategoryRows(string $date): void
    {
        TrackerDatabase::table('tracking_daily_categories')
            ->where('metric_date', $date)
            ->where('category_views', 0)
            ->where('product_views', 0)
            ->where('add_to_carts', 0)
            ->where('proceed_checkouts', 0)
            ->where('sold_qty', 0)
            ->where('orders', 0)
            ->where('sale_amount', 0)
            ->delete();
    }

    /** A blank device or browser is Unknown. Drop the zero row so it does not show as a second Unknown. */
    private function purgeEmptyDeviceBrowserAudienceRows(string $date): void
    {
        TrackerDatabase::table('tracking_daily_audiences')
            ->where('metric_date', $date)
            ->whereIn('type', [
                TrackingDailyAudience::TYPE_DEVICE,
                TrackingDailyAudience::TYPE_BROWSER,
            ])
            ->where('sessions', 0)
            ->where('views', 0)
            ->where('add_to_carts', 0)
            ->where('begin_checkouts', 0)
            ->where('proceed_checkouts', 0)
            ->where('payments', 0)
            ->where('sold_qty', 0)
            ->where('sale_amount', 0)
            ->delete();
    }

    /** Drop a zeroed traffic row left after that source and medium was merged into one id. */
    private function purgeEmptyTrafficAudienceRows(string $date): void
    {
        TrackerDatabase::table('tracking_daily_audiences')
            ->where('metric_date', $date)
            ->where('type', TrackingDailyAudience::TYPE_TRAFFIC_SOURCE)
            ->where('sessions', 0)
            ->where('views', 0)
            ->where('add_to_carts', 0)
            ->where('begin_checkouts', 0)
            ->where('proceed_checkouts', 0)
            ->where('payments', 0)
            ->where('sold_qty', 0)
            ->where('sale_amount', 0)
            ->delete();
    }

    // ===== 6. Status marks =====

    /**
     * @param  array<string, mixed>  $sessionRow
     */
    private function markSessionSynced(int $sessionPk, mixed $sessionUpdatedAt, array $sessionRow): bool
    {
        $updated = TrackerDatabase::table('activity_ecom_user')
            ->where('id', $sessionPk)
            ->where('updated_at', $sessionUpdatedAt)
            ->update([
                'is_sync' => ActivityEcomUser::SYNC_DONE,
                'sync_at' => now(),
                'sync_try' => 0,
            ]);

        if ($updated === 0) {
            Log::info('tracker.dashboard.sync: session changed during sync, stays pending', [
                'activity_ecom_user_id' => $sessionPk,
            ]);

            return false;
        }

        return true;
    }

    private function markSessionFailed(int $sessionPk): void
    {
        TrackerDatabase::table('activity_ecom_user')->where('id', $sessionPk)->update([
            'is_sync' => ActivityEcomUser::SYNC_FAILED,
            'sync_try' => DB::raw('sync_try + 1'),
            'sync_at' => now(),
        ]);
    }

    /**
     * @param  Collection<int, object>  $actions
     */
    private function markActionsSynced(Collection $actions): void
    {
        $ids = $actions->pluck('id')->map(fn ($id) => (int) $id)->filter()->all();

        if ($ids === []) {
            return;
        }

        TrackerDatabase::table('activity_ecom_user_actions')
            ->whereIn('id', $ids)
            ->update([
                'is_sync' => ActivityEcomUserAction::SYNC_DONE,
                'sync_at' => now(),
            ]);
    }

    /**
     * Failed actions are picked up again with their session on the next retry.
     *
     * @param  Collection<int, object>  $actions
     */
    private function markActionsFailed(Collection $actions): void
    {
        $ids = $actions->pluck('id')->map(fn ($id) => (int) $id)->filter()->all();

        if ($ids === []) {
            return;
        }

        TrackerDatabase::table('activity_ecom_user_actions')
            ->whereIn('id', $ids)
            ->update([
                'is_sync' => ActivityEcomUserAction::SYNC_FAILED,
                'sync_try' => DB::raw('sync_try + 1'),
                'sync_at' => now(),
            ]);
    }
}
