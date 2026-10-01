{{-- Reusable floating filter panel. Requires parent x-data with drawerOpen. --}}
@props([
    'action',
    'resetUrl' => null,
    'showDashboardFilters' => false,
    'showPeriodFilters' => false,
    'periodAllowsAll' => false,
    'showDayNavigation' => true,
    'baseQuery' => [],
    'range' => null,
    'routeName' => 'admin.ecom-tracker.dashboard',
    'showSessionFilters' => false,
    'showActivityFilters' => false,
    'activityFiltersIncludeDateRange' => true,
    'includeSessionSearch' => true,
    'includeVisitorTrust' => false,
    'includeCountry' => true,
    'categoryFilterOptions' => ['departments' => [], 'categories_by_department' => []],
    'showProductFilters' => false,
    'productFilterOptions' => ['categories' => [], 'colors' => [], 'sizes' => []],
    'eventScenarioOptions' => [],
    'productSortGroups' => [],
    'productActivityOptions' => [],
    'currentProductSort' => 'top_revenue',
    'period' => '24h',
    'dateFrom' => '',
    'dateTo' => '',
    'preservePeriodParams' => false,
    'sessionFiltersHeading' => null,
    'productFiltersHeading' => null,
    'drawerWide' => false,
    'periodFiltersMobileOnly' => false,
])

<div x-show="drawerOpen"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     @click="drawerOpen = false"
     class="etd-filter-panel__backdrop"
     style="display:none;"
     aria-hidden="true"></div>

<div x-show="drawerOpen"
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-start="translate-y-full md:translate-y-0 md:translate-x-full"
     x-transition:enter-end="translate-y-0 translate-x-0"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="translate-y-0 translate-x-0"
     x-transition:leave-end="translate-y-full md:translate-y-0 md:translate-x-full"
     x-effect="document.body.classList.toggle('etd-filter-panel-open', drawerOpen)"
     x-effect="if (drawerOpen) { $nextTick(() => window.refreshEtdFilterControls && window.refreshEtdFilterControls($el)) }"
     @keydown.escape.window="drawerOpen = false"
     role="dialog"
     aria-modal="true"
     aria-label="Filters"
     class="etd-filter-panel etd-filter-drawer {{ ($drawerWide ?? false) ? 'etd-filter-panel--wide etd-filter-drawer--wide' : '' }}"
     style="display:none;">
    <div class="etd-filter-panel__handle" aria-hidden="true"></div>

    <div class="etd-filter-panel__header">
        <div class="etd-filter-panel__title-wrap">
            <span class="etd-filter-panel__icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/>
                </svg>
            </span>
            <div>
                <p class="etd-filter-panel__title">Filters</p>
                <p class="etd-filter-panel__subtitle">Refine what you see on this page</p>
            </div>
        </div>
        <button type="button"
                @click="drawerOpen = false"
                class="etd-filter-panel__close"
                aria-label="Close filters">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>

    <form method="GET" action="{{ $action }}" class="etd-filter-panel__form">
        @if ($showActivityFilters)
            @include('ecom_activity.partials.preserve-filter-params')
        @elseif (request('back'))
            <input type="hidden" name="back" value="{{ request('back') }}">
        @endif
        @if ($preservePeriodParams)
            <input type="hidden" name="period" value="{{ $period }}">
            @if ($period === 'custom')
                <input type="hidden" name="date_from" value="{{ $dateFrom }}">
                <input type="hidden" name="date_to" value="{{ $dateTo }}">
            @endif
        @endif

        <div class="etd-filter-panel__body etd-filter-drawer__body">
            @unless ($showActivityFilters)
                @include('ecom_tracker.partials.timezone-notice')
            @endunless
            @if ($showPeriodFilters || $showDashboardFilters)
                @include('ecom_tracker.partials.filter-drawer-period', [
                    'period' => $period,
                    'dateFrom' => $dateFrom,
                    'dateTo' => $dateTo,
                    'periodAllowsAll' => $periodAllowsAll || $showDashboardFilters,
                    'showDayNavigation' => $showDayNavigation,
                    'baseQuery' => $baseQuery,
                    'range' => $range,
                    'routeName' => $routeName,
                    'sectionStyle' => ($showActivityFilters && ($drawerWide ?? false)) ? 'activity' : 'default',
                    'mobileOnly' => $periodFiltersMobileOnly ?? false,
                ])
                @if ($showActivityFilters || $showSessionFilters || $showProductFilters)
                    <hr class="etd-filter-divider"/>
                @endif
            @elseif ($preservePeriodParams)
                {{-- Period preserved via hidden fields below --}}
            @endif

            @if ($showActivityFilters)
                @include('ecom_activity.partials.activity-filters', [
                    'includeDateRange' => $activityFiltersIncludeDateRange,
                    'includeSessionSearch' => $includeSessionSearch ?? true,
                    'sessionFiltersHeading' => $sessionFiltersHeading ?? null,
                    'filterOptionCounts' => $filterOptionCounts ?? [],
                    'utmFilterState' => $utmFilterState ?? null,
                    'includeVisitorTrust' => $includeVisitorTrust ?? false,
                    'includeCountry' => $includeCountry ?? true,
                    'categoryFilterOptions' => $categoryFilterOptions ?? ['departments' => [], 'categories_by_department' => []],
                ])
                @if ($showProductFilters)
                    <hr class="etd-filter-divider"/>
                    @if ($productFiltersHeading)
                        <p class="etd-kpi-section-label mb-2">{{ $productFiltersHeading }}</p>
                    @endif
                    <div class="etd-filter-product-wrap etd-activity-filter-product-extras">
                        @include('ecom_tracker.partials.product-catalog-filters', [
                            'filterOptions' => $productFilterOptions,
                            'eventScenarioOptions' => $eventScenarioOptions,
                            'sortGroups' => $productSortGroups,
                            'activityOptions' => $productActivityOptions,
                            'currentSort' => $currentProductSort,
                            'showSort' => $productCatalogShowSort ?? true,
                        ])
                    </div>
                @endif
                @if ($includeVisitorTrust ?? false)
                    @include('ecom_activity.partials.activity-visitor-filter', [
                        'filterOptionCounts' => $filterOptionCounts ?? [],
                    ])
                @endif
            @endif

            @if ($showSessionFilters || ($showProductFilters && ! $showActivityFilters))
                @if ($showSessionFilters)
                    @if ($sessionFiltersHeading)
                        <p class="etd-kpi-section-label mb-2">{{ $sessionFiltersHeading }}</p>
                    @endif
                    @include('ecom_tracker.partials.session-filters', [
                        'filterOptionCounts' => $filterOptionCounts ?? [],
                        'utmFilterState' => $utmFilterState ?? null,
                        'includeVisitorTrust' => $includeVisitorTrust ?? false,
                        'includeCountry' => $includeCountry ?? true,
                    ])
                    @if ($showProductFilters)
                        <hr class="etd-filter-divider"/>
                    @endif
                @endif
                @if ($showProductFilters)
                    @if ($productFiltersHeading)
                        <p class="etd-kpi-section-label mb-2">{{ $productFiltersHeading }}</p>
                    @endif
                    <div class="etd-filter-product-wrap etd-activity-filter-product-extras">
                    @include('ecom_tracker.partials.product-catalog-filters', [
                        'filterOptions' => $productFilterOptions,
                        'eventScenarioOptions' => $eventScenarioOptions,
                        'sortGroups' => $productSortGroups,
                        'activityOptions' => $productActivityOptions,
                        'currentSort' => $currentProductSort,
                        'showSort' => $productCatalogShowSort ?? true,
                    ])
                    </div>
                @endif
            @endif
        </div>

        <div class="etd-filter-panel__footer">
            <a href="{{ $resetUrl ?? $action }}"
               class="etd-filter-panel__reset">
                Reset
            </a>
            <button type="submit" class="etd-filter-panel__apply">
                Apply filters
            </button>
        </div>
    </form>
</div>
