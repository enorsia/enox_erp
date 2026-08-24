@props([
    'filterOptions' => ['departments' => [], 'categories_by_department' => []],
    'sectionHeading' => null,
    'layout' => 'stack',
])

@php
    $departments = $filterOptions['departments'] ?? [];
    $categoriesByDepartment = $filterOptions['categories_by_department'] ?? [];
    $selectedDepartment = request('department', '');
    $selectedCategory = request('category', '');
    $tomSelectClass = 'tom-select etd-tom-select w-full';
@endphp

@if ($sectionHeading)
    <p class="etd-kpi-section-label mb-2">{{ $sectionHeading }}</p>
@endif

<div class="etd-product-filters-compact{{ ($layout ?? 'stack') === 'grid' ? ' etd-activity-filter-grid' : '' }}"
     data-etd-department-category
     data-categories-by-department='@json($categoriesByDepartment)'>
    <label class="etd-filter-compact-field" for="catalog-filter-department">
        <span class="etd-filter-compact-label">Department</span>
        <select id="catalog-filter-department"
                name="department"
                class="{{ $tomSelectClass }}"
                data-etd-department-select
                data-placeholder="All departments">
            <option value="" @selected($selectedDepartment === '')>All departments</option>
            @foreach ($departments as $department)
                <option value="{{ $department }}" @selected($selectedDepartment === $department)>{{ $department }}</option>
            @endforeach
        </select>
    </label>

    <label class="etd-filter-compact-field" for="catalog-filter-category">
        <span class="etd-filter-compact-label">Category</span>
        <select id="catalog-filter-category"
                name="category"
                class="{{ $tomSelectClass }}"
                data-etd-category-select
                data-placeholder="All categories"
                @disabled($selectedDepartment === '' && $selectedCategory === '')>
            <option value="" @selected($selectedCategory === '')>All categories</option>
            @if ($selectedDepartment !== '' && isset($categoriesByDepartment[$selectedDepartment]))
                @foreach ($categoriesByDepartment[$selectedDepartment] as $category)
                    <option value="{{ $category }}" @selected($selectedCategory === $category)>{{ $category }}</option>
                @endforeach
            @endif
        </select>
    </label>
</div>
