@extends('layouts.app')

@section('title', 'Daily Return Details')

@section('content')
    <div id="daily-returns-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-red-50 dark:bg-red-900/20 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M16 15v-1a4 4 0 00-4-4H8m0 0l3 3m-3-3l3-3m9 14V5a2 2 0 00-2-2H6a2 2 0 00-2 2v16l4-2 4 2 4-2 4 2z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">
                        {{ $dailyReturn->salePlatform->name ?? 'N/A' }}
                    </h1>
                    <div class="flex items-center gap-2 mt-0.5">
                        <span class="badge-custom badge-red text-[10px]">Daily Return</span>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $dailyReturn->date ? $dailyReturn->date->format('d M Y') : 'N/A' }}
                        </span>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">·</span>
                        <span class="text-[11px] text-slate-400 dark:text-slate-500">
                            {{ $dailyReturn->returnReasonType->name ?? 'N/A' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

            <!-- LEFT COLUMN -->
            <div class="space-y-5">

                <!-- Core Details -->
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Core Details
                    </div>

                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60">
                        @php
                            $coreFields = [
                                'Platform'         => $dailyReturn->salePlatform->name ?? 'N/A',
                                'Return Reason'    => $dailyReturn->returnReasonType->name ?? 'N/A',
                                'Date'             => $dailyReturn->date ? $dailyReturn->date->format('d M Y') : 'N/A',
                                'Returns'          => number_format($dailyReturn->number_of_returns),
                                'Return Qty'       => number_format($dailyReturn->number_of_return_quantities),
                            ];
                        @endphp
                        @foreach($coreFields as $label => $value)
                            <div class="flex items-center justify-between py-3 first:pt-0 last:pb-0">
                                <span class="text-[12px] font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wide w-36 shrink-0">{{ $label }}</span>
                                <span class="text-[13px] text-slate-800 dark:text-slate-100 font-medium text-right">{{ $value }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Gender Breakdown -->
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                        </svg>
                        Gender Breakdown
                    </div>

                    <div class="overflow-x-auto mt-3">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b border-slate-200 dark:border-slate-700">
                                    <th class="pb-2 text-left text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Category</th>
                                    <th class="pb-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Male</th>
                                    <th class="pb-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Female</th>
                                    <th class="pb-2 text-right text-[11px] font-semibold text-slate-500 uppercase tracking-wider">Kids</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100 dark:divide-slate-700/60">
                                <tr>
                                    <td class="py-2 text-[13px] text-slate-600 dark:text-slate-300">Returns</td>
                                    <td class="py-2 text-right text-[13px] text-slate-700 dark:text-slate-200">{{ number_format($dailyReturn->number_of_male_returns) }}</td>
                                    <td class="py-2 text-right text-[13px] text-slate-700 dark:text-slate-200">{{ number_format($dailyReturn->number_of_female_returns) }}</td>
                                    <td class="py-2 text-right text-[13px] text-slate-700 dark:text-slate-200">{{ number_format($dailyReturn->number_of_kids_returns) }}</td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-[13px] text-slate-600 dark:text-slate-300">Quantities</td>
                                    <td class="py-2 text-right text-[13px] text-slate-700 dark:text-slate-200">{{ number_format($dailyReturn->number_of_male_return_quantities) }}</td>
                                    <td class="py-2 text-right text-[13px] text-slate-700 dark:text-slate-200">{{ number_format($dailyReturn->number_of_female_return_quantities) }}</td>
                                    <td class="py-2 text-right text-[13px] text-slate-700 dark:text-slate-200">{{ number_format($dailyReturn->number_of_kids_return_quantities) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- RIGHT COLUMN -->
            <div class="space-y-5">
                <div class="section-card">
                    <div class="section-title">
                        <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Metadata
                    </div>
                    <div class="space-y-3">
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 font-medium mb-1">Created</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $dailyReturn->created_at ? $dailyReturn->created_at->format('d M Y H:i') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400">{{ $dailyReturn->created_at?->diffForHumans() }}</p>
                        </div>
                        <div>
                            <p class="text-[11px] uppercase tracking-wider text-slate-400 font-medium mb-1">Last Updated</p>
                            <p class="text-[13px] text-slate-700 dark:text-slate-200">
                                {{ $dailyReturn->updated_at ? $dailyReturn->updated_at->format('d M Y H:i') : 'N/A' }}
                            </p>
                            <p class="text-[11px] text-slate-400">{{ $dailyReturn->updated_at?->diffForHumans() }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ── STICKY FOOTER ── -->
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>ID: <code class="font-mono text-slate-500">{{ $dailyReturn->id }}</code></span>
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.daily-returns.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Back to List
                    </a>
                    @can('general.daily_return.edit')
                        <a href="{{ route('admin.daily-returns.edit', $dailyReturn->id) }}"
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

