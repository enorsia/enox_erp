<p class="mb-3 text-sm text-slate-500">Began checkout but didn't proceed · {{ number_format($data['session_count'] ?? 0) }} sessions · £{{ number_format($data['at_stake'] ?? 0, 2) }} at stake</p>
@include('ecom_tracker.partials.recoverable-sessions-table', [
    'rows' => $data['rows'] ?? [],
    'emptyMessage' => 'No begin checkout abandonment in this period.',
])
