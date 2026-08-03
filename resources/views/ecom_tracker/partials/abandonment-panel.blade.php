@php
    $rows = $d[$dataKey]['rows'] ?? [];
    $sessionCount = $d[$dataKey]['session_count'] ?? 0;
    $atStake = $d[$dataKey]['at_stake'] ?? 0;
    $panelTone = $panelTone ?? 'cart';
@endphp

<div @class([
    'etd-panel etd-panel--abandonment etd-panel--recoverable',
    'etd-panel--recoverable-cart' => $panelTone === 'cart',
    'etd-panel--recoverable-begin' => $panelTone === 'begin',
    'etd-panel--recoverable-proceed' => $panelTone === 'proceed',
    'etd-panel--recoverable-success' => $panelTone === 'success',
]) id="{{ $panelId }}">
    <div class="etd-panel-head etd-panel-head--abandonment">
        <div class="etd-panel-head-main">
            <h2 class="etd-panel-title">{{ $title }}</h2>
            <p class="etd-recoverable-summary">
                {{ number_format($sessionCount) }} {{ Str::plural('session', $sessionCount) }}
                · £{{ number_format($atStake, 2) }}
            </p>
        </div>
        <div class="etd-panel-head-meta">
            @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink($detailSection)])
        </div>
    </div>

    <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
        @include('ecom_tracker.partials.recoverable-sessions-table', [
            'rows' => $rows,
            'emptyMessage' => $emptyMessage,
        ])
    </div>
</div>
