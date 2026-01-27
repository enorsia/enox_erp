@extends('master.app')

@section('content')
    <div class="top_title">
       @include('master.breadcrumb', [
            'title' => 'Febrication',
            'icon' => '',
            'sub_title' => [
                'Selling Chart ' => '',
                'Febrication' => route('admin.selling_chart.fabrication.index'),
            ],
        ])
        <div>
            <a href="{{ route('admin.selling_chart.fabrication.create') }}" class="btn btn-outline-secondary">
                Create <span><i class="bi bi-plus-lg me-0"></i></span>
            </a>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.selling_chart.fabrication.index') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        </div>
                    </div>

                    <div class="col-12 col-md-2">
                        <div class="form-group mb-3 mb-md-0 new_select_field new_same_item d-flex flex-wrap">
                            <input type="text" name="name" id="name" class="form-control" placeholder="Search by name" value="{{request('name')}}" />
                        </div>
                    </div>
                    <div class="col-12 col-md-2">
                        <select name="status" class="form-select data-choices">
                            <option value="">Status</option>
                            <option value="1" {{ request('status') === "1" ? 'selected' : '' }}>Active</option>
                            <option value="0" {{ request('status') === "0" ? 'selected' : '' }}>Inactive</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-8 text-end">
                        <div class="flex-center">
                            <a href="{{ route('admin.selling_chart.fabrication.index') }}"
                                class="btn btn-outline-secondary flex-center mx-1"><i class="bi bi-arrow-clockwise ms-0"></i> Reset</a>
                            <button type="submit" class="btn btn-primary mx-1"><i class="fa fa-filter ms-0"
                                    aria-hidden="true"></i>
                                Search</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </form>
    <div class="card shadow-sm mt-3" style="overflow: hidden;">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-centered">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th width="50">#SL</th>
                            <th width="700">Name</th>
                            <th width="200" class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$lookup_names->isEmpty())
                            @foreach ($lookup_names as $lookup)
                                <tr>
                                    <td>{{ $start + $loop->index }}</td>
                                    <td>{{ $lookup->name }}</td>
                                    <td class="text-center">
                                        <div class="d-inline-flex text-center text-md-start text-nowrap">
                                            @if ($lookup->status == 1)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="9">
                                    <h5 class="text-danger text-center text-uppercase py-2 mb-0">No Result found.</h5>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            {!! optional($lookup_names)->links('master.custom-paginator') !!}
        </div>
    </div>

@endsection

@push('js')
    @include('selling_chart.expense.script')
@endpush
