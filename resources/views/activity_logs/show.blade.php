@extends('master.app')

@section('content')
    <div class="top_title">
        @include('master.breadcrumb', [
            'title' => 'Activity Details',
            'icon' => 'bi bi-clock-history',
            'sub_title' => [
                'Activity Logs' => route('admin.activity-logs.index'),
                'Details' => '',
            ],
        ])
    </div>

    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title mb-4">Activity Information</h5>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Description:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $activity->description ?? 'N/A' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>User:</strong>
                        </div>
                        <div class="col-md-9">
                            @if($activity->causer)
                                <div class="d-flex align-items-center">
                                    <img width="40" height="40" class="rounded-circle"
                                        src="{{ $activity->causer->avatar ? cloudflareImage($activity->causer->avatar, 40) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 40) }}"
                                        alt="Avatar">
                                    <div class="ms-3">
                                        <div>{{ $activity->causer->name ?? '' }}</div>
                                        <div class="text-muted small">{{ $activity->causer->email ?? '' }}</div>
                                    </div>
                                </div>
                            @else
                                <span class="text-muted">System</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Subject Type:</strong>
                        </div>
                        <div class="col-md-9">
                            @if($activity->subject_type)
                                <span class="badge bg-info">{{ class_basename($activity->subject_type) }}</span>
                            @else
                                <span class="text-muted">-</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Event:</strong>
                        </div>
                        <div class="col-md-9">
                            @if($activity->event)
                                @if($activity->event == 'created')
                                    <span class="badge bg-success">Created</span>
                                @elseif($activity->event == 'updated')
                                    <span class="badge bg-primary">Updated</span>
                                @elseif($activity->event == 'deleted')
                                    <span class="badge bg-danger">Deleted</span>
                                @else
                                    <span class="badge bg-secondary">{{ ucfirst($activity->event) }}</span>
                                @endif
                            @else
                                <span class="text-muted">Manual Log</span>
                            @endif
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Log Name:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $activity->log_name ?? 'default' }}
                        </div>
                    </div>

                    <div class="row mb-3">
                        <div class="col-md-3">
                            <strong>Date & Time:</strong>
                        </div>
                        <div class="col-md-9">
                            {{ $activity->created_at ? $activity->created_at->format('d M Y h:i A') : 'N/A' }}
                            <span class="text-muted">({{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }})</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

