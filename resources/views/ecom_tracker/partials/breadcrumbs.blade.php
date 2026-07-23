@props(['items' => []])

@if (count($items) > 0)
    <nav class="etd-breadcrumbs" aria-label="Breadcrumb">
        @foreach ($items as $index => $item)
            @if ($index > 0)
                <span class="etd-breadcrumbs-sep" aria-hidden="true">/</span>
            @endif
            @if (! empty($item['url']) && $index < count($items) - 1)
                <a href="{{ $item['url'] }}" class="etd-breadcrumbs-link">{{ $item['label'] }}</a>
            @else
                <span class="etd-breadcrumbs-current" @if($index === count($items) - 1) aria-current="page" @endif>{{ $item['label'] }}</span>
            @endif
        @endforeach
    </nav>
@endif
