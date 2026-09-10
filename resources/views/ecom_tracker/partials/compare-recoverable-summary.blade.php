@props([
    'panels' => [],
])

<div class="etd-compare-recoverable-grid" data-compare-sync="recoverable">
    @foreach ($panels as $panel)
        @php
            $data = $panel['data'] ?? [];
            $sessionCount = (int) ($data['session_count'] ?? 0);
            $atStake = (float) ($data['at_stake'] ?? 0);
        @endphp
        <div @class([
            'etd-compare-recoverable-card',
            'etd-compare-recoverable-card--cart' => ($panel['tone'] ?? '') === 'cart',
            'etd-compare-recoverable-card--begin' => ($panel['tone'] ?? '') === 'begin',
            'etd-compare-recoverable-card--proceed' => ($panel['tone'] ?? '') === 'proceed',
            'etd-compare-recoverable-card--success' => ($panel['tone'] ?? '') === 'success',
        ])>
            <p class="etd-compare-recoverable-card__title">{{ $panel['title'] ?? '' }}</p>
            <p class="etd-compare-recoverable-card__value">
                {{ number_format($sessionCount) }} {{ Str::plural('session', $sessionCount) }}
            </p>
            <p class="etd-compare-recoverable-card__stake">£{{ number_format($atStake, 2) }} at stake</p>
        </div>
    @endforeach
</div>
