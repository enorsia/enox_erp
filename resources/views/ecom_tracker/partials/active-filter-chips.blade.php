@props(['chips' => []])

@if (count($chips) > 0)
    <div class="flex items-center flex-wrap gap-2 mb-3 px-0.5">
        <span class="text-[11px] text-slate-400 dark:text-slate-500 font-medium">Active:</span>
        @foreach ($chips as $chip)
            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[11px] font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-400 border border-amber-100 dark:border-amber-800/50">
                {{ $chip['label'] }}
                @if (! empty($chip['remove_url']))
                    <a href="{{ $chip['remove_url'] }}" class="opacity-60 hover:opacity-100 transition-opacity" aria-label="Remove filter">
                        <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                @endif
            </span>
        @endforeach
    </div>
@endif
