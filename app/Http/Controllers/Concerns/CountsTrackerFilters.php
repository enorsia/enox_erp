<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Http\Request;

trait CountsTrackerFilters
{
    protected function dashboardActiveFilterCount(Request $request): int
    {
        $count = 0;

        foreach (['device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium'] as $key) {
            if (filled($request->input($key))) {
                $count++;
            }
        }

        if (($request->input('period') ?? '24h') !== '24h' || filled($request->input('date_from'))) {
            $count++;
        }

        return $count;
    }

    /**
     * @return array<string, mixed>
     */
    protected function dashboardSessionFilters(Request $request): array
    {
        return $request->only(['device_type', 'logged_in', 'has_order', 'country', 'utm_source', 'utm_medium']);
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
