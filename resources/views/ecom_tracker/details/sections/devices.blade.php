<div x-data="{ view: 'device' }">
    <div class="etd-device-browser__toolbar">
        @include('ecom_tracker.partials.device-browser-toggle')
    </div>

    @include('ecom_tracker.partials.device-browser-breakdown', [
        'devices' => $data,
    ])
</div>
