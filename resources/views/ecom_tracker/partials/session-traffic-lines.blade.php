@props([
    'source' => null,
    'utm' => null,
    'referer' => null,
])

<div class="etd-session-traffic">
    @foreach ([
        'Source' => $source,
        'UTM' => $utm,
        'Referer' => $referer,
    ] as $label => $value)
        <div class="etd-session-traffic__row">
            <span class="etd-session-traffic__label">{{ $label }}:</span>
            @if (filled($value))
                <span class="etd-session-traffic__value etd-hover-tip" tabindex="0">
                    <span class="etd-session-traffic__text">{{ $value }}</span>
                    <span class="etd-hover-tip__content etd-session-traffic__tip" role="tooltip">{{ $value }}</span>
                </span>
            @else
                <span class="etd-session-traffic__value etd-session-traffic__value--empty">—</span>
            @endif
        </div>
    @endforeach
</div>
