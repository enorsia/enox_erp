@php
    use App\Support\EcomTrackerViewData;
@endphp

@include('ecom_tracker.partials.traffic-sources-table', [
    'rows' => $data,
    'emptyMessage' => 'No data.',
    'activitySourceLink' => fn (string $source) => EcomTrackerViewData::activitySourceLink($filters, $source),
])
