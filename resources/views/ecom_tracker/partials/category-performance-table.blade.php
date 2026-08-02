@php
    $departments = $departments ?? [];
    $showCurrency = $showCurrency ?? true;
@endphp

<div x-data="{ expanded: null }" class="etd-category-departments">
    <table class="etd-table etd-table--categories w-full">
        <thead>
            <tr>
                <th class="etd-catalog-expand-col"></th>
                <th class="etd-col-category">Department / Category</th>
                <th class="etd-num">Views</th>
                <th class="etd-num">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Adds',
                        'tip' => 'Add to cart',
                        'align' => 'right',
                    ])
                </th>
                <th class="etd-num">Sale item</th>
                <th class="etd-num">Sale</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($departments as $department)
                @php
                    $departmentKey = $department['key'] ?? strtolower($department['name']);
                    $categoryCount = $department['category_count'] ?? count($department['categories'] ?? []);
                @endphp
                <tr class="etd-category-dept-row" :class="{ 'is-expanded': expanded === @js($departmentKey) }">
                    <td class="etd-catalog-expand-col">
                        @if ($categoryCount > 0)
                            <button type="button"
                                    class="etd-catalog-expand-btn"
                                    @click="expanded = expanded === @js($departmentKey) ? null : @js($departmentKey)"
                                    :aria-expanded="expanded === @js($departmentKey)">
                                <svg class="w-4 h-4 transition-transform" :class="{ 'rotate-90': expanded === @js($departmentKey) }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                            </button>
                        @endif
                    </td>
                    <td class="etd-col-category">
                        @if ($categoryCount > 0)
                            <button type="button"
                                    class="etd-catalog-product-trigger etd-category-dept-trigger"
                                    @click="expanded = expanded === @js($departmentKey) ? null : @js($departmentKey)">
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $department['name'] }}</span>
                                <span class="etd-subtle">{{ number_format($categoryCount) }} {{ Str::plural('category', $categoryCount) }}</span>
                            </button>
                        @else
                            <span class="font-medium text-slate-800 dark:text-slate-100">{{ $department['name'] }}</span>
                        @endif
                    </td>
                    <td class="etd-num etd-category-dept-metric">{{ number_format($department['views']) }}</td>
                    <td class="etd-num etd-category-dept-metric">{{ number_format($department['adds']) }}</td>
                    <td class="etd-num etd-category-dept-metric">{{ number_format($department['sale_items']) }}</td>
                    <td class="etd-num etd-category-dept-metric">
                        @if ($showCurrency)
                            £{{ number_format($department['sale_amount'], 2) }}
                        @else
                            {{ number_format($department['sale_amount'], 2) }}
                        @endif
                    </td>
                </tr>
                @if ($categoryCount > 0)
                    <tr class="etd-category-variant-wrap" x-show="expanded === @js($departmentKey)" x-cloak>
                        <td colspan="6" class="!p-0">
                            <div class="etd-catalog-variant-panel etd-category-variant-panel">
                                <table class="etd-table etd-table--variant-nested etd-table--compact-head w-full">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th class="etd-num">Views</th>
                                            <th class="etd-num">
                                                @include('ecom_tracker.partials.column-header-with-tip', [
                                                    'label' => 'Adds',
                                                    'tip' => 'Add to cart',
                                                    'align' => 'right',
                                                ])
                                            </th>
                                            <th class="etd-num">Sale item</th>
                                            <th class="etd-num">Sale</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($department['categories'] as $category)
                                            <tr>
                                                <td>{{ $category['category_name'] }}</td>
                                                <td class="etd-num">{{ number_format($category['views']) }}</td>
                                                <td class="etd-num">{{ number_format($category['adds']) }}</td>
                                                <td class="etd-num">{{ number_format($category['sale_items']) }}</td>
                                                <td class="etd-num">
                                                    @if ($showCurrency)
                                                        £{{ number_format($category['sale_amount'], 2) }}
                                                    @else
                                                        {{ number_format($category['sale_amount'], 2) }}
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </td>
                    </tr>
                @endif
            @endforeach
        </tbody>
    </table>
</div>
