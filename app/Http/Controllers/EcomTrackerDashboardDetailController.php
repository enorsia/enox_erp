<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CountsTrackerFilters;
use App\Services\EcomTrackerDashboardService;
use App\Services\EcomTrackerFeatureGate;
use App\Support\EcomTrackerLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardDetailController extends EcomTrackerAdminController
{
    use CountsTrackerFilters;

    private const SECTIONS = [
        'categories' => 'Category performance',
        'products' => 'Product performance',
        'colors' => 'Product performance',
        'cart-abandonment' => 'Cart abandonment',
        'begin-checkout-abandonment' => 'Begin checkout abandonment',
        'checkout-abandonment' => 'Begin checkout abandonment',
        'proceed-checkout-abandonment' => 'Proceed checkout abandonment',
        'payment-success-events' => 'Payment success events',
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
        'begin-checkout-abandonment',
        'checkout-abandonment',
        'proceed-checkout-abandonment',
        'payment-success-events',
        'traffic-sources',
        'geography',
    ];

    public function __construct(
        EcomTrackerFeatureGate $featureGate,
        private EcomTrackerDashboardService $service,
    ) {
        parent::__construct($featureGate);
    }

    public function show(Request $request, string $section): View|RedirectResponse
    {
        $startedAt = microtime(true);
        Gate::authorize('ecom_tracker.dashboard.index');

        abort_unless(isset(self::SECTIONS[$section]), 404);

        if ($section === 'colors') {
            return redirect()->route('admin.ecom-tracker.dashboard.details', array_merge(
                ['section' => 'products'],
                $request->query(),
            ));
        }

        $dateFilters = $this->dashboardDateFilters($request);
        $extraFilters = array_merge(
            $this->dashboardSessionFilters($request),
            $this->dashboardProductCatalogFilters($request),
        );

        $detail = $this->service->getSectionDetail($section, $dateFilters, $extraFilters, null);
        [$detail['data'], $paginator] = $this->applyPagination($section, $detail['data'], $request);

        $currentProductSort = $this->service->resolveProductCatalogSort($request->input('sort_by'));

        EcomTrackerLogger::backend()->info('analytics.dashboard.detail', 'Admin opened dashboard detail page', [
            'section' => $section,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
        ]);

        return view('ecom_tracker.details.show', [
            'section' => $section,
            'title' => self::SECTIONS[$section],
            'detail' => $detail,
            'filters' => array_merge($dateFilters, $extraFilters),
            'activeFilterCount' => $this->dashboardActiveFilterCount(
                $request,
                includeProductCatalog: in_array($section, ['products', 'colors'], true),
            ),
            'paginator' => $paginator,
            'productSortGroups' => in_array($section, ['products', 'colors'], true)
                ? $this->service->productCatalogSortGroups()
                : [],
            'productActivityOptions' => in_array($section, ['products', 'colors'], true)
                ? $this->service->productCatalogActivityFilterOptions()
                : [],
            'currentProductSort' => $currentProductSort,
            'eventScenarioOptions' => in_array($section, ['products', 'colors'], true)
                ? $this->service->productCatalogEventScenarioOptions()
                : [],
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
            'products', 'colors' => $data['products'] ?? [],
            'cart-abandonment', 'begin-checkout-abandonment', 'checkout-abandonment', 'proceed-checkout-abandonment', 'payment-success-events' => $data['rows'] ?? [],
            default => $data,
        };

        $paginator = $this->paginateArray(is_array($items) ? $items : [], $request);

        $data = match ($section) {
            'products', 'colors' => array_merge($data, ['products' => $paginator->items()]),
            'cart-abandonment', 'begin-checkout-abandonment', 'checkout-abandonment', 'proceed-checkout-abandonment', 'payment-success-events' => array_merge($data, ['rows' => $paginator->items()]),
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
