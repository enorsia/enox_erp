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
    <p class="sec-heading mb-4">Filter Report</p>

    <form method="get" action="{{ route('admin.ads-performance.report') }}" class="space-y-5">
        <input type="hidden" name="view" value="{{ $view }}" />

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-5 items-end">
            {{-- Platform --}}
            <div class="lg:col-span-4">
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
            <div class="lg:col-span-8 flex flex-wrap items-end gap-3">
                @include('sales.partials.report_period_fields', ['inForm' => true])
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-slate-100 dark:border-slate-700">
            <button type="submit"
                    class="px-5 py-2 bg-accent-400 hover:bg-accent-600 text-white text-sm font-semibold rounded-lg transition-colors">
                Apply Filters
            </button>
            <a href="{{ $reset_filters_url }}"
               class="px-5 py-2 border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-400 text-sm font-medium hover:bg-slate-100 dark:hover:bg-slate-600 transition-colors">
                Reset
            </a>
        </div>
    </form>
</div>
