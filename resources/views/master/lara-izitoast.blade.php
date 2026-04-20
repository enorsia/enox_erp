<script>
    document.addEventListener('DOMContentLoaded', function () {
        @foreach( session('toasts', collect())->toArray() as $toast)
        (function () {
            var options = {
                title: '{{ $toast['title'] }}',
                message: '{{ $toast['message'] }}',
                messageColor: '{{ $toast['messageColor'] }}',
                messageSize: '{{ $toast['messageSize'] }}',
                titleLineHeight: '{{ $toast['titleLineHeight'] }}',
                messageLineHeight: '{{ $toast['messageLineHeight'] }}',
                position: '{{ $toast['position'] }}',
                titleSize: '{{ $toast['titleSize'] }}',
                titleColor: '{{ $toast['titleColor'] }}',
                closeOnClick: {{ $toast['closeOnClick'] ? 'true' : 'false' }},
            };
            var type = '{{ $toast["type"] }}';
            if (type === 'info')         iziToast.info(options);
            else if (type === 'success') iziToast.success(options);
            else if (type === 'warning') iziToast.warning(options);
            else if (type === 'error')   iziToast.error(options);
            else                         iziToast.show(options);
        })();
        @endforeach
    });
</script>

{{ session()->forget('toasts') }}
