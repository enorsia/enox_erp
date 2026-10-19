@php
    $identityBadge = $row->is_logged_in
        ? '<span class="etd-badge etd-badge--user">Signed in</span>'
        : '<span class="etd-badge etd-badge--guest">Guest</span>';
    $durationSeconds = (int) ($row->duration_seconds ?? 0);
    $visitStarted = \App\Support\TrackerTime::formatFromStorage($row->created_at);
    $lastActive = \App\Support\TrackerTime::diffForHumansFromStorage($row->last_active_at);
    $country = trim((string) ($row->activity_country ?? ''));
@endphp

<div @class(['etd-user-cell', 'etd-user-cell--product-drill' => $productDrillTableLayout ?? false])>
    @if ($productDrillTableLayout ?? false)
        @php
            $displayName = filled($row->name) ? $row->name : (filled($row->email) ? $row->email : (filled($row->phone) ? $row->phone : null));
        @endphp
        <div class="etd-user-cell__head etd-user-cell__head--drill">
            <span class="etd-user-cell__name" title="{{ $displayName ?? 'Visitor' }}">
                {{ $displayName ?? ($row->is_logged_in ? 'Signed-in visitor' : 'Guest visitor') }}
            </span>
            {!! $identityBadge !!}
        </div>

        @if (filled($row->email) && filled($row->name))
            <div class="etd-subtle etd-user-cell__line" title="{{ $row->email }}">{{ $row->email }}</div>
        @endif
        @if (filled($row->phone) && (filled($row->name) || filled($row->email)))
            <div class="etd-subtle etd-user-cell__line" title="{{ $row->phone }}">{{ $row->phone }}</div>
        @endif

        <div class="etd-user-cell__drill-fact">
            <span class="etd-user-cell__drill-fact-label">Visit started</span>
            <span class="etd-user-cell__drill-fact-value">{{ $visitStarted ?? '—' }}</span>
        </div>
        @if ($durationSeconds > 0)
            <div class="etd-user-cell__drill-fact">
                <span class="etd-user-cell__drill-fact-label">Time on site</span>
                <span class="etd-user-cell__drill-fact-value">{{ format_duration($durationSeconds) }}</span>
            </div>
        @endif
        @if ($lastActive)
            <div class="etd-subtle etd-user-cell__drill-muted">Last active {{ $lastActive }}</div>
        @endif
        @if ($country !== '')
            <div class="etd-subtle etd-user-cell__drill-muted">From {{ $country }}</div>
        @endif
    @else
        @if (filled($row->name) || filled($row->email) || filled($row->phone))
            @php $displayName = $row->name ?: ($row->email ?: $row->phone); @endphp
            <div class="etd-user-cell__head">
                <span class="etd-user-cell__name" title="{{ $displayName }}">{{ $displayName }}</span>
                {!! $identityBadge !!}
            </div>
            @if (filled($row->email) && filled($row->name))
                <div class="etd-subtle etd-user-cell__line" title="{{ $row->email }}">{{ $row->email }}</div>
            @endif
            @if (filled($row->phone) && (filled($row->name) || filled($row->email)))
                <div class="etd-subtle etd-user-cell__line" title="{{ $row->phone }}">{{ $row->phone }}</div>
            @endif
        @else
            {!! $identityBadge !!}
        @endif
    @endif
</div>
