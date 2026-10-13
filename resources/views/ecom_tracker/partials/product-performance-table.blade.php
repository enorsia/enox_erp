@php
    $products = $products ?? [];
    $productActivityLink = $productActivityLink ?? null;
    $showInlineCompareDelta = $showInlineCompareDelta ?? false;
    $compareMetricDeltas = $compareMetricDeltas ?? [];
@endphp

<div @class(['etd-product-catalog', 'etd-product-catalog--compare-inline' => $showInlineCompareDelta])>
    <table class="etd-table etd-table--product-catalog etd-table--performance-metrics w-full">
        <thead>
            <tr>
                <th class="etd-col-product">Product</th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Views',
                        'tip' => 'Sessions that viewed this product (once per session, even if viewed many times)',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Adds',
                        'tip' => 'Total units added to cart for this product',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Proceed',
                        'tip' => 'Sessions that proceeded to checkout with this product in the cart',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Sold',
                        'tip' => 'Units sold (order line quantity)',
                        'align' => 'center',
                    ])
                </th>
                <th class="etd-num etd-col-metric">Sale</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $product)
                @php
                    $productKey = 'product:'.trim((string) (($product['code'] ?? $product['product_code'] ?? '') !== '' ? ($product['code'] ?? $product['product_code']) : ($product['name'] ?? '')));
                    $productDrillQuery = array_filter([
                        'product_code' => $product['code'] ?? ($product['product_code'] ?? null),
                    ]);
                    $productLink = filled($product['activity_url'] ?? null)
                        ? (string) $product['activity_url']
                        : (is_callable($productActivityLink)
                            ? $productActivityLink($productDrillQuery)
                            : null);
                @endphp
                <tr>
                    <td class="etd-col-product">
                        @if ($productLink)
                            <a href="{{ $productLink }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                                {{ $product['name'] }}
                            </a>
                        @else
                            {{ $product['name'] }}
                        @endif
                    </td>
                    <td class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => number_format($product['views'] ?? 0),
                            'showInlineCompareDelta' => $showInlineCompareDelta,
                            'delta' => $compareMetricDeltas[$productKey]['views'] ?? null,
                        ])
                    </td>
                    <td class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => number_format($product['adds'] ?? 0),
                            'showInlineCompareDelta' => $showInlineCompareDelta,
                            'delta' => $compareMetricDeltas[$productKey]['adds'] ?? null,
                        ])
                    </td>
                    <td class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => number_format($product['proceed_checkouts'] ?? 0),
                            'showInlineCompareDelta' => $showInlineCompareDelta,
                            'delta' => $compareMetricDeltas[$productKey]['proceed_checkouts'] ?? null,
                        ])
                    </td>
                    <td class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => number_format($product['qty'] ?? 0),
                            'showInlineCompareDelta' => $showInlineCompareDelta,
                            'delta' => $compareMetricDeltas[$productKey]['qty'] ?? null,
                        ])
                    </td>
                    <td class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.category-performance-metric-cell', [
                            'formatted' => '£'.number_format($product['revenue'] ?? 0, 2),
                            'showInlineCompareDelta' => $showInlineCompareDelta,
                            'delta' => $compareMetricDeltas[$productKey]['revenue'] ?? null,
                        ])
                        <div class="etd-mini-bar"><div style="width: {{ $product['revenue_bar_percent'] ?? 0 }}%"></div></div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-slate-400">No product activity in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
