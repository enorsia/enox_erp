<div class="etd-chart-wrap lg"><canvas id="vaTrendMini"></canvas></div>
<table class="etd-table mt-4 w-full">
    <thead><tr><th>Date</th><th class="etd-num">Visitors</th><th class="etd-num">Sessions</th></tr></thead>
    <tbody>
        @foreach ($data['trend']['labels'] ?? [] as $i => $label)
            <tr>
                <td>{{ $label }}</td>
                <td class="etd-num">{{ number_format($data['trend']['visitors'][$i] ?? 0) }}</td>
                <td class="etd-num">{{ number_format($data['trend']['sessions'][$i] ?? 0) }}</td>
            </tr>
        @endforeach
    </tbody>
</table>
