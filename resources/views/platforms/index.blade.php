@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Platforms',
            'icon' => '',
            'sub_title' => [
                'Enox ERP Platforms' => '',
            ],
        ])
        {{-- @can('settings.platforms.create')
            <div>
                <a href="{{ route('admin.platforms.create') }}" class="btn btn-outline-secondary">
                    Create <span><i class="bi bi-plus-lg me-0"></i></span>
                </a>
            </div>
        @endcan --}}
    </div>
    <form method="GET" action="{{ route('admin.platforms.index') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec border-bottom">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group mb-3 mb-md-0 new_select_field new_same_item d-flex flex-wrap">
                            <input type="text" name="q" id="q" class="form-control"
                                placeholder="Search here...." value="{{ request('q') }}" />
                        </div>
                    </div>
                    <div class="col-12 col-md-8 text-end mt-2 mt-md-0">
                        <div class="flex-center">
                            <a href="{{ route('admin.platforms.index') }}"
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
                            <th width="10">#SL</th>
                            <th width="15">Platform</th>
                            <th width="6">Min Profit</th>
                            <th width="6">Shipping</th>
                            <th width="150">Create / Update</th>
                            <th width="300">Note</th>
                            <th width="30" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$platforms->isEmpty())
                            @foreach ($platforms as $data)
                                <tr>
                                    <td>{{ $start + $loop->index }}</td>
                                    <td>{{ $data->name }}</td>
                                    <td>@price($data->min_profit)</td>
                                    <td>@price($data->shipping_charge)</td>
                                    <td>
                                        CA: {{ $data->created_at }} <br />
                                        UA: {{ $data->updated_at }}
                                    </td>
                                    <td>{{ $data->note }}</td>
                                    <td class="text-center">
                                        <div class="d-inline-flex text-center text-md-start text-nowrap">
                                            @can('settings.platforms.edit')
                                                <a class="btn btn-soft-primary btn-sm mx-1" title="Edit"
                                                    href="{{ route('admin.platforms.edit', $data->id) }}">
                                                    <iconify-icon icon="solar:pen-2-broken" class="fs-18"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('settings.platforms.delete')
                                                <button class="btn btn-soft-danger btn-sm delete-btn mx-1" type="button"
                                                    onclick="deleteData({{ $data->id }})" data-id="{{ $data->id }}">
                                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                        class="fs-18 delete-icon"></iconify-icon>
                                                </button>
                                                <form id="delete-form-{{ $data->id }}" method="POST"
                                                    action="{{ route('admin.platforms.destroy', $data->id) }}"
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
            {!! $platforms->links('master.custom-paginator') !!}
        </div>
    </div>

@endsection

@push('js')
    @include('selling_chart.expense.script')
@endpush
