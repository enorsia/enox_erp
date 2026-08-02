<table class="etd-table etd-table--categories w-full">
    <thead>
        <tr>
            <th class="etd-col-category">Category</th>
            <th class="etd-num">Views</th>
            <th class="etd-num">
                @include('ecom_tracker.partials.column-header-with-tip', [
                    'label' => 'Adds',
                    'tip' => 'Add to cart',
                    'align' => 'right',
                ])
            </th>
            <th class="etd-num">Sale item</th>
            <th class="etd-num">Sale amount</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $row)
            <tr>
                <td class="etd-col-category">{{ $row['label'] }}</td>
                <td class="etd-num">{{ number_format($row['views']) }}</td>
                <td class="etd-num">{{ number_format($row['adds']) }}</td>
                <td class="etd-num">{{ number_format($row['sale_items']) }}</td>
                <td class="etd-num">{{ number_format($row['sale_amount'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-slate-400">No category views in this period.</td></tr>
        @endforelse
    </tbody>
</table>
