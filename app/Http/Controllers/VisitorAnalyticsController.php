<?php

namespace App\Http\Controllers;

use App\Exports\VisitorAnalyticsExport;
use App\Services\VisitorAnalyticsService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class VisitorAnalyticsController extends Controller
{
    public function __construct(
        private VisitorAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
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
        ];

        return view('ecom_tracker.visitors', [
            'analytics' => $data,
            'filters' => $filters,
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
        $extraFilters = $request->only(['search', 'device_type', 'logged_in', 'has_order', 'sort_by']);
        $perPage = max(10, min(100, (int) $request->input('per_page', 25)));
        $sortBy = $this->analytics->resolveVisitorSort($request->input('sort_by'));

        $data = match ($section) {
            'trend' => ['trend' => $this->analytics->buildVisitorTrend($range['from'], $range['until'])],
            'new-returning' => ['new_returning' => $this->analytics->buildNewVsReturning($range['from'], $range['until'])],
            'duration' => ['duration_buckets' => $this->analytics->buildDurationBuckets($range['from'], $range['until'])],
            'visitors' => ['visitors' => $this->analytics->buildVisitorBreakdown($range['from'], $perPage, $range['until'], $extraFilters)],
        };

        return view('ecom_tracker.visitor_details.show', [
            'section' => $section,
            'title' => $titles[$section],
            'range' => $range,
            'data' => $data,
            'filters' => array_merge(
                $request->only(['window', 'datetime_from', 'datetime_to']),
                $extraFilters,
                ['window_label' => $range['label'], 'sort_by' => $sortBy],
            ),
            'activeFilterCount' => $this->visitorActiveFilterCount($request),
            'visitorSortOptions' => $section === 'visitors' ? $this->analytics->visitorSortOptions() : [],
            'currentSort' => $section === 'visitors' ? $sortBy : null,
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

        foreach (['search', 'device_type', 'logged_in', 'has_order'] as $key) {
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
}
