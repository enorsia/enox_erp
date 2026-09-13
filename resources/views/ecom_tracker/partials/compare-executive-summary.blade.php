@props([
    'rows' => [],
    'leftRange' => [],
    'rightRange' => [],
])

@php
    use App\Support\TrackerTime;

    $leftRangeParts = TrackerTime::rangeDisplayParts($leftRange);
    $rightRangeParts = TrackerTime::rangeDisplayParts($rightRange);
    $leftHasRange = ($leftRangeParts['type'] ?? '') === 'range'
        || (($leftRangeParts['type'] ?? '') === 'single' && ($leftRangeParts['label'] ?? '') !== '');
    $rightHasRange = ($rightRangeParts['type'] ?? '') === 'range'
        || (($rightRangeParts['type'] ?? '') === 'single' && ($rightRangeParts['label'] ?? '') !== '');
@endphp

<section class="etd-compare-exec etd-panel"
         aria-label="Executive comparison summary"
         x-data="{ execOpen: false }">
    <button type="button"
            class="etd-compare-exec__toggle"
            @click="execOpen = !execOpen"
            :aria-expanded="execOpen.toString()">
        <span class="etd-compare-exec__toggle-main">
            <span class="etd-panel-title etd-compare-exec__title">Executive summary</span>
            <span class="etd-compare-exec__subtitle">
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
            </span>
        </span>
        <span class="etd-compare-exec__chevron" :class="{ 'is-open': execOpen }" aria-hidden="true">
            <svg class="etd-compare-exec__chevron-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
        </span>
    </button>

    <div class="etd-compare-exec__body" x-show="execOpen" x-collapse>
        <h2 class="etd-compare-exec__print-heading">Executive summary</h2>
        <div class="etd-compare-exec__table-wrap">
            <table class="etd-compare-exec__table">
                <thead>
                    <tr>
                        <th scope="col" class="etd-compare-exec__col-metric">Metric</th>
                        <th scope="col" class="etd-num etd-compare-exec__col-period etd-compare-exec__col-period--a">
                            <span class="etd-compare-exec__col-label">Period A</span>
                            @if ($leftHasRange)
                                <span class="etd-compare-exec__col-range">
                                    @include('ecom_tracker.partials.range-display-label', ['range' => $leftRange])
                                </span>
                            @endif
                        </th>
                        <th scope="col" class="etd-num etd-compare-exec__col-period etd-compare-exec__col-period--b">
                            <span class="etd-compare-exec__col-label">Period B</span>
                            @if ($rightHasRange)
                                <span class="etd-compare-exec__col-range">
                                    @include('ecom_tracker.partials.range-display-label', ['range' => $rightRange])
                                </span>
                            @endif
                        </th>
                        <th scope="col" class="etd-num etd-compare-exec__col-change">Change</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @include('ecom_tracker.partials.compare-metric-row', ['row' => $row])
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
