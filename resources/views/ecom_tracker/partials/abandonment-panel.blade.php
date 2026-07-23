@php
    $rows = $d[$dataKey]['rows'] ?? [];
    $sessionCount = $d[$dataKey]['session_count'] ?? 0;
    $atStake = $d[$dataKey]['at_stake'] ?? 0;
@endphp

<div class="etd-panel etd-panel--abandonment" id="{{ $panelId }}">
    <div class="etd-panel-head etd-panel-head--abandonment">
        <div class="etd-panel-head-main">
            <h2 class="etd-panel-title">{{ $title }}</h2>
        </div>
        <div class="etd-panel-head-meta">
            @include('ecom_tracker.partials.view-details-button', ['detailUrl' => $detailLink($detailSection)])
        </div>
    </div>

    <div class="etd-table-scroll etd-table-scroll--abandonment etd-table-scroll--fixed">
        <table class="etd-table etd-table--abandonment">
            <thead>
                <tr>
                    <th class="etd-abandon-session">Session</th>
                    <th class="etd-abandon-detail">{{ $detailLabel }}</th>
                    <th class="etd-num">{{ $valueLabel }}</th>
                    <th class="etd-abandon-idle">Idle</th>
                    <th class="etd-col-action">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td class="etd-abandon-session"><span class="etd-chip" title="{{ $row['session_id'] ?? $row['session_label'] }}">{{ $row['session_label'] }}</span></td>
                        <td class="etd-abandon-detail" title="{{ $row['detail'] }}">{{ $row['detail'] }}</td>
                        <td class="etd-num">£{{ number_format($row['value'], 2) }}</td>
                        <td class="etd-abandon-idle">{{ $row['idle'] }}</td>
                        <td class="etd-col-action"><a href="{{ $row['activity_url'] }}" class="etd-link">View</a></td>
                    </tr>
                @empty
                    <tr class="etd-table-empty">
                        <td colspan="5" class="etd-table-empty-cell text-slate-400">{{ $emptyMessage }}</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
