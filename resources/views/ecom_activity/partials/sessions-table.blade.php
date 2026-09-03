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
    $isWideTable = $focusColspan > 0;
@endphp

<div class="etd-activity-table-shell" data-etd-activity-table-shell>
    <div class="etd-activity-table-loading" data-etd-activity-table-loading aria-hidden="true">
        <svg class="etd-activity-table-loading__spinner" fill="none" viewBox="0 0 24 24" aria-hidden="true">
            <circle class="etd-activity-table-loading__track" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="3"></circle>
            <path class="etd-activity-table-loading__head" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="etd-activity-table-loading__label">Loading sessions…</span>
    </div>

    <div
        class="etd-table-scroll etd-table-scroll--fixed etd-table-scroll--activity{{ $isWideTable ? ' etd-table-scroll--activity-wide' : '' }}"
        style="--etd-activity-focus-cols: {{ $focusColspan }}"
        data-etd-activity-table-viewport
        x-data="{ openEvent: null }"
    >
        <table class="etd-table etd-table--activity w-full">
        <thead>
            <tr>
                <th class="etd-col-session">
                    @include('ecom_activity.partials.sortable-column-header', [
                        'sortKey' => 'session',
                        'label' => 'Session',
                        'tip' => 'When the session started',
                    ])
                </th>
                <th class="etd-col-user">User</th>
                <th class="etd-col-trust">
                    @include('ecom_tracker.partials.column-header-with-tip', [
                        'label' => 'Visitor trust',
                        'tip' => 'Whether this session looks like a real visitor, automated traffic, or could not be checked',
                    ])
                </th>
                <th class="etd-col-commerce">
                    @include('ecom_activity.partials.sortable-column-header', [
                        'sortKey' => 'funnel_stage',
                        'label' => 'Commerce',
                        'tip' => 'Highest funnel stage reached in this period: Order, Proceed, Checkout, Cart, or View',
                    ])
                </th>
                <th class="etd-col-actions etd-num">
                    @include('ecom_activity.partials.sortable-column-header', [
                        'sortKey' => 'actions',
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
                <th class="etd-col-duration etd-activity-col--optional">
                    @include('ecom_activity.partials.sortable-column-header', [
                        'sortKey' => 'duration',
                        'label' => 'Duration',
                    ])
                </th>
                <th class="etd-col-last-active etd-activity-col--optional">
                    @include('ecom_activity.partials.sortable-column-header', [
                        'sortKey' => 'last_active',
                        'label' => 'Last active',
                    ])
                </th>
                <th class="etd-col-action">View</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($sessions as $session)
                @php
                    $metrics = $rowMetrics[$session->session_id] ?? [];
                    $commerceEvents = $metrics['commerce_events'] ?? [];
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
                <tr class="etd-activity-session-row">
                    <td class="etd-col-session" data-label="Session">
                        @include('ecom_tracker.partials.session-id-chip', ['sessionId' => $session->session_id])
                        <div class="etd-subtle mt-0.5">{{ TrackerTime::formatFromStorage($session->created_at) }}</div>
                        @if (request()->filled('department') || request()->filled('category'))
                            @php $catalogPath = trim((string) ($metrics['catalog_path'] ?? '')); @endphp
                            @if ($catalogPath !== '' && $catalogPath !== '—')
                                <div class="etd-subtle mt-0.5">{{ $catalogPath }}</div>
                            @endif
                        @endif
                    </td>
                    <td class="etd-col-user" data-label="User">
                        @include('ecom_tracker.partials.session-identity', ['session' => $session])
                    </td>
                    <td class="etd-col-trust" data-label="Visitor trust">
                        @include('ecom_tracker.partials.visitor-classification-badge', ['session' => $session, 'mode' => 'compact'])
                    </td>
                    <td class="etd-col-commerce" data-label="Commerce">
                        @include('ecom_activity.partials.commerce-cell', [
                            'metrics' => $metrics,
                            'events' => $commerceEvents,
                            'sessionKey' => $session->session_id,
                        ])
                    </td>
                    <td class="etd-col-actions etd-num" data-label="Actions">{{ number_format((int) ($session->actions_count ?? $metrics['actions_count'] ?? 0)) }}</td>
                    @foreach ($focusColumns as $column)
                        <td @class([$column['class'] ?? null]) data-label="{{ $column['label'] }}">
                            {{ $formatMetric($column['key']) }}
                        </td>
                    @endforeach
                    <td class="etd-col-duration etd-activity-col--optional" data-label="Duration">{{ format_duration((int) ($session->session_duration_seconds ?? 0)) }}</td>
                    <td class="etd-col-last-active etd-activity-col--optional" data-label="Last active">{{ TrackerTime::diffForHumansLatestActivity($session->updated_at, $session->last_active_at, $session->created_at) ?? '—' }}</td>
                    <td class="etd-col-action" data-label="View">
                        @can('ecom_tracker.activity.show')
                            <a href="{{ EcomTrackerViewData::activityShowUrlFromRequest(request(), $session->session_id) }}" class="etd-link">View session</a>
                        @endcan
                    </td>
                </tr>
                @foreach ($commerceEvents as $event)
                    @php $eventKey = $session->session_id.':'.($event['id'] ?? $loop->index); @endphp
                    <tr
                        class="etd-commerce-event-row"
                        :class="{ 'is-open': openEvent === @js($eventKey) }"
                        x-cloak
                    >
                        <td colspan="{{ $totalCols }}" class="etd-commerce-event-row__cell">
                            @include('ecom_activity.partials.commerce-event-detail', ['event' => $event])
                        </td>
                    </tr>
                @endforeach
            @empty
                <tr class="etd-activity-empty-row">
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
</div>
