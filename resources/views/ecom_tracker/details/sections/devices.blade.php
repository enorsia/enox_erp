<div class="etd-two-donut">
    <div class="etd-donut-block">
        <div class="etd-chart-wrap"><canvas id="etdDeviceChart"></canvas></div>
        <div class="etd-donut-cap">by device</div>
    </div>
    <div class="etd-donut-block">
        <div class="etd-chart-wrap"><canvas id="etdLoginChart"></canvas></div>
        <div class="etd-donut-cap">guest vs logged-in</div>
    </div>
</div>
<ul class="etd-legend mt-4">
    @php $deviceColors = ['#1D9E75', '#f59e0b', '#64748b', '#3b82f6', '#8b5cf6']; @endphp
    @foreach ($data['legend'] ?? [] as $index => $item)
        <li>
            <span>
                <span class="etd-swatch" style="background: {{ $deviceColors[$index % count($deviceColors)] }}"></span>
                {{ $item['label'] }}
            </span>
            <span>{{ $item['share'] }}% · conv. {{ $item['conversion_rate'] }}%</span>
        </li>
    @endforeach
</ul>
