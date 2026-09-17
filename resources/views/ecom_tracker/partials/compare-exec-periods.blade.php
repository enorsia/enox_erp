@props([
    'leftRange' => [],
    'rightRange' => [],
    'leftHasRange' => false,
    'rightHasRange' => false,
])

<span class="etd-compare-exec__periods">
    <span class="etd-compare-exec__period">
        <span class="etd-compare-exec__period-badge">Period A</span>
        @if ($leftHasRange)
            <span class="etd-compare-exec__period-range">
                @include('ecom_tracker.partials.range-display-label', ['range' => $leftRange])
            </span>
        @endif
    </span>
    <span class="etd-compare-exec__period-vs" aria-hidden="true">vs</span>
    <span class="etd-compare-exec__period">
        <span class="etd-compare-exec__period-badge">Period B</span>
        @if ($rightHasRange)
            <span class="etd-compare-exec__period-range">
                @include('ecom_tracker.partials.range-display-label', ['range' => $rightRange])
            </span>
        @endif
    </span>
</span>
