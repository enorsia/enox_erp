<div class="etd-chart-wrap"><canvas id="etdTrendChart"></canvas></div>
@if ($paginator ?? null)
    <p class="etd-chart-note">Chart shows the full selected period. The table below is paginated.</p>
@endif
<table class="etd-table mt-4 w-full">
    <thead><tr><th>Date</th><th class="etd-num">Sessions</th><th class="etd-num">Purchases</th><th class="etd-num">Conversion</th></tr></thead>
    <tbody>
        @forelse ($data['table_rows'] ?? [] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td class="etd-num">{{ number_format($row['sessions']) }}</td>
                <td class="etd-num">{{ number_format($row['purchases']) }}</td>
                <td class="etd-num">{{ $row['conversion_rate'] }}%</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-slate-400">No trend data in this period.</td></tr>
        @endforelse
    </tbody>
</table>
