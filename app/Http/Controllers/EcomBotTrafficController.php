<?php

namespace App\Http\Controllers;

use App\Services\BotTrafficAnalyticsService;
use App\Support\EcomTrackerLogger;
use App\Support\EcomTrackerViewData;
use App\Support\TrackerRedisSupport;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomBotTrafficController extends Controller
{
    private const FILTER_KEYS = [
        'search',
        'device_type',
        'logged_in',
        'has_order',
        'country',
        'utm_source',
        'utm_medium',
    ];

    public function __construct(
        private BotTrafficAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        $startedAt = microtime(true);
        Gate::authorize('ecom_tracker.bot_traffic.index');

        $filters = [
            'period' => $request->input('period', '24h'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'compare' => 'none',
            'search' => $request->input('search', ''),
            'device_type' => $request->input('device_type', ''),
            'logged_in' => $request->input('logged_in', ''),
            'has_order' => $request->input('has_order', ''),
            'country' => $request->input('country', ''),
            'utm_source' => $request->input('utm_source', ''),
            'utm_medium' => $request->input('utm_medium', ''),
        ];

        $report = $this->analytics->buildReport($filters);

        $chips = $this->buildFilterChips($request);
        $page = EcomTrackerViewData::forBotTraffic($request, collect(self::FILTER_KEYS)
            ->filter(fn (string $key) => filled($request->input($key)))
            ->count());

        TrackerRedisSupport::logBackendHealth('bot_traffic_page');

        EcomTrackerLogger::backend()->info('analytics.bot_traffic', 'Admin opened bot traffic page', [
            'period' => $filters['period'],
            'active_filter_count' => collect(self::FILTER_KEYS)
                ->filter(fn (string $key) => filled($request->input($key)))
                ->count(),
            'session_count' => $report['sessions']->total(),
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('ecom_tracker.bot_traffic.index', [
            'report' => $report,
            'filters' => $filters,
            'chips' => $chips,
            'page' => $page,
            'activeFilterCount' => collect(self::FILTER_KEYS)
                ->filter(fn (string $key) => filled($request->input($key)))
                ->count(),
        ]);
    }

    /**
     * @return array<int, array{label: string, remove_url: string}>
     */
    private function buildFilterChips(Request $request): array
    {
        $chips = [];

        if ($request->filled('device_type')) {
            $chips[] = [
                'label' => 'Device: '.ucfirst((string) $request->device_type),
                'remove_url' => $request->fullUrlWithQuery(['device_type' => null, 'page' => null]),
            ];
        }

        if ($request->filled('logged_in')) {
            $chips[] = [
                'label' => $request->logged_in === '1' ? 'Logged in' : 'Guest',
                'remove_url' => $request->fullUrlWithQuery(['logged_in' => null, 'page' => null]),
            ];
        }

        if ($request->filled('has_order')) {
            $chips[] = [
                'label' => $request->has_order === '1' ? 'With order' : 'No order',
                'remove_url' => $request->fullUrlWithQuery(['has_order' => null, 'page' => null]),
            ];
        }

        if ($request->filled('utm_source')) {
            $chips[] = [
                'label' => 'Source: '.$request->utm_source,
                'remove_url' => $request->fullUrlWithQuery(['utm_source' => null, 'page' => null]),
            ];
        }

        if ($request->filled('utm_medium')) {
            $chips[] = [
                'label' => 'Medium: '.$request->utm_medium,
                'remove_url' => $request->fullUrlWithQuery(['utm_medium' => null, 'page' => null]),
            ];
        }

        if ($request->filled('country')) {
            $chips[] = [
                'label' => 'Country: '.$request->country,
                'remove_url' => $request->fullUrlWithQuery(['country' => null, 'page' => null]),
            ];
        }

        if ($request->filled('search')) {
            $chips[] = [
                'label' => '"'.$request->search.'"',
                'remove_url' => $request->fullUrlWithQuery(['search' => null, 'page' => null]),
            ];
        }

        return $chips;
    }
}
