<?php

namespace App\Services;

use App\Models\TrackingCategory;
use App\Models\TrackingDevice;
use App\Models\TrackingSession;
use App\Models\TrackingTrafficSource;
use App\Support\TrackerTime;
use App\Support\VisitorClassificationLabels;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;

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

    /**
     * Option lists for the filter drawer (small lookup tables only).
     *
     * @return array{funnelStages: array<int, string>, durationBuckets: array<string, string>, devices: array<int, string>, utmSources: array<int, string>, utmMediums: list<string>, departments: array<int, string>}
     */
    public function filterOptions(): array
    {
        $trafficSources = TrackingTrafficSource::query()->orderBy('name')->get(['id', 'name', 'medium']);

        return [
            'funnelStages' => TrackingSession::FUNNEL_STAGES,
            'durationBuckets' => array_map(fn (array $bucket) => $bucket['label'], TrackingSession::DURATION_BUCKETS),
            'devices' => TrackingDevice::query()->orderBy('name')->pluck('name', 'id')->all(),
            'utmSources' => $trafficSources->pluck('name', 'id')->all(),
            'utmMediums' => $trafficSources->pluck('medium')->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all(),
            'departments' => TrackingCategory::query()->whereNull('parent_id')->orderBy('name')->pluck('name', 'id')->all(),
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
     * @return array{from: Carbon, to: Carbon}
     */
    public static function periodRange(string $period, string $dateFrom = '', string $dateTo = ''): array
    {
        $today = TrackerTime::localNow()->startOfDay();

        [$from, $to] = match (self::normalizePeriod($period)) {
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()],
            '7d' => [$today->copy()->subDays(6), $today->copy()],
            '30d' => [$today->copy()->subDays(29), $today->copy()],
            'custom' => [self::parseLocalDate($dateFrom) ?? $today->copy(), self::parseLocalDate($dateTo) ?? self::parseLocalDate($dateFrom) ?? $today->copy()],
            default => [$today->copy(), $today->copy()],
        };

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        return ['from' => $from->startOfDay(), 'to' => $to->endOfDay()];
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

    private static function parseLocalDate(string $value): ?Carbon
    {
        try {
            return $value !== '' ? Carbon::createFromFormat('Y-m-d', $value, TrackerTime::timezone())->startOfDay() : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{search: string, funnel: list<int>, has_order: ?bool, devices: list<int>, logged_in: ?bool, durations: list<string>, utm_sources: list<int>, utm_mediums: list<string>, department: ?int, categories: list<int>}
     */
    public static function filtersFromRequest(Request $request): array
    {
        $ints = fn (string $key) => array_values(array_filter(array_map('intval', (array) $request->input($key, [])), fn (int $id) => $id > 0));
        $strings = fn (string $key) => array_values(array_filter(array_map('strval', (array) $request->input($key, [])), 'filled'));
        $bool = fn (string $key) => in_array($request->input($key), ['0', '1'], true) ? $request->input($key) === '1' : null;

        return [
            'search' => mb_substr(trim((string) $request->input('search', '')), 0, 100),
            'funnel' => array_values(array_intersect($ints('funnel'), array_keys(TrackingSession::FUNNEL_STAGES))),
            'has_order' => $bool('has_order'),
            'devices' => $ints('device_type'),
            'logged_in' => $bool('logged_in'),
            'durations' => array_values(array_intersect($strings('duration_bucket'), array_keys(TrackingSession::DURATION_BUCKETS))),
            'utm_sources' => $ints('utm_source'),
            'utm_mediums' => $strings('utm_medium'),
            'department' => $request->integer('department') ?: null,
            'categories' => $ints('category'),
        ];
    }

    /**
     * @param  array{from: Carbon, to: Carbon}  $range
     * @param  array<string, mixed>  $filters  from filtersFromRequest()
     */
    public function paginate(array $range, array $filters = [], string $sortBy = self::DEFAULT_SORT, int $perPage = 25): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();
        $bounds = TrackerTime::storageRange($range['from'], $range['to']);
        $scope = function (Builder $query) use ($bounds, $filters) {
            $query->whereBetween('ts.last_active_at', $bounds);
            $this->applyFilters($query, $filters);
        };

        $counts = DB::table('tracking_session as ts')
            ->tap($scope)
            ->selectRaw('COUNT(*) as total, COALESCE(SUM(ts.has_order), 0) as orders')
            ->first();
        $total = (int) $counts->total;
        $page = min($page, max(1, (int) ceil($total / $perPage)));

        $sortBy = self::normalizeSort($sortBy);
        $pageIds = $sortBy === 'order_value'
            ? $this->orderValuePageIds($scope, (int) $counts->orders, ($page - 1) * $perPage, $perPage)
            : DB::table('tracking_session as ts')
                ->tap($scope)
                ->tap(fn (Builder $query) => $this->applySort($query, $sortBy))
                ->forPage($page, $perPage)
                ->pluck('ts.id')
                ->all();

        // Join commerce and trust data only for this page's rows; the sort/offset above stays index-only.
        $rows = $this->withCommerceAction(DB::table('tracking_session as ts')->whereIn('ts.id', $pageIds))
            ->tap(fn (Builder $query) => $this->withVisitorTrust($query))
            ->get()
            ->sortBy(fn (object $row) => array_search($row->id, $pageIds))
            ->values()
            ->map(function (object $row) {
                $row->commerce = $this->commerceCell($row);
                $row->trust = $this->trustCell($row);

                return $row;
            });

        return new LengthAwarePaginator($rows, $total, $perPage, $page, [
            'path' => Paginator::resolveCurrentPath(),
            'query' => request()->query(),
        ]);
    }

    /**
     * Every filter is a condition on the same query (no extra round trips); detail / category
     * filters use unique or composite indexes keyed by the session id.
     *
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $this->applySearch($query, (string) ($filters['search'] ?? ''));

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

        if (! empty($filters['devices']) || ! empty($filters['utm_sources']) || ! empty($filters['utm_mediums'])) {
            // One details row per session, so a join cannot duplicate rows and lets MySQL stop at the page limit.
            $query->join(DB::raw('tracking_session_details as fd USE INDEX (tsd_filter_idx)'), 'fd.tracking_session_id', '=', 'ts.id')
                ->when(! empty($filters['devices']), fn (Builder $q) => $q->whereIn('fd.tracking_device_id', $filters['devices']))
                ->when(! empty($filters['utm_sources']), fn (Builder $q) => $q->whereIn('fd.tracking_traffic_source_id', $filters['utm_sources']))
                ->when(! empty($filters['utm_mediums']), fn (Builder $q) => $q->whereIn('fd.tracking_traffic_source_id', fn (Builder $sources) => $sources
                    ->select('id')
                    ->from('tracking_traffic_source')
                    ->whereIn('medium', $filters['utm_mediums'])));
        }

        if (! empty($filters['categories'])) {
            $query->whereExists(fn (Builder $catalog) => $catalog
                ->selectRaw('1')
                ->from('tracking_session_p_cat as fc')
                ->whereColumn('fc.tracking_session_id', 'ts.id')
                ->whereIn('fc.tracking_category_id', $filters['categories']));
        }
    }

    /**
     * Prefix match on indexed columns, picked by what the keyword looks like:
     * IP → tracking_session_details.ip, email → email, digits → phone / session id,
     * session-id-like hex → session_id or product, anything else → name, email or product.
     * Product = code / SKU prefix or title contains (tracking_product is a small lookup table),
     * linked to sessions through tracking_session_p_cat.
     */
    private function applySearch(Builder $query, string $search): void
    {
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

        match (true) {
            (bool) preg_match('/^\d{1,3}\.\d{0,3}(\.\d{0,3}){0,2}$/', $search),
            str_contains($search, ':') => $query->whereIn('ts.id', fn (Builder $details) => $details
                ->select('tracking_session_id')
                ->from('tracking_session_details')
                ->where('ip', 'like', $prefix)),
            str_contains($search, '@') => $query->where('ts.email', 'like', $prefix),
            (bool) preg_match('/^\+?[\d\s()-]+$/', $search) => $query->where(fn (Builder $q) => $q
                ->where('ts.phone', 'like', str_replace([' ', '(', ')'], '', $prefix))
                ->orWhere('ts.session_id', 'like', $prefix)),
            (bool) preg_match('/^(?=.*\d)[0-9a-f-]{4,}$/i', $search) => $matching($sessionsWhere('session_id'), $productSessions),
            default => $matching($sessionsWhere('name'), $sessionsWhere('email'), $productSessions),
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
        $query
            ->leftJoin('tracking_session_details as sd', 'sd.tracking_session_id', '=', 'ts.id')
            ->leftJoin('activity_ecom_user_bot_context as bc', 'bc.session_id', '=', 'ts.session_id')
            ->addSelect([
                'sd.ip as visitor_ip',
                'sd.user_agent as visitor_user_agent',
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
     * Stored ingest verdict (Cloudflare score, then UA rules), re-checked against the current
     * crawler list so sessions saved before a pattern was added are not shown as real visitors.
     *
     * @return array{classification: string, label: string, badge_class: string, help: string, country_code: ?string, country_label: ?string, ips: list<list<string>>}
     */
    private function trustCell(object $row): array
    {
        $userAgent = (string) ($row->visitor_user_agent ?: $row->bot_user_agent);
        $isBot = (bool) $row->bot_is_bot;
        $reason = $row->bot_reason;

        if (! $isBot && $this->isKnownCrawler($userAgent)) {
            [$isBot, $reason] = [true, 'known crawler/script UA'];
        }

        $classification = $row->bot_context_id === null && ! $isBot ? 'unclassified' : ($isBot ? 'bot' : 'human');
        $countryCode = filled($row->bot_ip_country) ? strtoupper($row->bot_ip_country) : null;
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

    private function isKnownCrawler(string $userAgent): bool
    {
        if (trim($userAgent) === '') {
            return false;
        }

        static $patterns = null;
        $patterns ??= array_filter(array_map('strtolower', config('bot-detection.known_bot_user_agents', [])));
        $userAgent = strtolower($userAgent);

        foreach ($patterns as $pattern) {
            if (str_contains($userAgent, $pattern)) {
                return true;
            }
        }

        return false;
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

        if ($stageKey !== 'payment_success') {
            $discount = (float) ($row->commerce_discount ?? 0);
            $shipping = (float) ($row->commerce_shipping ?? 0);
            $coupon = trim((string) ($row->commerce_coupon_code ?? ''));

            return $event + [
                'title' => $stageKey === 'add_to_cart' ? 'Cart details' : $label,
                'layout' => 'compact',
                'cart_qty' => $qty,
                'cart_total' => $amount !== null ? $this->money($amount) : null,
                'footer_note' => implode(' · ', array_filter([
                    $coupon !== '' ? 'Coupon '.$coupon : null,
                    $discount > 0 ? 'Discount '.$this->money($discount) : null,
                    $shipping > 0 ? 'Shipping '.$this->money($shipping) : null,
                ])) ?: null,
            ];
        }

        $customer = $json['checkout_info']['customer'] ?? [];
        $totals = $json['checkout_info']['totals'] ?? [];

        return $event + [
            'title' => 'Order details'.($orderId !== '' ? ': #'.$orderId : ''),
            'layout' => 'detail',
            'info_groups' => array_values(array_filter([
                [
                    'title' => 'Order info',
                    'fields' => array_values(array_filter([
                        $orderId !== '' ? ['label' => 'Order ID', 'value' => $orderId] : null,
                        $amount !== null ? ['label' => 'Total', 'value' => $this->money($amount), 'emphasis' => true] : null,
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
                [
                    'title' => 'Prices',
                    'fields' => array_values(array_filter([
                        $this->moneyField('Sub total', $row->commerce_subtotal ?? ($totals['subtotal'] ?? null)),
                        $this->moneyField('Delivery charge', $row->commerce_shipping ?? ($totals['delivery_charge'] ?? null)),
                        $this->moneyField('Discount', $row->commerce_discount ?? null, true),
                        $amount !== null ? ['label' => 'Grand total', 'value' => $this->money($amount), 'emphasis' => true] : null,
                    ])),
                ],
            ])),
        ];
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
