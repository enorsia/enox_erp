@php use App\Support\TrackerTime; @endphp
<span class="etd-meta-item">
    <svg class="etd-meta-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/>
    </svg>
    All times {{ TrackerTime::timezoneLabel() }}
</span>
