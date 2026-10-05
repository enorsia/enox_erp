<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class TrackerDashboardSyncService
{
    private const MAX_SYNC_TRY = 5;

    private const FAILED_RETRY_COOLDOWN_MINUTES = 5;

    /** @var array<string, int|null> */
    private array $cache = [];

    /**
     * @return int|null Next activity_ecom_user id cursor, or null when no more work.
     */
    public function processBatch(?int $afterId = null, int $batchSize = 25): ?int
    {
        $this->cache = [];
        $limit = max(1, $batchSize);
        $afterId = max(0, (int) $afterId);

        $sessions = $this->fetchPendingSessions($afterId, $limit);

        if ($sessions->isEmpty()) {
            Log::info('tracker.dashboard.sync: batch empty', ['after_id' => $afterId]);

            return null;
        }

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

        $lastId = (int) $sessions->last()->id;

        Log::info('tracker.dashboard.sync: batch done', [
            'after_id' => $afterId,
            'last_id' => $lastId,
            'ok' => $ok,
            'failed' => $failed,
            'count' => $sessions->count(),
        ]);

        return $sessions->count() === $limit ? $lastId : null;
    }

    /**
     * @param  array<string, mixed>  $sessionRow
     * @param  Collection<int, object>  $actions
     */
    private function syncOneSession(array $sessionRow, Collection $actions): bool
    {
        $sessionPk = (int) $sessionRow['id'];
        $rawSessionId = (string) ($sessionRow['session_id'] ?? '');
        $actionIds = $actions->pluck('id')->map(fn ($id) => (int) $id)->all();
        $cacheBefore = $this->cache;
        $sessionUpdatedAt = $sessionRow['updated_at'] ?? null;

        try {
            DB::transaction(function () use ($sessionRow, $actions, $actionIds, $sessionPk, $cacheBefore, $sessionUpdatedAt): void {
                $this->cache = $cacheBefore;
                $this->upsertTrackingFromSession($sessionRow, $actions);
                $this->markSessionSynced($sessionPk, $sessionUpdatedAt);
                $this->markActionsSyncedByIds($actionIds);
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

    private function fetchPendingSessions(int $afterId, int $limit): Collection
    {
        $retryAfter = now()->subMinutes(self::FAILED_RETRY_COOLDOWN_MINUTES);

        return DB::table('activity_ecom_user as u')
            ->select('u.*')
            ->where('u.id', '>', $afterId)
            ->where('u.sync_try', '<', self::MAX_SYNC_TRY)
            ->where(function ($query) use ($retryAfter): void {
                $query->where('u.is_sync', ActivityEcomUser::SYNC_PENDING)
                    ->orWhere(function ($q) use ($retryAfter): void {
                        $q->where('u.is_sync', ActivityEcomUser::SYNC_FAILED)
                            ->where(function ($cooldown) use ($retryAfter): void {
                                $cooldown->whereNull('u.sync_at')
                                    ->orWhere('u.sync_at', '<', $retryAfter);
                            });
                    })
                    ->orWhere(function ($q): void {
                        $q->where('u.is_sync', ActivityEcomUser::SYNC_DONE)
                            ->whereColumn('u.updated_at', '>', 'u.sync_at');
                    })
                    ->orWhereExists(function ($q): void {
                        $q->select(DB::raw('1'))
                            ->from('activity_ecom_user_actions as a')
                            ->whereColumn('a.session_id', 'u.session_id')
                            ->whereIn('a.is_sync', [
                                ActivityEcomUserAction::SYNC_PENDING,
                                ActivityEcomUserAction::SYNC_FAILED,
                            ]);
                    });
            })
            ->orderBy('u.id')
            ->limit($limit)
            ->get();
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

        if ($visitorId === '') {
            $visitorId = $sessionId;
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

        $trackingSessionId = $this->upsertTrackingSession($sessionRow, $sessionId, $dailyVisitorRowId);
        $this->upsertSessionDetails($trackingSessionId, $sessionRow, $deviceId, $browserId, $trafficId);

        $this->syncActionsForSession($trackingSessionId, $actions);
    }

    /**
     * @param  Collection<int, object>  $actions
     */
    private function syncActionsForSession(int $trackingSessionId, Collection $actions): void
    {
        $pCatRows = [];

        foreach ($actions as $action) {
            $productId = $this->findOrCreateProduct(
                (string) ($action->product_code ?? ''),
                (string) ($action->sku ?? ''),
                (string) ($action->product_name ?? ''),
            );

            $categoryId = $this->findOrCreateCategory(
                (string) ($action->category_code ?? ''),
                (string) ($action->category_name ?? ''),
                (string) ($action->department_name ?? ''),
            );

            $colorCode = (string) ($action->product_color_code ?? $action->product_color_id ?? '');
            $colorName = (string) ($action->general_color_name ?? '');
            if ($colorCode !== '' || $colorName !== '') {
                $this->findOrCreateColor($colorCode, $colorName);
            }

            foreach (['add_to_cart', 'begin_checkout', 'proceed_to_checkout', 'payment_success'] as $jsonColumn) {
                $payload = json_decode((string) ($action->{$jsonColumn} ?? ''), true);
                if (! is_array($payload)) {
                    continue;
                }
                $size = (string) ($payload['size_name'] ?? $payload['size'] ?? '');
                if ($size !== '') {
                    $this->findOrCreateSize($size, $size);
                }
            }

            if ($productId !== null && $categoryId !== null) {
                $key = $trackingSessionId.'|'.$productId.'|'.$categoryId;
                $pCatRows[$key] = [
                    'tracking_session_id' => $trackingSessionId,
                    'tracking_product_id' => $productId,
                    'tracking_category_id' => $categoryId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        if ($pCatRows !== []) {
            DB::table('tracking_session_p_cat')->insertOrIgnore(array_values($pCatRows));
        }
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
     */
    private function upsertTrackingSession(array $sessionRow, string $sessionId, int $dailyVisitorId): int
    {
        $payload = [
            'tracking_daily_visitor_id' => $dailyVisitorId,
            'name' => $sessionRow['user_name'] ?? null,
            'email' => $sessionRow['user_email'] ?? null,
            'phone' => $sessionRow['user_phone'] ?? null,
            'is_logged_in' => (int) (bool) ($sessionRow['is_logged_in'] ?? false),
            'duration_seconds' => (int) ($sessionRow['session_duration_seconds'] ?? 0),
            'last_active_at' => $sessionRow['last_active_at'] ?? null,
            'latest_funnel_stage' => $this->funnelStageCode($sessionRow['latest_funnel_stage'] ?? null),
            'has_order' => (int) (bool) ($sessionRow['has_payment_success'] ?? false),
        ];

        $existing = DB::table('tracking_session')->where('session_id', $sessionId)->first();

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
     * @param  array<string, mixed>  $sessionRow
     */
    private function upsertSessionDetails(
        int $trackingSessionId,
        array $sessionRow,
        ?int $deviceId,
        ?int $browserId,
        ?int $trafficId,
    ): void {
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
            'referer' => null,
            'user_agent' => $sessionRow['user_agent'] ?? null,
        ];

        $existing = DB::table('tracking_session_details')
            ->where('tracking_session_id', $trackingSessionId)
            ->first();

        if ($existing) {
            $this->applyTableUpdates('tracking_session_details', $existing, $payload);

            return;
        }

        $payload['created_at'] = now();
        $payload['updated_at'] = now();

        try {
            DB::table('tracking_session_details')->insert($payload);
        } catch (QueryException) {
            // Unique tracking_session_id — race with another worker.
        }
    }

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

        return $this->insertOrFetch('tracking_traffic_source', [
            'name' => $source,
            'medium' => $medium,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['name' => $source, 'medium' => $medium]);
    }

    private function findOrCreateProduct(string $code, string $sku, string $title): ?int
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $code = Str::limit($code, 100, '');
        $cacheKey = 'product:'.$code;

        return $this->remember($cacheKey, function () use ($code, $sku, $title): int {
            $existing = DB::table('tracking_product')->where('code', $code)->first();
            $skuVal = $sku !== '' ? Str::limit($sku, 100, '') : null;
            $titleVal = $title !== '' ? Str::limit($title, 500, '') : null;

            if ($existing) {
                $updates = $this->diffUpdates((array) $existing, [
                    'sku' => $skuVal ?? $existing->sku,
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
            ], ['code' => $code]);
        });
    }

    private function findOrCreateCategory(string $code, string $name, string $departmentName): ?int
    {
        $name = trim($name !== '' ? $name : $departmentName);
        if ($name === '') {
            return null;
        }

        $name = Str::limit($name, 255, '');
        $codeVal = $code !== '' ? Str::limit($code, 100, '') : null;
        $cacheKey = 'category:'.$name.'|'.($codeVal ?? '');

        return $this->remember($cacheKey, function () use ($name, $codeVal): int {
            $query = DB::table('tracking_category')->where('name', $name);
            if ($codeVal !== null) {
                $query->where('code', $codeVal);
            }
            $existing = $query->first();

            if ($existing) {
                return (int) $existing->id;
            }

            return $this->insertOrFetch('tracking_category', [
                'parent_id' => null,
                'code' => $codeVal,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ], ['name' => $name, 'code' => $codeVal]);
        });
    }

    private function findOrCreateColor(string $code, string $name): ?int
    {
        $code = trim($code !== '' ? $code : $name);
        if ($code === '') {
            return null;
        }

        $code = Str::limit($code, 100, '');

        return $this->remember('color:'.$code, fn () => $this->insertOrFetch('tracking_color', [
            'code' => $code,
            'name' => $name !== '' ? Str::limit($name, 255, '') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['code' => $code]));
    }

    private function findOrCreateSize(string $code, string $name): ?int
    {
        $code = trim($code !== '' ? $code : $name);
        if ($code === '') {
            return null;
        }

        $code = Str::limit($code, 100, '');

        return $this->remember('size:'.$code, fn () => $this->insertOrFetch('tracking_size', [
            'code' => $code,
            'name' => $name !== '' ? Str::limit($name, 255, '') : null,
            'created_at' => now(),
            'updated_at' => now(),
        ], ['code' => $code]));
    }

    /**
     * @param  array<string, mixed>  $where
     */
    private function insertOrFetch(string $table, array $row, array $where): int
    {
        $existingId = DB::table($table)->where($where)->value('id');
        if ($existingId) {
            return (int) $existingId;
        }

        try {
            return (int) DB::table($table)->insertGetId($row);
        } catch (QueryException $exception) {
            $existingId = DB::table($table)->where($where)->value('id');
            if ($existingId) {
                return (int) $existingId;
            }

            throw $exception;
        }
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

    private function markSessionSynced(int $sessionPk, mixed $sessionUpdatedAt): void
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
            throw new \RuntimeException('Session row changed during sync');
        }
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
     * @param  list<int>  $actionIds
     */
    private function markActionsSyncedByIds(array $actionIds): void
    {
        if ($actionIds === []) {
            return;
        }

        DB::table('activity_ecom_user_actions')
            ->whereIn('id', $actionIds)
            ->update([
                'is_sync' => ActivityEcomUserAction::SYNC_DONE,
                'sync_at' => now(),
            ]);
    }
}
