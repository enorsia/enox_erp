@extends('layouts.app')

@section('title', 'Activity Details')

@section('content')
    <div class="max-w-4xl mx-auto px-5 py-6">

        <!-- PAGE HEADER -->
        <div class="flex items-start justify-between gap-4 mb-5 flex-wrap">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Activity Details</h1>
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5">
                    {{ $activity->created_at ? $activity->created_at->format('d M Y, h:i A') : 'N/A' }}
                    <span class="ml-1">({{ $activity->created_at ? $activity->created_at->diffForHumans() : '' }})</span>
                </p>
            </div>
            <a href="{{ route('admin.activity-logs.index') }}"
               class="action-btn border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Logs
            </a>
        </div>

        <!-- CONTENT GRID -->
        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            <!-- LEFT -->
            <div class="space-y-5">

                <!-- Activity Info -->
                <div class="info-card">
                    <div class="card-title-custom">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                        Activity Information
                    </div>

                    <div class="kv">
                        <p class="row-label">Description</p>
                        <p class="row-value">{{ $activity->description ?? 'N/A' }}</p>
                    </div>

                    <div class="kv">
                        <p class="row-label">Event</p>
                        <p class="row-value">
                            @if($activity->event)
                                @php
                                    $eventColor = match($activity->event) {
                                        'created' => 'badge-green',
                                        'updated' => 'badge-blue',
                                        'deleted' => 'badge-red',
                                        default   => 'badge-amber',
                                    };
                                @endphp
                                <span class="badge-custom {{ $eventColor }}">{{ ucfirst($activity->event) }}</span>
                            @else
                                <span class="text-slate-400 dark:text-slate-500 italic">Manual Log</span>
                            @endif
                        </p>
                    </div>

                    <div class="kv">
                        <p class="row-label">Log Name</p>
                        <p class="row-value">{{ $activity->log_name ?? 'default' }}</p>
                    </div>

                    <div class="kv">
                        <p class="row-label">Subject Type</p>
                        <p class="row-value">
                            @if($activity->subject_type)
                                <span class="badge-custom badge-blue">{{ class_basename($activity->subject_type) }}</span>
                            @else
                                <span class="text-slate-400 dark:text-slate-500">–</span>
                            @endif
                        </p>
                    </div>

                    <div class="kv">
                        <p class="row-label">Date &amp; Time</p>
                        <p class="row-value">{{ $activity->created_at ? $activity->created_at->format('d M Y, h:i A') : 'N/A' }}</p>
                    </div>
                </div>

                <!-- Properties -->
                @if($activity->properties && $activity->properties->isNotEmpty())
                    <div class="info-card">
                        <div class="card-title-custom">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                            Changed Properties
                        </div>

                        @if($activity->properties->has('old') && count($activity->properties->get('old', [])))
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">Before</p>
                            <div class="space-y-1 mb-4">
                                @foreach($activity->properties->get('old', []) as $key => $value)
                                    <div class="flex items-start gap-2 text-[12px]">
                                        <span class="text-slate-400 dark:text-slate-500 min-w-[120px] capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                        <span class="text-red-500 dark:text-red-400 font-mono break-all">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif

                        @if($activity->properties->has('attributes') && count($activity->properties->get('attributes', [])))
                            <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 mb-2">After</p>
                            <div class="space-y-1">
                                @foreach($activity->properties->get('attributes', []) as $key => $value)
                                    <div class="flex items-start gap-2 text-[12px]">
                                        <span class="text-slate-400 dark:text-slate-500 min-w-[120px] capitalize">{{ str_replace('_', ' ', $key) }}</span>
                                        <span class="text-accent-400 font-mono break-all">{{ is_array($value) ? json_encode($value) : $value }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- RIGHT -->
            <div class="space-y-5">

                <!-- Causer -->
                <div class="info-card">
                    <div class="card-title-custom">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                        Performed By
                    </div>

                    @if($activity->causer)
                        <div class="flex items-center gap-3 mb-4">
                            <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-100 dark:bg-slate-700 border border-slate-200 dark:border-slate-600 flex-shrink-0">
                                <img class="w-full h-full object-cover"
                                     src="{{ $activity->causer->avatar ? cloudflareImage($activity->causer->avatar, 40) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 40) }}"
                                     alt="Avatar">
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ $activity->causer->name ?? '' }}</p>
                                <p class="text-xs text-accent-400">{{ $activity->causer->email ?? '' }}</p>
                            </div>
                        </div>
                    @else
                        <p class="text-sm text-slate-400 dark:text-slate-500 italic">System action</p>
                    @endif
                </div>

                <!-- Quick Actions -->
                <div class="info-card">
                    <div class="card-title-custom">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                        Quick Links
                    </div>
                    <a href="{{ route('admin.activity-logs.index') }}"
                       class="w-full py-2.5 text-[13px] rounded-lg border border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium flex items-center justify-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                        All Activity Logs
                    </a>
                </div>
            </div>
        </div>
    </div>
@endsection

