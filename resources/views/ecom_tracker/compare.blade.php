@extends('layouts.app')

@section('title', 'Compare Store Performance')

@section('content')
<div id="ecom-tracker-compare-content" class="etd-page etd-compare-page">
    <header class="etd-page-header">
        <div class="etd-page-header-bar etd-compare-page-header-bar">
            <div class="etd-page-header-left-stack">
                <h1 class="etd-page-title">Store performance</h1>
                <div class="etd-page-header-sub etd-compare-page-header-sub">
                    <span class="etd-compare-page-mode">Compare</span>
                    <span class="etd-header-sep" aria-hidden="true">·</span>
                    <p class="etd-compare-page-subtitle" title="{{ $left['range']['label'] ?? '' }} vs {{ $right['range']['label'] ?? '' }}">
                        <span class="etd-compare-page-subtitle-range">{{ $left['range']['label'] ?? '' }}</span>
                        <span class="etd-compare-page-subtitle-vs">vs</span>
                        <span class="etd-compare-page-subtitle-range">{{ $right['range']['label'] ?? '' }}</span>
                    </p>
                    <span class="etd-header-sep etd-header-sep--meta" aria-hidden="true">·</span>
                    <div class="etd-page-meta">
                        @include('ecom_tracker.partials.timezone-notice')
                    </div>
                </div>
            </div>

            <div class="etd-page-header-right">
                <div class="etd-header-actions">
                    @include('ecom_tracker.partials.header-back-button', [
                        'url' => $backUrl,
                        'label' => 'Back',
                    ])
                    @include('ecom_tracker.partials.header-print-button', [
                        'id' => 'etdComparePrintBtn',
                        'label' => 'Print',
                    ])
                </div>
            </div>
        </div>
    </header>

    <div class="etd-compare-grid">
        @include('ecom_tracker.partials.compare-column', [
            'side' => 'left',
            'd' => $left,
            'filters' => $leftFilters,
            'otherFilters' => $rightFilters,
            'backUrl' => $backUrl,
            'chartCanvasId' => 'etdTrendChartLeft',
            'chartScrollId' => 'etdTrendChartScrollLeft',
            'chartWrapId' => 'etdTrendChartWrapLeft',
            'chartLegendId' => 'etdTrendLegendLeft',
            'chartHintId' => 'etdTrendChartScrollHintLeft',
        ])

        @include('ecom_tracker.partials.compare-column', [
            'side' => 'right',
            'd' => $right,
            'filters' => $rightFilters,
            'otherFilters' => $leftFilters,
            'backUrl' => $backUrl,
            'chartCanvasId' => 'etdTrendChartRight',
            'chartScrollId' => 'etdTrendChartScrollRight',
            'chartWrapId' => 'etdTrendChartWrapRight',
            'chartLegendId' => 'etdTrendLegendRight',
            'chartHintId' => 'etdTrendChartScrollHintRight',
        ])
    </div>
</div>

<script>
    window.ecomTrackerCompareData = @json([
        'left' => $left['chart_payload'] ?? null,
        'right' => $right['chart_payload'] ?? null,
    ]);
</script>
@endsection
