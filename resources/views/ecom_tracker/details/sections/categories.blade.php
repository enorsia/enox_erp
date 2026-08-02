<table class="etd-table w-full">
    <thead>
        <tr>
            <th>Category</th>
            <th class="etd-num">Views</th>
            <th class="etd-num">Adds</th>
            <th class="etd-num">Purchase</th>
            <th class="etd-num">Sale item</th>
            <th class="etd-num">Sale amount</th>
            <th class="etd-num">Conversion</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data as $row)
            <tr>
                <td>{{ $row['label'] }}</td>
                <td class="etd-num">{{ number_format($row['views']) }}</td>
                <td class="etd-num">{{ number_format($row['adds']) }}</td>
                <td class="etd-num">{{ number_format($row['purchases']) }}</td>
                <td class="etd-num">{{ number_format($row['sale_items']) }}</td>
                <td class="etd-num">{{ number_format($row['sale_amount'], 2) }}</td>
                <td class="etd-num">{{ $row['conversion_rate'] }}%</td>
            </tr>
        @empty
            <tr><td colspan="7" class="text-slate-400">No category views in this period.</td></tr>
        @endforelse
    </tbody>
</table>
