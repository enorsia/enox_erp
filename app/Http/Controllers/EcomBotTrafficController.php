<?php

namespace App\Http\Controllers;

use App\Services\BotTrafficAnalyticsService;
use App\Support\VisitorClassificationLabels;
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
        'visitor_type',
        'utm_source',
        'utm_medium',
    ];

    public function __construct(
        private BotTrafficAnalyticsService $analytics,
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('ecom_tracker.bot_traffic.index');

        $filters = [
            'period' => $request->input('period', '24h'),
            'date_from' => $request->input('date_from'),
            'date_to' => $request->input('date_to'),
            'compare' => $request->input('compare', 'previous_period'),
            'search' => $request->input('search', ''),
            'device_type' => $request->input('device_type', ''),
            'logged_in' => $request->input('logged_in', ''),
            'has_order' => $request->input('has_order', ''),
            'country' => $request->input('country', ''),
            'visitor_type' => $request->input('visitor_type', ''),
            'utm_source' => $request->input('utm_source', ''),
            'utm_medium' => $request->input('utm_medium', ''),
        ];

        $report = $this->analytics->buildReport($filters);

        $chips = $this->buildFilterChips($request);

        return view('ecom_tracker.bot_traffic.index', [
            'report' => $report,
            'filters' => $filters,
            'chips' => $chips,
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
        $labels = VisitorClassificationLabels::filterTypeLabels();

        if ($request->filled('visitor_type')) {
            $chips[] = [
                'label' => $labels[$request->visitor_type] ?? $request->visitor_type,
                'remove_url' => $request->fullUrlWithQuery(['visitor_type' => null, 'page' => null]),
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
