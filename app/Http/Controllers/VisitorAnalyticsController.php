<?php

namespace App\Http\Controllers;

use App\Exports\VisitorAnalyticsExport;
use App\Services\VisitorAnalyticsService;
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
        Gate::authorize('general.ecom_tracker_dashboard.index');

        $range = $this->resolveRange($request);
        $filters = $request->only(['window', 'datetime_from', 'datetime_to']);
        $filters['window_label'] = $range['label'];

        $data = [
            'window' => $request->input('window', '7d'),
            'from' => $range['from'],
            'to' => $range['to'],
            'summary' => $this->analytics->buildSummary($range['from'], $range['until']),
            'rolling_windows' => $this->analytics->buildRollingWindows(),
            'duration_buckets' => $this->analytics->buildDurationBuckets($range['from'], $range['until']),
            'visitors' => $this->analytics->buildVisitorBreakdown($range['from'], 25, $range['until']),
        ];

        return view('ecom_tracker.visitors', [
            'analytics' => $data,
            'filters' => $filters,
        ]);
    }

    public function export(Request $request): BinaryFileResponse
    {
        Gate::authorize('general.ecom_tracker_dashboard.index');

        $range = $this->resolveRange($request);
        $filename = 'visitor-analytics-'.$range['from']->format('Y-m-d-His').'.xlsx';

        return Excel::download(
            new VisitorAnalyticsExport($this->analytics, $range['from'], $range['until']),
            $filename,
        );
    }

    /**
     * @return array{from: Carbon, to: Carbon, until: ?Carbon, label: string}
     */
    private function resolveRange(Request $request): array
    {
        $timezone = config('tracker.visitor_timezone', 'Europe/London');
        $datetimeFrom = $request->input('datetime_from');
        $datetimeTo = $request->input('datetime_to');

        if (filled($datetimeFrom) && filled($datetimeTo)) {
            $from = Carbon::parse($datetimeFrom, $timezone);
            $to = Carbon::parse($datetimeTo, $timezone);

            if ($from->gt($to)) {
                [$from, $to] = [$to, $from];
            }

            return [
                'from' => $from,
                'to' => $to,
                'until' => $to,
                'label' => $from->format('d M Y, H:i').' – '.$to->format('d M Y, H:i'),
            ];
        }

        $window = $request->input('window', '7d');
        $from = $this->resolveSince($request, $window);
        $to = Carbon::now($timezone);

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
            default => 'Last 7 days',
        };
    }
}
