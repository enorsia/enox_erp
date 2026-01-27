@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Selling Chart Expense',
            'icon' => '',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Selling Chart Expense' => route('admin.selling_chart.expense.index'),
            ],
        ])
        <div>
            <a href="{{ route('admin.platforms.create') }}" class="btn btn-outline-secondary">
                Create <span><i class="bi bi-plus-lg me-0"></i></span>
            </a>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.selling_chart.expense.index') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group mb-3 mb-md-0 new_select_field new_same_item d-flex flex-wrap">
                            <select name="year" id="year" class="form-control select2">
                                <option value="">Select Year</option>
                                @for ($i = 2020; $i <= 2030; $i++)
                                    <option value="{{ $i }}" {{ request('year') == $i ? 'selected' : '' }}>
                                        {{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-8 text-end">
                        <div class="flex-center">
                            <a href="{{ route('admin.selling_chart.expense.index') }}"
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
                            <th>#SL</th>
                            <th>Platform</th>
                            <th>Created At</th>
                            <th>Updated At</th>
                            <th>Note</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$platforms->isEmpty())
                            @foreach ($platforms as $data)
                                <tr>
                                    <td>{{ $start + $loop->index }}</td>
                                    <td>{{ $data->name }}</td>
                                    <td>{{ $data->created_at }}</td>
                                    <td>{{ $data->updated_at }}</td>
                                    <td>{{ Str::limit($data->note, 20) }}</td>
                                    <td>
                                        <div class="d-inline-flex text-center text-md-start text-nowrap">
                                            <a class="btn btn-soft-primary btn-sm mx-1" title="Edit"
                                                href="{{ route('admin.selling_chart.expense.edit', $data->id) }}">
                                                <iconify-icon icon="solar:pen-2-broken" class="fs-18"></iconify-icon>
                                            </a>

                                            <button class="btn btn-soft-danger btn-sm delete-btn mx-1" type="button"
                                                onclick="deleteData({{ $data->id }})" data-id="{{ $data->id }}">
                                                <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                    class="fs-18 delete-icon"></iconify-icon>
                                            </button>
                                            <form id="delete-form-{{ $data->id }}" method="POST"
                                                action="{{ route('admin.selling_chart.expense.destroy', $data->id) }}"
                                                style="display: none;">
                                                @csrf
                                                @method('DELETE')
                                            </form>
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
            {!! $platforms->links('master.custom-paginator') !!}
        </div>
    </div>

@endsection

@push('js')
    @include('selling_chart.expense.script')
@endpush
