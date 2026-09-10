@props([
    'rows' => [],
    'verdict' => ['label' => 'Mixed', 'tone' => 'neutral'],
])

<section class="etd-compare-exec etd-panel" aria-label="Executive comparison summary">
    <div class="etd-compare-exec__head">
        <div>
            <h2 class="etd-panel-title">Executive summary</h2>
            <p class="etd-compare-exec__subtitle">Period A vs Period B — headline store performance for management review</p>
        </div>
        <span @class([
            'etd-compare-verdict',
            'etd-compare-verdict--good' => ($verdict['tone'] ?? '') === 'good',
            'etd-compare-verdict--bad' => ($verdict['tone'] ?? '') === 'bad',
            'etd-compare-verdict--neutral' => ($verdict['tone'] ?? '') === 'neutral',
        ])>{{ $verdict['label'] ?? 'Mixed' }}</span>
    </div>

    <div class="etd-compare-exec__table-wrap">
        <table class="etd-compare-exec__table">
            <thead>
                <tr>
                    <th scope="col">Metric</th>
                    <th scope="col" class="etd-num">Period A</th>
                    <th scope="col" class="etd-num">Period B</th>
                    <th scope="col" class="etd-num">Change</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($rows as $row)
                    @include('ecom_tracker.partials.compare-metric-row', ['row' => $row])
                @endforeach
            </tbody>
        </table>
    </div>
</section>
