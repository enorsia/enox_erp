{{--
    Reusable windowed paginator — always single line from 320 px upward.
    Usage: @include('master.pagination', ['paginator' => $variable])
--}}
@if ($paginator->hasPages())
    @php
        $current = $paginator->currentPage();
        $last    = $paginator->lastPage();
        $delta   = 1; // pages on each side of current

        // Build page set: first, window around current, last
        $range = collect();
        $range->push(1);
        for ($i = max(2, $current - $delta); $i <= min($last - 1, $current + $delta); $i++) {
            $range->push($i);
        }
        if ($last > 1) $range->push($last);

        $range = $range->unique()->sort()->values();

        // Insert '…' gaps
        $items = [];
        $prev  = null;
        foreach ($range as $page) {
            if ($prev !== null && $page - $prev > 1) {
                $items[] = '...';
            }
            $items[] = $page;
            $prev    = $page;
        }
    @endphp

    <div class="mt-5 flex justify-center">
        <nav aria-label="Pagination"
             class="flex flex-nowrap items-center justify-center gap-0.5">

            {{-- Prev --}}
            @if ($paginator->onFirstPage())
                <span class="inline-flex items-center gap-1 px-2 py-1 text-[12px] font-medium text-slate-300 dark:text-slate-600 cursor-not-allowed select-none rounded-lg">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
                    <span class="hidden sm:inline">Prev</span>
                </span>
            @else
                <a href="{{ $paginator->previousPageUrl() }}"
                   class="inline-flex items-center gap-1 px-2 py-1 text-[12px] font-medium text-slate-600 dark:text-slate-300 hover:text-accent-500 dark:hover:text-accent-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15 19l-7-7 7-7"/></svg>
                    <span class="hidden sm:inline">Prev</span>
                </a>
            @endif

            {{-- Page numbers with ellipsis --}}
            @foreach ($items as $item)
                @if ($item === '...')
                    <span class="w-7 h-7 flex items-center justify-center text-[12px] text-slate-400 dark:text-slate-500 select-none">…</span>
                @elseif ($item == $current)
                    <span aria-current="page"
                          class="w-7 h-7 flex items-center justify-center rounded-lg bg-accent-400 text-white text-[12px] font-semibold shadow shadow-accent-400/30 shrink-0">{{ $item }}</span>
                @else
                    <a href="{{ $paginator->url($item) }}"
                       class="w-7 h-7 flex items-center justify-center rounded-lg text-[12px] font-medium text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-slate-700 hover:text-accent-500 dark:hover:text-accent-400 transition-colors shrink-0">{{ $item }}</a>
                @endif
            @endforeach

            {{-- Next --}}
            @if ($paginator->hasMorePages())
                <a href="{{ $paginator->nextPageUrl() }}"
                   class="inline-flex items-center gap-1 px-2 py-1 text-[12px] font-medium text-slate-600 dark:text-slate-300 hover:text-accent-500 dark:hover:text-accent-400 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors">
                    <span class="hidden sm:inline">Next</span>
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                </a>
            @else
                <span class="inline-flex items-center gap-1 px-2 py-1 text-[12px] font-medium text-slate-300 dark:text-slate-600 cursor-not-allowed select-none rounded-lg">
                    <span class="hidden sm:inline">Next</span>
                    <svg class="w-3.5 h-3.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                </span>
            @endif

        </nav>
    </div>
@endif

