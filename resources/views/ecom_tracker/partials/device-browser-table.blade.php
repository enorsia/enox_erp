@props([
    'title' => 'Device',
    'rows' => [],
    'emptyMessage' => 'No data in this period.',
])

<div class="etd-device-browser-panel">
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
                            'label' => 'Qty',
                            'tip' => 'Total units sold from payment_success events',
                            'align' => 'right',
                        ])
                    </th>
                    <th class="etd-num">Conv.</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="etd-num">{{ number_format($row['sessions']) }}</td>
                        <td class="etd-num">{{ number_format($row['views']) }}</td>
                        <td class="etd-num">{{ number_format($row['add_to_cart']) }}</td>
                        <td class="etd-num">{{ number_format($row['begin_checkout']) }}</td>
                        <td class="etd-num">{{ number_format($row['proceed_checkout']) }}</td>
                        <td class="etd-num">{{ number_format($row['sold_qty']) }}</td>
                        <td class="etd-num">{{ $row['conversion_rate'] }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-slate-400">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
