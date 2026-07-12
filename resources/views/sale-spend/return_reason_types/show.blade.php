@extends('layouts.app')

@section('title', 'View Return Reason Type — ' . $returnReason->name)

@section('content')
    <div id="return-reason-type-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        {{-- ── PAGE HEADER ── --}}
        <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-accent-50 dark:bg-accent-900/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M9 15L3 9m0 0l6-6M3 9h12a6 6 0 010 12h-3"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">{{ $returnReason->name }}</h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        @if ($returnReason->is_active)
                            <span class="badge-custom badge-green text-[10px]">Active</span>
                        @else
                            <span class="badge-custom badge-red text-[10px]">Inactive</span>
                        @endif
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">Sort: {{ $returnReason->sort_order }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            {{-- ── LEFT COLUMN ── --}}
            <div class="space-y-5">

                {{-- Basic Information --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Basic Information
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        {{-- Name --}}
                        <div class="flex items-center justify-between py-3 first:pt-0">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Name</span>
                            <span class="text-[13px] text-slate-800 dark:text-slate-100 font-medium text-right">{{ $returnReason->name }}</span>
                        </div>

                        {{-- Slug --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Slug</span>
                            <code class="text-[12px] text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded font-mono">{{ $returnReason->slug }}</code>
                        </div>

                        {{-- Sort Order --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Sort Order</span>
                            <span class="text-[13px] text-slate-700 dark:text-slate-200 font-mono">{{ $returnReason->sort_order }}</span>
                        </div>

                        {{-- Description --}}
                        @if ($returnReason->description)
                            <div class="py-3 last:pb-0">
                                <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide block mb-2">Description</span>
                                <p class="text-[13px] text-slate-700 dark:text-slate-200 leading-relaxed whitespace-pre-wrap">{{ $returnReason->description }}</p>
                            </div>
                        @endif
                    </div>
                </div>

            </div>

            {{-- ── RIGHT COLUMN ── --}}
            <div class="space-y-5">

                {{-- Status & Metadata --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Status & Info
                    </div>

                    <div class="space-y-3">
                        {{-- Status --}}
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Status</p>
                            @if ($returnReason->is_active)
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></div>
                                    <span class="text-[13px] text-emerald-600 dark:text-emerald-400 font-medium">Active</span>
                                </div>
                            @else
                                <div class="flex items-center gap-2">
                                    <div class="w-2 h-2 rounded-full bg-red-400"></div>
                                    <span class="text-[13px] text-red-500 dark:text-red-400 font-medium">Inactive</span>
                                </div>
                            @endif
                        </div>

                        {{-- Created At --}}
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Created</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $returnReason->created_at ? $returnReason->created_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $returnReason->created_at ? $returnReason->created_at->format('H:i') : '' }}
                                @if($returnReason->created_at) · {{ $returnReason->created_at->diffForHumans() }} @endif
                            </p>
                        </div>

                        {{-- Updated At --}}
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Last Updated</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $returnReason->updated_at ? $returnReason->updated_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $returnReason->updated_at ? $returnReason->updated_at->format('H:i') : '' }}
                                @if($returnReason->updated_at) · {{ $returnReason->updated_at->diffForHumans() }} @endif
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Quick stats --}}
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Sort Order</p>
                        <p class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $returnReason->sort_order }}</p>
                    </div>
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-3">
                        <p class="text-[10px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1">Status</p>
                        @if($returnReason->is_active)
                            <p class="text-xl font-bold text-emerald-500">Active</p>
                        @else
                            <p class="text-xl font-bold text-red-400">Inactive</p>
                        @endif
                    </div>
                </div>

            </div>
        </div>

        {{-- ── STICKY FOOTER ── --}}
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>ID: <code class="font-mono text-slate-500">{{ $returnReason->id }}</code></span>
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.return-reason.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Back to List
                    </a>
                    @can('general.return_reason_type.edit')
                        <a href="{{ route('admin.return-reason.edit', $returnReason->id) }}"
                           class="px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                            Edit
                        </a>
                    @endcan
                </div>
            </div>
        </div>

    </div>
@endsection

