@php
    $chips = $catalogFilterUi['chips'] ?? [];
@endphp

@if ($chips !== [])
    <div class="etd-page-header-meta-row etd-print-hide">
        <div class="etd-page-header-meta-row__left">
            <div class="etd-activity-context__filters">
                <div class="etd-activity-context__filter-chips">
                    @foreach ($chips as $chip)
                        <span class="etd-activity-context__chip">
                            <span class="etd-activity-context__chip-label">{{ $chip['label'] }}</span>
                            @if (! empty($chip['remove_url']))
                                <a href="{{ $chip['remove_url'] }}"
                                   class="etd-activity-context__chip-remove"
                                   aria-label="Remove filter: {{ $chip['label'] }}">
                                    <svg fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </a>
                            @endif
                        </span>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="etd-page-header-meta-row__right"></div>
    </div>
@endif
