<div class="top_title_body">
    @if (isset($icon) && $icon)
        <div class="top_title_icon">
            <i class="{{ $icon ?? '' }}"></i>
        </div>
    @endif
    <div class="top_title_body_left">
        <div class="top_title_text">
            <h3>{{ $title ?? '' }}</h3>
        </div>
        <div class="top_title_breadcrumb">
            @foreach ($sub_title ?? [] as $text => $url)
                @if ($url)
                    <span><a href="{{ $url }}">{{ $text }}</a></span>
                    @if (!$loop->last)
                        <span style="padding: 0 3px;">&gt;</span>
                    @endif
                @else
                    <span>{{ $text }}</span>
                    @if (!$loop->last)
                        <span style="padding: 0 3px;">&gt;</span>
                    @endif
                @endif
            @endforeach
        </div>
    </div>
</div>
