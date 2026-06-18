@extends('layouts.app')

@section('title', 'Source Excel Preview')

@section('content')
<div class="p-5 lg:p-6">

    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Source Excel Preview</h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">
                Reading <code class="bg-slate-100 dark:bg-slate-700 px-1.5 py-0.5 rounded text-[12px]">public/enorsia_tracking.xlsx</code>
                — first 30 data rows shown
            </p>
        </div>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.ads-performance.index') }}"
               class="flex items-center gap-2 px-3.5 py-2 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/></svg>
                Back to Sale Tracking
            </a>
        </div>
    </div>

    {{-- Detected columns --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl p-5 mb-5">
        <p class="text-[10px] font-semibold tracking-[1.5px] uppercase text-slate-400 dark:text-slate-500 mb-3">Auto-Detected Columns (Row 3)</p>
        <div class="flex flex-wrap gap-2">
            @foreach($columns as $col => $label)
                <div class="flex items-center gap-1.5 bg-[#E6F3F0] dark:bg-accent-900/30 text-[#003D2B] dark:text-accent-300 text-[12px] font-medium px-2.5 py-1 rounded-full">
                    <span class="font-bold text-accent-600 dark:text-accent-400">{{ $col }}</span>
                    <span class="text-slate-500 dark:text-slate-400">→</span>
                    <span>{{ $label }}</span>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Data rows --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-2xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-[12px]">
                <thead>
                    <tr class="bg-[#009966] text-white">
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Sl.</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Month</th>
                        <th class="px-3 py-2.5 text-left font-semibold whitespace-nowrap">Platform</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Reach</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Impressions</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Clicks</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Sessions</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Engaged</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Users</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Ads Tax</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Orders</th>
                        <th class="px-3 py-2.5 text-right font-semibold whitespace-nowrap">Products</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-700/50">
                    @foreach($rows as $i => $row)
                    @php $isAlt = $i % 2 !== 0; @endphp
                    <tr class="{{ $isAlt ? 'bg-[#F0FAF5] dark:bg-slate-800/60' : 'bg-white dark:bg-slate-800' }}">
                        <td class="px-3 py-2 text-slate-500">{{ $row['sl_no'] ?? '—' }}</td>
                        <td class="px-3 py-2 text-slate-600 dark:text-slate-400 whitespace-nowrap font-medium">
                            {{ $row['month'] ? $row['month']->format('M Y') : '—' }}
                        </td>
                        <td class="px-3 py-2 text-slate-700 dark:text-slate-300 font-medium">{{ $row['platform_name'] }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['reach'] !== null ? number_format($row['reach']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['impressions'] !== null ? number_format($row['impressions']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['clicks'] !== null ? number_format($row['clicks']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['sessions'] !== null ? number_format($row['sessions']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['engaged_sessions'] !== null ? number_format($row['engaged_sessions']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['users'] !== null ? number_format($row['users']) : '—' }}</td>
                        <td class="px-3 py-2 text-right text-slate-500 tabular-nums">{{ $row['ads_tax_payments'] !== null ? '£'.number_format($row['ads_tax_payments'], 2) : '—' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

