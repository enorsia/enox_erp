<?php

namespace App\Services;

use App\Support\EcomDailyRollupSchema;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TrackerRollupKeyCompareService
{
    /**
     * @return list<array{
     *     metric_date: string,
     *     scope: string,
     *     string_total: float,
     *     id_total: float,
     *     delta: float,
     *     within_tolerance: bool
     * }>
     */
    public function compareRange(string $fromDate, string $toDate, float $tolerance = 0.01): array
    {
        if (! EcomDailyRollupSchema::hasCatalogIdColumns()) {
            return [];
        }

        $rows = [];
        $cursor = Carbon::parse($fromDate, TrackerTime::timezone())->startOfDay();
        $end = Carbon::parse($toDate, TrackerTime::timezone())->startOfDay();

        while ($cursor->lte($end)) {
            $rows = array_merge($rows, $this->compareDay($cursor->toDateString(), $tolerance));
            $cursor->addDay();
        }

        return $rows;
    }

    /**
     * @return list<array{metric_date: string, scope: string, string_total: float, id_total: float, delta: float, within_tolerance: bool}>
     */
    public function compareDay(string $metricDate, float $tolerance = 0.01): array
    {
        [$fromUtc, $toUtc] = TrackerTime::localCalendarDateStorageRange($metricDate);
        $bounds = [$fromUtc, $toUtc];

        $base = DB::table('activity_ecom_commerce_line_items as li')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'li.session_id')
            ->whereBetween('li.staged_at', $bounds)
            ->whereBetween('s.created_at', $bounds);

        $viewString = (float) (clone $base)
            ->whereIn('li.funnel_stage', ['product_view', 'product_view_popup'])
            ->whereNotNull('li.product_code')
            ->where('li.product_code', '!=', '')
            ->selectRaw('COUNT(*) as c')
            ->value('c');

        $viewId = (float) (clone $base)
            ->whereIn('li.funnel_stage', ['product_view', 'product_view_popup'])
            ->whereNotNull('li.tracker_product_id')
            ->selectRaw('COUNT(*) as c')
            ->value('c');

        $revenueString = (float) (clone $base)
            ->where('li.funnel_stage', 'payment_success')
            ->selectRaw('COALESCE(SUM(li.line_total), 0) as t')
            ->value('t');

        $revenueId = (float) (clone $base)
            ->where('li.funnel_stage', 'payment_success')
            ->whereNotNull('li.tracker_product_id')
            ->selectRaw('COALESCE(SUM(li.line_total), 0) as t')
            ->value('t');

        $categoryString = (float) (clone $base)
            ->where('li.funnel_stage', 'category_view')
            ->whereNotNull('li.category_name')
            ->where('li.category_name', '!=', '')
            ->selectRaw('COUNT(*) as c')
            ->value('c');

        $categoryId = (float) (clone $base)
            ->where('li.funnel_stage', 'category_view')
            ->whereNotNull('li.tracker_category_id')
            ->selectRaw('COUNT(*) as c')
            ->value('c');

        return [
            $this->row($metricDate, 'line_items.product_views', $viewString, $viewId, $tolerance),
            $this->row($metricDate, 'line_items.payment_revenue', $revenueString, $revenueId, $tolerance),
            $this->row($metricDate, 'line_items.category_views', $categoryString, $categoryId, $tolerance),
        ];
    }

    /**
     * @return array{metric_date: string, scope: string, string_total: float, id_total: float, delta: float, within_tolerance: bool}
     */
    private function row(string $metricDate, string $scope, float $stringTotal, float $idTotal, float $tolerance): array
    {
        $delta = round($stringTotal - $idTotal, 2);

        return [
            'metric_date' => $metricDate,
            'scope' => $scope,
            'string_total' => $stringTotal,
            'id_total' => $idTotal,
            'delta' => $delta,
            'within_tolerance' => abs($delta) <= $tolerance,
        ];
    }
}
