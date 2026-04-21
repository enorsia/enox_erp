@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Expense',
            'icon' => '',
            'sub_title' => [
                'Manage Selling Chart ' => '',
                'Selling Chart Expense' => route('admin.selling_chart.expense.index'),
            ],
        ])
        @can('general.expense.create')
            <div>
                <a href="{{ route('admin.selling_chart.expense.create') }}" class="btn btn-outline-secondary">
                    Create <span><i class="bi bi-plus-lg me-0"></i></span>
                </a>
            </div>
        @endcan
    </div>
    <form method="GET" action="{{ route('admin.selling_chart.expense.index') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec border-bottom">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group new_select_field new_same_item d-flex flex-wrap">
                            <select name="year" id="year" class="form-control" data-choices>
                                <option value="">Select Year</option>
                                @for ($i = 2020; $i <= 2030; $i++)
                                    <option value="{{ $i }}" {{ request('year') == $i ? 'selected' : '' }}>
                                        {{ $i }}</option>
                                @endfor
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-8 text-end mt-2 mt-md-0">
                        <div class="flex-center">
                            <a href="{{ route('admin.selling_chart.expense.index') }}"
                                class="btn btn-outline-danger flex-center mx-1"><i class="bi bi-arrow-clockwise ms-0"></i>
                                Reset</a>
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
        <div class="card-body px-0 pt-0">
            <div class="table-responsive">
                <table class="table align-middle mb-0 table-hover table-centered">
                    <thead class="bg-light-subtle">
                        <tr>
                            <th>#SL</th>
                            <th>Year</th>
                            <th>conversion rate</th>
                            <th>commercial expense</th>
                            <th>enorsia expense bd</th>
                            <th>enorsia expense uk</th>
                            <th>shipping cost</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$expenses->isEmpty())
                            @foreach ($expenses as $data)
                                <tr>
                                    <td>{{ $start + $loop->index }}</td>
                                    <td>{{ $data->year }}</td>
                                    <td>£ {{ $data->conversion_rate }}</td>
                                    <td>£ {{ $data->commercial_expense }}</td>
                                    <td>£ {{ $data->enorsia_expense_bd }}</td>
                                    <td>£ {{ $data->enorsia_expense_uk }}</td>
                                    <td>£ {{ $data->shipping_cost }}</td>
                                    <td>
                                        @if ($data->status == 1)
                                            Active
                                        @else
                                            Inactive
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-inline-flex text-center text-md-start text-nowrap">
                                            @can('general.expense.edit')
                                                <a class="btn btn-soft-primary btn-sm mx-1" title="Edit"
                                                    href="{{ route('admin.selling_chart.expense.edit', $data->id) }}">
                                                    <iconify-icon icon="solar:pen-2-broken" class="fs-18"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('general.expense.delete')
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
                                            @endcan
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
            {!! $expenses->links('master.custom-paginator') !!}
        </div>
    </div>

@endsection

@push('js')
    @include('selling_chart.expense.script')
@endpush
