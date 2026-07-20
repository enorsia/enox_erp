<table class="etd-table w-full">
    <thead><tr><th>Source</th><th>Medium</th><th class="etd-num">Sessions</th><th class="etd-num">Conv.</th><th class="etd-num">Revenue</th></tr></thead>
    <tbody>
        @forelse ($data as $row)
            <tr>
                <td>{{ $row['source'] }}</td>
                <td>{{ $row['medium'] }}</td>
                <td class="etd-num">{{ number_format($row['sessions']) }}</td>
                <td class="etd-num">{{ $row['conversion_rate'] }}%</td>
                <td class="etd-num">£{{ number_format($row['revenue'], 2) }}</td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-slate-400">No data.</td></tr>
        @endforelse
    </tbody>
</table>
