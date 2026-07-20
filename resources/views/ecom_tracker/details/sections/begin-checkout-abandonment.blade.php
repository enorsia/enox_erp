<p class="mb-3 text-sm text-slate-500">Began checkout but didn't proceed · {{ number_format($data['session_count'] ?? 0) }} sessions · £{{ number_format($data['at_stake'] ?? 0, 2) }} at stake</p>
<table class="etd-table w-full">
    <thead><tr><th>Session</th><th>Coupon</th><th class="etd-num">Total</th><th>Idle</th><th></th></tr></thead>
    <tbody>
        @forelse ($data['rows'] ?? [] as $row)
            <tr>
                <td>{{ $row['session_label'] }}</td>
                <td>{{ $row['detail'] }}</td>
                <td class="etd-num">£{{ number_format($row['value'], 2) }}</td>
                <td>{{ $row['idle'] }}</td>
                <td><a href="{{ $row['activity_url'] }}" class="etd-link">View</a></td>
            </tr>
        @empty
            <tr><td colspan="5" class="text-slate-400">No data.</td></tr>
        @endforelse
    </tbody>
</table>
