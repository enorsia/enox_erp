<?php

namespace App\View\Composers;

use App\Models\TrackerUtmFilter;
use App\Support\EcomActivityFocus;
use App\Support\EcomActivityIndexViewData;
use App\Support\EcomTrackerViewData;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\View\View;

class EcomActivityIndexComposer
{
    public function compose(View $view): void
    {
        $view->with($this->data(request()));
    }

    /**
     * @return array<string, mixed>
     */
    public function data(Request $request): array
    {
        $focus = $request->input('focus');

        $sessions = (new LengthAwarePaginator(
            [],
            0,
            25,
            max(1, (int) $request->input('page', 1)),
            ['path' => $request->url(), 'pageName' => 'page'],
        ))->appends($request->except(['page', 'fragment']));

        $hasFocus = EcomActivityFocus::isValid($focus);
        $filterOptionCounts = [
            'device_type' => [],
            'logged_in' => [],
            'has_order' => [],
            'visitor_type' => [],
            'utm_source' => [],
            'utm_medium' => [],
        ];

        $utmFilterState = TrackerUtmFilter::formState(
            $request->input('utm_source'),
            $request->input('utm_medium'),
            [],
            [],
        );
        $utmFilterState['sources'] = [];
        $utmFilterState['mediums'] = [];

        $range = EcomTrackerViewData::activityIndexRange($request);
        $rangeLabel = $range['label'];
        $period = $range['period'];
        $dateFrom = $request->input('date_from', '');
        $dateTo = $request->input('date_to', '');
        $focusLabel = EcomActivityFocus::label($focus);
        $filterChips = EcomActivityFocus::sidebarFilterChipsFromRequest($request);
        $sidebarFilterCount = EcomActivityFocus::sidebarFilterActiveCount($request);
        $filterResetUrl = EcomActivityFocus::sidebarFilterResetUrl($request);
        $backUrl = EcomTrackerViewData::activityIndexBackUrl($request);

        $summaryFocus = EcomActivityFocus::resolveFilterSummaryFocus($request);
        $activityListContext = null;
        $drillDownContext = null;

        if ($summaryFocus !== null) {
            $criteria = EcomActivityFocus::filterCriteriaFromRequest($request);
            $activityListContext = [
                'section' => EcomActivityFocus::label($summaryFocus),
                'description' => EcomActivityFocus::drillDownDescription($summaryFocus)
                    ?? EcomActivityFocus::filterSummaryDescription($summaryFocus),
                'range_label' => $rangeLabel,
                'criteria' => $criteria,
                'metrics' => [['label' => 'Matching sessions', 'value' => 0]],
                'filter_chips' => EcomActivityFocus::filterChipsFromCriteria($request, $criteria, $hasFocus),
                'clear_focus_url' => $hasFocus
                    ? $request->fullUrlWithQuery(['focus' => null, 'page' => null])
                    : EcomActivityFocus::sidebarFilterResetUrl($request),
                'clear_label' => $hasFocus ? 'Clear section' : 'Clear filters',
            ];
        }

        $crumbLabel = $activityListContext === null ? $focusLabel : null;
        $breadcrumbs = filled($crumbLabel) ? [['label' => $crumbLabel]] : [];

        $summaryCards = $hasFocus ? [['label' => 'Matching sessions', 'value' => 0]] : [];
        $visitorQualitySummary = ['real_shoppers' => 0, 'automated_traffic' => 0, 'not_classified' => 0];

        $showProductCatalogExtras = EcomActivityFocus::showProductCatalogExtrasInDrawer($request);
        $hasActivityContext = $activityListContext !== null || $drillDownContext !== null;
        $hasFilterChips = ! $hasActivityContext && $filterChips !== [];
        $showVisitorQualitySummary = ! $hasActivityContext
            && ! $hasFilterChips
            && $summaryCards === []
            && ! $hasFocus;
        $metaRowHasLeftContent = $hasActivityContext || $hasFilterChips || $showVisitorQualitySummary;
        $showFocusInTitle = filled($focusLabel) && ! $hasActivityContext;

        $activePreset = match ($period) {
            'yesterday', '7d', '30d', 'custom' => $period,
            default => '24h',
        };
        $basePreset = in_array($period, ['24h', 'yesterday', '7d', '30d'], true) ? $period : '24h';

        $includeSessionSearch = EcomActivityFocus::showActivitySearchInDrawer($request);
        $focusColumns = EcomActivityFocus::tableColumns($focus, $request);
        $drillDownContextForUi = $activityListContext ?? $drillDownContext;

        return [
            'sessions' => $sessions,
            'hasFocus' => $hasFocus,
            'rowMetrics' => [],
            'focusColumns' => $focusColumns,
            'emptyMessage' => EcomActivityFocus::emptyMessage($focus),
            'clearFocusUrl' => $request->fullUrlWithQuery(['focus' => null, 'page' => null]),
            'filterOptionCounts' => $filterOptionCounts,
            'utmFilterState' => $utmFilterState,
            'period' => $period,
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'rangeLabel' => $rangeLabel,
            'range' => $range,
            'baseQuery' => $request->except(['date_from', 'date_to', 'period', 'page']),
            'focusLabel' => $focusLabel,
            'filterChips' => $filterChips,
            'sidebarFilterCount' => $sidebarFilterCount,
            'filterResetUrl' => $filterResetUrl,
            'backUrl' => $backUrl,
            'activityListContext' => $activityListContext,
            'drillDownContext' => $drillDownContext,
            'breadcrumbs' => $breadcrumbs,
            'summaryCards' => $summaryCards,
            'visitorQualitySummary' => $visitorQualitySummary,
            'showCatalogFilters' => EcomActivityFocus::showCatalogFiltersInDrawer($request),
            'productFilterOptions' => ['categories' => [], 'colors' => [], 'sizes' => []],
            'categoryFilterOptions' => ['departments' => [], 'categories_by_department' => []],
            'eventScenarioOptions' => [],
            'productSortGroups' => [],
            'productActivityOptions' => [],
            'activityExport' => null,
            'showProductCatalogExtras' => $showProductCatalogExtras,
            'productFiltersHeading' => $showProductCatalogExtras ? 'Additional product filters' : null,
            'includeSessionSearch' => $includeSessionSearch,
            'preserveParams' => EcomActivityIndexViewData::preserveParams($request),
            'filterForm' => EcomActivityIndexViewData::filterForm(
                $request,
                $filterOptionCounts,
                $utmFilterState,
                $includeSessionSearch,
                false,
            ),
            'drillDownUi' => EcomActivityIndexViewData::drillDownUi($drillDownContextForUi, 'activity'),
            'tableShell' => EcomActivityIndexViewData::tableShell($request, $focusColumns),
            'sortSelectOptions' => EcomActivityIndexViewData::sortSelectOptions($request),
            'sortHeaders' => EcomActivityIndexViewData::tableSortHeaders($request),
            'currentProductSort' => $request->input('sort_by', ''),
            'headerResetActive' => count($request->except('page')) > 0,
            'hasActivityContext' => $hasActivityContext,
            'hasFilterChips' => $hasFilterChips,
            'showVisitorQualitySummary' => $showVisitorQualitySummary,
            'metaRowHasLeftContent' => $metaRowHasLeftContent,
            'showFocusInTitle' => $showFocusInTitle,
            'activePreset' => $activePreset,
            'basePreset' => $basePreset,
            'activityIndexUrl' => route('admin.ecom-activity.index'),
            'activityShowBack' => $request->input('back'),
        ];
    }
}
