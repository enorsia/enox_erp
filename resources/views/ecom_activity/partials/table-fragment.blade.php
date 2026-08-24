<div class="etd-panel">
    @include('ecom_activity.partials.activity-sort-toolbar')
    @include('ecom_activity.partials.sessions-table', [
        'sessions' => $sessions,
        'focusColumns' => $focusColumns ?? [],
        'rowMetrics' => $rowMetrics ?? [],
        'emptyMessage' => $emptyMessage ?? 'No visitor sessions found.',
        'clearFocusUrl' => $clearFocusUrl ?? null,
        'hasFocus' => $hasFocus ?? false,
    ])
</div>

<div class="etd-activity-pagination">
    @include('layouts.pagination', ['paginator' => $sessions])
</div>
