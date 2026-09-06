@include('ecom_tracker.partials.category-performance-table', [
    'departments' => is_array($data) ? $data : [],
    'showCurrency' => true,
])
