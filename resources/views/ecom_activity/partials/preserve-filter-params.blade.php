@php
    use App\Support\EcomActivityFocus;

    $preserveParams = EcomActivityFocus::drawerPreserveQueryParams(request());
@endphp

@foreach ($preserveParams as $key => $value)
    <input type="hidden" name="{{ $key }}" value="{{ $value }}">
@endforeach
