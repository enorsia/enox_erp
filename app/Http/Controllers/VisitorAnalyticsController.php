<?php

namespace App\Http\Controllers;

use App\Exports\VisitorAnalyticsExport;
use App\Models\TrackerUtmFilter;
use App\Services\BotTrafficAnalyticsService;
use App\Services\VisitorAnalyticsService;
use App\Support\EcomTrackerLogger;
use App\Support\EcomTrackerViewData;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisitorAnalyticsController extends Controller
{
    public function __construct(
        private VisitorAnalyticsService $analytics,
        private BotTrafficAnalyticsService $botTrafficAnalytics,
    ) {}

    public function index(Request $request): View
    {
        $startedAt = microtime(true);
        Gate::authorize('ecom_tracker.visitors.index');

        $range = $this->resolveRange($request);
        $filters = $request->only(['window', 'datetime_from', 'datetime_to']);
        $filters['window_label'] = $range['label'];

        $cached = $this->analytics->getCachedOverview($range['from'], $range['until']);
        $overview = $cached['overview'];

        $data = [
            'window' => $request->input('window', '24h'),
            'from' => $range['from'],
            'to' => $range['to'],
            'summary' => $overview,
            'duration_buckets' => $overview['duration_buckets'],
            'new_returning' => $overview['new_returning'],
            'trend' => $overview['trend'],
            'top_visitors' => $overview['top_visitors'],
            'analytics_cache' => $cached['analytics_cache'],
            'visitor_quality' => $this->botTrafficAnalytics->summaryOnly([
                'period' => 'custom',
                'date_from' => TrackerTime::toLocal($range['from'])?->toDateString(),
                'date_to' => TrackerTime::toLocal($range['until'] ?? $range['to'])?->toDateString(),
            ]),
        ];

        EcomTrackerLogger::backend()->info('analytics.visitors', 'Admin opened visitor analytics page', [
            'window' => $data['window'],
            'cache_hit' => $cached['analytics_cache']['hit'] ?? null,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('ecom_tracker.visitors', [
            'analytics' => $data,
            'filters' => $filters,
            'page' => EcomTrackerViewData::forVisitors($request, $filters),
        ]);
    }

    public function detail(Request $request, string $section): View
    {
        Gate::authorize('ecom_tracker.visitors.index');

        $titles = [
            'trend' => 'Unique visitors vs sessions',
            'new-returning' => 'Unique vs returning visitors',
            'duration' => 'Session duration distribution',
            'visitors' => 'All visitors',
        ];

        abort_unless(isset($titles[$section]), 404);

        $range = $this->resolveRange($request);
        $extraFilters = $this->visitorSessionFilters($request);
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));
        $sortBy = $this->analytics->resolveVisitorSort($request->input('sort_by'));
        $paginator = null;

        $data = match ($section) {
            'trend' => $this->buildTrendDetailData($range, $request, $paginator),
            'new-returning' => ['new_returning' => $this->analytics->buildNewVsReturning($range['from'], $range['until'])],
            'duration' => ['duration_buckets' => $this->analytics->buildDurationBuckets($range['from'], $range['until'])],
            'visitors' => ['visitors' => $this->analytics->buildVisitorBreakdown($range['from'], $perPage, $range['until'], $extraFilters)],
        };

        $filters = array_merge(
            $request->only(['window', 'datetime_from', 'datetime_to']),
            $extraFilters,
            ['window_label' => $range['label'], 'sort_by' => $sortBy],
        );

        return view('ecom_tracker.visitor_details.show', [
            'section' => $section,
            'title' => $titles[$section],
            'range' => $range,
            'data' => $data,
            'filters' => $filters,
            'paginator' => $paginator,
            'visitorSortOptions' => $section === 'visitors' ? $this->analytics->visitorSortOptions() : [],
            'currentSort' => $section === 'visitors' ? $sortBy : null,
            'page' => EcomTrackerViewData::forVisitorDetail(
                $request,
                $filters,
                $titles[$section],
                $section,
                $this->visitorActiveFilterCount($request),
            ),
        ]);
    }

    private function visitorActiveFilterCount(Request $request): int
    {
        $count = 0;

        if (filled($request->input('datetime_from')) && filled($request->input('datetime_to'))) {
            $count++;
        } elseif ($request->has('window') && $request->input('window', '24h') !== '24h') {
            $count++;
        }

        foreach (['search', 'device_type', 'logged_in', 'has_order', 'utm_source', 'utm_medium'] as $key) {
            if (filled($request->input($key))) {
                $count++;
            }
        }

        return $count;
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('ecom_tracker.visitors.index');

        $range = $this->resolveRange($request);
        $filename = 'visitor-analytics-'.$range['from']->format('Y-m-d').'-'.$range['to']->format('Y-m-d').'.xlsx';

        return Excel::download(
            VisitorAnalyticsExport::fromRange(
                $this->analytics,
                $range['from'],
                $range['until'],
                $range['label'],
            ),
            $filename,
        );
    }

    /**
     * @return array{from: Carbon, to: Carbon, until: ?Carbon, label: string}
     */
    private function resolveRange(Request $request): array
    {
        $timezone = TrackerTime::timezone();
        $datetimeFrom = $request->input('datetime_from');
        $datetimeTo = $request->input('datetime_to');

        if (filled($datetimeFrom) && filled($datetimeTo)) {
            $fromLocal = Carbon::parse($datetimeFrom, $timezone);
            $toLocal = Carbon::parse($datetimeTo, $timezone);

            if ($fromLocal->gt($toLocal)) {
                [$fromLocal, $toLocal] = [$toLocal, $fromLocal];
            }

            $from = $fromLocal->copy()->utc();
            $to = $toLocal->copy()->utc();

            return [
                'from' => $from,
                'to' => $to,
                'until' => $to,
                'label' => $fromLocal->format('d M Y, H:i').' – '.$toLocal->format('d M Y, H:i'),
            ];
        }

        $window = $request->input('window', '24h');
        $from = $this->resolveSince($request, $window);
        $to = TrackerTime::nowUtc();

        return [
            'from' => $from,
            'to' => $to,
            'until' => null,
            'label' => $this->buildWindowLabel($request, $window),
        ];
    }

    private function resolveSince(Request $request, string $window): Carbon
    {
        return $this->analytics->resolveWindow($window);
    }

    private function buildWindowLabel(Request $request, string $window): string
    {
        return match ($window) {
            '1h' => 'Last 1 hour',
            '3h' => 'Last 3 hours',
            '6h' => 'Last 6 hours',
            '12h' => 'Last 12 hours',
            '24h' => 'Last 24 hours',
            '1d' => 'Last 1 day',
            '7d' => 'Last 7 days',
            '14d' => 'Last 14 days',
            '30d' => 'Last 30 days',
            '90d' => 'Last 90 days',
            '1w' => 'Last 1 week',
            '4w' => 'Last 4 weeks',
            '12w' => 'Last 12 weeks',
            '52w' => 'Last 52 weeks',
            '1m' => 'Last 1 month',
            '3m' => 'Last 3 months',
            '6m' => 'Last 6 months',
            '12m' => 'Last 12 months',
            '1y' => 'Last 1 year',
            default => 'Last 24 hours',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function visitorSessionFilters(Request $request): array
    {
        $filters = $request->only(['search', 'device_type', 'logged_in', 'has_order', 'sort_by', 'utm_source', 'utm_medium']);
        $filters['utm_source'] = TrackerUtmFilter::resolveSource($filters['utm_source'] ?? null) ?? '';
        $filters['utm_medium'] = TrackerUtmFilter::resolveMedium($filters['utm_medium'] ?? null) ?? '';

        return $filters;
    }

    /**
     * @param  array{from: Carbon, to: Carbon, until: ?Carbon, label: string}  $range
     * @return array<string, mixed>
     */
    private function buildTrendDetailData(array $range, Request $request, ?LengthAwarePaginator &$paginator): array
    {
        $trend = $this->analytics->buildVisitorTrend($range['from'], $range['until']);

        $rows = collect($trend['labels'] ?? [])->map(function (string $label, int $index) use ($trend) {
            return [
                'date' => $label,
                'unique_visitors' => $trend['visitors'][$index] ?? 0,
                'sessions' => $trend['sessions'][$index] ?? 0,
            ];
        })
            ->filter(fn (array $row) => ($row['unique_visitors'] ?? 0) > 0 || ($row['sessions'] ?? 0) > 0)
            ->values()
            ->all();

        $paginator = $this->paginateArray($rows, $request);

        return [
            'trend' => $trend,
            'table_rows' => $paginator->items(),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    private function paginateArray(array $items, Request $request): LengthAwarePaginator
    {
        $page = max(1, (int) $request->input('page', 1));
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));
        $collection = collect($items);

        return new LengthAwarePaginator(
            $collection->slice(($page - 1) * $perPage, $perPage)->values(),
            $collection->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );
    }
}
