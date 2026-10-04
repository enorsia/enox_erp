@props([
    'visitors' => '704',
    'sessions' => '847',
    'totalStay' => '4d 11h',
    'avgStay' => '3m 42s',
    'itemsSold' => '38',
    'revenue' => '£2,847.50',
    'cartDrop' => '7.3% / 62',
    'checkoutDrop' => '3.3% / 28',
    'proceedDrop' => '1.4% / 12',
    'payments' => '4.5% / 38',
])

<div class="etd-kpi-panel etd-compare-kpi-panel etd-print-unit" data-compare-sync="kpis">
    <div class="etd-kpi-groups">
        <div class="etd-kpi-group etd-kpi-group--4 etd-kpi-group--audience">
            <p class="etd-kpi-section-label">Audience &amp; engagement</p>
            <div class="etd-kpi-group-grid">
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Unique visitors</span></span>
                    <div class="etd-kpi-value">{{ $visitors }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Sessions</span></span>
                    <div class="etd-kpi-value">{{ $sessions }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Total stay time</span></span>
                    <div class="etd-kpi-value">{{ $totalStay }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Avg stay time</span></span>
                    <div class="etd-kpi-value">{{ $avgStay }}</div>
                </div>
            </div>
        </div>
        <div class="etd-kpi-group etd-kpi-group--2 etd-kpi-group--sale">
            <p class="etd-kpi-section-label">Sale &amp; conversion</p>
            <div class="etd-kpi-group-grid">
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Items sold</span></span>
                    <div class="etd-kpi-value">{{ $itemsSold }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Sale amount</span></span>
                    <div class="etd-kpi-value">{{ $revenue }}</div>
                </div>
            </div>
        </div>
        <div class="etd-kpi-group etd-kpi-group--4 etd-kpi-group--funnel">
            <p class="etd-kpi-section-label">Funnel drop-off</p>
            <div class="etd-kpi-group-grid">
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Cart drop</span></span>
                    <div class="etd-kpi-value">{{ $cartDrop }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Checkout drop</span></span>
                    <div class="etd-kpi-value">{{ $checkoutDrop }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Proceed drop</span></span>
                    <div class="etd-kpi-value">{{ $proceedDrop }}</div>
                </div>
                <div class="etd-kpi etd-kpi--compact">
                    <span class="etd-kpi-label-wrap"><span class="etd-kpi-label-text">Payments</span></span>
                    <div class="etd-kpi-value etd-kpi-value--success">{{ $payments }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
