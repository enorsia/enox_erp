@php
    $preserveParams = request()->except(['page', 'fragment']);
@endphp
@foreach ($preserveParams as $name => $value)
    @if (is_array($value))
        @foreach ($value as $item)
            <input type="hidden" name="{{ $name }}[]" value="{{ $item }}">
        @endforeach
    @elseif (filled($value))
        <input type="hidden" name="{{ $name }}" value="{{ $value }}">
    @endif
@endforeach
