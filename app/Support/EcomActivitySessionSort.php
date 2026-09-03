<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class EcomActivitySessionSort
{
    public const DEFAULT_SORT_KEY = 'funnel_stage';

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
     * Catalog drill-down sort uses SQL session flags after line-item EXISTS filters.
     *
     * @param  array{from?: ?Carbon, to?: ?Carbon, catalog_options?: array<string, mixed>}  $scope
     */
    public static function shouldRankCatalogSessionsInPhp(?string $sortBy, array $scope): bool
    {
        return false;
    }

    /**
     * @param  Builder<ActivityEcomUser>  $query
     * @return Builder<ActivityEcomUser>
     */
    private static function orderByActionsCount(Builder $query, string $direction): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->orderBy("{$table}.actions_count", $direction)
            ->orderByDesc("{$table}.id");
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
            'actions' => self::orderByActionsCount($query, $direction),
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
            || filled($catalogOptions['department'] ?? null)
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
        $table = $query->getModel()->getTable();
        $dir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $rankSql = <<<SQL
CASE
    WHEN {$table}.has_payment_success = 1 THEN 5
    WHEN {$table}.has_proceed_checkout = 1 THEN 4
    WHEN {$table}.has_begin_checkout = 1 THEN 3
    WHEN {$table}.has_add_to_cart = 1 THEN 2
    ELSE 0
END
SQL;

        $lineSub = DB::table('activity_ecom_commerce_line_items')
            ->selectRaw("session_id as line_session_id,
                MAX(CASE WHEN funnel_stage = 'payment_success' THEN staged_at END) as latest_payment_staged,
                MAX(CASE WHEN funnel_stage = 'proceed_checkout' THEN staged_at END) as latest_proceed,
                MAX(CASE WHEN funnel_stage = 'begin_checkout' THEN staged_at END) as latest_begin,
                MAX(CASE WHEN funnel_stage = 'add_to_cart' THEN staged_at END) as latest_cart")
            ->groupBy('session_id');

        $orderSub = DB::table('activity_ecom_orders')
            ->selectRaw('session_id as order_session_id, MAX(ordered_at) as latest_ordered_at')
            ->groupBy('session_id');

        $from = $scope['from'] ?? null;
        $to = $scope['to'] ?? null;

        if ($from instanceof Carbon && $to instanceof Carbon) {
            [$start, $end] = TrackerTime::storageRange($from, $to);
            $lineSub->whereBetween('staged_at', [$start, $end]);
            $orderSub->whereBetween('ordered_at', [$start, $end]);
        }

        $stageTimeSql = <<<SQL
CASE
    WHEN {$table}.has_payment_success = 1 THEN COALESCE(funnel_order_times.latest_ordered_at, funnel_line_times.latest_payment_staged, {$table}.first_payment_at, {$table}.last_active_at, {$table}.updated_at, {$table}.created_at)
    WHEN {$table}.has_proceed_checkout = 1 THEN COALESCE(funnel_line_times.latest_proceed, {$table}.last_active_at, {$table}.updated_at, {$table}.created_at)
    WHEN {$table}.has_begin_checkout = 1 THEN COALESCE(funnel_line_times.latest_begin, {$table}.last_active_at, {$table}.updated_at, {$table}.created_at)
    WHEN {$table}.has_add_to_cart = 1 THEN COALESCE(funnel_line_times.latest_cart, {$table}.last_active_at, {$table}.updated_at, {$table}.created_at)
    ELSE COALESCE({$table}.last_active_at, {$table}.updated_at, {$table}.created_at)
END
SQL;

        return $query
            ->leftJoinSub($lineSub, 'funnel_line_times', 'funnel_line_times.line_session_id', '=', "{$table}.session_id")
            ->leftJoinSub($orderSub, 'funnel_order_times', 'funnel_order_times.order_session_id', '=', "{$table}.session_id")
            ->select("{$table}.*")
            ->orderByRaw($rankSql.' '.$dir)
            ->orderByRaw($stageTimeSql.' '.$dir)
            ->orderByDesc("{$table}.id");
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

        if ($from instanceof Carbon && $to instanceof Carbon) {
            [$start, $end] = TrackerTime::storageRange($from, $to);

            return $query
                ->leftJoinSub(
                    DB::table('activity_ecom_orders')
                        ->selectRaw('session_id as period_order_session_id, MAX(CAST(amount_paid AS DECIMAL(12,2))) as period_order_value')
                        ->whereBetween('ordered_at', [$start, $end])
                        ->groupBy('session_id'),
                    'period_orders',
                    'period_orders.period_order_session_id',
                    '=',
                    "{$table}.session_id"
                )
                ->select("{$table}.*")
                ->orderByRaw('COALESCE(period_orders.period_order_value, 0) '.$direction)
                ->orderByDesc("{$table}.id");
        }

        return $query
            ->orderBy("{$table}.max_order_value", $direction)
            ->orderByDesc("{$table}.id");
    }
}
