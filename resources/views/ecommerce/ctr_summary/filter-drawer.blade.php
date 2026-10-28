@php
    $selectedDepartments = array_values(array_filter(
        array_map('strval', (array) request('department', [])),
        fn (string $value) => $value !== '' && $value !== '__all__',
    ));
    $departmentAllSelected = $selectedDepartments === [];
    $selectedCategories = array_values(array_filter(
        array_map('strval', (array) request('category', [])),
        fn (string $value) => $value !== '' && $value !== '__all__',
    ));
    $categoryAllSelected = $selectedCategories === [];
    $categoryPlaceholder = 'All';
    $filterDrawerHasFilters = trim((string) ($search ?? request('search', ''))) !== ''
        || $selectedDepartments !== []
        || $selectedCategories !== [];
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
                <p class="etd-filter-panel__subtitle">Keyword, department, and category</p>
            </div>
        </div>
        <div class="etd-filter-panel__header-actions">
            <a href="{{ $data['resetUrl'] ?? route('admin.ecommerce.ctr-summary.index') }}"
               class="etd-filter-panel__clear no-underline {{ $filterDrawerHasFilters ? 'is-active' : '' }}"
               aria-label="Clear all filters"
               title="Clear all filters">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </a>
            <button type="button" class="etd-filter-panel__close js-ecom-dashboard-filter-close" aria-label="Close filters">
                <svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>
    <form method="GET" action="{{ $data['filterFormAction'] ?? route('admin.ecommerce.ctr-summary.index') }}" class="etd-filter-panel__form">
        <input type="hidden" name="sort" value="{{ $sortBy }}">
        <input type="hidden" name="dir" value="{{ $sortDir }}">
        <div class="etd-filter-panel__body etd-filter-drawer__body">
            <section class="etd-activity-filter-section etd-activity-filter-section--full">
                <p class="etd-activity-filter-section-title">Search</p>
                <label class="etd-filter-compact-field block" for="ctr-summary-filter-search">
                    <span class="etd-filter-compact-label">Keyword</span>
                    <input type="search"
                           id="ctr-summary-filter-search"
                           name="search"
                           value="{{ $search }}"
                           maxlength="200"
                           placeholder="Product code or text in department / category"
                           autocomplete="off"
                           spellcheck="false"
                           class="etd-filter-input etd-filter-input--sm w-full">
                </label>
            </section>

            <hr class="etd-filter-divider"/>

            <section class="etd-activity-filter-section etd-activity-filter-section--full" data-etd-department-category data-etd-show-all-categories="1">
                <p class="etd-activity-filter-section-title">Catalog</p>
                <div class="etd-activity-filter-grid">
                    <label class="etd-filter-compact-field" for="ctr-summary-filter-department">
                        <span class="etd-filter-compact-label">Department</span>
                        <select id="ctr-summary-filter-department"
                                name="department[]"
                                multiple
                                class="tom-select etd-tom-select w-full"
                                data-placeholder="All"
                                data-dropdown-parent="body"
                                data-clearable="true"
                                data-etd-filter-all-value="__all__"
                                data-etd-department-select>
                            <option value="__all__">All</option>
                            @foreach ($filterCatalog['departments'] as $departmentId => $departmentName)
                                <option value="{{ $departmentId }}" @selected(in_array((string) $departmentId, $selectedDepartments, true))>
                                    {{ $departmentName }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                    <label class="etd-filter-compact-field" for="ctr-summary-filter-category">
                        <span class="etd-filter-compact-label">Category</span>
                        <select id="ctr-summary-filter-category"
                                name="category[]"
                                multiple
                                class="tom-select etd-tom-select w-full"
                                data-placeholder="{{ $categoryPlaceholder }}"
                                data-dropdown-parent="body"
                                data-clearable="true"
                                data-max-options="500"
                                data-etd-filter-all-value="__all__"
                                data-etd-category-select>
                            <option value="__all__">All</option>
                            @foreach ($filterCatalog['categories'] as $category)
                                <option value="{{ $category['value'] }}"
                                        data-department="{{ $category['department'] }}"
                                        @selected(in_array($category['value'], $selectedCategories, true))>
                                    {{ $category['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </section>
        </div>
        <div class="etd-filter-panel__footer">
            <a href="{{ $data['resetUrl'] ?? route('admin.ecommerce.ctr-summary.index') }}" class="etd-filter-panel__reset">Reset</a>
            <button type="submit" class="etd-filter-panel__apply">Apply filters</button>
        </div>
    </form>
</div>
