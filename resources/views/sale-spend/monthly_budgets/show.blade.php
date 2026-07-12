@extends('layouts.app')

@section('title', 'View Monthly Budget')

@section('content')
    <div id="monthly-budget-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        {{-- ── PAGE HEADER ── --}}
        <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                {{-- Icon badge --}}
                <div class="w-11 h-11 rounded-xl bg-blue-50 dark:bg-blue-900/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">
                        {{ $monthlyBudget->salePlatform->name ?? 'N/A' }} - {{ $months[$monthlyBudget->month] ?? 'N/A' }} {{ $monthlyBudget->year }}
                    </h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="badge-custom badge-blue text-[10px]">Monthly Budget</span>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            Budget: {{ number_format($monthlyBudget->budget, 2) }} {{ $monthlyBudget->currency }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            {{-- ── LEFT COLUMN ── --}}
            <div class="space-y-5">

                {{-- Budget Information --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Budget Information
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        {{-- Sale Platform --}}
                        <div class="flex items-center justify-between py-3 first:pt-0">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Platform</span>
                            <a href="{{ route('admin.sale-platforms.show', $monthlyBudget->salePlatform->id) }}"
                               class="text-[13px] text-slate-800 dark:text-slate-100 font-medium text-right hover:text-accent-500 transition-colors">
                                {{ $monthlyBudget->salePlatform->name ?? 'N/A' }}
                            </a>
                        </div>

                        {{-- Year --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Year</span>
                            <span class="text-[13px] text-slate-700 dark:text-slate-200 font-medium">{{ $monthlyBudget->year }}</span>
                        </div>

                        {{-- Month --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Month</span>
                            <span class="text-[13px] text-slate-700 dark:text-slate-200 font-medium">{{ $months[$monthlyBudget->month] ?? 'N/A' }}</span>
                        </div>

                        {{-- Budget --}}
                        <div class="flex items-center justify-between py-3">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Budget</span>
                            <span class="text-[13px] text-slate-700 dark:text-slate-200 font-medium">{{ number_format($monthlyBudget->budget, 2) }}</span>
                        </div>

                        {{-- Currency --}}
                        <div class="flex items-center justify-between py-3 last:pb-0">
                            <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-28 shrink-0">Currency</span>
                            <span class="text-[13px] text-slate-700 dark:text-slate-200 font-medium">{{ $monthlyBudget->currency }}</span>
                        </div>
                    </div>
                </div>

                {{-- Notes --}}
                @if ($monthlyBudget->notes)
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0115.75 21H5.25A2.25 2.25 0 013 18.75V8.25A2.25 2.25 0 015.25 6H10"/>
                            </svg>
                            Notes
                        </div>
                        <div class="text-[13px] text-slate-700 dark:text-slate-200 whitespace-pre-wrap">
                            {{ $monthlyBudget->notes }}
                        </div>
                    </div>
                @endif

            </div>

            {{-- ── RIGHT COLUMN ── --}}
            <div class="space-y-5">

                {{-- Metadata --}}
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Metadata
                    </div>

                    <div class="space-y-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Created</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $monthlyBudget->created_at ? $monthlyBudget->created_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $monthlyBudget->created_at ? $monthlyBudget->created_at->format('H:i A') : '' }}
                                · {{ $monthlyBudget->created_at?->diffForHumans() }}
                            </p>
                        </div>

                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 dark:text-slate-500 font-medium mb-1.5">Last Updated</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $monthlyBudget->updated_at ? $monthlyBudget->updated_at->format('M d, Y') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400 dark:text-slate-500">
                                {{ $monthlyBudget->updated_at ? $monthlyBudget->updated_at->format('H:i A') : '' }}
                                · {{ $monthlyBudget->updated_at?->diffForHumans() }}
                            </p>
                        </div>
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
                    <span>ID: <code class="font-mono text-slate-500">{{ $monthlyBudget->id }}</code></span>
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.monthly-budgets.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Back to List
                    </a>
                    @can('general.monthly_budget.edit')
                        <a href="{{ route('admin.monthly-budgets.edit', $monthlyBudget->id) }}"
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
