@props([
    'engagement' => [],
])

<div class="etd-engagement-quality">
    <div class="etd-engagement-legend" aria-hidden="true">
        <span class="etd-engagement-legend__item">
            <span class="etd-swatch etd-swatch--buyer"></span>
            Buyers
        </span>
        <span class="etd-engagement-legend__item">
            <span class="etd-swatch etd-swatch--non-buyer"></span>
            Non-buyers
        </span>
    </div>

    <div class="etd-chart-wrap etd-chart-wrap--engagement">
        <canvas id="etdDwellChart"></canvas>
    </div>

    <p class="etd-panel-note etd-panel-note--engagement">
        Average active time on category and product pages. Buyers completed payment in this period.
    </p>
</div>
