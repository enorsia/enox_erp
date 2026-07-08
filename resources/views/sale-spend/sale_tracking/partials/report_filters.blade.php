@php
    $fi     = $filter_input ?? [];
    $period = $fi['period'] ?? 'this_month';
    $fromYM = $fi['from_year_month'] ?? now()->format('Y-m');
    $toYM   = $fi['to_year_month'] ?? now()->format('Y-m');
@endphp

<div class="an-card p-5" x-data="{
    period: '{{ $period }}',
    fromYM: '{{ $fromYM }}',
    toYM: '{{ $toYM }}',
    markCustomPeriod() {
        this.period = 'custom';
    }
}">
    <form method="get" action="{{ route('admin.ads-performance.report') }}">
        <input type="hidden" name="view" value="{{ $view }}" />

        <div class="flex flex-wrap items-end gap-3">
            {{-- Platform --}}
            <div class="w-full sm:w-52">
                <label class="f-label">Platform</label>
                <select name="sale_platform_id"
                        class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                        data-placeholder="All Platforms">
                    <option value="">All Platforms</option>
                    @foreach($salePlatforms as $p)
                        <option value="{{ $p['id'] }}" {{ (string) ($fi['sale_platform_id'] ?? '') === (string) $p['id'] ? 'selected' : '' }}>
                            {!! $p['label'] !!}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Period (same as Sales Report) --}}
            @include('sales.partials.report_period_fields', ['inForm' => true])

            <button type="submit"
                    class="w-full sm:w-auto px-5 py-2 bg-accent-400 hover:bg-accent-600 text-white text-sm font-semibold rounded-lg transition-colors">
                Apply Filters
            </button>
            <a href="{{ $reset_filters_url }}"
               class="w-full sm:w-auto px-5 py-2 border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors text-center">
                Reset
            </a>
        </div>
    </form>
</div>
