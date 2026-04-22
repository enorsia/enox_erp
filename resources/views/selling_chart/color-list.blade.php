<ul class="divide-y divide-slate-100 dark:divide-slate-700 rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 shadow-lg overflow-hidden max-h-48 overflow-y-auto z-50">
    @foreach ($productColors as $color)
        <li class="px-3 py-2 text-[12px] text-slate-700 dark:text-slate-200 hover:bg-accent-50 dark:hover:bg-accent-900/20 hover:text-accent-600 cursor-pointer transition-colors"
            onclick="setColor(event, {{ $color['id'] }}, '{{ $color['name'] }}', '{{ $color['code'] }}')">
            {{ $color['name'] }} ({{ $color['code'] }})
        </li>
    @endforeach
</ul>
