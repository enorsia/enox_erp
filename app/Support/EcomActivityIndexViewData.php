<?php

namespace App\Support;

use Illuminate\Http\Request;

final class EcomActivityIndexViewData
{
    /**
     * @return array<string, string>
     */
    public static function preserveParams(Request $request): array
    {
        return EcomActivityFocus::drawerPreserveQueryParams($request);
    }

    /**
     * @param  array<string, array<string, int>>  $filterOptionCounts
     * @param  array<string, mixed>  $utmFilterState
     * @return array<string, mixed>
     */
    public static function filterForm(
        Request $request,
        array $filterOptionCounts,
        array $utmFilterState,
        bool $includeSessionSearch,
        bool $includeDateRange,
    ): array {
        $selectedFunnels = EcomActivityFocus::drawerFunnelSelectedValues($request);
        $selectedDevices = TrackerMultiSelectFilter::allowedValues(
            $request->input('device_type'),
            ['desktop', 'mobile', 'tablet'],
        );
        $selectedDurations = TrackerMultiSelectFilter::requestValues($request, 'duration_bucket');
        $selectedSources = $utmFilterState['selected_sources'] ?? TrackerMultiSelectFilter::requestValues($request, 'utm_source');
        $selectedMediums = $utmFilterState['selected_mediums'] ?? TrackerMultiSelectFilter::requestValues($request, 'utm_medium');

        $funnelOptions = [];
        foreach (EcomActivityFocus::sidebarFunnelFilterOptions() as $value => $label) {
            if ($value === '') {
                continue;
            }
            $funnelOptions[] = [
                'value' => $value,
                'label' => $label,
                'selected' => in_array($value, $selectedFunnels, true),
            ];
        }

        $durationOptions = [];
        foreach (SessionDurationBuckets::optionLabels() as $value => $label) {
            if ($value === '') {
                continue;
            }
            $durationOptions[] = [
                'value' => (string) $value,
                'label' => $label,
                'selected' => in_array((string) $value, $selectedDurations, true),
            ];
        }

        $deviceOptions = [];
        foreach (array_keys($filterOptionCounts['device_type'] ?? []) as $device) {
            $device = (string) $device;
            $deviceOptions[] = [
                'value' => $device,
                'label' => self::countLabel($device, ucfirst($device), $filterOptionCounts['device_type'] ?? []),
                'selected' => in_array($device, $selectedDevices, true),
            ];
        }

        $utmSources = [];
        foreach ($utmFilterState['sources'] ?? [] as $value => $label) {
            $utmSources[] = [
                'value' => $value,
                'label' => $label,
                'selected' => in_array($value, $selectedSources, true),
            ];
        }

        $utmMediums = [];
        foreach ($utmFilterState['mediums'] ?? [] as $value => $label) {
            $utmMediums[] = [
                'value' => $value,
                'label' => $label,
                'selected' => in_array($value, $selectedMediums, true),
            ];
        }

        return [
            'includeSessionSearch' => $includeSessionSearch,
            'includeDateRange' => $includeDateRange,
            'searchValue' => (string) $request->input('search', ''),
            'dateFromValue' => (string) $request->input('date_from', ''),
            'dateToValue' => (string) $request->input('date_to', ''),
            'tomSelectClass' => 'tom-select etd-tom-select w-full',
            'funnelOptions' => $funnelOptions,
            'hasOrderOptions' => self::singleSelectOptions(
                $request->input('has_order', ''),
                [
                    '' => 'All',
                    '1' => self::countLabel('1', 'With order', $filterOptionCounts['has_order'] ?? []),
                    '0' => self::countLabel('0', 'No order', $filterOptionCounts['has_order'] ?? []),
                ],
            ),
            'loggedInOptions' => self::singleSelectOptions(
                $request->input('logged_in', ''),
                [
                    '' => 'All',
                    '1' => self::countLabel('1', 'Logged in', $filterOptionCounts['logged_in'] ?? []),
                    '0' => self::countLabel('0', 'Guest', $filterOptionCounts['logged_in'] ?? []),
                ],
            ),
            'deviceOptions' => $deviceOptions,
            'durationOptions' => $durationOptions,
            'utmSourceOptions' => $utmSources,
            'utmMediumOptions' => $utmMediums,
        ];
    }

    /**
     * @return list<array{value: string, label: string, selected: bool}>
     */
    private static function singleSelectOptions(mixed $current, array $labels): array
    {
        $current = (string) ($current ?? '');
        $options = [];
        foreach ($labels as $value => $label) {
            $options[] = [
                'value' => (string) $value,
                'label' => $label,
                'selected' => (string) $value === $current,
            ];
        }

        return $options;
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function countLabel(string $value, string $label, array $counts): string
    {
        return isset($counts[$value]) ? "{$label} ({$counts[$value]})" : $label;
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    public static function drillDownUi(?array $context, ?string $exportKey): ?array
    {
        if ($context === null || $context === []) {
            return null;
        }

        $sessionMetric = collect($context['metrics'] ?? [])->firstWhere('label', 'Matching sessions');
        $sessionCount = (int) ($sessionMetric['value'] ?? 0);
        $extraMetrics = collect($context['metrics'] ?? [])
            ->reject(fn (array $metric) => ($metric['label'] ?? '') === 'Matching sessions')
            ->values()
            ->all();
        $filterChips = collect($context['filter_chips'] ?? [])
            ->filter(fn (array $chip) => filled($chip['label'] ?? null))
            ->values()
            ->all();
        $clearLabel = (string) ($context['clear_label'] ?? 'Clear section');

        return [
            'sessionCount' => $sessionCount,
            'sessionCountLabel' => $sessionCount === 1 ? 'session' : 'sessions',
            'extraMetrics' => $extraMetrics,
            'filterChips' => $filterChips,
            'tooltip' => trim((string) ($context['description'] ?? '')),
            'contextAriaLabel' => $clearLabel === 'Clear filters'
                ? 'Filtered activity summary'
                : 'Dashboard drill-down summary',
            'showExport' => filled($exportKey),
            'exportKey' => $exportKey,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $focusColumns
     * @return array<string, mixed>
     */
    public static function tableShell(Request $request, array $focusColumns): array
    {
        $focusColspan = count($focusColumns);
        $showCatalogFilterColumn = $request->filled('department') || $request->filled('category');

        return [
            'focusColspan' => $focusColspan,
            'usePeriodEventTime' => EcomActivityFocus::usesPeriodEventTimestamps($request),
            'showCatalogFilterColumn' => $showCatalogFilterColumn,
            'totalCols' => 8 + $focusColspan + ($showCatalogFilterColumn ? 1 : 0),
            'tableWide' => $focusColspan > 0,
        ];
    }

    /**
     * @return list<array{value: string, label: string, selected: bool}>
     */
    public static function sortSelectOptions(Request $request): array
    {
        $current = EcomActivitySessionSort::effectiveSortBy($request);
        $options = [];
        foreach (EcomActivitySessionSort::sortOptions() as $value => $label) {
            $options[] = [
                'value' => $value,
                'label' => $label,
                'selected' => $current === $value,
            ];
        }

        return $options;
    }

    /**
     * @return array{url: string, linkClass: string, active: bool, dir: string|null}
     */
    public static function sortHeader(Request $request, string $sortKey, ?string $align = null): array
    {
        $active = EcomActivitySessionSort::isActive($request, $sortKey);
        $dir = EcomActivitySessionSort::activeDirection($request, $sortKey);
        $linkClass = 'etd-sort-th-link'.($active ? ' is-active' : '');
        if ($align === 'center') {
            $linkClass .= ' etd-sort-th-link--center';
        }

        return [
            'url' => EcomActivitySessionSort::sortUrl($request, $sortKey),
            'linkClass' => $linkClass,
            'active' => $active,
            'dir' => $dir,
        ];
    }

    /**
     * @return array<string, array{url: string, linkClass: string, active: bool, dir: string|null}>
     */
    public static function tableSortHeaders(Request $request): array
    {
        return [
            'session' => self::sortHeader($request, 'session'),
            'funnel_stage' => self::sortHeader($request, 'funnel_stage'),
            'actions' => self::sortHeader($request, 'actions', 'center'),
            'duration' => self::sortHeader($request, 'duration'),
            'last_active' => self::sortHeader($request, 'last_active'),
        ];
    }
}
