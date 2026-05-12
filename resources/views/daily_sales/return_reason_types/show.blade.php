@extends('layouts.app')

@section('title', 'View Return Reason Type')

@section('content')
    <div id="return-reason-type-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">{{ $returnReasonType->name }}</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Return Reason Type Details</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            <!-- LEFT COLUMN -->
            <div class="space-y-5">

                <!-- ── Basic Information ── -->
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Basic Information
                    </div>

                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <label class="f-label">Name</label>
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-200">
                                {{ $returnReasonType->name }}
                            </div>
                        </div>

                        <!-- Slug -->
                        <div>
                            <label class="f-label">Slug</label>
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-200 font-mono text-sm">
                                {{ $returnReasonType->slug }}
                            </div>
                        </div>

                        <!-- Description -->
                        @if ($returnReasonType->description)
                            <div>
                                <label class="f-label">Description</label>
                                <div class="p-3 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-200 whitespace-pre-wrap">
                                    {{ $returnReasonType->description }}
                                </div>
                            </div>
                        @endif

                        <!-- Sort Order -->
                        <div>
                            <label class="f-label">Sort Order</label>
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-200">
                                {{ $returnReasonType->sort_order }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="space-y-5">

                <!-- ── Status & Metadata ── -->
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        Status & Info
                    </div>

                    <div class="space-y-4">
                        <!-- Active Status -->
                        <div>
                            <label class="f-label">Status</label>
                            <div class="flex items-center gap-2 mt-1">
                                @if ($returnReasonType->is_active)
                                    <span class="badge-custom badge-green">Active</span>
                                @else
                                    <span class="badge-custom badge-red">Inactive</span>
                                @endif
                            </div>
                        </div>

                        <!-- Created At -->
                        <div>
                            <label class="f-label">Created</label>
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-200 text-sm">
                                {{ $returnReasonType->created_at ? $returnReasonType->created_at->format('M d, Y H:i A') : 'N/A' }}
                            </div>
                        </div>

                        <!-- Updated At -->
                        <div>
                            <label class="f-label">Last Updated</label>
                            <div class="p-3 bg-slate-50 dark:bg-slate-700/50 border border-slate-200 dark:border-slate-600 rounded-lg text-slate-700 dark:text-slate-200 text-sm">
                                {{ $returnReasonType->updated_at ? $returnReasonType->updated_at->format('M d, Y H:i A') : 'N/A' }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── STICKY FOOTER ── -->
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-blue-400" fill="none" stroke="currentColor" stroke-width="2"
                         viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span>View detailed information about this return reason type.</span>
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.return-reason-types.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Back to List
                    </a>
                    @can('general.return_reason_type.edit')
                        <a href="{{ route('admin.return-reason-types.edit', $returnReasonType->id) }}"
                           class="px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                            </svg>
                            Edit
                        </a>
                    @endcan
                </div>
            </div>
        </div>

    </div>
@endsection

