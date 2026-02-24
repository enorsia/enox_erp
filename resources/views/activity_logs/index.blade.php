@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Activities',
            'icon' => 'bi bi-clock-history',
            'sub_title' => [
                'Activity Logs' => '',
            ],
        ])
    </div>

    <form method="get" action="{{ route('admin.activity-logs.index') }}">
        <div class="card" id="filterSection">
            <div class="card-body">
                <div class="row">
                    <div class="col-12">
                        <div class="filter_close_sec border-bottom">
                            <h4 class="mb-0"><i class="bi bi-sliders"></i>Filter</h4>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group new_search new_same_item mb-sm-0 mb-3">
                            <input class="form-control" type="text" name="search" placeholder="Search description"
                                value="{{ request('search') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group new_select_field new_same_item mb-sm-0 mb-3">
                            <select class="select2 form-control" name="user_id" data-choices>
                                <option value="">All Users</option>
                                @foreach ($users as $user)
                                    <option value="{{ $user->id }}"
                                        {{ request('user_id') == $user->id ? 'selected' : '' }}>
                                        {{ $user->name ?? '' }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="col-12 col-md-2">
                        <div class="form-group new_same_item mb-sm-0 mb-3">
                            <input class="form-control" type="date" name="date_from" placeholder="Date From"
                                value="{{ request('date_from') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-2">
                        <div class="form-group new_same_item mb-sm-0 mb-3">
                            <input class="form-control" type="date" name="date_to" placeholder="Date To"
                                value="{{ request('date_to') }}">
                        </div>
                    </div>
                    <div class="col-12 col-md-2 text-end mt-2 mt-md-0">
                        <div class="flex-center">
                            <a href="{{ route('admin.activity-logs.index') }}" class="btn btn-outline-danger flex-center mx-1">
                                <i class="bi bi-arrow-clockwise ms-0"></i> Reset
                            </a>
                            <button type="submit" class="btn btn-primary mx-1">
                                <i class="fa fa-filter ms-0" aria-hidden="true"></i> Search
                            </button>
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
                            <th>Description</th>
                            <th>User</th>
                            <th>Subject</th>
                            <th class="text-center">Date & Time</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @if (!$activities->isEmpty())
                            @foreach ($activities as $key => $activity)
                                @php
                                    $changedFields = 0;
                                    $hasOldValues = false;

                                    if ($activity->properties && $activity->properties->has('attributes')) {
                                        $changedFields = count($activity->properties->get('attributes', []));
                                    }

                                    if ($activity->properties && $activity->properties->has('old')) {
                                        $hasOldValues = true;
                                        $oldCount = count($activity->properties->get('old', []));
                                        if ($oldCount > 0) {
                                            $changedFields = $oldCount;
                                        }
                                    }
                                @endphp
                                <tr>
                                    <td class="text-center">{{ $start + $loop->index }}</td>
                                    <td>
                                        <div class="text-truncate" style="max-width: 300px;">
                                            {{ $activity->description ?? 'N/A' }}
                                        </div>
                                        @if($activity->event)
                                            <div class="mt-1">
                                                @if($activity->event == 'created')
                                                    <span class="badge bg-success">Created</span>
                                                @elseif($activity->event == 'updated')
                                                    <span class="badge bg-primary">Updated</span>
                                                @elseif($activity->event == 'deleted')
                                                    <span class="badge bg-danger">Deleted</span>
                                                @else
                                                    <span class="badge bg-secondary">{{ ucfirst($activity->event) }}</span>
                                                @endif
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($activity->causer)
                                            <div class="d-flex align-items-center">
                                                <div class="widget-content-left">
                                                    <img width="32" height="32" class="rounded-circle"
                                                        src="{{ $activity->causer->avatar ? cloudflareImage($activity->causer->avatar, 32) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 32) }}"
                                                        alt="Avatar">
                                                </div>
                                                <div class="ms-2">
                                                    <div class="widget-heading">{{ $activity->causer->name ?? '' }}</div>
                                                    <div class="widget-subheading text-muted small">{{ $activity->causer->email ?? '' }}</div>
                                                </div>
                                            </div>
                                        @else
                                            <span class="text-muted">System</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($activity->subject)
                                            <span class="badge bg-info">
                                                {{ class_basename($activity->subject_type) }}
                                            </span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <div>{{ $activity->created_at ? $activity->created_at->format('d M Y') : '' }}</div>
                                        <div class="text-muted small">{{ $activity->created_at ? $activity->created_at->format('h:i A') : '' }}</div>
                                    </td>
                                    <td class="text-center">
                                        @can('authentication.activity_logs.show')
                                            <a class="btn btn-soft-info btn-sm"
                                                href="{{ route('admin.activity-logs.show', $activity->id) }}">
                                                <iconify-icon icon="solar:eye-broken"
                                                    class="align-middle fs-18"></iconify-icon>
                                            </a>
                                        @endcan
                                    </td>
                                </tr>
                            @endforeach
                        @else
                            <tr>
                                <td colspan="7" class="text-center text-danger text-uppercase">No activity logs found.</td>
                            </tr>
                        @endif
                    </tbody>
                </table>
            </div>

            {!! $activities->links('master.custom-paginator') !!}
        </div>
    </div>
@endsection

@push('js')
@endpush

