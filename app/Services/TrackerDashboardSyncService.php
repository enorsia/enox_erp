<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies closed sessions from activity_ecom_user (+ actions) into tracking_* tables.
 *
 * Flow: main visitor (visitor_id) → daily visitor (calendar day) → session (session_id)
 * → session details (ip, device, browser, …) → action dimensions → tracking_session_p_cat.
 *
 * activity_ecom_user.is_sync: 0 = pending, 1 = done, 2 = failed (retry until sync_try >= MAX).
 * Action rows use the same flags; only ids loaded in the batch are marked done.
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

    /** @var array<string, int|null> */
    private array $cache = [];

    /**
     * PLANNER: find pending sessions and split their ids into groups (one group = one chunk job).
     *
     * @return list<list<int>>
     */
    public function planChunks(int $chunkSize = 25): array
    {
        $query = DB::table('activity_ecom_user as u');

        $query->where('u.sync_try', '<', self::MAX_SYNC_TRY);
        $this->applyPendingOrRetryFilter($query, now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES), 'u');

        return $query->orderBy('u.id')
            ->pluck('u.id')
            ->map(fn ($id) => (int) $id)
            ->chunk(max(1, $chunkSize))
            ->map(fn ($ids) => $ids->values()->all())
            ->values()
            ->all();
    }

    /**
     * CHUNK JOB: sync ONLY these session ids (same filters as the queue, so a retry is safe).
     *
     * @param  list<int>  $ids
     */
    public function syncSessionIds(array $ids): void
    {
        $this->cache = [];

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
            Log::warning('tracker.dashboard.sync: missing visitor_id', [
                'activity_ecom_user_id' => $sessionPk,
                'session_id' => $rawSessionId,
            ]);

            try {
                $this->markSessionSkippedBadData($sessionPk);
            } catch (Throwable $inner) {
                Log::error('tracker.dashboard.sync: could not mark bad session', [
                    'activity_ecom_user_id' => $sessionPk,
                    'exception' => $inner,
                ]);
            }

            return true;
        }

        $cacheBefore = $this->cache;
        $sessionUpdatedAt = $sessionRow['updated_at'] ?? null;

        try {
            DB::transaction(function () use ($sessionRow, $actions, $sessionPk, $cacheBefore, $sessionUpdatedAt): void {
                // Restored on each attempt so rolled-back dimension ids are not reused after deadlock.
                $this->cache = $cacheBefore;
                $this->upsertTrackingFromSession($sessionRow, $actions);
                // If the session row changed while syncing, keep tracking data but leave pending (no throw).
                if ($this->markSessionSynced($sessionPk, $sessionUpdatedAt)) {
                    $this->markActionsSynced($actions);
                }
            }, 3);

            return true;
        } catch (Throwable $e) {
            $this->cache = $cacheBefore;

            Log::error('tracker.dashboard.sync: session failed', [
                'activity_ecom_user_id' => $sessionPk,
                'session_id' => $rawSessionId,
                'exception' => $e,
            ]);

            try {
                $this->markSessionFailed($sessionPk);
            } catch (Throwable $inner) {
                Log::error('tracker.dashboard.sync: could not mark session failed', [
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

        $query = DB::table('activity_ecom_user as u')
            ->select('u.*')
            ->where('u.id', '>', $afterId);

        // Fan-out: a chunk job passes its own ids, so it only works on those sessions.
        if ($onlyIds !== null) {
            $query->whereIn('u.id', $onlyIds);
        }

        $query->where('u.sync_try', '<', self::MAX_SYNC_TRY);
        $this->applyPendingOrRetryFilter($query, $retryAfter, 'u');

        return $query->orderBy('u.id')->limit($limit)->get();
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
     * @param  list<string>  $sessionIds
     * @return Collection<string, Collection<int, object>>
     */
    private function loadUnsyncedActionsForSessions(array $sessionIds): Collection
    {
        if ($sessionIds === []) {
            return collect();
        }

        return DB::table('activity_ecom_user_actions')
            ->whereIn('session_id', $sessionIds)
            ->whereIn('is_sync', [
                ActivityEcomUserAction::SYNC_PENDING,
                ActivityEcomUserAction::SYNC_FAILED,
            ])
            ->orderBy('id')
            ->get()
            ->groupBy('session_id');
    }

    // ===== 2. Visitor / session =====

    /**
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function upsertTrackingFromSession(array $sessionRow, Collection $actions): void
    {
        $sessionId = $this->normalizeSessionId((string) ($sessionRow['session_id'] ?? ''));
        $visitorId = trim((string) ($sessionRow['visitor_id'] ?? ''));

        if ($sessionId === '') {
            throw new \InvalidArgumentException('Missing session_id');
        }

        $deviceId = $this->remember('device:'.($sessionRow['device_type'] ?? ''), fn () => $this->findOrCreateDevice((string) ($sessionRow['device_type'] ?? '')));
        $browserId = $this->remember('browser:'.($sessionRow['browser'] ?? ''), fn () => $this->findOrCreateBrowser((string) ($sessionRow['browser'] ?? '')));
        $trafficId = $this->remember(
            'traffic:'.($sessionRow['utm_source'] ?? '').'|'.($sessionRow['utm_medium'] ?? ''),
            fn () => $this->findOrCreateTrafficSource(
                (string) ($sessionRow['utm_source'] ?? ''),
                (string) ($sessionRow['utm_medium'] ?? ''),
            ),
        );

        $mainVisitorId = $this->findOrCreateMainVisitor($visitorId, $sessionRow);
        $visitDate = $this->visitDateForSession($sessionRow);
        $dailyVisitorRowId = $this->findOrCreateDailyVisitor($mainVisitorId, $visitorId, $visitDate);

        $trackingSessionId = $this->upsertTrackingSession($sessionRow, $sessionId, $dailyVisitorRowId, $actions);
        $this->upsertSessionDetails($trackingSessionId, $sessionRow, $actions, $deviceId, $browserId, $trafficId);

        $this->syncActionsForSession($trackingSessionId, $actions);
    }

    // ===== 3. Actions → p_cat =====

    /**
     * @param  Collection<int, object>  $actions
     */
    private function syncActionsForSession(int $trackingSessionId, Collection $actions): void
    {
        $pCatRows = [];

        foreach ($actions as $action) {
            foreach ($this->actionCommerceLines($action) as $line) {
                $productId = $this->findOrCreateProduct(
                    $line['product_code'],
                    $line['sku'],
                    $line['product_name'],
                );

                if ($productId === null) {
                    continue;
                }

                $categoryId = $this->resolveCategoryIdFromLine($line);
                $colorId = $this->findOrCreateColor($line['color_code'], $line['color_name']);
                $sizeId = $this->findOrCreateSize($line['size_code'], $line['size_name']);

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
        }

        if ($pCatRows !== []) {
            DB::table('tracking_session_p_cat')->insert(array_values($pCatRows));
        }
    }

    /**
     * @return list<array{product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}>
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

        return [$this->commerceLineFromScalars($action, $json)];
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
     * @return array{product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}|null
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

        return [
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
     * @return array{product_code: string, sku: string, product_name: string, category_code: string, category_name: string, department_name: string, color_code: string, color_name: string, size_code: string, size_name: string}
     */
    private function commerceLineFromScalars(object $action, array $json = []): array
    {
        return [
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
        if ($categoryName === '') {
            return null;
        }

        return $this->resolveCategoryId(
            trim($line['category_code']),
            $categoryName,
            $line['department_name'],
            trim($line['department_code'] ?? ''),
        );
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

        $existing = DB::table('tracking_main_visitor')->where('visitor_id', $visitorId)->first();

        if ($existing) {
            $this->updateMainVisitorById((int) $existing->id, $name, $email, $phone, $existing);
            $this->cache[$cacheKey] = (int) $existing->id;

            return (int) $existing->id;
        }

        $id = $this->insertOrFetch('tracking_main_visitor', [
            'visitor_id' => $visitorId,
            'name' => $name,
            'email' => $email,
            'phone' => $phone,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['visitor_id' => $visitorId]);

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
        $existing ??= DB::table('tracking_main_visitor')->where('id', $mainVisitorId)->first();

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
     * @param  array<string, mixed>  $sessionRow
     */
    private function visitDateForSession(array $sessionRow): string
    {
        $at = $sessionRow['created_at'] ?? $sessionRow['last_active_at'] ?? now();

        return Carbon::parse($at)->timezone(TrackerTime::timezone())->toDateString();
    }

    private function findOrCreateDailyVisitor(int $mainVisitorId, string $visitorId, string $visitDate): int
    {
        $cacheKey = 'daily_visitor:'.$mainVisitorId.'|'.$visitDate;

        return $this->remember($cacheKey, function () use ($mainVisitorId, $visitorId, $visitDate): int {
            $existing = DB::table('tracking_daily_visitor')
                ->where('tracking_main_visitor_id', $mainVisitorId)
                ->where('visit_date', $visitDate)
                ->first();

            if ($existing) {
                return (int) $existing->id;
            }

            $dailyVisitorId = md5($visitorId.'|'.$visitDate);

            return $this->insertOrFetch('tracking_daily_visitor', [
                'tracking_main_visitor_id' => $mainVisitorId,
                'daily_visitor_id' => $dailyVisitorId,
                'visit_date' => $visitDate,
                'created_at' => now(),
                'updated_at' => now(),
            ], [
                'tracking_main_visitor_id' => $mainVisitorId,
                'visit_date' => $visitDate,
            ]);
        }) ?? throw new \RuntimeException('Failed to resolve daily visitor');
    }

    /**
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions  Unsynced actions included in this sync pass.
     */
    private function upsertTrackingSession(array $sessionRow, string $sessionId, int $dailyVisitorId, Collection $actions): int
    {
        $existing = DB::table('tracking_session')->where('session_id', $sessionId)->first();

        $payload = array_merge([
            'tracking_daily_visitor_id' => $dailyVisitorId,
            'name' => $sessionRow['user_name'] ?? null,
            'email' => $sessionRow['user_email'] ?? null,
            'phone' => $sessionRow['user_phone'] ?? null,
            'is_logged_in' => (int) (bool) ($sessionRow['is_logged_in'] ?? false),
            'last_active_at' => $sessionRow['last_active_at'] ?? null,
            'has_order' => (int) (
                (bool) ($sessionRow['has_payment_success'] ?? false)
                || ($existing !== null && (bool) ($existing->has_order ?? false))
            ),
        ], $this->mergedSessionRollups($existing, $sessionRow, $actions));

        if ($existing) {
            $this->applyTableUpdates('tracking_session', $existing, $payload);

            return (int) $existing->id;
        }

        $payload['session_id'] = $sessionId;
        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        return $this->insertOrFetch('tracking_session', $payload, ['session_id' => $sessionId]);
    }

    /**
     * Roll up metrics on each sync: actions sum, duration and funnel stage take the higher value.
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     * @return array{duration_seconds: int, actions_count: int, latest_funnel_stage: ?int}
     */
    private function mergedSessionRollups(?object $existing, array $sessionRow, Collection $actions): array
    {
        $incomingDuration = (int) ($sessionRow['session_duration_seconds'] ?? 0);
        $incomingFunnel = $this->funnelStageCode($sessionRow['latest_funnel_stage'] ?? null);
        $batchActions = $actions->count();

        if ($existing === null) {
            return [
                'duration_seconds' => $incomingDuration,
                'actions_count' => $batchActions,
                'latest_funnel_stage' => $incomingFunnel,
            ];
        }

        return [
            'duration_seconds' => max((int) ($existing->duration_seconds ?? 0), $incomingDuration),
            'actions_count' => (int) ($existing->actions_count ?? 0) + $batchActions,
            'latest_funnel_stage' => $this->maxFunnelStage($existing->latest_funnel_stage ?? null, $incomingFunnel),
        ];
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
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function upsertSessionDetails(
        int $trackingSessionId,
        array $sessionRow,
        Collection $actions,
        ?int $deviceId,
        ?int $browserId,
        ?int $trafficId,
    ): void {
        $referer = $this->earliestRefererFromActions($actions);

        $payload = [
            'tracking_session_id' => $trackingSessionId,
            'ip' => $sessionRow['ip'] ?? null,
            'tracking_device_id' => $deviceId,
            'tracking_browser_id' => $browserId,
            'tracking_traffic_source_id' => $trafficId,
            'utm_source' => $sessionRow['utm_source'] ?? null,
            'utm_medium' => $sessionRow['utm_medium'] ?? null,
            'utm_campaign' => $sessionRow['utm_campaign'] ?? null,
            'landing_page' => $sessionRow['landing_page'] ?? null,
            'user_agent' => $sessionRow['user_agent'] ?? null,
        ];

        $existing = DB::table('tracking_session_details')
            ->where('tracking_session_id', $trackingSessionId)
            ->first();

        if ($existing) {
            if ($referer !== null && trim((string) ($existing->referer ?? '')) === '') {
                $payload['referer'] = $referer;
            }

            $this->applyTableUpdates('tracking_session_details', $existing, $payload);

            return;
        }

        $payload['referer'] = $referer;

        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        try {
            DB::table('tracking_session_details')->insert($payload);
        } catch (QueryException) {
            // Unique tracking_session_id — race with another worker.
        }
    }

    // ===== 4. Lookup tables =====

    private function findOrCreateDevice(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $name = Str::limit($name, 50, '');

        return $this->insertOrFetch('tracking_device', [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['name' => $name]);
    }

    private function findOrCreateBrowser(string $name): ?int
    {
        $name = trim($name);
        if ($name === '') {
            return null;
        }

        $name = Str::limit($name, 50, '');

        return $this->insertOrFetch('tracking_browser', [
            'name' => $name,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['name' => $name]);
    }

    private function findOrCreateTrafficSource(string $source, string $medium): ?int
    {
        $source = Str::limit(trim($source !== '' ? $source : '(direct)'), 100, '');
        $medium = Str::limit(trim($medium !== '' ? $medium : 'none'), 100, '');

        $existing = DB::table('tracking_traffic_source')->where('name', $source)->first();
        if ($existing !== null && (string) ($existing->medium ?? '') !== $medium) {
            DB::table('tracking_traffic_source')->where('id', $existing->id)->update([
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

    private function findOrCreateProduct(string $code, string $sku, string $title): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $code = Str::limit($code, 100, '');
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
                    DB::table('tracking_product')->where('id', $existing->id)->update($updates);
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
    private function lookupRowQuery(string $table, array $where): \Illuminate\Database\Query\Builder
    {
        $query = DB::table($table);

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
            $existing = DB::table('tracking_color')->where('name', $nameVal)->first();
            if ($existing !== null && $codeVal !== null && (string) ($existing->code ?? '') !== $codeVal) {
                DB::table('tracking_color')->where('id', $existing->id)->update([
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
            $existing = DB::table('tracking_size')->where('name', $nameVal)->first();
            if ($existing !== null && $codeVal !== null && (string) ($existing->code ?? '') !== $codeVal) {
                DB::table('tracking_size')->where('id', $existing->id)->update([
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
            $existing = DB::table('tracking_category')
                ->whereNull('parent_id')
                ->where('name', $departmentName)
                ->first();

            if ($existing !== null) {
                if ($codeVal !== null && (string) ($existing->code ?? '') !== $codeVal) {
                    DB::table('tracking_category')->where('id', $existing->id)->update([
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
            $existing = DB::table('tracking_category')
                ->where('parent_id', $parentId)
                ->where('name', $name)
                ->first();

            if ($existing !== null) {
                if ($code !== null && (string) ($existing->code ?? '') !== $code) {
                    DB::table('tracking_category')->where('id', $existing->id)->update([
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
        $existingId = $this->lookupRowId($table, $where);
        if ($existingId !== null) {
            return $existingId;
        }

        try {
            return (int) DB::table($table)->insertGetId($row);
        } catch (QueryException $exception) {
            $existingId = $this->lookupRowId($table, $where);
            if ($existingId !== null) {
                return $existingId;
            }

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function lookupRowId(string $table, array $where): ?int
    {
        $id = $this->lookupRowQuery($table, $where)->value('id');

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
        DB::table($table)->where('id', $existing->id)->update($updates);
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

    // ===== 5. Status marks =====

    private function markSessionSynced(int $sessionPk, mixed $sessionUpdatedAt): bool
    {
        $updated = DB::table('activity_ecom_user')
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

    private function markSessionSkippedBadData(int $sessionPk): void
    {
        DB::table('activity_ecom_user')->where('id', $sessionPk)->update([
            'is_sync' => ActivityEcomUser::SYNC_FAILED,
            'sync_try' => self::MAX_SYNC_TRY,
            'sync_at' => now(),
        ]);
    }

    private function markSessionFailed(int $sessionPk): void
    {
        DB::table('activity_ecom_user')->where('id', $sessionPk)->update([
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

        DB::table('activity_ecom_user_actions')
            ->whereIn('id', $ids)
            ->update([
                'is_sync' => ActivityEcomUserAction::SYNC_DONE,
                'sync_at' => now(),
            ]);
    }
}
