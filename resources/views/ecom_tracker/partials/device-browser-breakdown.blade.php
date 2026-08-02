@props([
    'devices' => ['by_device' => [], 'by_browser' => []],
])

@php
    $deviceRows = $devices['by_device'] ?? [];
    $browserRows = $devices['by_browser'] ?? [];
@endphp

<div class="etd-device-browser">
    <div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--device-browser">
        <table class="etd-table etd-table--device-browser w-full">
            <thead>
                <tr>
                    <th x-text="view === 'browser' ? 'Browser' : 'Device'"></th>
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
            <tbody x-show="view === 'device'">
                @forelse ($deviceRows as $row)
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
                        <td colspan="8" class="text-slate-400">No device data in this period.</td>
                    </tr>
                @endforelse
            </tbody>
            <tbody x-show="view === 'browser'" x-cloak>
                @forelse ($browserRows as $row)
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
                        <td colspan="8" class="text-slate-400">No browser data in this period.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
