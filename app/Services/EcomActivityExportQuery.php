<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\TrackerUtmFilter;
use App\Support\CommerceHasOrderFilter;
use App\Support\EcomActivityFocus;
use App\Support\EcomActivityKeywordSearch;
use App\Support\EcomActivitySessionSort;
use App\Support\SessionDurationBuckets;
use App\Support\TrackerMultiSelectFilter;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class EcomActivityExportQuery
{
    public function __construct(
        private EcomTrackerDashboardService $dashboardService,
        private EcomActivityFunnelSessions $funnelSessions,
    ) {}

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array{from: Carbon, to: Carbon, label: string, period: ?string}
     */
    public function resolveRange(array $queryParams): array
    {
        $request = $this->requestFromParams($queryParams);

        if ($request->input('period') === 'all') {
            return [
                'from' => Carbon::parse('2000-01-01', 'UTC'),
                'to' => TrackerTime::nowUtc(),
                'label' => 'All sessions',
                'period' => 'all',
            ];
        }

        $range = $this->dashboardService->resolveDateRange([
            'period' => $request->input('period', '24h'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
        ]);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'label' => $range['label'],
            'period' => $range['period'] ?? $request->input('period', '24h'),
        ];
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return Builder<ActivityEcomUser>
     */
    public function buildSortedQuery(array $queryParams): Builder
    {
        $request = $this->requestFromParams($queryParams);
        $range = $this->resolveRange($queryParams);
        $focus = $request->input('focus');
        $query = $this->buildIndexQuery($request, $range);
        $scope = $this->sessionSortScope($request, $range, $focus);

        return EcomActivitySessionSort::apply(
            $query,
            EcomActivitySessionSort::resolveSortBy($request),
            EcomActivitySessionSort::resolveSortDir(
                $request,
                EcomActivitySessionSort::resolveSortBy($request) ?? EcomActivitySessionSort::DEFAULT_SORT_KEY,
            ),
            $scope,
        );
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<string, array<string, mixed>>
     */
    public function funnelMetricsForExport(array $queryParams): array
    {
        $request = $this->requestFromParams($queryParams);
        $metricsFocus = EcomActivityFocus::resolveFunnelMetricsFocus($request);

        if ($metricsFocus === null) {
            return [];
        }

        $range = $this->resolveRange($queryParams);

        return EcomActivityFocus::resolveFunnelContext(
            $metricsFocus,
            $range['from'],
            $range['to'],
            EcomActivityFocus::sessionFiltersFromRequest($request),
            $range['period'],
            $this->funnelSessions,
        )['metrics'];
    }

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<string, mixed>
     */
    public function productCatalogOptions(array $queryParams): array
    {
        $request = $this->requestFromParams($queryParams);
        $focus = $request->input('focus');

        return in_array($focus, ['products', 'categories'], true)
            ? EcomActivityFocus::productCatalogFiltersFromRequest($request)
            : EcomActivityFocus::indexCatalogFiltersFromRequest($request);
    }

    /**
     * @param  array<string, mixed>  $queryParams
     */
    public function requestFromParams(array $queryParams): Request
    {
        return Request::create('/', 'GET', $queryParams);
    }

    /**
     * @return Builder<ActivityEcomUser>
     */
    private function buildIndexQuery(Request $request, array $range): Builder
    {
        $query = ActivityEcomUser::query()->with(['botContext']);
        $focus = $request->input('focus');

        if (! EcomActivityFocus::usesActionScopedSessionDate($request)) {
            $this->applySessionDateFilter($query, $request, $range);
        }

        EcomActivityFocus::applyFocusFilter(
            $query,
            $focus,
            $range['from'],
            $range['to'],
            $request,
            $this->dashboardService,
            $this->funnelSessions,
        );

        EcomActivityFocus::applyDrawerFunnelFilter(
            $query,
            $request,
            $range['from'],
            $range['to'],
            $this->funnelSessions,
        );

        if (
            $request->filled('search')
            && EcomActivityFocus::shouldApplySessionKeywordSearch($request)
        ) {
            EcomActivityKeywordSearch::apply(
                $query,
                trim((string) $request->search),
                $this->dashboardService,
                $range['from'],
                $range['to'],
                $range['period'] ?? $request->input('period', '24h'),
            );
        }

        if ($request->filled('country')) {
            $query->where(function ($q) use ($request) {
                $q->where('country', $request->country)
                    ->orWhereHas('botContext', fn ($b) => $b->where('ip_country', $request->country));
            });
        }

        $visitorType = $request->input('visitor_type');

        if ($visitorType === 'bot') {
            $query->whereHas('botContext', fn ($b) => $b->where('is_bot', true));
        } elseif ($visitorType === 'human') {
            $query->whereHas('botContext', fn ($b) => $b->where('is_bot', false));
        } elseif ($visitorType === 'unclassified') {
            $query->whereDoesntHave('botContext');
        }

        if (TrackerMultiSelectFilter::requestFilled($request, 'device_type')) {
            $devices = TrackerMultiSelectFilter::allowedValues(
                $request->input('device_type'),
                ['desktop', 'mobile', 'tablet'],
            );

            if ($devices !== []) {
                $query->whereIn('device_type', $devices);
            }
        }

        if (TrackerMultiSelectFilter::requestFilled($request, 'duration_bucket')) {
            SessionDurationBuckets::applyManyToQuery($query, TrackerMultiSelectFilter::requestValues($request, 'duration_bucket'));
        }

        if ($request->filled('logged_in')) {
            $query->where('is_logged_in', $request->logged_in === '1');
        }

        if ($request->filled('has_order') && ! EcomActivityFocus::shouldDeferHasOrderFilter($request)) {
            CommerceHasOrderFilter::apply(
                $query,
                $request->has_order === '1',
                $range['from'],
                $range['to'],
            );
        }

        TrackerUtmFilter::applySourceFilter($query, $request->input('utm_source'));
        TrackerUtmFilter::applyMediumFilter($query, $request->input('utm_medium'));

        if (EcomActivityFocus::shouldApplyCatalogConstraintsInIndexQuery($focus, $request)) {
            EcomActivityFocus::applyProductCatalogConstraints(
                $query,
                $range['from'],
                $range['to'],
                $request,
                $this->dashboardService,
                $range['period'] ?? $request->input('period', '24h'),
            );
        }

        return $query;
    }

    private function applySessionDateFilter(Builder $query, Request $request, array $range): void
    {
        if (($range['period'] ?? null) === 'all') {
            return;
        }

        TrackerTime::applyEcomActivitySessionScope(
            $query,
            $range['from'],
            $range['to'],
            $range['period'] ?? $request->input('period', '24h'),
        );
    }

    /**
     * @return array{from: Carbon, to: Carbon, catalog_options: array<string, mixed>}
     */
    private function sessionSortScope(Request $request, array $range, ?string $focus): array
    {
        $catalogOptions = in_array($focus, ['products', 'categories'], true)
            ? EcomActivityFocus::productCatalogFiltersFromRequest($request)
            : EcomActivityFocus::indexCatalogFiltersFromRequest($request);

        return [
            'from' => $range['from'],
            'to' => $range['to'],
            'catalog_options' => EcomActivitySessionSort::usesCatalogActionScope($catalogOptions)
                ? $catalogOptions
                : [],
        ];
    }
}
