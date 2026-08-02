@props([
    'rows' => [],
    'emptyColspan' => 11,
    'emptyMessage' => 'No traffic source data in this period.',
    'activitySourceLink' => null,
])

@php
    $resolveSourceLink = function (array $row) use ($activitySourceLink) {
        if (! is_callable($activitySourceLink) || ($row['source'] ?? '') === 'Other') {
            return null;
        }

        $url = $activitySourceLink($row['source']);

        return filled($url) ? $url : null;
    };
@endphp

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
                        'tip' => 'Sessions with a product view',
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
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $source)
                @php($sourceUrl = $resolveSourceLink($source))
                <tr>
                    <td>
                        @if ($sourceUrl)
                            <a href="{{ $sourceUrl }}" class="etd-source-link">{{ $source['source'] }}</a>
                        @else
                            {{ $source['source'] }}
                        @endif
                    </td>
                    <td>{{ $source['medium'] }}</td>
                    <td class="etd-num">{{ number_format($source['sessions']) }}</td>
                    <td class="etd-num">{{ number_format($source['views']) }}</td>
                    <td class="etd-num">{{ number_format($source['add_to_cart']) }}</td>
                    <td class="etd-num">{{ number_format($source['begin_checkout']) }}</td>
                    <td class="etd-num">{{ number_format($source['proceed_checkout']) }}</td>
                    <td class="etd-num">{{ number_format($source['payment_success']) }}</td>
                    <td class="etd-num">{{ number_format($source['sold_qty']) }}</td>
                    <td class="etd-num">{{ $source['conversion_rate'] }}%</td>
                    <td class="etd-num">£{{ number_format($source['revenue'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $emptyColspan }}" class="text-slate-400">{{ $emptyMessage }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
