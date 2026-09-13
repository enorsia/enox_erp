<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CountsTrackerFilters;
use App\Services\EcomTrackerDashboardService;
use App\Services\EcomTrackerFeatureGate;
use App\Support\EcomTrackerCompareSupport;
use App\Support\EcomTrackerViewData;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EcomTrackerDashboardCompareController extends EcomTrackerAdminController
{
    use CountsTrackerFilters;

    public function __construct(
        EcomTrackerFeatureGate $featureGate,
        private EcomTrackerDashboardService $service,
    ) {
        parent::__construct($featureGate);
    }

    public function index(Request $request): View|RedirectResponse
    {
        Gate::authorize('ecom_tracker.dashboard.index');

        $leftFilters = EcomTrackerViewData::compareSideFiltersFromRequest($request, 'left');

        if (! EcomTrackerViewData::hasCompareSideParams($request, 'right')) {
            $leftRange = $this->service->resolveDateRange($leftFilters);
            $rightFilters = $this->defaultRightFilters($leftFilters, $leftRange);

            return redirect()->route(
                'admin.ecom-tracker.dashboard.compare',
                EcomTrackerViewData::comparePageQuery($request, $leftFilters, $rightFilters),
            );
        }

        $left = $this->service->getDashboardData($leftFilters);
        $left['chart_payload'] = $this->service->chartPayload($left);

        $rightFilters = EcomTrackerViewData::compareSideFiltersFromRequest($request, 'right');
        $right = $this->service->getDashboardData($rightFilters);
        $right['chart_payload'] = $this->service->chartPayload($right);

        $executiveRows = EcomTrackerCompareSupport::buildExecutiveSummary($left, $right);
        $tableCompareDeltas = EcomTrackerCompareSupport::buildTableCompareDeltas($left, $right);

        $leftRaw = $left;
        $rightRaw = $right;

        $left = EcomTrackerCompareSupport::enrichDashboardWithCrossComparison(
            $leftRaw,
            $rightRaw,
            TrackerTime::rangeDisplayLabel($rightRaw['range'] ?? []),
        );
        $right = EcomTrackerCompareSupport::enrichDashboardWithCrossComparison(
            $rightRaw,
            $leftRaw,
            TrackerTime::rangeDisplayLabel($leftRaw['range'] ?? []),
        );

        return view('ecom_tracker.compare', [
            'left' => $left,
            'right' => $right,
            'leftFilters' => $leftFilters,
            'rightFilters' => $rightFilters,
            'backUrl' => EcomTrackerViewData::compareBackUrl($request),
            'compareBaseQuery' => $this->compareBaseQuery($request),
            'pageQuery' => EcomTrackerViewData::comparePageQuery($request, $leftFilters, $rightFilters),
            'executiveRows' => $executiveRows,
            'tableCompareDeltas' => $tableCompareDeltas,
        ]);
    }

    /**
     * @param  array<string, mixed>  $leftFilters
     * @param  array{from: Carbon, to: Carbon, label: string, days: int, period?: string}  $leftRange
     * @return array<string, mixed>
     */
    private function defaultRightFilters(array $leftFilters, array $leftRange): array
    {
        $prevRange = $this->service->resolvePreviousPeriodRange($leftRange);
        $fromLocal = TrackerTime::toLocal($prevRange['from']);
        $toLocal = TrackerTime::toLocal($prevRange['to']);

        $sessionFilters = collect($leftFilters)
            ->except(['period', 'date_from', 'date_to'])
            ->filter(fn ($value) => filled($value))
            ->all();

        return array_merge($sessionFilters, [
            'period' => 'custom',
            'date_from' => $fromLocal?->toDateString(),
            'date_to' => $toLocal?->toDateString(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function compareBaseQuery(Request $request): array
    {
        return $request->only(array_merge(
            ['back'],
            collect(EcomTrackerViewData::compareSideFilterKeys())
                ->flatMap(fn (string $key) => ["left_{$key}", "right_{$key}"])
                ->all(),
        ));
    }
}
