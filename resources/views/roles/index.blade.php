@extends('master.app')

@section('content')
    <div class="top_title">
       @include('master.breadcrumb', [
            'title' => 'Roles',
        ])
        <div>
            <a href="{{ route('admin.roles.create') }}" class="btn btn-outline-secondary">
                Create <span><i class="bi bi-plus-lg me-0"></i></span>
            </a>
        </div>
    </div>
    <form method="GET" action="{{ route('admin.roles.index') }}">
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
                            <input type="text" name="search" id="search" class="form-control" placeholder="Search" value="{{request('search')}}" />
                        </div>
                    </div>
                    <div class="col-12 col-md-8 text-end">
                        <div class="flex-center">
                            <a href="{{ route('admin.roles.index') }}"
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
                            <th width="15">Name</th>
                            <th width="6">Permission</th>
                            <th width="30" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$roles->isEmpty())
                            @foreach ($roles as $role)
                                <tr>
                                    <td class="fw-semibold text-dark">{{ ucfirst($role->name) }}</td>

                                    <td>
                                        @php
                                            $countNested = $role->permissions->groupBy(function ($perm) {
                                                $parts = explode('.', $perm->name);
                                                $module = $parts[0] ?? 'Other';
                                                $model = $parts[1] ?? $module;
                                                return $module . '.' . $model;
                                            });
                                        @endphp

                                        @if ($countNested->isNotEmpty())
                                            <span class="badge bg-light-subtle text-dark">
                                                {{ $role->permissions->count() }} total
                                            </span>
                                            @foreach ($countNested as $key => $perms)
                                                <span class="badge bg-info-subtle text-info ms-1">
                                                    {{ $key }}: {{ $perms->count() }}
                                                </span>
                                            @endforeach
                                        @else
                                            <span class="text-muted">No Permissions</span>
                                        @endif
                                    </td>

                                    <td class="text-center">
                                        <div class="d-flex gap-2 justify-content-center">
                                            @can('authentication.role.show')
                                                 <a href="{{ route('admin.roles.show', $role->id) }}?page={{ request('page') }}"
                                                    class="btn btn-soft-info btn-sm" title="View">
                                                    <iconify-icon icon="solar:eye-bold" class="fs-18"></iconify-icon>
                                                </a>
                                            @endcan

                                            @can('authentication.role.edit')
                                                <a href="{{ route('admin.roles.edit', $role->id) }}?page={{ request('page') }}"
                                                    class="btn btn-soft-primary btn-sm" title="Edit">
                                                    <iconify-icon icon="solar:pen-2-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            @endcan

                                            @can('authentication.role.delete')
                                                <button type="button" class="btn btn-soft-danger btn-sm delete-btn"
                                                    data-role-id="{{ $role->id }}" title="Delete">
                                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                        class="align-middle fs-18 delete-icon"></iconify-icon>
                                                    <div class="spinner-border text-secondary spinner-border-sm ms-2 d-none delete-spinner"
                                                        role="status">
                                                        <span class="visually-hidden">Loading...</span>
                                                    </div>
                                                </button>

                                                <form id="delete-form-{{ $role->id }}"
                                                    action="{{ route('admin.roles.destroy', $role->id) }}?page={{ request('page') }}"
                                                    method="POST" class="d-none">
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
                                <td colspan="3">
                                    <h5 class="text-danger text-center text-uppercase py-2 mb-0">No Result found.</h5>
                                </td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>
            {!! $roles->links('master.custom-paginator') !!}
        </div>
    </div>

@endsection

@push('js')
    <script>
         $(function() {
            $('.delete-btn').on('click', function(e) {
                e.preventDefault();

                const $btn = $(this);
                const roleId = $btn.data('role-id');
                const form = $('#delete-form-' + roleId);

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You won't be able to revert this!",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, delete it!',
                    cancelButtonText: 'No, cancel!',
                    customClass: {
                        confirmButton: 'btn btn-primary w-xs me-2 mt-2',
                        cancelButton: 'btn btn-danger w-xs mt-2'
                    },
                    buttonsStyling: false
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $btn.prop('disabled', true);

                        $btn.find('.delete-icon').addClass('d-none');
                        $btn.find('.delete-spinner').removeClass('d-none');

                        form.submit();
                    }
                });
            });
        });
    </script>
@endpush
