@php use App\Support\TrackerTime; @endphp
<table class="etd-table w-full">
    <thead>
        <tr>
            <th>Visitor ID</th>
            <th class="etd-num">Sessions</th>
            <th class="etd-num">Order qty</th>
            <th class="etd-num">Total stay</th>
            <th class="etd-num">Avg / session</th>
            <th>First seen</th>
            <th>Last active</th>
            <th>Visitor trust</th>
            <th>Device</th>
            <th class="etd-col-action">Action</th>
        </tr>
    </thead>
    <tbody>
        @forelse ($data['visitors'] as $visitor)
            <tr>
                <td>
                    <code class="text-xs" title="{{ $visitor['visitor_id'] }}">{{ Str::limit($visitor['visitor_id'], 12) }}</code>
                </td>
                <td class="etd-num">{{ $visitor['session_count'] }}</td>
                <td class="etd-num">{{ number_format($visitor['order_qty'] ?? 0) }}</td>
                <td class="etd-num">{{ $visitor['total_stay_label'] }}</td>
                <td class="etd-num">{{ $visitor['avg_stay_label'] }}</td>
                <td>{{ TrackerTime::toLocal($visitor['first_seen_at'])?->format('d M Y, H:i') ?? '—' }}</td>
                <td>{{ TrackerTime::toLocal($visitor['last_active_at'])?->diffForHumans() ?? '—' }}</td>
                <td>
                    @if (! empty($visitor['latest_session']))
                        @include('ecom_tracker.partials.visitor-classification-badge', ['session' => $visitor['latest_session'], 'mode' => 'compact'])
                    @else
                        —
                    @endif
                </td>
                <td>{{ trim(($visitor['device_type'] ?? '') . ' · ' . ($visitor['browser'] ?? ''), ' ·') ?: '—' }}</td>
                <td class="etd-col-action">
                    @can('ecom_tracker.activity.index')
                        <a href="{{ ($activityLink)($visitor['visitor_id']) }}" class="etd-link">View sessions</a>
                    @endcan
                </td>
            </tr>
        @empty
            <tr><td colspan="10" class="text-center text-slate-500 py-8">No visitors in this window.</td></tr>
        @endforelse
    </tbody>
</table>
