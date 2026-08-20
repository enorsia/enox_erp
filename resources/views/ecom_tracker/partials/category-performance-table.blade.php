@php
    $departments = $departments ?? [];
    $showCurrency = $showCurrency ?? true;
    $categoryActivityLink = $categoryActivityLink ?? null;
    $maxSaleAmount = max(1, (float) collect($departments)->max('sale_amount'));
@endphp

@if ($departments === [])
    <p class="etd-empty-note">No category activity in this period.</p>
@else
    <div x-data="{ expanded: null }" class="etd-category-departments">
        <table class="etd-table etd-table--categories etd-table--catalog etd-table--performance-metrics w-full">
            <thead>
                <tr>
                    <th class="etd-catalog-expand-col"></th>
                    <th class="etd-col-category">Department / Category</th>
                    <th class="etd-num etd-col-metric">Views</th>
                    <th class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Adds',
                            'tip' => 'Add to cart',
                            'align' => 'center',
                        ])
                    </th>
                    <th class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Proceed',
                            'tip' => 'Proceed to checkout',
                            'align' => 'center',
                        ])
                    </th>
                    <th class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Sold',
                            'tip' => 'Sale item',
                            'align' => 'center',
                        ])
                    </th>
                    <th class="etd-num etd-col-metric">Sale</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($departments as $department)
                    @php
                        $departmentKey = $department['key'] ?? strtolower($department['name']);
                        $categoryCount = $department['category_count'] ?? count($department['categories'] ?? []);
                        $saleBarPercent = (int) round(((float) ($department['sale_amount'] ?? 0) / $maxSaleAmount) * 100);
                    @endphp
                    <tr class="etd-catalog-product-row etd-category-dept-row" :class="{ 'is-expanded': expanded === @js($departmentKey) }">
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
                            <button type="button"
                                    class="etd-catalog-product-trigger"
                                    @if ($categoryCount > 0) @click="expanded = expanded === @js($departmentKey) ? null : @js($departmentKey)" @endif>
                                <span class="font-medium text-slate-800 dark:text-slate-100">{{ $department['name'] }}</span>
                                @if ($categoryCount > 0)
                                    <span class="etd-category-count-badge">{{ $categoryCount }}</span>
                                @endif
                            </button>
                        </td>
                        <td class="etd-num etd-col-metric">{{ number_format($department['views']) }}</td>
                        <td class="etd-num etd-col-metric">{{ number_format($department['adds']) }}</td>
                        <td class="etd-num etd-col-metric">{{ number_format($department['proceed_checkouts'] ?? 0) }}</td>
                        <td class="etd-num etd-col-metric">{{ number_format($department['sale_items']) }}</td>
                        <td class="etd-num etd-col-metric">
                            @if ($showCurrency)
                                £{{ number_format($department['sale_amount'], 2) }}
                            @else
                                {{ number_format($department['sale_amount'], 2) }}
                            @endif
                            <div class="etd-mini-bar"><div style="width: {{ $saleBarPercent }}%"></div></div>
                        </td>
                    </tr>
                    @foreach ($department['categories'] as $category)
                        @php
                            $categorySaleBar = (int) round(((float) ($category['sale_amount'] ?? 0) / $maxSaleAmount) * 100);
                        @endphp
                        <tr class="etd-category-child-row" x-show="expanded === @js($departmentKey)" x-cloak>
                            <td class="etd-catalog-expand-col"></td>
                            <td class="etd-col-category etd-category-child-name">
                                @if (is_callable($categoryActivityLink))
                                    <a href="{{ $categoryActivityLink(array_merge($category, ['department_name' => $category['department_name'] ?? $department['name'] ?? ''])) }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                                        {{ $category['category_name'] }}
                                    </a>
                                @else
                                    {{ $category['category_name'] }}
                                @endif
                            </td>
                            <td class="etd-num etd-col-metric">{{ number_format($category['views']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($category['adds']) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($category['proceed_checkouts'] ?? 0) }}</td>
                            <td class="etd-num etd-col-metric">{{ number_format($category['sale_items']) }}</td>
                            <td class="etd-num etd-col-metric">
                                @if ($showCurrency)
                                    £{{ number_format($category['sale_amount'], 2) }}
                                @else
                                    {{ number_format($category['sale_amount'], 2) }}
                                @endif
                                <div class="etd-mini-bar"><div style="width: {{ $categorySaleBar }}%"></div></div>
                            </td>
                        </tr>
                    @endforeach
                @endforeach
            </tbody>
        </table>
    </div>
@endif
