@php
    $cache = $analytics_cache ?? null;
    $ttlMinutes = max(1, (int) round(((int) ($cache['ttl_seconds'] ?? 300)) / 60));
@endphp
@if (($cache['enabled'] ?? false) && filled($cache['cached_at'] ?? null))
    <p class="etd-cache-notice">
        Metrics refresh every {{ $ttlMinutes }} min
    </p>
@endif
