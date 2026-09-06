<p class="mb-3 text-sm text-slate-500">{{ number_format($data['session_count'] ?? 0) }} completed orders · £{{ number_format($data['at_stake'] ?? 0, 2) }} total</p>
@include('ecom_tracker.partials.recoverable-sessions-table', [
    'rows' => $data['rows'] ?? [],
    'emptyMessage' => 'No payment success events in this period.',
])
