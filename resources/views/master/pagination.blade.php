{{--
    Reusable Bootstrap-style windowed paginator.
    Usage: @include('master.pagination', ['paginator' => $variable])
--}}
@if ($paginator->hasPages())
    @php
        $current  = $paginator->currentPage();
        $last     = $paginator->lastPage();
        $delta    = 2; // pages on each side of current

        // Build the set of page numbers to show
        $range = collect();
        // Always first 2
        for ($i = 1; $i <= min(2, $last); $i++) $range->push($i);
        // Window around current
        for ($i = max(1, $current - $delta); $i <= min($last, $current + $delta); $i++) $range->push($i);
        // Always last 2
        for ($i = max(1, $last - 1); $i <= $last; $i++) $range->push($i);

        $range = $range->unique()->sort()->values();

        // Build display items: insert '...' gaps
        $items = [];
        $prev  = null;
        foreach ($range as $page) {
            if ($prev !== null && $page - $prev > 1) {
                $items[] = '...';
            }
            $items[] = $page;
            $prev = $page;
        }
    @endphp

    <div class="mt-5 flex justify-center px-2">
        <nav aria-label="Pagination" class="flex flex-wrap items-center justify-center gap-1">

            {{-- Prev --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[13px] font-medium text-slate-300 dark:text-slate-600 cursor-not-allowed select-none rounded-lg">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
                    <span class="hidden xs:inline">Prev</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[13px] font-medium text-slate-600 dark:text-slate-300 hover:text-accent-500 dark:hover:text-accent-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
                    <span class="hidden xs:inline">Prev</span>
                </a>
            @endif

            {{-- Page numbers with ellipsis --}}
            @foreach ($items as $item)
                @if ($item === '...')
                    <span class="w-8 h-8 flex items-center justify-center text-[13px] text-slate-400 dark:text-slate-500 select-none">…</span>
                @elseif ($item == $current)
                    <span aria-current="page"
                          class="w-8 h-8 flex items-center justify-center rounded-lg bg-accent-400 text-white text-[13px] font-semibold shadow shadow-accent-400/30">{{ $item }}</span>
                @else
                    <a href="{{ $paginator->url($item) }}"
                       class="w-8 h-8 flex items-center justify-center rounded-lg text-[13px] font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-accent-500 dark:hover:text-accent-400 transition-colors">{{ $item }}</a>
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[13px] font-medium text-slate-600 dark:text-slate-300 hover:text-accent-500 dark:hover:text-accent-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <span class="hidden xs:inline">Next</span>
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center gap-1 px-2.5 py-1.5 text-[13px] font-medium text-slate-300 dark:text-slate-600 cursor-not-allowed select-none rounded-lg">
                    <span class="hidden xs:inline">Next</span>
                    <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif

        </nav>
    </div>
@endif

