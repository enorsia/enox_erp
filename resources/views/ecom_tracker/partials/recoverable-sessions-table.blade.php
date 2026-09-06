@php
    $rows = $rows ?? ($data['rows'] ?? []);
    $emptyMessage = $emptyMessage ?? 'No data.';
@endphp

<table class="etd-table etd-table--abandonment etd-table--recoverable w-full">
    <thead>
        <tr>
            <th class="etd-abandon-session">Session</th>
            <th class="etd-col-center etd-abandon-qty">Qty</th>
            <th class="etd-col-center etd-abandon-value">Value</th>
            <th class="etd-col-center etd-abandon-time">Time</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($rows as $row)
            <tr>
                <td class="etd-abandon-session">
                    <a href="{{ $row['activity_url'] }}" class="etd-abandon-session-link" title="{{ $row['session_id'] ?? $row['session_label'] }}">
                        <span class="etd-chip etd-chip--recoverable">{{ $row['session_label'] }}</span>
                    </a>
                </td>
                <td class="etd-col-center etd-abandon-qty">{{ number_format((int) ($row['qty'] ?? 0)) }}</td>
                <td class="etd-col-center etd-abandon-value">£{{ number_format($row['value'], 2) }}</td>
                <td class="etd-col-center etd-abandon-time">{{ $row['occurred_ago'] ?? '—' }}</td>
            </tr>
        @empty
            <tr class="etd-table-empty">
                <td colspan="4" class="etd-table-empty-cell text-slate-400">{{ $emptyMessage }}</td>
            </tr>
        @endforelse
    </tbody>
</table>
