<ul class="list-group">
    @foreach ($productColors as $color)
    <li class="list-group-item" onclick="setColor(event, {{$color['id']}}, '{{$color['name']}}', '{{$color['code']}}')">{{$color['name']}} ({{$color['code']}})</li>
    @endforeach
</ul>
