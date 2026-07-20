<table class="etd-table w-full">
    <thead>
        <tr>
            <th>Category</th>
            <th class="etd-num">Views</th>
            <th class="etd-num">Add to cart</th>
            <th class="etd-num">Conversion</th>
            <th>@include('ecom_tracker.partials.signal-header')</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $row)
            <tr>
                <td>{{ $row['name'] }}</td>
                <td class="etd-num">{{ number_format($row['views']) }}</td>
                <td class="etd-num">{{ $row['add_rate'] }}%</td>
                <td class="etd-num">{{ $row['conversion_rate'] }}%</td>
                <td><span class="etd-badge {{ $row['signal'] }}">{{ $row['signal_label'] }}</span></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-slate-400">No category views in this period.</td></tr>
        @endforelse
    </tbody>
</table>
