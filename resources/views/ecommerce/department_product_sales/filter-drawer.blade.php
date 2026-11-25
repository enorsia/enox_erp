@php
    $selectedDepartments = array_values(array_filter(
        array_map('strval', $departmentIds ?? []),
        fn (string $value) => $value !== '' && $value !== '__all__',
    ));
    $departmentAllSelected = $selectedDepartments === [];
    $activePeriod = $data['period'] ?? \App\Http\Controllers\EcommerceDepartmentProductSalesController::DEFAULT_PERIOD;
    $defaultPeriod = \App\Http\Controllers\EcommerceDepartmentProductSalesController::DEFAULT_PERIOD;
    $drawerResetUrl = $data['drawerResetUrl'] ?? ($data['resetUrl'] ?? route('admin.ecommerce.department-product-sales.index'));
    $periodPresetUrl = $data['periodPresetUrl'] ?? null;
    $periodLabels = \App\Http\Controllers\EcommerceDepartmentProductSalesController::PERIOD_PRESETS;
@endphp

<div id="ecom-dashboard-filter-backdrop" class="etd-filter-panel__backdrop etd-filter-panel--closed js-ecom-dashboard-filter-close" aria-hidden="true"></div>

<div id="ecom-dashboard-filter-drawer"
     role="dialog" aria-modal="true" aria-label="Filters" aria-hidden="true"
     class="etd-filter-panel etd-filter-drawer etd-filter-panel--wide etd-filter-drawer--wide etd-filter-panel--closed">
    <div class="etd-filter-panel__handle" aria-hidden="true"></div>
    <div class="etd-filter-panel__header">
        <div class="etd-filter-panel__title-wrap">
            <span class="etd-filter-panel__icon" aria-hidden="true">
                <svg fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M7 12h10M10 18h4"/></svg>
            </span>
            <div>
                <p class="etd-filter-panel__title">Filters</p>
                <p class="etd-filter-panel__subtitle">Date range, product code, and department</p>
            </div>
        </div>
        <button type="button" class="etd-filter-panel__close js-ecom-dashboard-filter-close" aria-label="Close filters">
            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <form method="GET" action="{{ $data['filterFormAction'] ?? route('admin.ecommerce.department-product-sales.index') }}" class="etd-filter-panel__form">
        <input type="hidden" name="sort" value="{{ $sortBy ?? 'total_sale' }}">
        <input type="hidden" name="dir" value="{{ $sortDir ?? 'desc' }}">
        <input type="hidden" name="period" id="ecom-dashboard-filter-period" value="{{ $activePeriod }}">
        <div class="etd-filter-panel__body etd-filter-drawer__body">
            <section class="etd-filter-period etd-filter-period--drawer-mobile etd-activity-filter-section etd-activity-filter-section--full">
                <p class="etd-activity-filter-section-title">Date range</p>
                <div class="etd-filter-period__nav">
                    @if (filled($data['prevUrl'] ?? null))
                        <a href="{{ $data['prevUrl'] }}" class="etd-filter-period__nav-btn no-underline" aria-label="Previous period">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
                        </a>
                    @else
                        <span class="etd-filter-period__nav-btn is-disabled" aria-disabled="true">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
                        </span>
                    @endif
                    <span class="etd-filter-period__nav-label">{{ $data['periodLabel'] ?? 'Date range' }} · {{ $data['rangeLabel'] ?? '' }}</span>
                    @if (filled($data['nextUrl'] ?? null))
                        <a href="{{ $data['nextUrl'] }}" class="etd-filter-period__nav-btn no-underline" aria-label="Next period">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                        </a>
                    @else
                        <span class="etd-filter-period__nav-btn is-disabled" aria-disabled="true">
                            <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                        </span>
                    @endif
                </div>
                <div class="etd-filter-period__presets" role="group" aria-label="Date presets">
                    @foreach ($periodLabels as $periodKey => $periodLabel)
                        @if ($periodPresetUrl)
                            <a href="{{ $periodPresetUrl($periodKey) }}"
                               class="etd-filter-period__preset no-underline {{ $activePeriod === $periodKey ? 'is-active' : '' }}">{{ $periodLabel }}</a>
                        @endif
                    @endforeach
                    <button type="button"
                            class="etd-filter-period__preset js-ecom-dashboard-drawer-custom-preset {{ $activePeriod === 'custom' ? 'is-active' : '' }}"
                            data-period="custom">Custom range</button>
                </div>
                <div id="ecom-dashboard-drawer-custom-dates"
                     class="etd-date-range etd-filter-period__custom etd-activity-filter-grid @if($activePeriod !== 'custom') etd-filter-period__custom--closed @endif"
                     data-etd-date-range>
                    <label class="etd-filter-compact-field" for="style-sold-filter-date-from">
                        <span class="etd-filter-compact-label">From</span>
                        <input type="text"
                               id="style-sold-filter-date-from"
                               name="date_from"
                               value="{{ $data['dateFrom'] ?? '' }}"
                               data-range="from"
                               data-default="{{ $data['dateFrom'] ?? '' }}"
                               placeholder="Select date"
                               readonly
                               @disabled($activePeriod !== 'custom')
                               class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                    </label>
                    <label class="etd-filter-compact-field" for="style-sold-filter-date-to">
                        <span class="etd-filter-compact-label">To</span>
                        <input type="text"
                               id="style-sold-filter-date-to"
                               name="date_to"
                               value="{{ $data['dateTo'] ?? '' }}"
                               data-range="to"
                               data-default="{{ $data['dateTo'] ?? '' }}"
                               placeholder="Select date"
                               readonly
                               @disabled($activePeriod !== 'custom')
                               class="etd-flatpickr-date etd-filter-input etd-filter-input--sm w-full">
                    </label>
                </div>
            </section>

            <hr class="etd-filter-divider"/>

            <section class="etd-activity-filter-section etd-activity-filter-section--full">
                <p class="etd-activity-filter-section-title">Search</p>
                <label class="etd-filter-compact-field block" for="dept-product-sales-filter-search">
                    <span class="etd-filter-compact-label">Product code</span>
                    <input type="search"
                           id="dept-product-sales-filter-search"
                           name="search"
                           value="{{ $search ?? '' }}"
                           maxlength="200"
                           placeholder="Search SKU / product code"
                           autocomplete="off"
                           spellcheck="false"
                           class="etd-filter-input etd-filter-input--sm w-full">
                </label>
            </section>

            <hr class="etd-filter-divider"/>

            <section class="etd-activity-filter-section etd-activity-filter-section--full">
                <label class="etd-filter-compact-field block" for="dept-product-sales-filter-department">
                    <span class="etd-filter-compact-label">Department name</span>
                    <select id="dept-product-sales-filter-department"
                            name="department[]"
                            multiple
                            class="tom-select etd-tom-select w-full"
                            data-placeholder="All departments"
                            data-dropdown-parent="body"
                            data-clearable="true"
                            data-etd-filter-all-value="__all__">
                        <option value="__all__" @selected($departmentAllSelected)>All</option>
                        @foreach ($departmentOptions ?? [] as $option)
                            <option value="{{ $option['id'] }}" @selected(in_array((string) $option['id'], $selectedDepartments, true))>
                                {{ $option['name'] }}
                            </option>
                        @endforeach
                    </select>
                    @if (($departmentOptions ?? []) === [])
                        <span class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 block">No departments with sales in the selected date range.</span>
                    @endif
                </label>
            </section>
        </div>
        <div class="etd-filter-panel__footer">
            <a href="{{ $drawerResetUrl }}" class="etd-filter-panel__reset">Reset</a>
            <button type="submit" class="etd-filter-panel__apply">Apply filters</button>
        </div>
    </form>
</div>