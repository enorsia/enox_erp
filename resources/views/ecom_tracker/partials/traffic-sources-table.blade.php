@props([
    'rows' => [],
    'emptyColspan' => 11,
    'emptyMessage' => 'No traffic source data in this period.',
    'activitySourceLink' => null,
    'readOnly' => false,
    'showCompareDelta' => false,
    'compareDeltas' => [],
    'showInlineCompareDelta' => false,
    'compareMetricDeltas' => [],
])

@php
    use App\Support\SessionTrafficAttribution;

    $resolveSourceLink = function (array $row) use ($activitySourceLink, $readOnly) {
        if (($readOnly ?? false) || ! is_callable($activitySourceLink) || ($row['source'] ?? '') === 'Other') {
            return null;
        }

        $url = $activitySourceLink($row['source']);

        return filled($url) ? $url : null;
    };
@endphp

<div @class(['etd-traffic-sources', 'etd-traffic-sources--compare-inline' => $showInlineCompareDelta])>
    <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--traffic">
        <table class="etd-table etd-table--traffic w-full">
            <thead>
                <tr>
                    <th>
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Source',
                            'tip' => 'UTM source from the session (utm_source). Direct when missing.',
                        ])
                    </th>
                    <th>
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Medium',
                            'tip' => 'UTM medium from the session (utm_medium).',
                        ])
                    </th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Sessions',
                            'tip' => 'Sessions grouped by source and medium',
                            'align' => 'right',
                        ])
                    </th>
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
                            'label' => 'Paid',
                            'tip' => 'Sessions with payment_success',
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
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Conv.',
                            'tip' => 'Paid sessions as a share of sessions',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">
                        @include('ecom_tracker.partials.column-header-with-tip', [
                            'label' => 'Sale',
                            'tip' => 'Revenue from payment_success in this period',
                            'align' => 'right',
                        ])
                    </th>
                    @if ($showCompareDelta && ! $showInlineCompareDelta)
                        <th class="etd-num etd-compare-table-delta-head">Δ vs B</th>
                    @endif
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $source)
                    @php
                        $sourceUrl = $resolveSourceLink($source);
                        $trafficKey = ($source['source'] ?? '').'|'.($source['medium'] ?? '');
                        $trafficDelta = $compareDeltas[$trafficKey] ?? null;
                        $trafficRowClass = match ($trafficDelta['highlight'] ?? null) {
                            'up' => 'etd-compare-row--up',
                            'down' => 'etd-compare-row--down',
                            default => '',
                        };
                    @endphp
                    <tr @class([$trafficRowClass])>
                        <td>
                            @if ($sourceUrl)
                                <a href="{{ $sourceUrl }}" class="etd-source-link">{{ SessionTrafficAttribution::displaySourceLabel($source['source']) ?? $source['source'] }}</a>
                            @else
                                {{ SessionTrafficAttribution::displaySourceLabel($source['source']) ?? $source['source'] }}
                            @endif
                        </td>
                        <td>{{ $source['medium'] }}</td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['sessions'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['sessions'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['views'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['views'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['add_to_cart'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['add_to_cart'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['begin_checkout'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['begin_checkout'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['proceed_checkout'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['proceed_checkout'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['payment_success'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['payment_success'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => number_format($source['sold_qty'] ?? 0),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['sold_qty'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => ($source['conversion_rate'] ?? 0).'%',
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['conversion_rate'] ?? null,
                            ])
                        </td>
                        <td class="etd-num etd-col-metric">
                            @include('ecom_tracker.partials.category-performance-metric-cell', [
                                'formatted' => '£'.number_format($source['revenue'] ?? 0, 2),
                                'showInlineCompareDelta' => $showInlineCompareDelta,
                                'delta' => $compareMetricDeltas[$trafficKey]['revenue'] ?? null,
                            ])
                        </td>
                        @if ($showCompareDelta && ! $showInlineCompareDelta)
                            @include('ecom_tracker.partials.compare-table-delta-cell', ['delta' => $trafficDelta])
                        @endif
                    </tr>
                @empty
                    <tr>
                        <td colspan="{{ ($showCompareDelta && ! $showInlineCompareDelta) ? $emptyColspan + 1 : $emptyColspan }}" class="text-slate-400">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
