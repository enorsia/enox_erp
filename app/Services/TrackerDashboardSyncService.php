<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\TrackingDailyAudience;
use App\Models\TrackingDailyDuration;
use App\Models\TrackingOrderAddress;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
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
            'unique' => ['metric_date', 'tracking_product_id'],
            'counters' => ['product_views', 'add_to_carts', 'proceed_checkouts'],
        ],
        'tracking_daily_audiences' => [
            'unique' => ['metric_date', 'type', 'dimension_id', 'source', 'medium'],
            'counters' => ['views'],
        ],
    ];

    /** @var array<string, int|null> */
    private array $cache = [];

    /** @var array<string, true> metric dates whose daily totals are recalculated after the batch */
    private array $touchedDates = [];

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
                // Loaded actions are counted into the daily tables now, so they must be done even if the
                // session row changed meanwhile (it stays pending and only its new actions are synced next time).
                $this->markActionsSynced($actions);
                $this->markSessionSynced($sessionPk, $sessionUpdatedAt);
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
                $this->markActionsFailed($actions);
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

        $sessionRow = $this->withCheckoutCustomer($sessionRow, $actions);

        $deviceId = $this->remember('device:'.($sessionRow['device_type'] ?? ''), fn () => $this->findOrCreateDevice((string) ($sessionRow['device_type'] ?? '')));
        $browserId = $this->remember('browser:'.($sessionRow['browser'] ?? ''), fn () => $this->findOrCreateBrowser((string) ($sessionRow['browser'] ?? '')));

        $mainVisitorId = $this->findOrCreateMainVisitor($visitorId, $sessionRow);
        $visitDate = $this->visitDateForSession($sessionRow);
        $dailyVisitorRowId = $this->findOrCreateDailyVisitor($mainVisitorId, $visitorId, $visitDate);

        $trackingSessionId = $this->upsertTrackingSession($sessionRow, $sessionId, $dailyVisitorRowId, $actions);
        [$trafficId, $source, $medium] = $this->upsertSessionDetails($trackingSessionId, $sessionRow, $actions, $deviceId, $browserId);

        $audienceKeys = $this->audienceKeys($deviceId, $browserId, $trafficId, $source, $medium);

        $this->syncActionsForSession($trackingSessionId, $visitDate, $actions, $audienceKeys);
        $this->upsertRecoverableSale($trackingSessionId, $visitDate, $sessionRow, $actions);
        $this->touchedDates[$visitDate] = true;
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
     * @param  list<array{type: int, dimension_id: int, source: string, medium: string}>  $audienceKeys
     */
    private function syncActionsForSession(int $trackingSessionId, string $visitDate, Collection $actions, array $audienceKeys): void
    {
        $pCatRows = [];
        $counters = [];

        foreach ($actions as $action) {
            $type = $this->metricActionType($action);
            $counted = $this->localDate($action->created_at ?? null) === $visitDate;
            $orderLines = [];

            if ($counted) {
                $this->countAction($counters, $type, $visitDate, $audienceKeys);
            }

            foreach ($this->actionCommerceLines($action) as $line) {
                $productId = $this->findOrCreateProduct(
                    $line['product_code'],
                    $line['sku'],
                    $line['product_name'],
                );
                $categoryId = $this->resolveCategoryIdFromLine($line);

                if ($counted) {
                    $this->countLine($counters, $type, $visitDate, $productId, $categoryId);
                }

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
                $this->upsertOrder($trackingSessionId, $action, $orderLines);
            }
        }

        if ($pCatRows !== []) {
            DB::table('tracking_session_p_cat')->insert(array_values($pCatRows));
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
        return $this->localDate($sessionRow['created_at'] ?? $sessionRow['last_active_at'] ?? null);
    }

    private function localDate(mixed $at): string
    {
        return Carbon::parse($at ?? now())->timezone(TrackerTime::timezone())->toDateString();
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
     * A funnel flag never goes back to 0 once a sync has seen it.
     *
     * @param  array<string, mixed>  $sessionRow
     */
    private function stickyFlag(array $sessionRow, string $sourceColumn, ?object $existing, string $column): int
    {
        return (int) ((bool) ($sessionRow[$sourceColumn] ?? false) || (bool) ($existing->{$column} ?? false));
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
     * utm_source / utm_medium hold the dashboard traffic bucket (same rule as the master dashboard),
     * resolved on the first sync and kept afterwards so the day counters always use the same key.
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

        $existing = DB::table('tracking_session_details')
            ->where('tracking_session_id', $trackingSessionId)
            ->first();

        if ($existing?->tracking_traffic_source_id !== null) {
            $trafficId = (int) $existing->tracking_traffic_source_id;
            [$source, $medium] = $this->trafficSourceMedium($existing->utm_source, $existing->utm_medium);
        } else {
            // An empty referer makes the helper look up the session's first referer itself.
            $bucket = SessionTrafficAttribution::resolvedTrafficBucket((object) $sessionRow, [''], (string) ($existing?->referer ?: $referer));
            [$source, $medium] = $this->trafficSourceMedium($bucket['source'], $bucket['medium']);
            $trafficId = $this->remember("traffic:{$source}|{$medium}", fn () => $this->findOrCreateTrafficSource($source, $medium));
        }

        $payload = [
            'tracking_session_id' => $trackingSessionId,
            'ip' => $sessionRow['ip'] ?? null,
            'tracking_device_id' => $deviceId,
            'tracking_browser_id' => $browserId,
            'tracking_traffic_source_id' => $trafficId,
            'utm_source' => $source,
            'utm_medium' => $medium,
            'utm_campaign' => $sessionRow['utm_campaign'] ?? null,
            'landing_page' => $sessionRow['landing_page'] ?? null,
            'user_agent' => $sessionRow['user_agent'] ?? null,
        ];

        if ($existing) {
            if ($referer !== null && trim((string) ($existing->referer ?? '')) === '') {
                $payload['referer'] = $referer;
            }

            $this->applyTableUpdates('tracking_session_details', $existing, $payload);

            return [$trafficId, $source, $medium];
        }

        $payload['referer'] = $referer;

        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        try {
            DB::table('tracking_session_details')->insert($payload);
        } catch (QueryException) {
            // Unique tracking_session_id — race with another worker.
        }

        return [$trafficId, $source, $medium];
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

    /**
     * @return array{0: string, 1: string} source and medium with the "(direct)" / "none" defaults
     */
    private function trafficSourceMedium(?string $source, ?string $medium): array
    {
        $source = trim((string) $source);
        $medium = trim((string) $medium);

        return [
            Str::limit($source !== '' ? $source : '(direct)', 100, ''),
            Str::limit($medium !== '' ? $medium : 'none', 100, ''),
        ];
    }

    private function findOrCreateTrafficSource(string $source, string $medium): ?int
    {
        [$source, $medium] = $this->trafficSourceMedium($source, $medium);

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
    private function lookupRowQuery(string $table, array $where): Builder
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
    private function audienceKeys(?int $deviceId, ?int $browserId, ?int $trafficId, string $source, string $medium): array
    {
        return [
            ['type' => TrackingDailyAudience::TYPE_DEVICE, 'dimension_id' => $deviceId ?? 0, 'source' => '', 'medium' => ''],
            ['type' => TrackingDailyAudience::TYPE_BROWSER, 'dimension_id' => $browserId ?? 0, 'source' => '', 'medium' => ''],
            ['type' => TrackingDailyAudience::TYPE_TRAFFIC_SOURCE, 'dimension_id' => $trafficId ?? 0, 'source' => $source, 'medium' => $medium],
        ];
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
            $row->tracking_traffic_source_id !== null ? (int) $row->tracking_traffic_source_id : null,
            $source,
            $medium,
        );
    }

    /**
     * Once per action: device / browser / traffic source product views
     * (their funnel columns count sessions, see refreshDay).
     *
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     * @param  list<array{type: int, dimension_id: int, source: string, medium: string}>  $audienceKeys
     */
    private function countAction(array &$counters, string $type, string $date, array $audienceKeys): void
    {
        if ($type !== 'product_view') {
            return;
        }

        foreach ($audienceKeys as $key) {
            $this->bump($counters, 'tracking_daily_audiences', ['metric_date' => $date] + $key, 'views');
        }
    }

    /**
     * Once per product line of an action: category and product counts.
     * Day view totals are the category totals, like the master dashboard KPI.
     *
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     */
    private function countLine(array &$counters, string $type, string $date, ?int $productId, ?int $categoryId): void
    {
        $column = match ($type) {
            'category_view' => 'category_views',
            'product_view' => 'product_views',
            'add_to_cart' => 'add_to_carts',
            'proceed_checkout' => 'proceed_checkouts',
            default => null,
        };

        if ($column === null) {
            return;
        }

        if ($categoryId !== null) {
            $this->bump($counters, 'tracking_daily_categories', ['metric_date' => $date, 'tracking_category_id' => $categoryId], $column);

            if ($column === 'category_views' || $column === 'product_views') {
                $this->bump($counters, 'tracking_daily_summaries', ['metric_date' => $date], $column);
            }
        }

        if ($productId !== null && $column !== 'category_views') {
            $this->bump($counters, 'tracking_daily_products', ['metric_date' => $date, 'tracking_product_id' => $productId], $column);
        }
    }

    /**
     * @param  array<string, array<string, array<string, mixed>>>  $counters
     * @param  array<string, mixed>  $key
     */
    private function bump(array &$counters, string $table, array $key, string $column): void
    {
        $id = implode('|', $key);
        $counters[$table][$id] ??= $key;
        $counters[$table][$id][$column] = ($counters[$table][$id][$column] ?? 0) + 1;
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

            DB::table($table)->upsert($values, $unique, $update);
        }
    }

    /**
     * payment_success → tracking_orders (+ details, addresses). Keyed by order number, so a re-sync replaces it.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function upsertOrder(int $trackingSessionId, object $action, array $lines): void
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
        $metricDate = $this->localDate($orderedAt);
        $now = now();

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
            'created_at' => $now,
            'updated_at' => $now,
        ];

        DB::table('tracking_orders')->upsert(
            [$order],
            ['order_number'],
            array_keys(Arr::except($order, ['order_number', 'created_at'])),
        );

        $orderId = (int) DB::table('tracking_orders')->where('order_number', $orderNumber)->value('id');

        DB::table('tracking_order_details')->where('tracking_order_id', $orderId)->delete();

        if ($lines !== []) {
            DB::table('tracking_order_details')->insert(array_map(fn (array $line) => $line + [
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
            DB::table('tracking_order_addresses')->upsert(
                $addresses,
                ['tracking_order_id', 'type'],
                ['line_1', 'line_2', 'town_city', 'postcode', 'country', 'updated_at'],
            );
        }

        $this->touchedDates[$metricDate] = true;
    }

    /**
     * Add to cart / begin / proceed panels (master rule): the session's furthest funnel flag is that step
     * and its latest commerce action of the visit day is at that step too; the row holds that action's lines.
     * Payment rows come from the orders instead (refreshDay).
     *
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function upsertRecoverableSale(int $trackingSessionId, string $visitDate, array $sessionRow, Collection $actions): void
    {
        $stage = match (true) {
            (bool) ($sessionRow['has_payment_success'] ?? false) => 6,
            (bool) ($sessionRow['has_proceed_checkout'] ?? false) => 5,
            (bool) ($sessionRow['has_begin_checkout'] ?? false) => 4,
            (bool) ($sessionRow['has_add_to_cart'] ?? false) => 3,
            default => 0,
        };

        // Latest commerce action with product lines on the visit day (actions come in id order).
        $latest = null;
        foreach ($actions as $action) {
            $code = (int) $this->funnelStageCode($this->metricActionType($action));
            $at = (string) ($action->created_at ?? '');
            if ($code < 3 || $this->localDate($at) !== $visitDate) {
                continue;
            }

            $lines = array_filter($this->actionCommerceLines($action), fn (array $line) => $line['product_code'] !== '' || $line['product_name'] !== '');
            if ($lines !== [] && ($latest === null || [$at, $code] >= [$latest['at'], $latest['stage']])) {
                $latest = ['at' => $at, 'stage' => $code, 'lines' => $lines];
            }
        }

        $row = DB::table('tracking_recoverable_sales')
            ->where('metric_date', $visitDate)
            ->where('tracking_session_id', $trackingSessionId);

        if ($latest === null) {
            // Nothing new at a commerce step: a row of the same step from an earlier sync stays.
            $row->where('type', '<', $stage)->delete();

            return;
        }

        if ($latest['stage'] !== $stage || $stage === 6) {
            $row->where('type', '<', 6)->delete();

            return;
        }

        $now = now();

        DB::table('tracking_recoverable_sales')->upsert([[
            'metric_date' => $visitDate,
            'type' => $stage,
            'tracking_session_id' => $trackingSessionId,
            'qty' => array_sum(array_column($latest['lines'], 'qty')),
            'sale_value' => round(array_sum(array_column($latest['lines'], 'line_total')), 2),
            'occurred_at' => $latest['at'],
            'created_at' => $now,
            'updated_at' => $now,
        ]], ['metric_date', 'tracking_session_id'], ['type', 'qty', 'sale_value', 'occurred_at', 'updated_at']);
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
                Log::error('tracker.dashboard.sync: daily totals failed', ['date' => $date, 'exception' => $e]);
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

        $sessions = DB::table('tracking_session as ts')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->leftJoin('tracking_session_details as sd', 'sd.tracking_session_id', '=', 'ts.id')
            ->where('dv.visit_date', $date)
            ->get([
                'ts.tracking_daily_visitor_id', 'ts.duration_seconds',
                'ts.has_add_to_cart', 'ts.has_begin_checkout', 'ts.has_proceed_checkout', 'ts.has_order',
                'sd.tracking_device_id', 'sd.tracking_browser_id', 'sd.tracking_traffic_source_id', 'sd.utm_source', 'sd.utm_medium',
            ]);

        // same_day: the order's session started that day too (device / traffic / payment panel need both, like master).
        $orders = DB::table('tracking_orders as o')
            ->leftJoin('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->leftJoin('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->leftJoin('tracking_session_details as sd', 'sd.tracking_session_id', '=', 'o.tracking_session_id')
            ->where('o.metric_date', $date)
            ->selectRaw('dv.visit_date = ? as same_day', [$date])
            ->addSelect([
                'o.tracking_session_id', 'o.items_qty', 'o.amount_paid', 'o.ordered_at',
                'sd.tracking_device_id', 'sd.tracking_browser_id', 'sd.tracking_traffic_source_id', 'sd.utm_source', 'sd.utm_medium',
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
            $summary['payments'] += (int) (bool) $session->has_order;

            $bucket = TrackingDailyDuration::bucketFor($seconds);
            $durations[$bucket]['sessions']++;
            $durations[$bucket]['duration_seconds'] += $seconds;

            foreach ($this->audienceKeysFromDetails($session) as $key) {
                $id = implode('|', $key);
                $audience[$id] ??= $key + $emptyAudience;
                $audience[$id]['sessions']++;
                foreach ($funnel as $column => $value) {
                    $audience[$id][$column] += $value;
                }
            }
        }

        $paidSessions = [];
        $paymentRows = [];

        foreach ($orders as $order) {
            $summary['items_sold'] += (int) $order->items_qty;
            $summary['sale_amount'] += (float) $order->amount_paid;

            if (! $order->same_day) {
                continue;
            }

            $sessionId = (int) $order->tracking_session_id;
            $paymentRows[$sessionId] ??= ['qty' => 0, 'sale_value' => 0, 'occurred_at' => $order->ordered_at];
            $paymentRows[$sessionId]['qty'] += max(1, (int) $order->items_qty);
            $paymentRows[$sessionId]['sale_value'] += (float) $order->amount_paid;
            $paymentRows[$sessionId]['occurred_at'] = max($paymentRows[$sessionId]['occurred_at'], $order->ordered_at);

            foreach ($this->audienceKeysFromDetails($order) as $key) {
                $id = implode('|', $key);
                $audience[$id] ??= $key + $emptyAudience;
                $audience[$id]['payments'] += (int) ! isset($paidSessions[$id][$sessionId]);
                $audience[$id]['sold_qty'] += (int) $order->items_qty;
                $audience[$id]['sale_amount'] += (float) $order->amount_paid;
                $paidSessions[$id][$sessionId] = true;
            }
        }

        $this->refreshPaymentRows($date, $paymentRows, $now);

        $drops = DB::table('tracking_recoverable_sales')
            ->where('metric_date', $date)
            ->whereIn('type', [3, 4, 5])
            ->groupBy('type')
            ->pluck(DB::raw('COUNT(*)'), 'type');
        $summary['cart_drops'] = (int) ($drops[3] ?? 0);
        $summary['checkout_drops'] = (int) ($drops[4] ?? 0);
        $summary['proceed_drops'] = (int) ($drops[5] ?? 0);

        $summary['sale_amount'] = round($summary['sale_amount'], 2);

        DB::table('tracking_daily_summaries')->upsert(
            [['metric_date' => $date] + $summary + ['created_at' => $now, 'updated_at' => $now]],
            ['metric_date'],
            [...array_keys($summary), 'updated_at'],
        );

        $durationRows = [];
        foreach ($durations as $bucket => $values) {
            $durationRows[] = ['metric_date' => $date, 'bucket' => $bucket] + $values + ['created_at' => $now, 'updated_at' => $now];
        }
        DB::table('tracking_daily_durations')->upsert($durationRows, ['metric_date', 'bucket'], ['sessions', 'duration_seconds', 'updated_at']);

        $audienceColumns = [...array_keys($emptyAudience), 'conversion_rate'];
        DB::table('tracking_daily_audiences')
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

            DB::table('tracking_daily_audiences')->upsert(
                $audienceRows,
                ['metric_date', 'type', 'dimension_id', 'source', 'medium'],
                [...$audienceColumns, 'updated_at'],
            );
        }

        $this->refreshSoldTotals($date, 'tracking_daily_categories', 'tracking_category_id', true, $now);
        $this->refreshSoldTotals($date, 'tracking_daily_products', 'tracking_product_id', false, $now);
    }

    /**
     * Payment success panel: one row per paying session of the day, rebuilt from its orders.
     *
     * @param  array<int, array{qty: int, sale_value: float, occurred_at: string}>  $rows
     */
    private function refreshPaymentRows(string $date, array $rows, Carbon $now): void
    {
        DB::table('tracking_recoverable_sales')
            ->where('metric_date', $date)
            ->where('type', 6)
            ->whereNotIn('tracking_session_id', array_keys($rows) ?: [0])
            ->delete();

        if ($rows === []) {
            return;
        }

        $values = [];
        foreach ($rows as $sessionId => $row) {
            $values[] = [
                'metric_date' => $date,
                'type' => 6,
                'tracking_session_id' => $sessionId,
                'qty' => $row['qty'],
                'sale_value' => round($row['sale_value'], 2),
                'occurred_at' => $row['occurred_at'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('tracking_recoverable_sales')->upsert(
            $values,
            ['metric_date', 'tracking_session_id'],
            ['type', 'qty', 'sale_value', 'occurred_at', 'updated_at'],
        );
    }

    /**
     * Sold qty / sale amount (and order count for categories) per category or product
     * from that day's orders of sessions that started that day (master rule).
     */
    private function refreshSoldTotals(string $date, string $table, string $column, bool $withOrders, Carbon $now): void
    {
        $soldColumns = $withOrders ? ['sold_qty', 'orders', 'sale_amount'] : ['sold_qty', 'sale_amount'];

        DB::table($table)->where('metric_date', $date)->update(array_fill_keys($soldColumns, 0) + ['updated_at' => $now]);

        $sold = DB::table('tracking_order_details as od')
            ->join('tracking_orders as o', 'o.id', '=', 'od.tracking_order_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->join('tracking_daily_visitor as dv', 'dv.id', '=', 'ts.tracking_daily_visitor_id')
            ->where('o.metric_date', $date)
            ->where('dv.visit_date', $date)
            ->whereNotNull("od.{$column}")
            ->groupBy("od.{$column}")
            ->selectRaw("od.{$column} as dimension_id, SUM(od.qty) as sold_qty, COUNT(DISTINCT od.tracking_order_id) as orders, SUM(od.line_total) as sale_amount")
            ->get();

        if ($sold->isEmpty()) {
            return;
        }

        $rows = $sold->map(function (object $row) use ($date, $column, $withOrders, $now): array {
            $values = [
                'metric_date' => $date,
                $column => (int) $row->dimension_id,
                'sold_qty' => (int) $row->sold_qty,
                'sale_amount' => round((float) $row->sale_amount, 2),
            ];

            if ($withOrders) {
                $values['orders'] = (int) $row->orders;
            }

            return $values + ['created_at' => $now, 'updated_at' => $now];
        })->all();

        DB::table($table)->upsert($rows, ['metric_date', $column], [...$soldColumns, 'updated_at']);
    }

    // ===== 6. Status marks =====

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

        DB::table('activity_ecom_user_actions')
            ->whereIn('id', $ids)
            ->update([
                'is_sync' => ActivityEcomUserAction::SYNC_FAILED,
                'sync_try' => DB::raw('sync_try + 1'),
                'sync_at' => now(),
            ]);
    }
}
