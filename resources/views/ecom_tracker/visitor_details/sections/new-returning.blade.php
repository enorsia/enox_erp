<div class="etd-chart-wrap"><canvas id="vaNewReturningMini"></canvas></div>
<table class="etd-table mt-4 w-full">
    <thead><tr><th>Type</th><th class="etd-num">Count</th></tr></thead>
    <tbody>
        <tr><td>Unique</td><td class="etd-num">{{ number_format($data['new_returning']['unique'] ?? $data['new_returning']['new'] ?? 0) }}</td></tr>
        <tr><td>Returning</td><td class="etd-num">{{ number_format($data['new_returning']['returning'] ?? 0) }}</td></tr>
    </tbody>
</table>
