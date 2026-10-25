@php
    $products = $products ?? [];
    $productActivityLink = $productActivityLink ?? null;
    $showInlineCompareDelta = $showInlineCompareDelta ?? false;
    $compareMetricDeltas = $compareMetricDeltas ?? [];
    $funnelColumns = [
        [
            'key' => 'views',
            'label' => 'View',
            'tip' => 'Sessions in this period with a catalog click on this product (product_click). Once per session per product.',
        ],
        [
            'key' => 'add_to_cart',
            'label' => 'Add',
            'tip' => 'Total units of this style added to cart: for each session, sum of quantities on every add click for this style in the period, then summed across sessions.',
        ],
        [
            'key' => 'begin_checkout',
            'label' => 'Begin',
            'tip' => 'Total units of this style that moved into checkout: for each session, quantity in the cart when checkout began (once per session; last begin in the period if repeated), summed across sessions.',
        ],
        [
            'key' => 'proceed_checkout',
            'label' => 'Proceed',
            'tip' => 'Total units at the proceed-to-payment step — same per-session quantity rules as Begin, summed across sessions.',
        ],
        [
            'key' => 'paid',
            'label' => 'Paid',
            'tip' => 'Total units sold in the period (sold_qty on tracking_daily_products, summed across days after dashboard sync).',
        ],
    ];
    $serialOffset = isset($serialOffset) ? max(0, (int) $serialOffset) : null;
    $showSerial = $serialOffset !== null;
    $metricColspan = count($funnelColumns) + 2 + ($showSerial ? 1 : 0);
@endphp

<div @class(['etd-product-catalog', 'etd-product-catalog--compare-inline' => $showInlineCompareDelta])>
    <table class="etd-table etd-table--product-catalog etd-table--performance-metrics etd-table--product-funnel w-full">
        <thead>
            <tr>
                @if ($showSerial)
                    <th class="etd-num etd-col-sl">SL</th>
                @endif
                <th class="etd-col-product">Product</th>
                @foreach ($funnelColumns as $column)
                    <th class="etd-num etd-col-metric">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => $column['label'],
                            'tip' => $column['tip'],
                            'align' => 'center',
                        ])
                    </th>
                @endforeach
                <th class="etd-num etd-col-metric">Sale</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($products as $rowIndex => $product)
                @php
                    $productCode = trim((string) ($product['code'] ?? $product['product_code'] ?? ''));
                    $productTitle = trim((string) ($product['name'] ?? ''));
                    $productDisplay = $productTitle !== '' && $productCode !== ''
                        ? "{$productTitle} ({$productCode})"
                        : ($productTitle !== '' ? $productTitle : $productCode);
                    $productKey = 'product:'.trim((string) ($productCode !== '' ? $productCode : $productTitle));
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
                    @if ($showSerial)
                        <td class="etd-num etd-col-sl">{{ $serialOffset + $rowIndex + 1 }}</td>
                    @endif
                    <td class="etd-col-product">
                        @if ($productLink)
                            <a href="{{ $productLink }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">
                                {{ $productDisplay }}
                            </a>
                        @else
                            {{ $productDisplay }}
                        @endif
                    </td>
                    @foreach ($funnelColumns as $column)
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($product[$column['key']] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$productKey][$column['key']] ?? null,
                            ])
                        </td>
                    @endforeach
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
                <tr><td colspan="{{ $metricColspan }}" class="text-slate-400">No product activity in this period.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
