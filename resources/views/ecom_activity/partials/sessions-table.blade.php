@props([
    'sessions',
    'focusColumns' => [],
    'rowMetrics' => [],
    'emptyMessage' => 'No visitor sessions found.',
    'clearFocusUrl' => null,
    'hasFocus' => false,
])

@php
    use App\Support\TrackerTime;
    use App\Support\EcomTrackerViewData;

    $focusColspan = count($focusColumns);
    $totalCols = 8 + $focusColspan;
@endphp

<div class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--activity">
    <table class="etd-table etd-table--activity w-full">
        <thead>
            <tr>
                <th class="etd-col-session">Session</th>
                <th class="etd-col-user">User</th>
                <th class="etd-col-trust">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Visitor trust',
                        'tip' => 'Whether this session looks like a real visitor, automated traffic, or could not be checked',
                    ])
                </th>
                <th class="etd-col-commerce">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Commerce',
                        'tip' => 'Highest funnel stage reached in this period: Cart, Checkout, Proceed, or Order with value',
                    ])
                </th>
                <th class="etd-col-actions etd-num">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Actions',
                        'tip' => 'Total tracked events in this session',
                        'align' => 'center',
                    ])
                </th>
                @foreach ($focusColumns as $column)
                    <th @class([$column['class'] ?? null])>
                        @if (! empty($column['tip']))
                            @include('ecom_tracker.partials.column-header-with-tip', [
                                'label' => $column['label'],
                                'tip' => $column['tip'],
                                'align' => str_contains($column['class'] ?? '', 'etd-num') ? 'center' : null,
                            ])
                        @else
                            {{ $column['label'] }}
                        @endif
                    </th>
                @endforeach
                <th>Duration</th>
                <th>Last active</th>
                <th class="etd-col-action">View</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php
                    $metrics = $rowMetrics[$session->session_id] ?? [];
                    $formatMetric = function (string $key, mixed $default = '—') use ($metrics) {
                        $value = $metrics[$key] ?? $default;

                        if (is_numeric($value) && in_array($key, ['cart_value', 'checkout_value', 'order_value'], true)) {
                            return '£'.number_format((float) $value, 2);
                        }

                        if (is_numeric($value) && ! in_array($key, ['purchased'], true)) {
                            return number_format((int) $value);
                        }

                        return $value;
                    };
                @endphp
                <tr>
                    <td class="etd-col-session">
                        @include('ecom_tracker.partials.session-id-chip', ['sessionId' => $session->session_id])
                        <div class="etd-subtle mt-0.5">{{ TrackerTime::formatFromStorage($session->created_at) }}</div>
                    </td>
                    <td class="etd-col-user">
                        @include('ecom_tracker.partials.session-identity', ['session' => $session])
                    </td>
                    <td class="etd-col-trust">
                        @include('ecom_tracker.partials.visitor-classification-badge', ['session' => $session, 'mode' => 'compact'])
                    </td>
                    <td class="etd-col-commerce">
                        @include('ecom_activity.partials.commerce-cell', ['metrics' => $metrics])
                    </td>
                    <td class="etd-col-actions etd-num">{{ number_format((int) ($session->actions_count ?? $metrics['actions_count'] ?? 0)) }}</td>
                    @foreach ($focusColumns as $column)
                        <td @class([$column['class'] ?? null])>
                            {{ $formatMetric($column['key']) }}
                        </td>
                    @endforeach
                    <td>{{ format_duration((int) ($session->session_duration_seconds ?? 0)) }}</td>
                    <td>{{ TrackerTime::diffForHumansLatestActivity($session->updated_at, $session->last_active_at, $session->created_at) ?? '—' }}</td>
                    <td class="etd-col-action">
                        @can('ecom_tracker.activity.show')
                            <a href="{{ EcomTrackerViewData::activityShowUrlFromRequest(request(), $session->session_id) }}" class="etd-link">View session</a>
                        @endcan
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="{{ $totalCols }}" class="text-center text-slate-500 py-10">
                        {{ $emptyMessage }}
                        @if ($hasFocus && filled($clearFocusUrl))
                            <div class="mt-2">
                                <a href="{{ $clearFocusUrl }}" class="text-accent-500 no-underline hover:underline text-[12px]">Clear focus</a>
                            </div>
                        @endif
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
