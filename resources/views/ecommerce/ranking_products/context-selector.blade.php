<form method="GET"
      action="{{ $filterFormAction ?? route('admin.ecommerce.ranking-products.index') }}"
      id="ranking-products-context-form"
      class="etd-ranking-products-context px-4 sm:px-5 py-4 border-b border-slate-200/80 dark:border-slate-700/80 bg-slate-50/50 dark:bg-slate-900/20 etd-print-hide">
    @if (($search ?? '') !== '')
        <input type="hidden" name="search" value="{{ $search }}">
    @endif
    @php
        $allContextsUrl = route('admin.ecommerce.ranking-products.index');
        if (($search ?? '') !== '') {
            $allContextsUrl .= '?search='.rawurlencode((string) $search);
        }
    @endphp
    <div class="max-w-2xl min-w-0">
        <label for="ranking-products-context" class="block text-[13px] font-semibold text-slate-700 dark:text-slate-200 m-0">
            Where should this ranking apply?
        </label>
        <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 mb-2 leading-snug">
            Global, department, category, or micro-category listing.
        </p>
        <select name="context"
                id="ranking-products-context"
                class="w-full text-[13px] ranking-context-ts"
                data-all-contexts-url="{{ $allContextsUrl }}"
                data-placeholder="Select listing context…">
            <option value="">All listing contexts</option>
            @if (($contextKey ?? '') !== '' && ! collect($listingContexts ?? [])->contains(fn ($ctx) => ($ctx['key'] ?? '') === ($contextKey ?? '')))
                <option value="{{ $contextKey }}" selected>{{ $listingScopeLabel ?? $contextKey }}</option>
            @endif
            @foreach ($listingContexts ?? [] as $ctx)
                <option value="{{ $ctx['key'] }}" @selected(($contextKey ?? '') === $ctx['key'])>{{ $ctx['label'] }}</option>
            @endforeach
        </select>
        @if (! ($contextsLoaded ?? true))
            <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-2 mb-0">
                Could not load listing labels from Enorsia; you can still filter with a URL like
                <span class="font-mono text-[10px]">?context=category:4</span>.
            </p>
        @endif
    </div>
</form>
