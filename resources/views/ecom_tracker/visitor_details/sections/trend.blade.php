<div class="etd-chart-wrap lg"><canvas id="vaTrendMini"></canvas></div>
@if ($paginator ?? null)
    <p class="etd-chart-note">Chart shows the full selected period. The table below is paginated.</p>
@endif
<table class="etd-table mt-4 w-full">
    <thead><tr><th>Date</th><th class="etd-num">Unique visitors</th><th class="etd-num">Sessions</th></tr></thead>
    <tbody>
        @forelse ($data['table_rows'] ?? [] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td class="etd-num">{{ number_format($row['unique_visitors']) }}</td>
                <td class="etd-num">{{ number_format($row['sessions']) }}</td>
            </tr>
        @empty
            <tr><td colspan="3" class="text-slate-400">No trend data in this period.</td></tr>
        @endforelse
    </tbody>
</table>
