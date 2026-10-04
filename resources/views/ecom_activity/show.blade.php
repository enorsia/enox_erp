@extends('layouts.app')

@section('title', 'Session Activity')

@section('content')
    <div class="max-w-6xl mx-auto px-5 py-6 pb-28">
        <div class="flex items-start justify-between mb-6 flex-wrap gap-3">
            <div class="flex items-center gap-3">
                <div class="w-11 h-11 rounded-xl bg-accent-400/10 flex items-center justify-center shrink-0">
                    <svg class="w-5 h-5 text-accent-500" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                        <path stroke-linecap="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Visitor Session</h1>
                    <p class="text-[12px] font-mono text-slate-400 mt-0.5">{{ $session }}</p>
                    <p class="text-[11px] text-slate-400 mt-1">Preview · sample data · All times {{ \App\Support\TrackerTime::timezoneLabel() }}</p>
                </div>
            </div>
            <a href="{{ $backUrl }}"
               class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors no-underline">
                ← Back
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">
            <div class="space-y-4 min-w-0">
                <div class="section-card">
                    <div class="section-title flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Action Timeline
                        </div>
                        <span class="text-[11px] font-normal text-slate-400">3 events</span>
                    </div>

                    <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-3 min-w-0 overflow-hidden">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="badge-custom badge-blue">page view</span>
                            <span class="text-[12px] text-slate-400">4 Oct 2026, 2:14:22 PM</span>
                            <span class="text-[11px] text-slate-500">Dwell: 42s</span>
                        </div>
                        <div class="text-[12px] text-slate-500 dark:text-slate-400 mb-2">
                            <span class="text-slate-400">To:</span>
                            <span class="text-slate-600 dark:text-slate-300 break-all">/living-room/sofas</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[12px]">
                            <div><span class="text-slate-400">Category:</span> Living room</div>
                            <div><span class="text-slate-400">Product:</span> Oslo corner sofa — grey</div>
                        </div>
                    </div>

                    <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-3 min-w-0 overflow-hidden">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="badge-custom badge-amber">add to cart</span>
                            <span class="text-[12px] text-slate-400">4 Oct 2026, 2:15:08 PM</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[12px]">
                            <div><span class="text-slate-400">Product:</span> Oslo corner sofa — grey</div>
                            <div><span class="text-slate-400">Price:</span> £299.00</div>
                        </div>
                    </div>

                    <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 min-w-0 overflow-hidden">
                        <div class="flex flex-wrap items-center gap-2 mb-2">
                            <span class="badge-custom badge-green">payment success</span>
                            <span class="text-[12px] text-slate-400">4 Oct 2026, 2:18:41 PM</span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[12px]">
                            <div><span class="text-slate-400">Value:</span> £598.00</div>
                            <div><span class="text-slate-400">Qty:</span> 2</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-4">
                <div class="section-card">
                    <div class="section-title">Session Summary</div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-[13px]">
                        <div class="py-2.5 first:pt-0 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">Device</div>
                            <div class="text-slate-700 dark:text-slate-200">Mobile · Chrome · Android</div>
                        </div>
                        <div class="py-2.5 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">User</div>
                            <div class="text-slate-700 dark:text-slate-200">Guest</div>
                        </div>
                        <div class="py-2.5 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">First seen</div>
                            <div class="text-slate-700 dark:text-slate-200">4 Oct 2026, 2:14 PM</div>
                        </div>
                        <div class="py-2.5 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">Last active</div>
                            <div class="text-slate-700 dark:text-slate-200">4 Oct 2026, 2:18 PM</div>
                        </div>
                        <div class="py-2.5 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">Landing page</div>
                            <div class="text-slate-700 dark:text-slate-200 break-all">/living-room/sofas</div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">Session traffic</div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-[13px]">
                        <div class="py-2.5 first:pt-0 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">Source / medium</div>
                            <div class="text-slate-700 dark:text-slate-200">google / cpc</div>
                        </div>
                        <div class="py-2.5 min-w-0">
                            <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">Campaign</div>
                            <div class="text-slate-700 dark:text-slate-200">autumn_living</div>
                        </div>
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">Funnel Progress</div>
                    <div class="flex flex-wrap gap-2">
                        <span class="badge-custom badge-blue">page view</span>
                        <span class="badge-custom badge-amber">add to cart</span>
                        <span class="badge-custom badge-green">payment success</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
