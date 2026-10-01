@props([
    'title' => 'Device',
    'rows' => [],
    'emptyMessage' => 'No data in this period.',
    'rowActivityLink' => null,
    'showCompareDelta' => false,
    'compareDeltas' => [],
    'showInlineCompareDelta' => false,
    'compareMetricDeltas' => [],
])

<div @class(['etd-device-browser-panel', 'etd-device-browser-panel--compare-inline' => $showInlineCompareDelta])>
    <h3 class="etd-device-browser-panel__title">{{ $title }}</h3>
    <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--device-browser">
        <table class="etd-table etd-table--device-browser w-full">
            <thead>
                <tr>
                    <th>{{ $title }}</th>
                    <th class="etd-num">Sessions</th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Views',
                            'tip' => 'Product view events (product_view / product_view_popup)',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Cart',
                            'tip' => 'Sessions with add to cart',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Begin',
                            'tip' => 'Sessions that reached begin checkout',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Proceed',
                            'tip' => 'Sessions that reached proceed checkout',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Qty',
                            'tip' => 'Total units sold from payment_success events',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">Conv.</th>
                    @if ($showCompareDelta && ! $showInlineCompareDelta)
                        <th class="etd-num etd-compare-table-delta-head">Δ vs B</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $rowKey = $row['label'] ?? '';
                        $rowLink = is_callable($rowActivityLink) ? $rowActivityLink($rowKey) : null;
                        $rowDelta = $compareDeltas[$rowKey] ?? null;
                        $rowClass = match ($rowDelta['highlight'] ?? null) {
                            'up' => 'etd-compare-row--up',
                            'down' => 'etd-compare-row--down',
                            default => '',
                        };
                    @endphp
                    <tr @class([$rowClass])>
                        <td>
                            @if (filled($rowLink))
                                <a href="{{ $rowLink }}" class="etd-row-drilldown-link no-underline text-inherit hover:text-accent-500">{{ $rowKey }}</a>
                            @else
                                {{ $rowKey }}
                            @endif
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($row['sessions'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['sessions'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($row['views'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['views'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($row['add_to_cart'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['add_to_cart'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($row['begin_checkout'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['begin_checkout'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($row['proceed_checkout'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['proceed_checkout'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($row['sold_qty'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['sold_qty'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => ($row['conversion_rate'] ?? 0).'%',
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$rowKey]['conversion_rate'] ?? null,
                            ])
                        </td>
                        @if ($showCompareDelta && ! $showInlineCompareDelta)
                            @include('ecom_tracker.partials.compare-table-delta-cell', ['delta' => $rowDelta])
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($showCompareDelta && ! $showInlineCompareDelta) ? 9 : 8 }}" class="text-slate-400">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
