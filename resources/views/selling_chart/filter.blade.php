<form method="get" action="{{ route('admin.selling_chart.index') }}">
    <div class="card" id="filterSection">
        <div class="card-body">
            <div class="row">
                <div class="col-12">
                    <div class="filter_close_sec border-bottom d-flex align-items-center justify-content-between">
                        <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        <button type="button" class="btn btn-outline-secondary" data-bs-toggle="collapse" href="#collapseExample" role="button" aria-expanded="false" aria-controls="collapseExample">Advance Filters</button>
                    </div>
                </div>
                <div class="col-12">
                    <input type="hidden" name="advance_search" id="advance_search" value="{{ request('advance_search', 0) }}">
                    <div class="advance-search collapse {{ request('advance_search') ? 'show' : '' }}" id="collapseExample">
                        <div class="row">
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="form-group mb-2 new_select_field new_same_item">
                                    <select id="department_select" name="department_id" class=" form-control" data-choices>
                                        <option value="">Select Department</option>
                                        @foreach ($departments as $department)
                                            <option value="{{ $department->id }}"
                                                {{ request('department_id') == $department->id ? 'selected' : '' }}>
                                                {{ $department->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="form-group mb-2 new_select_field new_same_item">
                                    <select id="product_category" name="product_category_id"
                                        class=" form-control" data-choices>
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
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="position-relative form-group mb-2 new_search">
                                    <div class="new_select_field new_same_item d-flex flex-wrap">
                                        <select id="product_mini_category" name="mini_category"
                                            class=" form-control" data-choices>
                                            <option value="">Select Mini Category</option>
                                            @foreach ($selling_chart_types as $selling_chart_type)
                                                <option value="{{ $selling_chart_type->id }}"
                                                    {{ request('mini_category') == $selling_chart_type->id ? 'selected' : '' }}>
                                                    {{ $selling_chart_type->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="form-group mb-2 new_select_field new_same_item">
                                    <select id="season_id" name="season_id" class=" form-control" data-choices>
                                        <option value="">Select Season</option>
                                        @foreach ($seasons as $season)
                                            <option value="{{ $season->id }}"
                                                {{ request('season_id') == $season->id ? 'selected' : '' }}>
                                                {{ $season->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="form-group mb-2 new_select_field new_same_item">
                                    <select id="Season_Phase" name="season_phase_id" class=" form-control" data-choices>
                                        <option value="">Select Season Phase</option>
                                        @foreach ($seasons_phases as $seasons_phase)
                                            <option value="{{ $seasons_phase->id }}"
                                                {{ request('season_phase_id') == $seasons_phase->id ? 'selected' : '' }}>
                                                {{ $seasons_phase->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="form-group mb-2 new_select_field new_same_item">
                                    <select id="Repeat_Order" name="initial_repeat_id" class="select2 form-control" data-choices>
                                        <option value="">Select Initial/ Repeat Order</option>
                                        @foreach ($initialRepeats as $initialRepeat)
                                            <option value="{{ $initialRepeat->id }}"
                                                {{ $initialRepeat->id == request('initial_repeat_id') ? 'selected' : '' }}>
                                                {{ $initialRepeat->name }}
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="col-12 col-md-6 col-xl-3 ">
                                <div class="new_select_field new_same_item d-flex flex-wrap">
                                    <select id="fabrication" name="fabrication_id" class="select2 form-control" data-choices>
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
                </div>
                <div class="col-12 col-md-4">
                    <div class="form-group new_search new_same_item ">
                        <input class="form-control" id="search_id" type="text" name="name"
                            placeholder="Search here.. " value="{{ request('name') }}">
                    </div>
                </div>
                <div class="col-12 col-md-8 text-end">
                    <div class="flex-center">
                        <a href="{{ route('admin.selling_chart.index') }}"
                            class="btn btn-outline-danger flex-center mx-1"><i
                                class="bi bi-arrow-clockwise ms-0"></i> Reset</a>
                        <button type="submit" class="btn btn-primary mx-1"><i class="fa fa-filter ms-0"
                                aria-hidden="true"></i>
                            Search</button>
                    </div>
                </div>
                <div class="col-12">
                    <div class="mt-2" id="filter_dropdown">
                        <button type="submit" value="bulkEdit" name="action" class="btn btn-soft-secondary me-2"><i
                                class="bi bi-pencil-square ms-0"></i>
                            Bulk Edit</button>
                        <button type="submit" class="btn btn-soft-info export_button" value="excel" name="action"><i
                                class="bi bi-download"></i> Export Excel</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>
