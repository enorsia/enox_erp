<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CountsTrackerFilters;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardDetailController extends Controller
{
    use CountsTrackerFilters;

    private const SECTIONS = [
        'funnel' => 'Conversion funnel',
        'trend' => 'Sessions & conversion trend',
        'categories' => 'Category performance',
        'products' => 'Product performance',
        'colors' => 'Color performance',
        'cart-abandonment' => 'Cart abandonment',
        'checkout-abandonment' => 'Checkout abandonment',
        'devices' => 'Device breakdown',
        'traffic-sources' => 'Traffic sources',
        'geography' => 'Geography',
        'engagement' => 'Engagement',
    ];

    private const PAGINATED_SECTIONS = [
        'categories',
        'products',
        'colors',
        'cart-abandonment',
        'checkout-abandonment',
        'traffic-sources',
        'geography',
        'trend',
    ];

    public function __construct(
        private EcomTrackerDashboardService $service,
    ) {}

    public function show(Request $request, string $section): View
    {
        Gate::authorize('general.ecom_tracker_dashboard.index');

        abort_unless(isset(self::SECTIONS[$section]), 404);

        $dateFilters = $this->dashboardDateFilters($request);
        $extraFilters = $this->dashboardSessionFilters($request);

        $detail = $this->service->getSectionDetail($section, $dateFilters, $extraFilters, null);
        [$detail['data'], $paginator] = $this->applyPagination($section, $detail['data'], $request);

        return view('ecom_tracker.details.show', [
            'section' => $section,
            'title' => self::SECTIONS[$section],
            'detail' => $detail,
            'filters' => array_merge($dateFilters, $extraFilters),
            'activeFilterCount' => $this->dashboardActiveFilterCount($request),
            'paginator' => $paginator,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: array<string, mixed>, 1: ?LengthAwarePaginator}
     */
    private function applyPagination(string $section, array $data, Request $request): array
    {
        if (! in_array($section, self::PAGINATED_SECTIONS, true)) {
            return [$data, null];
        }

        $items = match ($section) {
            'colors' => $data['products'] ?? [],
            'cart-abandonment', 'checkout-abandonment' => $data['rows'] ?? [],
            'trend' => collect($data['labels'] ?? [])->map(fn (string $label, int $index) => [
                'date' => $label,
                'sessions' => $data['sessions'][$index] ?? 0,
                'purchases' => $data['purchases'][$index] ?? 0,
                'conversion_rate' => $data['conversion_rates'][$index] ?? 0,
            ])->values()->all(),
            default => $data,
        };

        $paginator = $this->paginateArray(is_array($items) ? $items : [], $request);

        $data = match ($section) {
            'colors' => array_merge($data, ['products' => $paginator->items()]),
            'cart-abandonment', 'checkout-abandonment' => array_merge($data, ['rows' => $paginator->items()]),
            'trend' => array_merge($data, ['table_rows' => $paginator->items()]),
            default => $paginator->items(),
        };

        return [$data, $paginator];
    }

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
