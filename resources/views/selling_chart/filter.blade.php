<form method="get" action="{{ route('admin.selling_chart.index') }}">
    <div id="filterSection" class="rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800">
        <div class="p-4">
            <div class="space-y-4">
                <div class="flex items-center justify-between border-b border-slate-200 dark:border-slate-700 pb-3">
                    <h4 class="mb-0 text-[15px] font-semibold text-slate-800 dark:text-slate-100 flex items-center gap-2">
                        <i class="bi bi-sliders"></i>Filter
                    </h4>
                    <button type="button"
                        class="advance-btn inline-flex items-center justify-center w-9 h-9 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-700 text-slate-500 dark:text-slate-300 hover:text-accent-500 transition-colors"
                        title="Advance Search" data-bs-toggle="collapse" href="#collapseAdvance" role="button"
                        aria-expanded="false" aria-controls="collapseAdvance">
                        <iconify-icon icon="solar:card-search-broken" class="text-[20px]"></iconify-icon>
                    </button>
                </div>
                <div>
                    <input type="hidden" name="advance_search" id="advance_search"
                        value="{{ request('advance_search', 0) }}">
                    <div class="advance-search collapse {{ request('advance_search') ? 'show' : '' }}"
                        id="collapseAdvance">
                        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-3">
                            <div>
                                <select id="department_select" name="department_id"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select Department">
                                    <option value="">Select Department</option>
                                    @foreach ($departments as $department)
                                        <option value="{{ $department->id }}"
                                            {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                            {{ $department->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select id="product_category" name="product_category_id"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select a Product Category">
                                    <option value="">Select a Product Category</option>
                                    @if (request('department_id'))
                                        @foreach ($selling_chart_cats->where('lookup_id', request('department_id')) as $selling_chart_cat)
                                            <option value="{{ $selling_chart_cat->id }}"
                                                {{ request('product_category_id') == $selling_chart_cat->id ? 'selected' : '' }}>
                                                {{ $selling_chart_cat->name }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                            <div>
                                <select id="product_mini_category" name="mini_category"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select Mini Category">
                                    <option value="">Select Mini Category</option>
                                    @foreach ($selling_chart_types as $selling_chart_type)
                                        <option value="{{ $selling_chart_type->id }}"
                                            {{ request('mini_category') == $selling_chart_type->id ? 'selected' : '' }}>
                                            {{ $selling_chart_type->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select id="season_id" name="season_id"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select Season">
                                    <option value="">Select Season</option>
                                    @foreach ($seasons as $season)
                                        <option value="{{ $season->id }}"
                                            {{ request('season_id') == $season->id ? 'selected' : '' }}>
                                            {{ $season->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select id="Season_Phase" name="season_phase_id"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select Season Phase">
                                    <option value="">Select Season Phase</option>
                                    @foreach ($seasons_phases as $seasons_phase)
                                        <option value="{{ $seasons_phase->id }}"
                                            {{ request('season_phase_id') == $seasons_phase->id ? 'selected' : '' }}>
                                            {{ $seasons_phase->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select id="Repeat_Order" name="initial_repeat_id"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select Initial/Repeat Order">
                                    <option value="">Select Initial/ Repeat Order</option>
                                    @foreach ($initialRepeats as $initialRepeat)
                                        <option value="{{ $initialRepeat->id }}"
                                            {{ $initialRepeat->id == request('initial_repeat_id') ? 'selected' : '' }}>
                                            {{ $initialRepeat->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <select id="fabrication" name="fabrication_id"
                                    class="tom-select w-full text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-slate-50 dark:bg-slate-700 text-slate-700 dark:text-slate-200"
                                    data-choices data-placeholder="Select a fabrication">
                                    <option value="">Select a fabrication</option>
                                    @foreach ($fabrics as $fabric)
                                        <option {{ request('fabrication_id') == $fabric->id ? 'selected' : '' }}
                                            value="{{ $fabric->id }}">{{ $fabric->name }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-end">
                    <div class="md:col-span-5 lg:col-span-4">
                        <input
                            class="w-full px-3 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 placeholder-slate-400"
                            id="search_id" type="text" name="name" placeholder="Search here.." value="{{ request('name') }}">
                    </div>
                    <div class="md:col-span-7 lg:col-span-8 flex items-center justify-end gap-2 flex-wrap">
                        <a href="{{ route('admin.selling_chart.index') }}"
                            class="inline-flex items-center gap-1 px-3 py-2 text-[13px] rounded-lg border border-rose-200 bg-rose-50 text-rose-600 hover:bg-rose-100 transition-colors">
                            <i class="bi bi-arrow-clockwise ms-0"></i> Reset
                        </a>
                        <button type="submit"
                            class="inline-flex items-center gap-1 px-3 py-2 text-[13px] rounded-lg bg-accent-400 hover:bg-accent-600 text-white transition-colors">
                            <i class="fa fa-filter ms-0" aria-hidden="true"></i> Search
                        </button>
                    </div>
                    <div class="md:col-span-12">
                        <div class="pt-1 flex items-center gap-2 flex-wrap" id="filter_dropdown">
                            @can('general.chart.bulk_edit')
                                <button type="submit" value="bulkEdit" name="action"
                                    class="inline-flex items-center gap-1 px-3 py-1.5 text-[12px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                                    <i class="bi bi-pencil-square ms-0"></i> Bulk Edit
                                </button>
                            @endcan
                            @can('general.chart.export')
                                <button type="submit" class="export_button inline-flex items-center gap-1 px-3 py-1.5 text-[12px] rounded-lg bg-blue-500 hover:bg-blue-600 text-white transition-colors" value="excel"
                                    name="action">
                                    <i class="bi bi-download"></i> Export Excel
                                </button>
                            @endcan
                            @can('general.chart.export')
                                <button type="submit" class="export_button inline-flex items-center gap-1 px-3 py-1.5 text-[12px] rounded-lg bg-rose-500 hover:bg-rose-600 text-white transition-colors" value="mismatch_excel"
                                    name="action">
                                    <i class="bi bi-download"></i> Price Mismatch RPT
                                </button>
                            @endcan
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
