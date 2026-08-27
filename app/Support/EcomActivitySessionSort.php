<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Services\EcomTrackerDashboardService;
use App\Support\CommerceReadSupport;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class EcomActivitySessionSort
{
    public const DEFAULT_SORT_KEY = 'funnel_stage';

    /** @var list<string> */
    private const FUNNEL_STAGE_ACTION_TYPES = [
        'product_view',
        'product_view_popup',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
    ];

    /** @var array<string, int> */
    private const FUNNEL_STAGE_RANKS = [
        'payment_success' => 5,
        'proceed_checkout' => 4,
        'begin_checkout' => 3,
        'add_to_cart' => 2,
        'product_view' => 1,
        'product_view_popup' => 1,
    ];

  /** @var list<string> */
    public const SORT_KEYS = [
        'funnel_stage',
        'latest',
        'order_value',
        'actions',
        'duration',
        'last_active',
        'session',
    ];

    public static function resolveSortBy(Request $request): ?string
    {
        $sortBy = (string) $request->input('sort_by', '');

        if ($sortBy === '' || ! in_array($sortBy, self::SORT_KEYS, true)) {
            return null;
        }

        return $sortBy;
    }

    public static function effectiveSortBy(Request $request): string
    {
        return self::resolveSortBy($request) ?? self::DEFAULT_SORT_KEY;
    }

    public static function resolveSortDir(Request $request, ?string $sortBy = null): string
    {
        $dir = strtolower((string) $request->input('sort_dir', 'desc'));

        return in_array($dir, ['asc', 'desc'], true) ? $dir : 'desc';
    }

    /**
     * @return array<string, string>
     */
    public static function sortOptions(): array
    {
        return [
            'funnel_stage' => 'Funnel stage (sold first)',
            'latest' => 'Latest activity',
            'order_value' => 'Order value',
            'actions' => 'Actions',
            'duration' => 'Duration',
            'last_active' => 'Last active',
        ];
    }

    public static function sortUrl(Request $request, string $sortBy): string
    {
        $current = self::effectiveSortBy($request);
        $dir = self::resolveSortDir($request, $current);

        if ($current === $sortBy) {
            $dir = $dir === 'desc' ? 'asc' : 'desc';
        } else {
            $dir = in_array($sortBy, ['funnel_stage', 'order_value', 'actions', 'duration', 'last_active'], true)
                ? 'desc'
                : ($sortBy === 'session' ? 'desc' : 'desc');
        }

        return $request->fullUrlWithQuery([
            'sort_by' => $sortBy,
            'sort_dir' => $dir,
            'page' => null,
            'fragment' => null,
        ]);
    }

    public static function isActive(Request $request, string $sortBy): bool
    {
        return self::effectiveSortBy($request) === $sortBy;
    }

    public static function activeDirection(Request $request, string $sortBy): ?string
    {
        return self::isActive($request, $sortBy) ? self::resolveSortDir($request, $sortBy) : null;
    }

    public static function usesDefaultSort(Request $request): bool
    {
        return self::resolveSortBy($request) === null;
    }

    /**
     * Catalog drill-down matching uses PHP (JSON line items), so funnel/order sorts must too.
     *
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     */
    public static function shouldRankCatalogSessionsInPhp(?string $sortBy, array $scope): bool
    {
        if (! self::usesCatalogActionScope($scope['catalog_options'] ?? [])) {
            return false;
        }

        $sortBy = $sortBy ?? self::DEFAULT_SORT_KEY;

        return in_array($sortBy, ['funnel_stage', 'order_value'], true);
    }

    /**
     * Rank sessions using the same catalog-aware commerce rules as the table Commerce column.
     *
     * @param  Collection<int, string>  $sessionIds
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     * @return Collection<int, string>
     */
    public static function sortSessionIdsForCatalogScope(
        Collection $sessionIds,
        string $sortBy,
        string $sortDir,
        array $scope,
        EcomTrackerDashboardService $dashboard,
    ): Collection {
        if ($sessionIds->isEmpty()) {
            return $sessionIds->values();
        }

        $catalogOptions = $scope['catalog_options'] ?? [];
        $metrics = self::catalogCommerceSortMetrics($sessionIds, $scope, $dashboard);
        $descending = strtolower($sortDir) !== 'asc';

        return $sessionIds
            ->sort(function (string $leftId, string $rightId) use ($metrics, $sortBy, $descending) {
                $left = $metrics[$leftId] ?? ['funnel_rank' => 0, 'order_value' => 0.0, 'latest_at' => 0, 'id' => 0];
                $right = $metrics[$rightId] ?? ['funnel_rank' => 0, 'order_value' => 0.0, 'latest_at' => 0, 'id' => 0];

                if ($sortBy === 'order_value') {
                    $primary = $left['order_value'] <=> $right['order_value'];
                } else {
                    $primary = $left['funnel_rank'] <=> $right['funnel_rank'];
                }

                if ($primary !== 0) {
                    return $descending ? -$primary : $primary;
                }

                if ($sortBy === 'funnel_stage') {
                    $secondary = $left['order_value'] <=> $right['order_value'];

                    if ($secondary !== 0) {
                        return $descending ? -$secondary : $secondary;
                    }
                }

                $latest = $left['latest_at'] <=> $right['latest_at'];

                if ($latest !== 0) {
                    return $descending ? -$latest : $latest;
                }

                $id = $left['id'] <=> $right['id'];

                return $descending ? -$id : $id;
            })
            ->values();
    }

    /**
     * @param  Collection<int, string>  $sessionIds
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     * @return array<string, array{funnel_rank: int, order_value: float, latest_at: int, id: int}>
     */
    private static function catalogCommerceSortMetrics(
        Collection $sessionIds,
        array $scope,
        EcomTrackerDashboardService $dashboard,
    ): array {
        $catalogOptions = $scope['catalog_options'] ?? [];
        $from = $scope['from'] ?? null;
        $to = $scope['to'] ?? null;
        $metrics = [];

        foreach ($sessionIds as $sessionId) {
            $metrics[$sessionId] = [
                'funnel_rank' => 0,
                'order_value' => 0.0,
                'latest_at' => 0,
                'id' => 0,
            ];
        }

        $actionsQuery = ActivityEcomUserAction::query()
            ->select(CommerceReadSupport::scalarActionColumns())
            ->whereIn('session_id', $sessionIds->all())
            ->whereIn('action_type', self::FUNNEL_STAGE_ACTION_TYPES);

        if ($from instanceof Carbon && $to instanceof Carbon) {
            $actionsQuery->whereBetween('created_at', TrackerTime::storageRange($from, $to));
        }

        $actionsBySession = $actionsQuery->get()->groupBy('session_id');

        foreach ($sessionIds as $sessionId) {
            $sessionActions = $actionsBySession->get($sessionId, collect());
            $summary = EcomActivityCommerceSummary::summarizeCatalogActions(
                $sessionActions,
                $catalogOptions,
                $dashboard,
            );
            $funnelRank = EcomActivityCommerceSummary::funnelStageRankFromSummary($summary);
            $orderValue = (float) ($summary['commerce_value'] ?? 0.0);
            $latestAt = 0;
            $latestId = 0;

            foreach ($sessionActions as $action) {
                if (! self::actionMatchesCatalogScope($action, $catalogOptions, $dashboard)) {
                    continue;
                }

                $timestamp = $action->created_at?->timestamp ?? 0;

                if ($timestamp > $latestAt || ($timestamp === $latestAt && $action->id > $latestId)) {
                    $latestAt = $timestamp;
                    $latestId = (int) $action->id;
                }
            }

            $metrics[$sessionId] = [
                'funnel_rank' => $funnelRank,
                'order_value' => $orderValue,
                'latest_at' => $latestAt,
                'id' => $latestId,
            ];
        }

        return $metrics;
    }

    private static function funnelStageRankForActionType(string $actionType): int
    {
        return self::FUNNEL_STAGE_RANKS[$actionType] ?? 0;
    }

  /**
     * @param  array<string, mixed>  $catalogOptions
     */
    private static function actionMatchesCatalogScope(
        ActivityEcomUserAction $action,
        array $catalogOptions,
        EcomTrackerDashboardService $dashboard,
    ): bool {
        if ($action->action_type === 'payment_success') {
            return $dashboard->paymentActionMatchesCategoryCatalog($action, $catalogOptions);
        }

        return $dashboard->actionMatchesCatalogOptions($action, $catalogOptions);
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     * @return Builder<ActivityEcomUser>
     */
    public static function apply(Builder $query, ?string $sortBy, string $dir, array $scope = []): Builder
    {
        $sortBy = $sortBy ?? self::DEFAULT_SORT_KEY;

        if ($sortBy === 'latest') {
            return $query->orderByLatestActivity();
        }

        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        return match ($sortBy) {
            'session' => $query->orderBy('created_at', $direction)->orderByDesc('id'),
            'last_active' => self::orderByLastActive($query, $direction),
            'duration' => $query->orderBy('session_duration_seconds', $direction)->orderByDesc('id'),
            'actions' => $query->orderBy('actions_count', $direction)->orderByDesc('id'),
            'funnel_stage' => self::orderByFunnelStage($query, $direction, $scope),
            'order_value' => self::orderByOrderValue($query, $direction, $scope),
            default => self::orderByFunnelStage($query, $direction, $scope),
        };
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     */
    public static function usesCatalogActionScope(array $catalogOptions): bool
    {
        return filled($catalogOptions['category'] ?? null)
            || filled($catalogOptions['product_code'] ?? null)
            || filled($catalogOptions['product_name'] ?? null)
            || (
                filled($catalogOptions['search'] ?? null)
                && EcomActivityFocus::looksLikeProductCodeSearch((string) $catalogOptions['search'])
            );
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @return Builder<ActivityEcomUser>
     */
    private static function orderByLastActive(Builder $query, string $direction): Builder
    {
        $driver = $query->getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            return $query
                ->orderByRaw('CASE WHEN COALESCE(updated_at, created_at) >= COALESCE(last_active_at, created_at) THEN COALESCE(updated_at, created_at) ELSE COALESCE(last_active_at, created_at) END '.$direction)
                ->orderByDesc('id');
        }

        return $query
            ->orderByRaw('GREATEST(COALESCE(updated_at, created_at), COALESCE(last_active_at, created_at)) '.$direction)
            ->orderByDesc('id');
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     * @return Builder<ActivityEcomUser>
     */
    private static function orderByFunnelStage(Builder $query, string $direction, array $scope = []): Builder
    {
        ['sql' => $rankSql, 'bindings' => $bindings] = self::funnelStageRankSubquery(
            $query->getModel()->getTable(),
            $scope,
        );

        return $query
            ->orderByRaw($rankSql.' '.$direction, $bindings)
            ->orderByDesc('id');
    }

    /**
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     * @return array{sql: string, bindings: list<mixed>}
     */
    private static function funnelStageRankSubquery(string $table, array $scope = []): array
    {
        $actionsTable = 'activity_ecom_user_actions';
        $actionTypeList = "'".implode("', '", self::FUNNEL_STAGE_ACTION_TYPES)."'";
        $conditions = [
            "{$actionsTable}.session_id = {$table}.session_id",
            "{$actionsTable}.action_type IN ({$actionTypeList})",
        ];
        $bindings = [];

        $from = $scope['from'] ?? null;
        $to = $scope['to'] ?? null;

        if ($from instanceof Carbon && $to instanceof Carbon) {
            [$start, $end] = TrackerTime::storageRange($from, $to);
            $conditions[] = "{$actionsTable}.created_at BETWEEN ? AND ?";
            $bindings[] = $start;
            $bindings[] = $end;
        }

        foreach (self::catalogScopeSql($actionsTable, $scope['catalog_options'] ?? []) as $fragment) {
            $conditions[] = $fragment['sql'];
            array_push($bindings, ...$fragment['bindings']);
        }

        $where = implode(' AND ', $conditions);
        $caseWhen = collect(self::FUNNEL_STAGE_RANKS)
            ->map(fn (int $rank, string $actionType) => "WHEN '{$actionType}' THEN {$rank}")
            ->implode("\n        ");
        $sql = <<<SQL
(
    SELECT MAX(CASE {$actionsTable}.action_type
        {$caseWhen}
        ELSE 0
    END)
    FROM {$actionsTable}
    WHERE {$where}
)
SQL;

        return ['sql' => $sql, 'bindings' => $bindings];
    }

    /**
     * @param  array<string, mixed>  $catalogOptions
     * @return list<array{sql: string, bindings: list<mixed>}>
     */
    private static function catalogScopeSql(string $actionsTable, array $catalogOptions): array
    {
        if (! self::usesCatalogActionScope($catalogOptions)) {
            return [];
        }

        $fragments = [];

        if (filled($catalogOptions['category'] ?? null)) {
            $fragments[] = [
                'sql' => "LOWER(TRIM({$actionsTable}.category_name)) = ?",
                'bindings' => [mb_strtolower(trim((string) $catalogOptions['category']))],
            ];
        }

        if (filled($catalogOptions['department'] ?? null)) {
            $fragments[] = [
                'sql' => "(TRIM(COALESCE({$actionsTable}.department_name, '')) = '' OR LOWER(TRIM({$actionsTable}.department_name)) = ?)",
                'bindings' => [mb_strtolower(TrackerCategoryIdentity::normalizeDepartmentName((string) $catalogOptions['department']))],
            ];
        }

        if (filled($catalogOptions['product_code'] ?? null) || filled($catalogOptions['product_name'] ?? null)) {
            $identityConditions = [];
            $bindings = [];

            if (filled($catalogOptions['product_code'] ?? null)) {
                $code = mb_strtolower(trim((string) $catalogOptions['product_code']));
                $identityConditions[] = "LOWER(TRIM({$actionsTable}.product_code)) = ?";
                $identityConditions[] = "LOWER(TRIM({$actionsTable}.sku)) = ?";
                $bindings[] = $code;
                $bindings[] = $code;
            }

            if (filled($catalogOptions['product_name'] ?? null)) {
                $identityConditions[] = "LOWER(TRIM({$actionsTable}.product_name)) = ?";
                $bindings[] = mb_strtolower(trim((string) $catalogOptions['product_name']));
            }

            if ($identityConditions !== []) {
                $fragments[] = [
                    'sql' => '('.implode(' OR ', $identityConditions).')',
                    'bindings' => $bindings,
                ];
            }
        }

        return $fragments;
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     * @return Builder<ActivityEcomUser>
     */
    private static function orderByOrderValue(Builder $query, string $direction, array $scope = []): Builder
    {
        $table = $query->getModel()->getTable();
        $from = $scope['from'] ?? null;
        $to = $scope['to'] ?? null;
        $catalogOptions = $scope['catalog_options'] ?? [];

        if (($from === null || $to === null) && $catalogOptions === []) {
            return $query
                ->orderBy('max_order_value', $direction)
                ->orderByDesc('id');
        }

        $actionsTable = 'activity_ecom_user_actions';
        $ordersTable = 'activity_ecom_orders';
        $driver = $query->getConnection()->getDriverName();
        $bindings = [];

        if ($from instanceof Carbon && $to instanceof Carbon) {
            [$start, $end] = TrackerTime::storageRange($from, $to);
            $bindings = [$start, $end];

            $valueSql = <<<SQL
(
    SELECT MAX(CAST({$ordersTable}.amount_paid AS DECIMAL(12,2)))
    FROM {$ordersTable}
    WHERE {$ordersTable}.session_id = {$table}.session_id
      AND {$ordersTable}.ordered_at BETWEEN ? AND ?
)
SQL;

            return $query
                ->orderByRaw($valueSql.' '.$direction, $bindings)
                ->orderByDesc('id');
        }

        $conditions = [
            "{$actionsTable}.session_id = {$table}.session_id",
            "{$actionsTable}.action_type = 'payment_success'",
        ];

        foreach (self::catalogScopeSql($actionsTable, $catalogOptions) as $fragment) {
            $conditions[] = $fragment['sql'];
            array_push($bindings, ...$fragment['bindings']);
        }

        $where = implode(' AND ', $conditions);

        $valueSql = <<<SQL
(
    SELECT MAX(CAST(COALESCE({$actionsTable}.amount_paid, {$actionsTable}.commerce_total, 0) AS DECIMAL(12,2)))
    FROM {$actionsTable}
    WHERE {$where}
)
SQL;

        return $query
            ->orderByRaw($valueSql.' '.$direction, $bindings)
            ->orderByDesc('id');
    }
}
