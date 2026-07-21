<?php

namespace App\Http\Controllers\Concerns;

use App\Models\TrackerUtmFilter;
use Illuminate\Http\Request;

trait CountsTrackerFilters
{
    protected function dashboardActiveFilterCount(Request $request): int
    {
        $count = 0;

        foreach (['device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium', 'search', 'category', 'color', 'size', 'activity', 'has_purchases', 'has_views', 'has_adds', 'event_scenario'] as $key) {
            if (filled($request->input($key))) {
                $count++;
            }
        }

        if (($request->input('period') ?? '24h') !== '24h' || filled($request->input('date_from'))) {
            $count++;
        }

        $defaultSort = app(\App\Services\EcomTrackerDashboardService::class)->productCatalogDefaultSort();
        if (filled($request->input('sort_by')) && $request->input('sort_by') !== $defaultSort) {
            $count++;
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
        $filters = $request->only(['device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium']);

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
        $filters['period'] = $filters['period'] ?? '24h';

        return $filters;
    }
}
