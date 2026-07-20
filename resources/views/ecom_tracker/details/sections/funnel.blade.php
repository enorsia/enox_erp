<div class="etd-funnel">
    @foreach ($data as $row)
        <div class="etd-funnel-row">
            <div class="etd-funnel-stage">{{ $row['stage'] }}</div>
            <div class="etd-funnel-track">
                <div class="etd-funnel-fill" style="width: {{ max(8, $row['percent_of_top']) }}%">{{ number_format($row['count']) }}</div>
            </div>
            <div class="etd-funnel-stats">
                {{ $row['percent_of_top'] }}% of top
                @if ($row['drop_off_percent'] !== null)
                    <span class="etd-funnel-drop">−{{ $row['drop_off_percent'] }}% drop-off</span>
                @endif
            </div>
        </div>
    @endforeach
</div>
