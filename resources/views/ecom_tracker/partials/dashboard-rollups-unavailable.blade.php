@php
    $message = $dashboard['rollups_unavailable_message'] ?? 'Daily rollups are not available for this date range.';
    $expected = (int) ($dashboard['rollups_coverage']['expected'] ?? 0);
    $found = (int) ($dashboard['rollups_coverage']['found'] ?? 0);
@endphp

<section class="etd-dashboard-section" aria-labelledby="rollups-unavailable-title">
    <div class="etd-panel etd-panel--empty">
        <h2 id="rollups-unavailable-title" class="etd-panel-title">Rollups required</h2>
        <p class="etd-section-note mb-3">{{ $message }}</p>
        @if ($expected > 0)
            <p class="etd-meta-item text-sm">
                Site rollup coverage: <strong>{{ $found }}</strong> / <strong>{{ $expected }}</strong> closed days
                (today is always read live).
            </p>
        @endif
        <p class="etd-section-note mt-3 mb-0">
            Backfill closed days, then reload this page. User activity and other tracker pages are unchanged.
        </p>
    </div>
</section>
