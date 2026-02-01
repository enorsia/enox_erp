@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Users',
            'icon' => 'bi bi-people',
            'sub_title' => [
                'Admin User' => '',
            ],
        ])
        @can('authentication.users.create')
            <div>
                <a href="{{ route('admin.users.create') }}" class="btn btn-outline-secondary rounded-pill px-3">
                    Create
                </a>
            </div>
        @endcan
    </div>

    <form method="get" action="{{ route('admin.users.index') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec border-bottom">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group new_search new_same_item mb-sm-0 mb-3">
                            <input class="form-control" type="text" name="search" placeholder="Search by name or email"
                                value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-4">
                        <div class="form-group new_select_field new_same_item mb-sm-0 mb-3">
                            <select class="select2 form-control" id="role_id" name="role_id" data-choices>
                                <option value="">Select Role</option>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->id }}"
                                        {{ request('role_id') == $role->id ? 'selected' : '' }}>
                                        {{ $role->name ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-4 text-end mt-2 mt-md-0">
                        <div class="flex-center">
                            <a href="{{ route('admin.users.index') }}" class="btn btn-outline-danger flex-center mx-1"><i
                                    class="bi bi-arrow-clockwise ms-0"></i>
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
                <table class="table table-hover table-centered">
                    <thead>
                        <tr>
                            <th class="text-center">#SL</th>
                            <th>Name</th>
                            <th>E-mail</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Joined At</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$users->isEmpty())
                            @foreach ($users as $key => $user)
                                <tr>
                                    <td class="text-center">{{ $start + $loop->index }}</td>
                                    <td>
                                        <div class="p-0 widget-content">
                                            <div class="d-flex align-items-center widget-content-wrapper">
                                                <div class="widget-content-left">
                                                    <div class="widget-content-left">
                                                        <img width="40" height="40" class="rounded-circle"
                                                            src="{{ $user->avatar ? cloudflareImage($user->avatar, 40) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 40) }}"
                                                            alt="Avatar">
                                                    </div>
                                                </div>
                                                <div class="widget-content-left flex2 ms-2">
                                                    <div class="widget-heading">{{ $user->name ?? '' }}</div>
                                                    <div class="widget-subheading ">
                                                        @if ($user?->roles?->isNotEmpty())
                                                            <span class="badge bg-primary">
                                                                {{ strtoupper($user->roles->first()->name) }}
                                                            </span>
                                                        @else
                                                            <span class="badge bg-warning">
                                                                No role found 😢
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>{{ $user->email ?? '' }}</td>
                                    <td class="text-center">
                                        @if ($user->status)
                                            <span class="badge bg-success">
                                                Active
                                            </span>
                                        @else
                                            <span class="badge bg-danger">
                                                Inactive
                                            </span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        {{ $user->created_at ? $user->created_at->diffForHumans() : '' }}
                                    </td>
                                    <td class="text-center">
                                        <div class="text-center d-md-inline-flex text-md-start">
                                            @can('authentication.users.show')
                                                <a class="btn btn-soft-success btn-sm mx-1"
                                                    href="{{ route('admin.users.show', $user->id) }}">
                                                    <iconify-icon icon="solar:eye-broken"
                                                        class="align-middle fs-18"></iconify-icon>
                                                </a>
                                            @endcan
                                            @can('authentication.users.edit')
                                                <a class="btn btn-soft-primary btn-sm mx-1"
                                                    href="{{ route('admin.users.edit', $user->id) }}">
                                                    <iconify-icon icon="solar:pen-2-broken" class="fs-18"></iconify-icon>
                                                </a>
                                            @endcan

                                            @can('authentication.users.delete')
                                                <button class="btn btn-soft-danger btn-sm" type="button"
                                                    onclick="deleteData({{ $user->id }})">
                                                    <iconify-icon icon="solar:trash-bin-minimalistic-2-broken"
                                                        class="fs-18 delete-icon"></iconify-icon>
                                                </button>
                                                <form id="delete-form-{{ $user->id }}" method="POST"
                                                    action="{{ route('admin.users.destroy', $user->id) }}"
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
                                <td colspan="6" class="text-center text-danger text-uppercase">No result found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {!! $users->links('master.custom-paginator') !!}
        </div>
    </div>
@endsection
@push('js')
@endpush
