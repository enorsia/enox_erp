@extends('backend.master')

@section('content')
    <div class="top_title">
        @include('backend.partials.breadcrumb', [
            'title' => 'Selling Chart Fabrication',
            'icon' => 'bi bi-graph-up-arrow',
            'sub_title' => [
                'Main' => '',
                'Manage Selling Chart ' => '',
                'Selling Chart Fabrication' => route('admin.selling_chart.fabrication.index'),
            ],
        ])
        <div>
            @include('backend.component-list.filter-toggle-button')
            @can('admin.selling_chart.fabrication.create')
                <a href="{{ route('admin.selling_chart.fabrication.create') }}" class="btn tlt-btn">
                    Create <span><i class="bi bi-plus-lg me-0"></i></span>
                </a>
            @endcan
        </div>
    </div>

    <form method="get" action="{{ route('admin.selling_chart.fabrication.index') }}">
        <div class="card-dark" id="filterSection">
            <div class="row">
                <div class="col-12">
                    <div class="filter_close_sec">
                        <h6><i class="bi bi-sliders"></i>Filter</h6>
                        <i class="bi bi-x filterCloseBtn" style="cursor: pointer"></i>
                    </div>
                </div>
                <div class="col-12 col-xl-4  text-end ">
                    <div class="flex-center flex-wrap">
                        <div class="filter_button new_same_item me-3">
                            <button type="submit" class="btn"><i class="fa fa-filter ms-0" aria-hidden="true"></i>
                                Filter</button>
                        </div>
                        <div class="reset_button new_same_item">
                            <a href="{{ route('admin.selling_chart.fabrication.index') }}" class="btn flex-center"><i
                                    class="bi bi-arrow-clockwise ms-0"></i> Reset</a>
                        </div>
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-4 ">
                    <div class="form-group new_search new_same_item mb-sm-0 mb-3">
                        <label for="search">Search</label>
                        <input class="form-control" type="text" name="name" id="search" placeholder="Search by name"
                            value="{{ request('name') }}">
                    </div>
                </div>
                <div class="col-12 col-md-6 col-xl-4 ">
                    <div class="form-group new_select_field new_same_item mb-sm-0 mb-3">
                        <label for="status">Status</label>
                        <select class="js-states form-control select2" name="status" id="status">
                            <option value="">Select status</option>
                            <option value="1" {{ request('status') == '1' ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') == '0' ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                </div>
            </div>

        </div>
    </form>

    <div class="new_table table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th class="text-center">#SL</th>
                    <th class="text-left">Name</th>
                    <th class="text-center">status</th>
                </tr>
            </thead>
            <tbody>
                @if (!$lookup_names->isEmpty())
                    @foreach ($lookup_names as $lookup_name)
                        <tr>
                            <td class="text-center">{{ $start + $loop->index }}</td>
                            <td>{{ $lookup_name->name }}</td>
                            <td class="text-center">
                                @if ($lookup_name->status == 1)
                                    @include('backend.component-list.active-icon', ['title' => 'Active'])
                                @else
                                    @include('backend.component-list.inactive-icon', [
                                        'title' => 'Inactive',
                                    ])
                                @endif
                            </td>
                        </tr>
                    @endforeach
                @else
                    <tr>
                        <td colspan="3">
                            <h5 class="text-danger text-center text-uppercase py-2 mb-0">No Result found.</h5>
                        </td>
                    </tr>
                @endif
            </tbody>
        </table>

        {!! $lookup_names->links('backend.partials.custom-paginator') !!}

    </div>
@endsection

@push('js')
@endpush
