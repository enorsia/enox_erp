<?php

namespace App\Services;

use App\Exceptions\CommerceParseException;
use App\Models\ActivityEcomUser;
use App\Models\TrackingCategory;
use App\Models\TrackingDailyDuration;
use App\Models\TrackingSession;
use App\Support\CommercePricingExtractor;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use App\Support\VisitorClassificationLabels;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ecom activity list: tracking_session rows + the latest action for each session's funnel stage
 * (used to build the Commerce column and its expandable order / checkout / cart details).
 */
class EcomActivityListService
{
    private const STAGE_KEYS = [
        3 => 'add_to_cart',
        4 => 'begin_checkout',
        5 => 'proceed_checkout',
        6 => 'payment_success',
    ];

    private const STAGE_LABELS = [
        'add_to_cart' => 'Cart',
        'begin_checkout' => 'Checkout',
        'proceed_checkout' => 'Proceed',
        'payment_success' => 'Order',
    ];

    public const SORT_OPTIONS = [
        'funnel_stage' => 'Funnel stage (sold first)',
        'latest_activity' => 'Latest activity',
        'order_value' => 'Order value',
        'actions' => 'Actions',
        'duration' => 'Duration',
        'last_active' => 'Last active',
    ];

    public const DEFAULT_SORT = 'funnel_stage';

    public const PERIODS = ['24h', 'yesterday', '7d', '30d', 'custom'];

    public const DEFAULT_PERIOD = '24h';

    /** latest_funnel_stage => activity drill-down label (matches dashboard abandon panels) */
    private const FUNNEL_FOCUS_LABELS = [
        3 => 'Cart abandoned',
        4 => 'Begin checkout abandoned',
        5 => 'Proceed checkout abandoned',
        6 => 'Payment success',
    ];

    /**
     * Option lists for the filter drawer: devices, traffic sources, departments and the selected
     * department's categories, read from the small lookup tables in one UNION ALL query.
     *
     * @return array{funnelStages: array<int, string>, durationBuckets: array<string, string>, devices: array<int, string>, utmSources: array<int, string>, utmMediums: list<string>, departments: array<int, string>, categories: array<int, string>}
     */
    public function filterOptions(?int $departmentId = null): array
    {
        $lookups = DB::table('tracking_device')->selectRaw("'device' as type, id, name, NULL as medium, NULL as parent_id")
            ->unionAll(DB::table('tracking_traffic_source')->selectRaw("'source', id, name, medium, NULL"))
            ->unionAll(DB::table('tracking_category')->selectRaw("'category', id, name, NULL, parent_id")
                ->whereNull('parent_id')
                ->when($departmentId, fn (Builder $query) => $query->orWhere('parent_id', $departmentId)))
            ->get()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->groupBy('type');
        $categories = $lookups->get('category', collect());
        $sources = $lookups->get('source', collect());

        return [
            'funnelStages' => array_intersect_key(TrackingSession::FUNNEL_STAGES, array_flip(TrackingSession::FILTER_FUNNEL_STAGES)),
            'durationBuckets' => array_map(fn (array $bucket) => $bucket['label'], TrackingSession::DURATION_BUCKETS),
            'devices' => $lookups->get('device', collect())->pluck('name', 'id')->all(),
            'utmSources' => $sources->pluck('name', 'id')->all(),
            'utmMediums' => $sources->pluck('medium')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'departments' => $categories->whereNull('parent_id')->pluck('name', 'id')->all(),
            'categories' => $departmentId ? $categories->where('parent_id', $departmentId)->pluck('name', 'id')->all() : [],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function departmentCategories(int $departmentId): array
    {
        return TrackingCategory::query()
            ->where('parent_id', $departmentId)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public static function normalizeSort(mixed $sortBy): string
    {
        return is_string($sortBy) && isset(self::SORT_OPTIONS[$sortBy]) ? $sortBy : self::DEFAULT_SORT;
    }

    public static function normalizePeriod(mixed $period): string
    {
        return is_string($period) && in_array($period, self::PERIODS, true) ? $period : self::DEFAULT_PERIOD;
    }

    /**
     * Calendar days in the store timezone, from start of the first day to end of the last day.
     *
     * @return array{from: Carbon, to: Carbon, period: string}
     */
    public static function periodRange(string $period, string $dateFrom = '', string $dateTo = ''): array
    {
        $period = self::normalizePeriod($period);
        $today = TrackerTime::localNow()->startOfDay();

        [$from, $to] = match ($period) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            '7d' => [$today->copy()->subDays(6), $today->copy()],
            '30d' => [$today->copy()->subDays(29), $today->copy()],
            'custom' => [self::parseLocalDate($dateFrom) ?? $today->copy(), self::parseLocalDate($dateTo) ?? self::parseLocalDate($dateFrom) ?? $today->copy()],
            default => [$today->copy(), $today->copy()],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from->startOfDay(),
            'to' => $to->endOfDay(),
            'period' => $period,
        ];
    }

    /**
     * Query params for the same-length range before (-1) or after (+1); null when it would start after today.
     *
     * @param  array{from: Carbon, to: Carbon}  $range
     * @return array{period: string, date_from?: string, date_to?: string}|null
     */
    public static function shiftedPeriodQuery(array $range, int $direction): ?array
    {
        $days = (int) $range['from']->copy()->startOfDay()->diffInDays($range['to']->copy()->startOfDay()) + 1;
        $from = $range['from']->copy()->addDays($days * $direction)->startOfDay();
        $to = $range['to']->copy()->addDays($days * $direction)->startOfDay();
        $today = TrackerTime::localNow()->startOfDay();

        if ($from->greaterThan($today)) {
            return null;
        }

        if ($to->greaterThan($today)) {
            $to = $today->copy();
        }

        if ($from->equalTo($to) && $to->equalTo($today)) {
            return ['period' => '24h'];
        }

        if ($from->equalTo($to) && $to->equalTo($today->copy()->subDay())) {
            return ['period' => 'yesterday'];
        }

        return ['period' => 'custom', 'date_from' => $from->toDateString(), 'date_to' => $to->toDateString()];
    }

    /**
     * The exact previous page (with its filters) carried in `back`; only this site's URLs, else the fallback.
     */
    public static function backUrl(Request $request, string $fallback): string
    {
        $back = $request->query('back');

        return is_string($back) && str_starts_with($back, url('/').'/') ? $back : $fallback;
    }

    private static function parseLocalDate(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('Y-m-d', $value, TrackerTime::timezone())->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{search: string, product_code: string, funnel: list<int>, has_order: ?bool, devices: list<int>, logged_in: ?bool, durations: list<string>, duration: ?int, utm_sources: list<int>, utm_mediums: list<string>, department: ?int, categories: list<int>, visitor_type: ?string}
     */
    public static function filtersFromRequest(Request $request): array
    {
        $ints = fn (string $key) => array_values(array_filter(array_map('intval', (array) $request->input($key, [])), fn (int $id) => $id > 0));
        $strings = fn (string $key) => array_values(array_filter(array_map('strval', (array) $request->input($key, [])), 'filled'));
        $bool = fn (string $key) => in_array($request->input($key), ['0', '1'], true) ? $request->input($key) === '1' : null;
        $visitorType = $request->input('visitor_type');
        $visitorType = in_array($visitorType, ['human', 'bot', 'unclassified'], true) ? $visitorType : null;
        $productCode = mb_substr(trim((string) $request->input('product_code', '')), 0, 100);

        return [
            'search' => $productCode !== '' ? '' : mb_substr(trim((string) $request->input('search', '')), 0, 100),
            'product_code' => $productCode,
            'funnel' => array_values(array_intersect($ints('funnel'), TrackingSession::FILTER_FUNNEL_STAGES)),
            'has_order' => $bool('has_order'),
            'devices' => $ints('device_type'),
            'logged_in' => $bool('logged_in'),
            'durations' => array_values(array_intersect($strings('duration_bucket'), array_keys(TrackingSession::DURATION_BUCKETS))),
            'duration' => isset(TrackingDailyDuration::BUCKETS[$request->integer('duration')]) ? $request->integer('duration') : null,
            'utm_sources' => $ints('utm_source'),
            'utm_mediums' => $strings('utm_medium'),
            'department' => $request->integer('department') ?: null,
            'categories' => $ints('category'),
            'visitor_type' => $visitorType,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function filterQueryParams(Request $request): array
    {
        $params = [];

        if (trim((string) $request->query('search', '')) !== '') {
            $params['search'] = $request->query('search');
        }

        if (trim((string) $request->query('product_code', '')) !== '') {
            $params['product_code'] = $request->query('product_code');
        }

        if ($request->query('focus') === 'products') {
            $params['focus'] = 'products';
        }

        foreach (['device_type', 'utm_source', 'utm_medium', 'funnel', 'category', 'duration_bucket'] as $key) {
            $values = array_values(array_filter((array) $request->query($key, []), fn ($value) => $value !== null && $value !== ''));
            if ($values !== []) {
                $params[$key] = $values;
            }
        }

        foreach (['logged_in', 'has_order', 'visitor_type', 'department', 'duration'] as $key) {
            $value = $request->query($key);
            if (in_array($value, ['0', '1'], true) || (is_string($value) && $value !== '')) {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{
     *     sessions: int,
     *     unique_visitors: int,
     *     avg_stay_seconds: int,
     *     adds: int,
     *     checkouts: int,
     *     proceeds: int,
     *     cart_abandoned: int,
     *     sold: int,
     *     sold_qty: int,
     *     sale_amount: float
     * }
     */
    public function filteredSummary(array $range, array $filters): array
    {
        $productCode = trim((string) ($filters['product_code'] ?? ''));
        if ($productCode !== '') {
            return $this->productCatalogFilteredSummary($range, $filters, $productCode);
        }

        $scope = function (Builder $query) use ($range, $filters): void {
            $this->applySessionDateScope($query, $range);
            $this->applyFilters($query, $filters, $range);
        };

        $row = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT ts.tracking_daily_visitor_id) as unique_visitors')
            ->selectRaw('COALESCE(SUM(ts.duration_seconds), 0) as stay_seconds')
            ->selectRaw('COALESCE(SUM(ts.has_add_to_cart), 0) as adds')
            ->selectRaw('COALESCE(SUM(ts.has_begin_checkout), 0) as checkouts')
            ->selectRaw('COALESCE(SUM(ts.has_proceed_checkout), 0) as proceeds')
            ->selectRaw('SUM(CASE WHEN ts.latest_funnel_stage = 3 THEN 1 ELSE 0 END) as cart_abandoned')
            ->selectRaw('COALESCE(SUM(ts.has_order), 0) as sold')
            ->first();

        $sales = DB::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap($scope)
            ->selectRaw('COALESCE(SUM(o.items_qty), 0) as sold_qty')
            ->selectRaw('COALESCE(SUM(o.amount_paid), 0) as sale_amount')
            ->first();

        $sessions = (int) ($row->sessions ?? 0);
        $staySeconds = (int) ($row->stay_seconds ?? 0);

        return [
            'sessions' => $sessions,
            'unique_visitors' => (int) ($row->unique_visitors ?? 0),
            'avg_stay_seconds' => $sessions > 0 ? (int) round($staySeconds / $sessions) : 0,
            'adds' => (int) ($row->adds ?? 0),
            'checkouts' => (int) ($row->checkouts ?? 0),
            'proceeds' => (int) ($row->proceeds ?? 0),
            'cart_abandoned' => (int) ($row->cart_abandoned ?? 0),
            'sold' => (int) ($row->sold ?? 0),
            'sold_qty' => (int) ($sales->sold_qty ?? 0),
            'sale_amount' => round((float) ($sales->sale_amount ?? 0), 2),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{human: int, bot: int, unclassified: int}
     */
    public function visitorQualitySummary(array $range, array $filters): array
    {
        $row = DB::table('tracking_session as ts')
            ->leftJoin('activity_ecom_user_bot_context as bc', 'bc.session_id', '=', 'ts.session_id')
            ->tap(fn (Builder $query) => $this->applySessionDateScope($query, $range))
            ->tap(fn (Builder $query) => $this->applyFilters($query, $filters, $range))
            ->selectRaw('SUM(CASE WHEN bc.id IS NULL THEN 1 ELSE 0 END) as unclassified')
            ->selectRaw('SUM(CASE WHEN bc.is_bot = 1 THEN 1 ELSE 0 END) as bot')
            ->selectRaw('SUM(CASE WHEN bc.id IS NOT NULL AND bc.is_bot = 0 THEN 1 ELSE 0 END) as human')
            ->first();

        return [
            'human' => (int) ($row->human ?? 0),
            'bot' => (int) ($row->bot ?? 0),
            'unclassified' => (int) ($row->unclassified ?? 0),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @param  array<string, int|float>  $summary
     * @return array<string, mixed>|null
     */
    public function drillDownUi(
        Request $request,
        array $filters,
        array $summary,
        string $resetUrl,
        array $filterOptions = [],
    ): ?array {
        if (! self::hasActiveFilters($filters) && (int) ($summary['sessions'] ?? 0) === 0) {
            return null;
        }

        $sessions = (int) ($summary['sessions'] ?? 0);
        $sessionLabel = $sessions === 1 ? 'session' : 'sessions';
        $productCode = trim((string) ($filters['product_code'] ?? ''));
        $isProductDrill = $productCode !== '';

        $extraMetrics = [
            ['label' => 'Adds', 'value' => number_format((int) $summary['adds'])],
            ['label' => 'Checkout', 'value' => number_format((int) $summary['checkouts'])],
            ['label' => 'Proceed', 'value' => number_format((int) $summary['proceeds'])],
            ['label' => 'Cart abandoned', 'value' => number_format((int) $summary['cart_abandoned'])],
            ['label' => 'Sold', 'value' => number_format((int) $summary['sold'])],
            ['label' => 'Sold qty', 'value' => number_format((int) $summary['sold_qty'])],
            ['label' => 'Sale', 'value' => '£'.number_format((float) $summary['sale_amount'], 2)],
        ];

        if (! $isProductDrill) {
            $extraMetrics[] = ['label' => 'Unique visitors', 'value' => number_format((int) $summary['unique_visitors'])];
            $extraMetrics[] = ['label' => 'Avg stay', 'value' => format_duration((int) $summary['avg_stay_seconds'])];
        }

        $chips = $this->filterChips($request, $resetUrl, $filterOptions);
        if ($chips !== [] && ! $isProductDrill) {
            $chips[] = [
                'label' => 'Clear filters',
                'remove_url' => $resetUrl,
            ];
        }

        $focusLabel = $this->funnelFocusLabel($filters);

        return [
            'sectionLabel' => $isProductDrill ? 'Product performance' : 'Audience & engagement',
            'sectionSubtitle' => $isProductDrill ? $productCode : null,
            'contextAriaLabel' => $isProductDrill ? 'Product drill-down summary' : 'Filtered session summary',
            'tooltip' => $isProductDrill
                ? "Sessions in this period that touched product {$productCode}"
                : ($focusLabel !== null
                    ? "Sessions in this period that match: {$focusLabel}"
                    : 'Sessions in this period that match the active filters'),
            'sessionCount' => $sessions,
            'sessionCountLabel' => $sessionLabel,
            'extraMetrics' => $extraMetrics,
            'showExport' => false,
            'exportKey' => 'ecom_activity',
            'filterChips' => $chips,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public static function hasActiveFilters(array $filters): bool
    {
        if (trim((string) ($filters['search'] ?? '')) !== '') {
            return true;
        }

        if (trim((string) ($filters['product_code'] ?? '')) !== '') {
            return true;
        }

        foreach (['funnel', 'devices', 'durations', 'utm_sources', 'utm_mediums', 'categories'] as $key) {
            if (! empty($filters[$key])) {
                return true;
            }
        }

        if (($filters['has_order'] ?? null) !== null
            || ($filters['logged_in'] ?? null) !== null
            || ($filters['duration'] ?? null) !== null
            || ($filters['department'] ?? null) !== null
            || in_array($filters['visitor_type'] ?? null, ['human', 'bot', 'unclassified'], true)) {
            return true;
        }

        return false;
    }

    /**
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range
     * @param  array<string, mixed>  $filters
     */
    public function constrainSessionsInRange(Builder $query, array $range, array $filters): void
    {
        $this->applySessionDateScope($query, $range);
        $this->applyFilters($query, $filters, $range);
    }

    /**
     * Session-level totals in the same shape as {@see EcomTrackerDashboardService::totals()} for KPI groups.
     *
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, float|int>
     */
    public function dashboardTotals(array $range, array $filters): array
    {
        $scope = function (Builder $query) use ($range, $filters): void {
            $this->applySessionDateScope($query, $range);
            $this->applyFilters($query, $filters, $range);
        };

        $row = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT ts.tracking_daily_visitor_id) as unique_visitors')
            ->selectRaw('COALESCE(SUM(ts.duration_seconds), 0) as stay_seconds')
            ->selectRaw('SUM(CASE WHEN ts.has_add_to_cart = 1 THEN 1 ELSE 0 END) as cart_sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_begin_checkout = 1 THEN 1 ELSE 0 END) as checkout_sessions')
            ->selectRaw('SUM(CASE WHEN ts.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceed_sessions')
            ->selectRaw('SUM(CASE WHEN ts.latest_funnel_stage = 3 THEN 1 ELSE 0 END) as cart_drops')
            ->selectRaw('SUM(CASE WHEN ts.latest_funnel_stage = 4 THEN 1 ELSE 0 END) as checkout_drops')
            ->selectRaw('SUM(CASE WHEN ts.latest_funnel_stage = 5 THEN 1 ELSE 0 END) as proceed_drops')
            ->selectRaw('COALESCE(SUM(ts.has_order), 0) as payments')
            ->first();

        $sales = DB::table('tracking_orders as o')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap($scope)
            ->selectRaw('COALESCE(SUM(o.items_qty), 0) as sold_qty')
            ->selectRaw('COALESCE(SUM(o.amount_paid), 0) as sale_amount')
            ->first();

        $sessions = (int) ($row->sessions ?? 0);
        $staySeconds = (int) ($row->stay_seconds ?? 0);

        return [
            'unique_visitors' => (int) ($row->unique_visitors ?? 0),
            'sessions' => $sessions,
            'stay_seconds' => $staySeconds,
            'cart_sessions' => (int) ($row->cart_sessions ?? 0),
            'checkout_sessions' => (int) ($row->checkout_sessions ?? 0),
            'proceed_sessions' => (int) ($row->proceed_sessions ?? 0),
            'cart_drops' => (int) ($row->cart_drops ?? 0),
            'checkout_drops' => (int) ($row->checkout_drops ?? 0),
            'proceed_drops' => (int) ($row->proceed_drops ?? 0),
            'payments' => (int) ($row->payments ?? 0),
            'items_sold' => (int) ($sales->sold_qty ?? 0),
            'sale_amount' => round((float) ($sales->sale_amount ?? 0), 2),
            'avg_stay_seconds' => $sessions > 0 ? (int) round($staySeconds / $sessions) : 0,
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function funnelFocusLabel(array $filters): ?string
    {
        $stages = $filters['funnel'] ?? [];

        if (count($stages) === 1) {
            $stage = (int) $stages[0];

            return self::FUNNEL_FOCUS_LABELS[$stage]
                ?? (TrackingSession::FUNNEL_STAGES[$stage] ?? null);
        }

        return null;
    }

    /**
     * @param  array{funnelStages: array<int, string>, devices: array<int, string>, utmSources: array<int, string>, utmMediums: list<string>, departments: array<int, string>, categories: array<int, string>}  $filterOptions
     * @return list<array{label: string, remove_url: string}>
     */
    private function filterChips(Request $request, string $resetUrl, array $filterOptions): array
    {
        $chips = [];
        $baseQuery = collect($request->query())
            ->except(['page', 'fragment'])
            ->all();

        $addChip = function (string $label, array $withoutKeys) use (&$chips, $baseQuery, $resetUrl): void {
            $query = collect($baseQuery)->except($withoutKeys)->filter(fn ($value) => $value !== null && $value !== '')->all();

            $chips[] = [
                'label' => $label,
                'remove_url' => $query === []
                    ? $resetUrl
                    : route('admin.ecom-activity.index', $query),
            ];
        };

        if (filled($request->query('search'))) {
            $addChip('Search: '.Str::limit((string) $request->query('search'), 40), ['search']);
        }

        if (filled($request->query('product_code'))) {
            $addChip((string) $request->query('product_code'), ['product_code', 'focus']);
            $chips[] = [
                'label' => 'Clear section',
                'remove_url' => $resetUrl,
            ];
        }

        $funnelStages = array_values(array_filter(array_map('intval', (array) $request->query('funnel', []))));
        if ($funnelStages !== []) {
            $labels = array_map(
                fn (int $stage) => self::FUNNEL_FOCUS_LABELS[$stage]
                    ?? ($filterOptions['funnelStages'][$stage] ?? "Stage {$stage}"),
                $funnelStages,
            );
            $addChip('Funnel: '.implode(', ', $labels), ['funnel']);
        }

        if ($request->query('has_order') === '1') {
            $addChip('Has order: With order', ['has_order']);
        } elseif ($request->query('has_order') === '0') {
            $addChip('Has order: No order', ['has_order']);
        }

        if ($request->query('logged_in') === '1') {
            $addChip('Logged in', ['logged_in']);
        } elseif ($request->query('logged_in') === '0') {
            $addChip('Guest', ['logged_in']);
        }

        foreach ((array) $request->query('device_type', []) as $deviceId) {
            $name = $filterOptions['devices'][(int) $deviceId] ?? $deviceId;
            $addChip('Device: '.ucfirst((string) $name), ['device_type']);
        }

        foreach ((array) $request->query('utm_source', []) as $sourceId) {
            $name = $filterOptions['utmSources'][(int) $sourceId] ?? $sourceId;
            $addChip('Source: '.$name, ['utm_source']);
        }

        foreach ((array) $request->query('utm_medium', []) as $medium) {
            $addChip('Medium: '.$medium, ['utm_medium']);
        }

        if (filled($request->query('department'))) {
            $departmentId = (int) $request->query('department');
            $name = $filterOptions['departments'][$departmentId] ?? $departmentId;
            $addChip('Department: '.$name, ['department', 'category']);
        }

        foreach ((array) $request->query('category', []) as $categoryId) {
            $name = $filterOptions['categories'][(int) $categoryId] ?? $categoryId;
            $addChip('Category: '.$name, ['category']);
        }

        foreach ((array) $request->query('duration_bucket', []) as $bucket) {
            $label = TrackingSession::DURATION_BUCKETS[$bucket]['label'] ?? $bucket;
            $addChip('Duration: '.$label, ['duration_bucket']);
        }

        if (filled($request->query('duration'))) {
            $bucket = TrackingDailyDuration::BUCKETS[(int) $request->query('duration')] ?? null;
            $addChip('Duration: '.($bucket['label'] ?? $request->query('duration')), ['duration']);
        }

        return $chips;
    }

    /**
     * @param  list<array{key: string, label: string, class?: string, tip?: string}>  $focusColumns
     */
    public function paginate(
        array $range,
        array $filters = [],
        string $sortBy = self::DEFAULT_SORT,
        int $perPage = 25,
        array $focusColumns = [],
        bool $attachCatalogPaths = false,
    ): LengthAwarePaginator {
        $page = Paginator::resolveCurrentPage();
        $scope = function (Builder $query) use ($range, $filters) {
            $this->applySessionDateScope($query, $range);
            $this->applyFilters($query, $filters, $range);
        };

        $counts = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(ts.has_order), 0) as orders')
            ->first();
        $total = (int) $counts->total;
        $page = min($page, max(1, (int) ceil($total / $perPage)));

        $focusKeys = array_column($focusColumns, 'key');
        $sortBy = self::normalizeSort($sortBy);
        $rows = $total === 0 ? collect() : $this->pageRows($scope, $sortBy, (int) $counts->orders, $page, $perPage);

        if ($attachCatalogPaths && $rows->isNotEmpty()) {
            $this->attachCatalogPaths($rows);
        }

        $rows = $rows->map(function (object $row) use ($focusKeys) {
            $row->commerce = $this->commerceCell($row);
            $row->trust = $this->trustCell($row);
            $row->focus = $this->focusMetrics($row, $focusKeys);

            return $row;
        });

        return new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Deferred join: the inner query sorts + offsets on indexes and returns only this page's ids;
     * commerce and trust data are joined for those ids alone, in the same round trip.
     *
     * @return Collection<int, object>
     */
    private function pageRows(\Closure $scope, string $sortBy, int $orderCount, int $page, int $perPage): Collection
    {
        $rows = $this->withCommerceAction(DB::table('tracking_session as ts'))
            ->tap(fn (Builder $query) => $this->withVisitorTrust($query));

        if ($sortBy !== 'order_value') {
            $pageIds = DB::table('tracking_session as ts')
                ->select('ts.id')
                ->tap($scope)
                ->tap(fn (Builder $query) => $this->applySort($query, $sortBy))
                ->forPage($page, $perPage);

            return $rows->joinSub($pageIds, 'page_ids', 'page_ids.id', '=', 'ts.id')
                ->tap(fn (Builder $query) => $this->applySort($query, $sortBy))
                ->get();
        }

        $pageIds = $this->orderValuePageIds($scope, $orderCount, ($page - 1) * $perPage, $perPage);

        return $rows->whereIn('ts.id', $pageIds)
            ->get()
            ->sortBy(fn (object $row) => array_search($row->id, $pageIds))
            ->values();
    }

    /**
     * Every filter is a condition on the same query (no extra round trips); detail / category
     * filters use unique or composite indexes keyed by the session id.
     *
     * @param  array<string, mixed>  $filters
     */
    /**
     * Same calendar rules as master User activity ({@see TrackerTime::applyEcomActivitySessionScope}).
     *
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range
     */
    private function applySessionDateScope(Builder $query, array $range): void
    {
        TrackerTime::applyEcomActivitySessionScope(
            $query,
            $range['from'],
            $range['to'],
            $range['period'] ?? null,
            'ts',
        );
    }

    private function applyFilters(Builder $query, array $filters, ?array $range = null): void
    {
        $productCode = trim((string) ($filters['product_code'] ?? ''));
        if ($productCode !== '' && $range !== null) {
            $this->applyProductCodeSessionFilter($query, $range, $productCode);
        } else {
            $this->applySearch($query, (string) ($filters['search'] ?? ''));
        }

        if (! empty($filters['funnel'])) {
            $query->whereIn('ts.latest_funnel_stage', $filters['funnel']);
        }

        if (($filters['has_order'] ?? null) !== null) {
            $query->where('ts.has_order', $filters['has_order']);
        }

        if (($filters['logged_in'] ?? null) !== null) {
            $query->where('ts.is_logged_in', $filters['logged_in']);
        }

        if (! empty($filters['durations'])) {
            $query->where(function (Builder $durations) use ($filters) {
                foreach ($filters['durations'] as $key) {
                    $bucket = TrackingSession::DURATION_BUCKETS[$key];
                    $durations->orWhere(fn (Builder $range) => $range
                        ->where('ts.duration_seconds', '>=', $bucket['min'])
                        ->when($bucket['max'] !== null, fn (Builder $q) => $q->where('ts.duration_seconds', '<=', $bucket['max'])));
                }
            });
        }

        if (($filters['duration'] ?? null) !== null) {
            $bucket = TrackingDailyDuration::BUCKETS[$filters['duration']];
            $query->where('ts.duration_seconds', '>=', $bucket['min'])
                ->when($bucket['max'] !== null, fn (Builder $q) => $q->where('ts.duration_seconds', '<=', $bucket['max']));
        }

        if (! empty($filters['devices']) || ! empty($filters['utm_sources']) || ! empty($filters['utm_mediums'])) {
            $query->whereExists(function (Builder $exists) use ($filters) {
                $exists->selectRaw('1')
                    ->from('tracking_session_details as fd')
                    ->whereColumn('fd.tracking_session_id', 'ts.id')
                    ->whereRaw('fd.touch_sequence = (
                        SELECT MAX(fd_last.touch_sequence)
                        FROM tracking_session_details as fd_last
                        WHERE fd_last.tracking_session_id = ts.id
                    )')
                    ->when(! empty($filters['devices']), fn (Builder $q) => $q->whereIn('fd.tracking_device_id', $filters['devices']))
                    ->when(! empty($filters['utm_sources']), fn (Builder $q) => $q->whereIn('fd.tracking_traffic_source_id', $filters['utm_sources']))
                    ->when(! empty($filters['utm_mediums']), fn (Builder $q) => $q->whereIn('fd.tracking_traffic_source_id', fn (Builder $sources) => $sources
                        ->select('id')
                        ->from('tracking_traffic_source')
                        ->whereIn('medium', $filters['utm_mediums'])));
            });
        }

        if (! empty($filters['categories'])) {
            $query->whereExists(fn (Builder $catalog) => $catalog
                ->selectRaw('1')
                ->from('tracking_session_p_cat as fc')
                ->whereColumn('fc.tracking_session_id', 'ts.id')
                ->whereIn('fc.tracking_category_id', $filters['categories']));
        }

        $visitorType = $filters['visitor_type'] ?? null;
        if ($visitorType === 'bot') {
            $query->whereExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from('activity_ecom_user_bot_context as bc_filter')
                ->whereColumn('bc_filter.session_id', 'ts.session_id')
                ->where('bc_filter.is_bot', true));
        } elseif ($visitorType === 'human') {
            $query->whereExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from('activity_ecom_user_bot_context as bc_filter')
                ->whereColumn('bc_filter.session_id', 'ts.session_id')
                ->where('bc_filter.is_bot', false));
        } elseif ($visitorType === 'unclassified') {
            $query->whereNotExists(fn (Builder $exists) => $exists
                ->selectRaw('1')
                ->from('activity_ecom_user_bot_context as bc_filter')
                ->whereColumn('bc_filter.session_id', 'ts.session_id'));
        }
    }

    /**
     * Sessions that touched a product (commerce line items), aligned with dashboard product drill-down.
     *
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range
     */
    private function applyProductCodeSessionFilter(Builder $query, array $range, string $productCode): void
    {
        $query->whereIn('ts.session_id', $this->productLineItemSessionsQuery($range, $productCode));
    }

    /**
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range
     */
    private function productLineItemSessionsQuery(array $range, string $productCode): Builder
    {
        $query = DB::table('activity_ecom_commerce_line_items as li')
            ->select('li.session_id')
            ->join('activity_ecom_user as aeu', 'aeu.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', TrackerTime::storageRange($range['from'], $range['to']))
            ->where(function (Builder $inner) use ($productCode) {
                $inner->where('li.product_code', $productCode)
                    ->orWhere('li.sku', $productCode);
            })
            ->distinct();

        TrackerTime::applyEcomActivitySessionScope(
            $query,
            $range['from'],
            $range['to'],
            $range['period'] ?? null,
            'aeu',
        );

        return $query;
    }

    /**
     * Product drill-down KPIs: sessions from commerce lines; funnel counts for that SKU/code; sold from order lines.
     *
     * @param  array{from: Carbon, to: Carbon, period?: string}  $range
     * @param  array<string, mixed>  $filters
     * @return array<string, int|float>
     */
    private function productCatalogFilteredSummary(array $range, array $filters, string $productCode): array
    {
        $scope = function (Builder $query) use ($range, $filters): void {
            $this->applySessionDateScope($query, $range);
            $this->applyFilters($query, $filters, $range);
        };

        $row = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw('COUNT(*) as sessions')
            ->selectRaw('COUNT(DISTINCT ts.tracking_daily_visitor_id) as unique_visitors')
            ->selectRaw('COALESCE(SUM(ts.duration_seconds), 0) as stay_seconds')
            ->selectRaw('SUM(CASE WHEN ts.has_begin_checkout = 1 THEN 1 ELSE 0 END) as checkouts')
            ->selectRaw('SUM(CASE WHEN ts.has_proceed_checkout = 1 THEN 1 ELSE 0 END) as proceeds')
            ->selectRaw('SUM(CASE WHEN ts.latest_funnel_stage = 3 THEN 1 ELSE 0 END) as cart_abandoned')
            ->first();

        $fromDate = $range['from']->toDateString();
        $toDate = $range['to']->toDateString();

        $dailyAdds = (int) DB::table('tracking_daily_products')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->where('product_code', $productCode)
            ->sum('add_to_carts');

        $lineAdds = (int) DB::query()
            ->fromSub($this->productLineItemSessionsQuery($range, $productCode), 'li_add')
            ->join('activity_ecom_commerce_line_items as li', function ($join) use ($productCode) {
                $join->on('li.session_id', '=', 'li_add.session_id')
                    ->where('li.funnel_stage', 'add_to_cart')
                    ->where(function (Builder $inner) use ($productCode) {
                        $inner->where('li.product_code', $productCode)
                            ->orWhere('li.sku', $productCode);
                    });
            })
            ->whereBetween('li.staged_at', TrackerTime::storageRange($range['from'], $range['to']))
            ->distinct()
            ->count('li_add.session_id');

        $adds = $dailyAdds > 0 ? $dailyAdds : $lineAdds;

        $sold = (int) DB::query()
            ->fromSub($this->productLineItemSessionsQuery($range, $productCode), 'li_pay')
            ->join('activity_ecom_commerce_line_items as li', function ($join) use ($productCode) {
                $join->on('li.session_id', '=', 'li_pay.session_id')
                    ->where('li.funnel_stage', 'payment_success')
                    ->where(function (Builder $inner) use ($productCode) {
                        $inner->where('li.product_code', $productCode)
                            ->orWhere('li.sku', $productCode);
                    });
            })
            ->whereBetween('li.staged_at', TrackerTime::storageRange($range['from'], $range['to']))
            ->distinct()
            ->count('li_pay.session_id');

        [$fromBound, $toBound] = TrackerTime::storageRange($range['from'], $range['to']);

        $sales = DB::table('tracking_order_details as od')
            ->join('tracking_orders as o', 'o.id', '=', 'od.tracking_order_id')
            ->join('tracking_product as p', 'p.id', '=', 'od.tracking_product_id')
            ->join('tracking_session as ts', 'ts.id', '=', 'o.tracking_session_id')
            ->tap($scope)
            ->where('p.code', $productCode)
            ->whereBetween('o.ordered_at', [$fromBound, $toBound])
            ->selectRaw('COALESCE(SUM(od.qty), 0) as sold_qty')
            ->selectRaw('COALESCE(SUM(od.line_total), 0) as sale_amount')
            ->first();

        $sessions = (int) ($row->sessions ?? 0);
        $staySeconds = (int) ($row->stay_seconds ?? 0);

        return [
            'sessions' => $sessions,
            'unique_visitors' => (int) ($row->unique_visitors ?? 0),
            'avg_stay_seconds' => $sessions > 0 ? (int) round($staySeconds / $sessions) : 0,
            'adds' => $adds,
            'checkouts' => (int) ($row->checkouts ?? 0),
            'proceeds' => (int) ($row->proceeds ?? 0),
            'cart_abandoned' => (int) ($row->cart_abandoned ?? 0),
            'sold' => $sold,
            'sold_qty' => (int) ($sales->sold_qty ?? 0),
            'sale_amount' => round((float) ($sales->sale_amount ?? 0), 2),
        ];
    }

    /**
     * Prefix match on indexed columns, picked by what the keyword looks like:
     * IP → tracking_session_details.ip, email → email, digits → order # / phone / session id,
     * session-id-like hex → session_id or product, anything else → name, email, order # or product.
     * Product = code / SKU prefix or title contains (tracking_product is a small lookup table),
     * linked to sessions through tracking_session_p_cat.
     */
    private function applySearch(Builder $query, string $search): void
    {
        $search = ltrim(trim($search), '#');

        if ($search === '') {
            return;
        }

        $prefix = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $search).'%';
        // Each UNION branch uses its own index (OR across columns cannot); UNION also de-duplicates.
        $union = fn (Builder ...$branches) => array_reduce(array_slice($branches, 1), fn (Builder $all, Builder $branch) => $all->union($branch), $branches[0]);
        $sessionsWhere = fn (string $column) => DB::table('tracking_session')->select('id')->where($column, 'like', $prefix);
        $productsWhere = fn (string $column) => DB::table('tracking_product')->select('id')->where($column, 'like', $prefix);

        $productBranches = [$productsWhere('code'), $productsWhere('sku')];
        $titleWords = array_filter(preg_split('/[^\p{L}\p{N}]+/u', mb_strtolower($search)), fn (string $word) => mb_strlen($word) >= 3);

        if ($titleWords !== []) {
            $productBranches[] = DB::table('tracking_product')->select('id')->whereFullText('title', implode(' ', array_map(fn (string $word) => '+'.$word.'*', $titleWords)), ['mode' => 'boolean']);
        }

        $productSessions = DB::query()
            ->fromSub($union(...$productBranches), 'search_product')
            ->join('tracking_session_p_cat as search_pc', 'search_pc.tracking_product_id', '=', 'search_product.id')
            ->select('search_pc.tracking_session_id as id');
        $matching = fn (Builder ...$branches) => $query->joinSub($union(...$branches), 'search_match', 'search_match.id', '=', 'ts.id');

        $orderSessions = DB::table('tracking_orders')
            ->select('tracking_session_id as id')
            ->whereNotNull('tracking_session_id')
            ->where('order_number', 'like', $prefix);
        $orderActionSessions = DB::table('activity_ecom_user_actions as oa')
            ->join('tracking_session as ts_oa', 'ts_oa.session_id', '=', 'oa.session_id')
            ->where('oa.action_type', 'payment_success')
            ->whereNotNull('oa.order_id')
            ->where('oa.order_id', 'like', $prefix)
            ->select('ts_oa.id');
        $phoneOrSessionSessions = DB::table('tracking_session')
            ->select('id')
            ->where(fn (Builder $q) => $q
                ->where('phone', 'like', str_replace([' ', '(', ')'], '', $prefix))
                ->orWhere('session_id', 'like', $prefix));

        match (true) {
            (bool) preg_match('/^\d{1,3}\.\d{0,3}(\.\d{0,3}){0,2}$/', $search),
            str_contains($search, ':') => $query->whereIn('ts.id', fn (Builder $details) => $details
                ->select('tracking_session_id')
                ->from('tracking_session_details')
                ->where('ip', 'like', $prefix)),
            str_contains($search, '@') => $query->where('ts.email', 'like', $prefix),
            (bool) preg_match('/^\+?[\d\s()-]+$/', $search) => $matching($orderSessions, $orderActionSessions, $phoneOrSessionSessions),
            (bool) preg_match('/^(?=.*\d)[0-9a-f-]{4,}$/i', $search) => $matching($orderSessions, $orderActionSessions, $sessionsWhere('session_id'), $productSessions),
            default => $matching($sessionsWhere('name'), $sessionsWhere('email'), $orderSessions, $orderActionSessions, $productSessions),
        };
    }

    /**
     * Funnel stage DESC = Order (6), Proceed (5), Checkout (4), Cart (3), then views / no stage (NULL sorts last).
     */
    private function applySort(Builder $query, string $sortBy): void
    {
        match ($sortBy) {
            'funnel_stage' => $query->orderByDesc('ts.latest_funnel_stage')->orderByDesc('ts.last_active_at'),
            'latest_activity' => $query->orderByDesc('ts.created_at'),
            'actions' => $query->orderByDesc('ts.actions_count'),
            'duration' => $query->orderByDesc('ts.duration_seconds'),
            default => $query->orderByDesc('ts.last_active_at'),
        };

        $query->orderByDesc('ts.id');
    }

    /**
     * Ordered sessions by paid amount (only a few hundred rows), then everything else by last active.
     * Two small indexed queries instead of sorting every session by a computed value.
     *
     * @return list<int>
     */
    private function orderValuePageIds(\Closure $scope, int $orderCount, int $offset, int $limit): array
    {
        $orderIds = $offset < $orderCount
            ? DB::table('tracking_session as ts')
                ->tap($scope)
                ->where('ts.has_order', 1)
                ->orderByRaw("(
                    SELECT MAX(COALESCE(o.amount_paid, o.commerce_total)) FROM activity_ecom_user_actions o
                    WHERE o.session_id = ts.session_id AND o.action_type = 'payment_success'
                ) DESC")
                ->orderByDesc('ts.last_active_at')
                ->orderByDesc('ts.id')
                ->offset($offset)
                ->limit($limit)
                ->pluck('ts.id')
                ->all()
            : [];

        if (count($orderIds) === $limit) {
            return $orderIds;
        }

        $otherIds = DB::table('tracking_session as ts')
            ->tap($scope)
            ->where('ts.has_order', 0)
            ->orderByDesc('ts.last_active_at')
            ->orderByDesc('ts.id')
            ->offset(max(0, $offset - $orderCount))
            ->limit($limit - count($orderIds))
            ->pluck('ts.id')
            ->all();

        return array_merge($orderIds, $otherIds);
    }

    private function withCommerceAction(Builder $query): Builder
    {
        $stageActionType = "CASE ts.latest_funnel_stage
            WHEN 6 THEN 'payment_success'
            WHEN 5 THEN 'proceed_checkout'
            WHEN 4 THEN 'begin_checkout'
            WHEN 3 THEN 'add_to_cart'
        END";

        return $query
            ->leftJoin('activity_ecom_user_actions as ca', function ($join) use ($stageActionType) {
                $join->on('ca.session_id', '=', 'ts.session_id')
                    ->whereRaw("ca.id = (
                        SELECT MAX(a2.id) FROM activity_ecom_user_actions a2
                        WHERE a2.session_id = ts.session_id AND a2.action_type = {$stageActionType}
                    )");
            })
            ->select([
                'ts.*',
                'ca.id as commerce_action_id',
                'ca.order_id as commerce_order_id',
                'ca.amount_paid as commerce_amount_paid',
                'ca.commerce_total',
                'ca.commerce_subtotal',
                'ca.commerce_shipping',
                'ca.commerce_discount',
                'ca.coupon_code as commerce_coupon_code',
                'ca.item_qty as commerce_item_qty',
                'ca.line_count as commerce_line_count',
                'ca.add_to_cart as commerce_add_to_cart',
                'ca.begin_checkout as commerce_begin_checkout',
                'ca.proceed_to_checkout as commerce_proceed_to_checkout',
                'ca.payment_success as commerce_payment_success',
                'ca.created_at as commerce_at',
            ]);
    }

    private function withVisitorTrust(Builder $query): void
    {
        SessionTrafficAttribution::joinLatestSessionDetails($query);
        $query
            ->leftJoin('tracking_device as td', 'td.id', '=', 'sd.tracking_device_id')
            ->leftJoin('tracking_browser as tb', 'tb.id', '=', 'sd.tracking_browser_id')
            ->leftJoin('tracking_traffic_source as tts', 'tts.id', '=', 'sd.tracking_traffic_source_id')
            ->leftJoin('activity_ecom_user as aeu', 'aeu.session_id', '=', 'ts.session_id')
            ->leftJoin('activity_ecom_user_bot_context as bc', 'bc.session_id', '=', 'ts.session_id')
            ->addSelect([
                'sd.ip as visitor_ip',
                'sd.user_agent as visitor_user_agent',
                'td.name as device_name',
                'tb.name as browser_name',
                'aeu.os as activity_os',
                'sd.utm_source as session_touch_utm_source',
                'sd.utm_medium as session_touch_utm_medium',
                'tts.name as traffic_source_name',
                'tts.medium as traffic_source_medium',
                'aeu.country as activity_country',
                'bc.id as bot_context_id',
                'bc.client_ip as bot_client_ip',
                'bc.user_agent as bot_user_agent',
                'bc.ip_country as bot_ip_country',
                'bc.cf_bot_score as bot_cf_score',
                'bc.is_bot as bot_is_bot',
                'bc.bot_reason',
            ]);
    }

    /**
     * Same rules as {@see ActivityEcomUser::visitorClassification()} on master:
     * stored ingest verdict in activity_ecom_user_bot_context only (no live UA re-classification).
     *
     * @return array{classification: string, label: string, badge_class: string, help: string, country_code: ?string, country_label: ?string, ips: list<list<string>>}
     */
    private function trustCell(object $row): array
    {
        if ($row->bot_context_id === null) {
            $classification = 'unclassified';
            $isBot = false;
            $reason = null;
        } else {
            $isBot = (bool) $row->bot_is_bot;
            $classification = $isBot ? 'bot' : 'human';
            $reason = $row->bot_reason;
        }
        $countryRaw = $row->bot_ip_country ?? $row->activity_country ?? null;
        $countryCode = filled($countryRaw) ? strtoupper((string) $countryRaw) : null;
        $help = $classification === 'unclassified'
            ? VisitorClassificationLabels::unclassifiedHelp()
            : VisitorClassificationLabels::reason($reason, $isBot)['help'];

        if ($row->bot_cf_score !== null) {
            $help .= ' · '.VisitorClassificationLabels::trustScoreLabel((int) $row->bot_cf_score);
        }

        return [
            'classification' => $classification,
            'label' => VisitorClassificationLabels::typeLabel($classification),
            'badge_class' => VisitorClassificationLabels::typeBadgeClass($classification),
            'help' => $help,
            'country_code' => $countryCode,
            'country_label' => VisitorClassificationLabels::countryLabel($countryCode),
            'ips' => collect([$row->visitor_ip, $row->bot_client_ip])
                ->flatMap(fn ($ip) => explode(',', (string) $ip))
                ->map(fn (string $ip) => trim($ip))
                ->filter()
                ->unique()
                ->map(fn (string $ip) => $this->ipLines($ip))
                ->values()
                ->all(),
        ];
    }

    /**
     * Long IPv6 addresses are split at the colon nearest the middle so both lines are similar in length.
     *
     * @return list<string>
     */
    private function ipLines(string $ip): array
    {
        if (strlen($ip) <= 28 || ! str_contains($ip, ':')) {
            return [$ip];
        }

        $middle = intdiv(strlen($ip), 2);
        $splitAt = null;

        for ($position = 0, $length = strlen($ip); $position < $length; $position++) {
            if ($ip[$position] === ':' && ($splitAt === null || abs($position - $middle) < abs($splitAt - $middle))) {
                $splitAt = $position;
            }
        }

        return [substr($ip, 0, $splitAt + 1), substr($ip, $splitAt + 1)];
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private function focusMetrics(object $row, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $metrics = [];

        foreach ($keys as $key) {
            $metrics[$key] = match ($key) {
                'device' => ucfirst((string) ($row->device_name ?? '—')),
                'device_detail' => collect([
                    ucfirst((string) ($row->device_name ?? '—')),
                    (string) ($row->browser_name ?? '—'),
                    (string) ($row->activity_os ?? '—'),
                ])->filter(fn (string $part) => $part !== '' && $part !== '—')->implode(' · ') ?: '—',
                'traffic_source' => filled($row->session_touch_utm_source)
                    ? (string) $row->session_touch_utm_source
                    : (filled($row->traffic_source_name) ? (string) $row->traffic_source_name : 'Direct'),
                'traffic_medium' => filled($row->session_touch_utm_medium)
                    ? (string) $row->session_touch_utm_medium
                    : (filled($row->traffic_source_medium) ? (string) $row->traffic_source_medium : '—'),
                'classification_reason' => $row->bot_context_id === null
                    ? 'Not classified'
                    : VisitorClassificationLabels::reason($row->bot_reason, (bool) $row->bot_is_bot)['headline'],
                default => '—',
            };
        }

        return $metrics;
    }

    /**
     * @param  Collection<int, object>  $rows
     */
    private function attachCatalogPaths(Collection $rows): void
    {
        $sessionIds = $rows->pluck('id')->all();

        $paths = DB::table('tracking_session_p_cat as sp')
            ->join('tracking_category as cat', 'cat.id', '=', 'sp.tracking_category_id')
            ->leftJoin('tracking_category as dept', 'dept.id', '=', 'cat.parent_id')
            ->whereIn('sp.tracking_session_id', $sessionIds)
            ->groupBy('sp.tracking_session_id')
            ->selectRaw("sp.tracking_session_id as tracking_session_id, MIN(CASE WHEN dept.id IS NOT NULL THEN CONCAT(dept.name, ' › ', cat.name) ELSE cat.name END) as catalog_path")
            ->pluck('catalog_path', 'tracking_session_id');

        foreach ($rows as $row) {
            $row->catalog_path = $paths[$row->id] ?? '—';
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function commerceCell(object $row): array
    {
        $stage = (int) ($row->latest_funnel_stage ?? 0);
        $stageKey = self::STAGE_KEYS[$stage] ?? null;

        if ($stageKey === null) {
            return [
                'commerce_display' => $stage > 0 ? 'View' : '—',
                'commerce_has_order' => false,
                'expandable_commerce_events' => [],
            ];
        }

        $label = self::STAGE_LABELS[$stageKey];
        $isOrder = $stageKey === 'payment_success';
        $orderId = trim((string) ($row->commerce_order_id ?? ''));
        $amount = $this->amount($isOrder ? ($row->commerce_amount_paid ?: $row->commerce_total) : $row->commerce_total);
        $triggerLabel = ($isOrder ? ($orderId !== '' ? '#'.$orderId : 'Order') : $label)
            .($amount !== null ? ' · '.$this->money($amount) : '');

        $event = $row->commerce_action_id
            ? $this->commerceEvent($row, $stageKey, $label, $triggerLabel, $amount, $orderId)
            : null;

        return [
            'commerce_display' => $triggerLabel,
            'commerce_has_order' => $isOrder,
            'expandable_commerce_events' => $event ? [$event] : [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function commerceEvent(object $row, string $stageKey, string $label, string $triggerLabel, ?float $amount, string $orderId): array
    {
        $json = json_decode((string) ($row->{'commerce_'.($stageKey === 'proceed_checkout' ? 'proceed_to_checkout' : $stageKey)} ?? ''), true) ?: [];
        $items = match ($stageKey) {
            'payment_success' => $json['checkout_info']['items'] ?? [],
            'add_to_cart' => $json['items'] ?? [],
            default => $json['cart_items'] ?? [],
        };

        $products = array_values(array_map(
            fn (array $item) => $this->product($item),
            array_filter((array) $items, 'is_array'),
        ));

        $qty = (int) ($row->commerce_item_qty ?? 0)
            ?: (int) array_sum(array_map(fn (array $product) => (int) $product['qty'], $products))
            ?: (int) ($row->commerce_line_count ?? 0);

        $event = [
            'id' => $stageKey.':'.$row->commerce_action_id,
            'stage' => $stageKey,
            'stage_label' => $label,
            'trigger_label' => $triggerLabel,
            'occurred_at' => TrackerTime::formatFromStorage($row->commerce_at),
            'products' => $products,
        ];

        $pricing = $this->resolvedCommercePricing($row, $stageKey, $json);
        $grandTotal = $pricing['grand_total'] ?? $amount;
        $summaryRows = $this->pricingSummaryRows($pricing);

        if ($stageKey !== 'payment_success') {
            return $event + [
                'title' => $stageKey === 'add_to_cart' ? 'Cart details' : $label,
                'layout' => 'compact',
                'cart_qty' => $qty,
                'cart_total' => $grandTotal !== null ? $this->money($grandTotal) : null,
                'pricing_summary_rows' => $summaryRows,
            ];
        }

        $customer = $json['checkout_info']['customer'] ?? [];

        return $event + [
            'title' => 'Order details'.($orderId !== '' ? ': #'.$orderId : ''),
            'layout' => 'detail',
            'pricing_summary_rows' => $summaryRows,
            'info_groups' => array_values(array_filter([
                [
                    'title' => 'Order info',
                    'fields' => array_values(array_filter([
                        $orderId !== '' ? ['label' => 'Order ID', 'value' => $orderId] : null,
                        $grandTotal !== null ? ['label' => 'Total', 'value' => $this->money($grandTotal), 'emphasis' => true] : null,
                        $qty > 0 ? ['label' => 'Quantity', 'value' => (string) $qty] : null,
                        filled($json['payment_method'] ?? null) ? ['label' => 'Payment', 'value' => (string) $json['payment_method']] : null,
                        ['label' => 'Ordered', 'value' => TrackerTime::formatFromStorage($row->commerce_at, 'Y-m-d h:i A') ?? '—'],
                    ])),
                ],
                (filled($customer['email'] ?? null) || filled($customer['phone'] ?? null))
                    ? [
                        'title' => 'Customer',
                        'fields' => array_values(array_filter([
                            filled($customer['phone'] ?? null) ? ['label' => 'Phone', 'value' => (string) $customer['phone']] : null,
                            filled($customer['email'] ?? null) ? ['label' => 'Email', 'value' => (string) $customer['email']] : null,
                        ])),
                    ]
                    : null,
                $summaryRows !== []
                    ? [
                        'title' => 'Prices',
                        'fields' => $summaryRows,
                    ]
                    : null,
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $json
     * @return array{
     *     subtotal: ?float,
     *     shipping: ?float,
     *     discount: ?float,
     *     service_charge: ?float,
     *     extra_charges: ?float,
     *     grand_total: ?float,
     *     coupon_code: ?string
     * }
     */
    private function resolvedCommercePricing(object $row, string $stageKey, array $json): array
    {
        $actionType = $stageKey === 'proceed_checkout' ? 'proceed_checkout' : $stageKey;
        $extracted = [];

        try {
            $extracted = CommercePricingExtractor::fromPayload($actionType, $json);
        } catch (CommerceParseException) {
            $extracted = [];
        }

        $pick = fn (mixed $primary, mixed $fallback = null): ?float => $this->amount($primary) ?? (is_numeric($fallback) ? round((float) $fallback, 2) : null);

        $grandTotal = $pick(
            $stageKey === 'payment_success' ? ($row->commerce_amount_paid ?? null) : null,
            null,
        ) ?? $pick($row->commerce_total ?? null, $extracted['grand_total'] ?? $extracted['commerce_total'] ?? null);

        $subtotal = $pick($row->commerce_subtotal ?? null, $extracted['commerce_subtotal'] ?? $extracted['subtotal'] ?? null);
        $shipping = $pick($row->commerce_shipping ?? null, $extracted['commerce_shipping'] ?? $extracted['shipping_charge'] ?? null);
        $discount = $pick($row->commerce_discount ?? null, $extracted['commerce_discount'] ?? $extracted['discount_amount'] ?? null);
        $serviceCharge = $pick(null, $extracted['service_charge'] ?? null);
        $extraCharges = $pick(null, $extracted['extra_charges_total'] ?? null);

        $couponCode = trim((string) ($row->commerce_coupon_code ?? $extracted['coupon_code'] ?? $json['coupon_code'] ?? ''));

        return [
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'discount' => $discount,
            'service_charge' => $serviceCharge,
            'extra_charges' => $extraCharges,
            'grand_total' => $grandTotal,
            'coupon_code' => $couponCode !== '' ? $couponCode : null,
        ];
    }

    /**
     * @param  array{
     *     subtotal: ?float,
     *     shipping: ?float,
     *     discount: ?float,
     *     service_charge: ?float,
     *     extra_charges: ?float,
     *     grand_total: ?float,
     *     coupon_code: ?string
     * }  $pricing
     * @return list<array{label: string, value: string, emphasis?: bool}>
     */
    private function pricingSummaryRows(array $pricing): array
    {
        $subtotal = $pricing['subtotal'];
        $shipping = $pricing['shipping'];
        $discount = $pricing['discount'];
        $serviceCharge = $pricing['service_charge'];
        $extraCharges = $pricing['extra_charges'];
        $grandTotal = $pricing['grand_total'];
        $couponCode = $pricing['coupon_code'];

        $hasAdjustments = ($shipping !== null && $shipping > 0)
            || ($discount !== null && $discount > 0)
            || ($serviceCharge !== null && $serviceCharge > 0)
            || ($extraCharges !== null && $extraCharges > 0)
            || filled($couponCode);

        $rows = [];

        if ($subtotal !== null && $subtotal > 0 && ($hasAdjustments || ($grandTotal !== null && abs($subtotal - $grandTotal) > 0.009))) {
            $rows[] = ['label' => 'Subtotal', 'value' => $this->money($subtotal)];
        }

        if (filled($couponCode)) {
            $rows[] = ['label' => 'Coupon', 'value' => (string) $couponCode];
        }

        if ($discount !== null && $discount > 0) {
            $rows[] = ['label' => 'Discount', 'value' => '- '.$this->money($discount)];
        }

        if ($shipping !== null && $shipping > 0) {
            $rows[] = ['label' => 'Delivery', 'value' => $this->money($shipping)];
        }

        if ($serviceCharge !== null && $serviceCharge > 0) {
            $rows[] = ['label' => 'Service charge', 'value' => $this->money($serviceCharge)];
        }

        if ($extraCharges !== null && $extraCharges > 0) {
            $rows[] = ['label' => 'Extra charges', 'value' => $this->money($extraCharges)];
        }

        if ($grandTotal !== null) {
            $rows[] = ['label' => 'Total', 'value' => $this->money($grandTotal), 'emphasis' => true];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, string>
     */
    private function product(array $item): array
    {
        $title = trim((string) ($item['product_name'] ?? ''));
        $code = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));

        return array_filter([
            'title' => $title !== '' && $code !== '' ? "{$title} ({$code})" : ($title ?: ($code ?: 'Product')),
            'size' => trim((string) ($item['size_name'] ?? '')),
            'color_po' => trim((string) ($item['color_name'] ?? '')),
            'qty' => (string) max(1, (int) ($item['qty'] ?? 1)),
            'price' => is_numeric($item['price'] ?? null) ? $this->money((float) $item['price']) : '—',
        ], fn ($value) => $value !== '');
    }

    /**
     * @return array{label: string, value: string}|null
     */
    private function moneyField(string $label, mixed $value, bool $negative = false): ?array
    {
        $amount = $this->amount($value);

        return $amount !== null
            ? ['label' => $label, 'value' => ($negative ? '- ' : '').$this->money($amount)]
            : null;
    }

    private function amount(mixed $value): ?float
    {
        return is_numeric($value) && (float) $value > 0 ? round((float) $value, 2) : null;
    }

    private function money(float $amount): string
    {
        return '£'.number_format($amount, 2);
    }
}
