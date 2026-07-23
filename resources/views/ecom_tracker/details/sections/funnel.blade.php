<div class="etd-funnel">
    @foreach ($data as $row)
        <div class="etd-funnel-row">
            <div class="etd-funnel-stage">{{ $row['stage'] }}</div>
            <div class="etd-funnel-track">
                <div class="etd-funnel-fill" style="width: {{ max(8, $row['percent_of_top']) }}%">{{ number_format($row['count']) }}</div>
            </div>
        </div>
    @endforeach
</div>
