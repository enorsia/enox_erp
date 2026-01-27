<form method="get" action="{{ route('admin.selling_chart.index') }}">
    <div class="card-dark pb-2" id="filterSection">
        <div class="row">
            <div class="col-12">
                <div class="filter_close_sec">
                    <h6><i class="bi bi-sliders"></i>Filter</h6>
                    <i class="bi bi-x filterCloseBtn" style="cursor: pointer"></i>
                </div>
            </div>
            <div class="col-12  text-end">
                <div class="form-group mb-2 flex-center flex-wrap">
                    <div class="filter_button new_same_item m-2">
                        <button type="submit" value="filter" name="action" class="btn"><i class="fa fa-filter ms-0"
                                aria-hidden="true"></i>
                            Filter</button>
                    </div>

                    <div class="reset_button new_same_item ml-2 mt-2 mb-2">
                        <a href="{{ route('admin.selling_chart.index') }}" class="btn flex-center"><i
                                class="bi bi-arrow-clockwise ms-0"></i>
                            Reset</a>
                    </div>
                    <div class="dropdown ctm-dm d-inline-block ml-3 mt-2 mb-2"  id="filter_dropdown">
                        <a class="dot-btn" href="#" role="button" data-bs-toggle="dropdown"
                            aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                        </a>
                        <ul class="dropdown-menu">
                            <li class="dropdown-item" class="product_copy_btn">
                                @can('admin.selling_chart.bulkedit')
                                    <div class="reset_button new_same_item m-2">
                                        <button type="submit" value="bulkEdit" name="action" class="btn"><i
                                                class="bi bi-pencil-square ms-0"></i>
                                            Bulk Edit</button>
                                    </div>
                                @endcan
                            </li>
                            <li class="dropdown-item" class="product_copy_btn">
                                @can('admin.selling_chart.excel_export')
                                    <div class="new_export_button export_import_button m-2">
                                        <button type="submit" class="btn export_button" value="excel" name="action"><i
                                                class="bi bi-download"></i> Export Excel</button>
                                    </div>
                                @endcan
                            </li>
                        </ul>
                    </div>
                    {{-- <div class="reset_button new_same_item m-2">
                        <a href="{{ route('admin.selling_chart.bulk.edit', [
                            'name' => request('name'),
                            'department_id' => request('department_id'),
                            'season_id' => request('season_id'),
                            'season_phase_id' => request('season_phase_id'),
                            'initial_repeat_id' => request('initial_repeat_id'),
                            'product_category_id' => request('product_category_id'),
                            'mini_category' => request('mini_category'),
                        ]) }}"
                            class="btn flex-center"><i class="bi bi-pencil-square ms-0"></i>
                            Edit</a>
                    </div> --}}
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3 ">
                <div class="form-group new_search new_same_item ">
                    <label for="search_id">Search
                        <span class="filter_info" data-bs-toggle="tooltip" data-bs-placement="top"
                            data-bs-custom-class="custom-tooltip"
                            data-bs-title="Search by Product Launch Month, Product Code, Design No. & Ecom Sku"><i
                                class="bi bi-info-circle"></i></span></label>
                    <input class="form-control" id="search_id" type="text" name="name"
                        placeholder="Search here.. " value="{{ request('name') }}">
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-3 ">
                <div class="form-group new_select_field new_same_item">
                    <label for="department_select">Department</label>
                    <select id="department_select" name="department_id" class="js-states form-control">
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
                <div class="form-group new_select_field new_same_item">
                    <label for="product_category">Product Category</label>
                    <select id="product_category" name="product_category_id" class="js-states form-control">
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
                <div class="position-relative form-group new_search">
                    <div class="new_select_field new_same_item d-flex flex-wrap">
                        <label for="product_mini_category">Mini Category</label>
                        <select id="product_mini_category" name="mini_category" class="js-states form-control">
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
                <div class="form-group new_select_field new_same_item">
                    <label for="season_id">Season</label>
                    <select id="season_id" name="season_id" class="js-states form-control">
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
                <div class="form-group new_select_field new_same_item">
                    <label for="Season_Phase">Season Phase</label>
                    <select id="Season_Phase" name="season_phase_id" class="js-states form-control">
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
                <div class="form-group new_select_field new_same_item">
                    <label for="Repeat_Order">Initial/ Repeat Order</label>
                    <select id="Repeat_Order" name="initial_repeat_id" class="select2 form-control">
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
                    <label for="fabrication">Fabrication</label>
                    <select id="fabrication" name="fabrication_id" class="select2 form-control">
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
</form>
