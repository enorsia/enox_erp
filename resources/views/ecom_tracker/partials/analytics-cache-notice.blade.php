@php
    $cache = $analytics_cache ?? null;
    $ttlMinutes = max(1, (int) round(((int) ($cache['ttl_seconds'] ?? 300)) / 60));
@endphp
@if (($cache['enabled'] ?? false) && filled($cache['cached_at'] ?? null))
    <span class="etd-meta-item">
        <svg class="etd-meta-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5"/><path stroke-linecap="round" stroke-linejoin="round" d="M20 9a8 8 0 00-14.9-3M4 15a8 8 0 0014.9 3"/>
        </svg>
        Every {{ $ttlMinutes }} min
    </span>
@endif
