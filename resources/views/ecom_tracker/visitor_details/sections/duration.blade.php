@include('ecom_tracker.partials.session-duration-distribution', [
    'distribution' => $data,
    'chartId' => 'vaDurationDistChart',
    'showPanel' => false,
])
