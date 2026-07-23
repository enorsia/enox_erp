<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TrackerUtmFilter;
use App\Services\EcomTrackerDashboardService;
use App\Support\VisitorClassificationLabels;
use Illuminate\Http\Request;

trait CountsTrackerFilters
{
    protected function dashboardActiveFilterCount(Request $request, bool $includeProductCatalog = true): int
    {
        $count = 0;

        $sessionKeys = ['device_type', 'logged_in', 'has_order', 'country', 'visitor_type', 'utm_source', 'utm_medium'];
        $productKeys = ['search', 'category', 'color', 'size', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario'];

        foreach ($includeProductCatalog ? array_merge($sessionKeys, $productKeys) : $sessionKeys as $key) {
            if (filled($request->input($key))) {
                $count++;
            }
        }

        if (($request->input('period') ?? '24h') !== '24h' || filled($request->input('date_from'))) {
            $count++;
        }

        if ($includeProductCatalog) {
            $defaultSort = app(EcomTrackerDashboardService::class)->productCatalogDefaultSort();
            if (filled($request->input('sort_by')) && $request->input('sort_by') !== $defaultSort) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardProductCatalogFilters(Request $request): array
    {
        return $request->only(['search', 'category', 'color', 'size', 'sort_by', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardSessionFilters(Request $request): array
    {
        $filters = $request->only(['device_type', 'logged_in', 'has_order', 'country', 'visitor_type', 'utm_source', 'utm_medium']);

        $filters['utm_source'] = TrackerUtmFilter::resolveSource($filters['utm_source'] ?? null) ?? '';
        $filters['utm_medium'] = TrackerUtmFilter::resolveMedium($filters['utm_medium'] ?? null) ?? '';

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardDateFilters(Request $request): array
    {
        $filters = $request->only(['period', 'date_from', 'date_to']);
        $filters['period'] = filled($filters['period'] ?? null) ? $filters['period'] : '24h';

        return $filters;
    }

    /**
     * @return array<int, array{label: string, remove_url: string}>
     */
    protected function buildDashboardFilterChips(Request $request, bool $includeProductCatalog = true): array
    {
        $chips = [];
        $service = app(EcomTrackerDashboardService::class);
        $visitorLabels = VisitorClassificationLabels::filterTypeLabels();

        $addChip = function (string $label, string $key) use ($request, &$chips): void {
            $chips[] = [
                'label' => $label,
                'remove_url' => $request->fullUrlWithQuery([$key => null]),
            ];
        };

        if ($request->filled('device_type')) {
            $addChip('Device: '.ucfirst((string) $request->device_type), 'device_type');
        }

        if ($request->filled('logged_in')) {
            $addChip($request->logged_in === '1' ? 'Logged in' : 'Guest', 'logged_in');
        }

        if ($request->filled('has_order')) {
            $addChip($request->has_order === '1' ? 'With order' : 'No order', 'has_order');
        }

        if ($request->filled('visitor_type')) {
            $addChip($visitorLabels[$request->visitor_type] ?? (string) $request->visitor_type, 'visitor_type');
        }

        if ($request->filled('country')) {
            $addChip('Country: '.$request->country, 'country');
        }

        if ($request->filled('utm_source')) {
            $addChip('Source: '.$request->utm_source, 'utm_source');
        }

        if ($request->filled('utm_medium')) {
            $addChip('Medium: '.$request->utm_medium, 'utm_medium');
        }

        if (! $includeProductCatalog) {
            return $chips;
        }

        $scenarioOptions = $service->productCatalogEventScenarioOptions();
        $activityOptions = $service->productCatalogActivityFilterOptions();
        $defaultSort = $service->productCatalogDefaultSort();

        if ($request->filled('search')) {
            $addChip('Product: "'.$request->search.'"', 'search');
        }

        if ($request->filled('category')) {
            $addChip('Category: '.$request->category, 'category');
        }

        if ($request->filled('color')) {
            $addChip('Color: '.$request->color, 'color');
        }

        if ($request->filled('size')) {
            $addChip('Size: '.$request->size, 'size');
        }

        if ($request->filled('sort_by') && $request->sort_by !== $defaultSort) {
            $sortLabel = collect($service->productCatalogSortGroups())
                ->flatMap(fn (array $group) => $group['options'])
                ->get($request->sort_by)['label'] ?? $request->sort_by;
            $addChip('Sort: '.$sortLabel, 'sort_by');
        }

        if ($request->filled('activity')) {
            $addChip('Activity: '.($activityOptions[$request->activity] ?? $request->activity), 'activity');
        }

        if ($request->filled('event_scenario')) {
            $addChip('Funnel: '.($scenarioOptions[$request->event_scenario] ?? $request->event_scenario), 'event_scenario');
        }

        foreach (['has_purchases' => 'Purchases', 'has_views' => 'Views', 'has_adds' => 'Cart adds'] as $key => $label) {
            if ($request->input($key) === '1') {
                $addChip($label, $key);
            }
        }

        return $chips;
    }
}
