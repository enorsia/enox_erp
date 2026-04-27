@extends('layouts.app')

@section('title', 'User Journey')

@push('css')
<style>
    /* ── Session select ────────────────────────────── */
    #session-selector {
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2394a3b8' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right .75rem center;
        background-size: 1rem;
        padding-right: 2.5rem;
    }

    /* ── Event timeline inside a batch ─────────── */
    .ev-line { position: relative; }
    .ev-line::before {
        content: '';
        position: absolute;
        left: 10px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: rgba(148,163,184,.2);
    }
    .dark .ev-line::before { background: rgba(71,85,105,.35); }

    /* ── Batch toggle icon ────────────────────── */
    .batch-toggle-icon { transition: transform .2s; }

    /* ── Product detail grid ────────────────────── */
    .prod-detail {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(140px, 1fr));
        gap: 2px 12px;
        font-size: 11px;
        margin-top: 4px;
        padding: 6px 8px;
        background: rgba(255,255,255,.5);
        border-radius: 6px;
    }
    .dark .prod-detail { background: rgba(30,41,59,.4); }
    .prod-detail .pd-lbl { opacity: .55; }
    .prod-detail .pd-val { font-weight: 600; }
</style>
@endpush

@section('content')
<div class="p-5 lg:p-6 max-w-6xl mx-auto">

    {{-- ── HEADER ───────────────────────────────────────────────────────── --}}
    <div class="flex items-start justify-between gap-4 mb-6 flex-wrap">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">User Journey</h1>
            <p class="text-xs text-slate-400 dark:text-slate-500 mt-1 font-mono">{{ $anonymousId }}</p>
        </div>
        <a href="{{ route('admin.tracking.index') }}"
           class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors whitespace-nowrap">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Back to Visitors
        </a>
    </div>

    {{-- ── SUMMARY CARDS ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @php
            $cards = [
                ['label' => 'Total Events',  'value' => number_format($summary['total_events']   ?? 0), 'color' => 'blue'],
                ['label' => 'Sessions',       'value' => number_format($summary['total_sessions'] ?? 0), 'color' => 'purple'],
                ['label' => 'Page Views',     'value' => number_format($summary['page_views']     ?? 0), 'color' => 'sky'],
                ['label' => 'Orders',         'value' => number_format($summary['orders']         ?? 0), 'color' => 'green'],
                ['label' => 'Revenue',        'value' => '£'.number_format((float)($summary['revenue'] ?? 0),2), 'color' => 'emerald'],
                ['label' => 'Active Since',   'value' => !empty($summary['first_seen']) ? \Carbon\Carbon::parse($summary['first_seen'])->format('d M Y') : '—', 'color' => 'amber'],
            ];
            $badge = [
                'blue'    => 'text-blue-600 dark:text-blue-300',
                'purple'  => 'text-purple-600 dark:text-purple-300',
                'sky'     => 'text-sky-600 dark:text-sky-300',
                'green'   => 'text-green-600 dark:text-green-300',
                'emerald' => 'text-emerald-600 dark:text-emerald-300',
                'amber'   => 'text-amber-600 dark:text-amber-300',
            ];
        @endphp
        @foreach($cards as $card)
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-1">{{ $card['label'] }}</p>
                <p class="text-[18px] font-bold {{ $badge[$card['color']] }}">{{ $card['value'] }}</p>
            </div>
        @endforeach
    </div>

    {{-- ── VISITOR DETAILS ───────────────────────────────────────────────── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Visitor Details</p>
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-[13px]">
            @foreach([
                ['Device',    !empty($summary['device_type']) ? ucfirst($summary['device_type']) : null],
                ['Browser',   $summary['browser']  ?? null],
                ['OS',        $summary['os']        ?? null],
                ['Screen',    $summary['screen_resolution'] ?? null],
                ['Location',  implode(', ', array_filter([$summary['city'] ?? '', $summary['country'] ?? ''])) ?: null],
                ['IP',        $summary['ip_address'] ?? null],
                ['Language',  $summary['language']  ?? null],
                ['Entry',     !empty($summary['first_seen']) ? \Carbon\Carbon::parse($summary['first_seen'])->format('d M Y, H:i') : null],
                ['Last Seen', !empty($summary['last_seen'])  ? \Carbon\Carbon::parse($summary['last_seen'])->format('d M Y, H:i')  : null],
            ] as [$lbl, $val])
                @if($val)
                    <div>
                        <span class="text-slate-400">{{ $lbl }}</span>
                        <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ $val }}</span>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── SESSION DROPDOWN ──────────────────────────────────────────────── --}}
    @if(!empty($sessions))
        @php
            $sessionsForDropdown = array_reverse(array_values($sessions));
            $lastSession = $sessions[array_key_last($sessions)];
        @endphp
        <div class="mb-5 flex items-center gap-3 flex-wrap">
            <label for="session-selector" class="text-[12px] font-semibold text-slate-500 dark:text-slate-400 whitespace-nowrap">Select Session:</label>
            <div class="relative flex-1 min-w-[280px] max-w-xl">
                <select id="session-selector"
                        class="w-full h-10 pl-3 pr-8 text-[13px] font-medium border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-400 cursor-pointer">
                    @foreach($sessionsForDropdown as $di => $dsess)
                        @php
                            $dDur    = (int)$dsess['duration_seconds'];
                            $dDurStr = $dDur >= 3600 ? gmdate('H:i:s',$dDur) : ($dDur >= 60 ? gmdate('i:s',$dDur).'m' : $dDur.'s');
                            $dDate   = \Carbon\Carbon::parse($dsess['start_time'])->format('d M Y, H:i:s');
                            $dSrc    = $dsess['utm_source'] ?: ($dsess['referrer'] ? (parse_url($dsess['referrer'], PHP_URL_HOST) ?: $dsess['referrer']) : 'Direct');
                            $dSrc    = $dSrc ?: 'Direct';
                            $origIdx = count($sessions) - 1 - $di;
                            $sessNum = $origIdx + 1;
                            $hasOrd  = (int)($dsess['order_placed'] ?? 0) > 0;
                        @endphp
                        <option value="{{ $dsess['session_id'] }}"
                                {{ $dsess['session_id'] === $lastSession['session_id'] ? 'selected' : '' }}>
                            Session {{ $sessNum }} — {{ $dDate }} ({{ $dDurStr }}) — {{ ucfirst($dSrc) }}{{ $hasOrd ? ' ✓ Order' : '' }}
                        </option>
                    @endforeach
                </select>
            </div>
        </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════
         SESSION PANELS  —  page-batch journey (newest page first)
    ══════════════════════════════════════════════════════════════════════════ --}}
    @forelse($sessions as $si => $sess)
        @php
            $isActivePanel = $si === array_key_last($sessions);
            $hasOrder      = (int)($sess['order_placed'] ?? 0) > 0;
            $sidStr        = $sess['session_id'];
            $allBatches    = $pagesBySession[$sidStr] ?? [];
            $batches       = array_reverse(array_values($allBatches));
            $duration      = (int)$sess['duration_seconds'];
            $durStr        = $duration >= 3600 ? gmdate('H:i:s',$duration) : ($duration >= 60 ? gmdate('i:s',$duration).'m' : $duration.'s');
            $sessSource    = $sess['utm_source']
                ? $sess['utm_source'].($sess['utm_medium'] ? ' / '.$sess['utm_medium'] : '')
                : ($sess['referrer'] ? \Illuminate\Support\Str::limit($sess['referrer'], 35) : 'Direct');
        @endphp

        <div id="session-{{ $sidStr }}"
             data-session-id="{{ $sidStr }}"
             class="session-panel mb-6 {{ $isActivePanel ? '' : 'hidden' }}">

            {{-- Session summary header --}}
            <div class="flex items-center justify-between px-5 py-3 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl mb-4 flex-wrap gap-2">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[13px] font-bold text-slate-700 dark:text-slate-200">Session {{ $si + 1 }}</span>
                    <span class="text-[10px] font-mono text-slate-400 dark:text-slate-500">{{ substr($sidStr, 0, 36) }}</span>
                    @if($hasOrder)
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 uppercase tracking-wide">✓ Ordered</span>
                    @endif
                    @if($sessSource !== 'Direct')
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-blue-50 dark:bg-blue-900/20 text-blue-600 dark:text-blue-300 border border-blue-200 dark:border-blue-700">🎯 {{ $sessSource }}</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400">⚡ Direct</span>
                    @endif
                </div>
                <div class="flex items-center gap-4 text-[12px] text-slate-400 dark:text-slate-500 flex-wrap">
                    <span>⏱ {{ $durStr }}</span>
                    <span>📄 {{ count($allBatches) }} pages</span>
                    <span>⚡ {{ $sess['event_count'] }} events</span>
                    <span class="hidden sm:inline">{{ \Carbon\Carbon::parse($sess['start_time'])->format('d M Y, H:i') }}</span>
                </div>
            </div>

            {{-- ── PAGE BATCHES (newest first) ──────────────────────────────── --}}
            @forelse($batches as $bi => $batch)
                @php
                    $bEvents  = $batch['events'];
                    $bStart   = \Carbon\Carbon::parse($batch['start_time']);
                    $bEnd     = \Carbon\Carbon::parse($batch['end_time']);
                    $bDurSec  = max(0, $bEnd->diffInSeconds($bStart));
                    $bDurStr  = $bDurSec >= 3600 ? gmdate('H:i:s',$bDurSec) : ($bDurSec >= 60 ? gmdate('i:s',$bDurSec).'m' : $bDurSec.'s');

                    $utm  = $batch['utm_source'];
                    $med  = $batch['utm_medium'];
                    $camp = $batch['utm_campaign'];
                    $ref  = $batch['referrer'];

                    if ($utm) {
                        $srcLabel = $utm.($med ? '/'.$med : '').($camp ? ' ('.$camp.')' : '');
                        $srcBg    = 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-700';
                        $srcIcon  = '🎯';
                    } elseif ($ref) {
                        $srcLabel = \Illuminate\Support\Str::limit($ref, 45);
                        $srcBg    = 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-700';
                        $srcIcon  = '🔗';
                    } else {
                        $srcLabel = 'Direct';
                        $srcBg    = 'bg-slate-100 dark:bg-slate-700/50 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600';
                        $srcIcon  = '⚡';
                    }

                    $batchId = 'b-'.$sidStr.'-'.$bi;

                    // ── Pre-process events ───────────────────────────────────
                    // Reversed array: $bi=0 is newest, $bi=$totalPages-1 is landing.
                    // batch_num==1 means it is the landing (first) page of the session.
                    $isFirstBatch  = $batch['batch_num'] === 1;
                    $pageActiveMs  = 0;
                    $pageScrollMax = 0;
                    $visibleEvents = [];

                    foreach ($bEvents as $bev) {
                        $bn = $bev['event_name'];

                        // P3/P7 — time_on_page: extract stats, never show as event row
                        if ($bn === 'time_on_page') {
                            $pageActiveMs  = max($pageActiveMs,  (int)($bev['active_time_ms'] ?? 0));
                            $pageScrollMax = max($pageScrollMax, (int)($bev['scroll_depth_pct'] ?? 0));
                            continue;
                        }

                        // P8 — scroll_depth_final: use as badge stat, skip as event row
                        if ($bn === 'scroll_depth_final') {
                            $bevP = !empty($bev['properties']) ? (json_decode($bev['properties'], true) ?? []) : [];
                            $pageScrollMax = max($pageScrollMax,
                                (int)($bev['scroll_depth_pct'] ?? $bevP['depth_percent'] ?? 0));
                            continue;
                        }

                        // P4 — page_transition: skip as event row (shown via connectors in the header)
                        if ($bn === 'page_transition') continue;

                        // P6 — internal noise: completely hide
                        if (in_array($bn, [
                            'tab_visibility','tab_visible','tab_hidden',
                            'tab_focus','tab_blur','user_idle',
                        ], true)) continue;

                        // P1 — session_started: show ONLY on the first page of the session
                        if ($bn === 'session_started' && !$isFirstBatch) continue;

                        $visibleEvents[] = $bev;
                    }

                    $bEvCount   = count($visibleEvents);
                    $totalPages = count($batches);
                @endphp

                <div class="page-batch relative">

                    {{-- Connector between reversed pages --}}
{{--                    @if($bi > 0)--}}
{{--                        <div class="flex items-center gap-2 pl-9 pb-2 text-[11px] text-slate-400 dark:text-slate-500">--}}
{{--                            <div class="w-0.5 h-5 bg-slate-200 dark:bg-slate-600 ml-0.5"></div>--}}
{{--                            <svg class="w-3 h-3 text-slate-300 dark:text-slate-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">--}}
{{--                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>--}}
{{--                            </svg>--}}
{{--                            @php $navLabel = $batch['page_path'] === '/' ? 'Home (/)' : \Illuminate\Support\Str::limit($batch['page_path'], 60); @endphp--}}
{{--                            <span class="font-mono text-[10px]">{{ $navLabel }}</span>--}}
{{--                        </div>--}}
{{--                    @endif--}}
                    <div class="mb-5"></div>

                    {{-- "Latest page" badge at top --}}
                    @if($bi === 0)
                        <div class="flex items-center gap-2 pl-2 mb-1.5">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-100 dark:bg-slate-700 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-slate-600">
                                ↑ Latest page in session
                            </span>
                        </div>
                    @endif

                    {{-- Batch card --}}
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl overflow-hidden">

                        <button type="button"
                                onclick="toggleBatch('{{ $batchId }}')"
                                class="w-full text-left px-4 py-3 flex items-start gap-3 hover:bg-slate-50/80 dark:hover:bg-slate-700/30 transition-colors group">

                            <div class="shrink-0 w-7 h-7 rounded-full {{ $batch['order_placed'] ? 'bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300' : 'bg-indigo-100 dark:bg-indigo-900/40 text-indigo-700 dark:text-indigo-300' }} text-[11px] font-bold flex items-center justify-center mt-0.5">
                                {{ $batch['batch_num'] }}
                            </div>

                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 flex-wrap">
                                    @php
                                        $displayTitle = $batch['page_title'] ?: $batch['page_path'];
                                        $displayTitle = ($displayTitle === '/' || $displayTitle === '') ? 'Home' : $displayTitle;
                                    @endphp
                                    <span class="text-[13px] font-semibold text-slate-800 dark:text-slate-100">{{ $displayTitle }}</span>
                                    {{-- P5: Returned-to-page badge --}}
                                    @if($batch['is_revisit'] ?? false)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-amber-50 dark:bg-amber-900/30 text-amber-600 dark:text-amber-300 border border-amber-200 dark:border-amber-700">↩ Returned</span>
                                    @endif
                                    @if($batch['order_placed'])
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300">🛍 Order placed</span>
                                    @endif
                                    @if($batch['product_views'] > 0)
                                        <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-purple-50 dark:bg-purple-900/30 text-purple-600 dark:text-purple-300">👁 {{ $batch['product_views'] }} product{{ $batch['product_views'] > 1 ? 's' : '' }}</span>
                                    @endif
                                </div>

                                @php $displayPath = $batch['page_path'] === '/' ? '/  (Home)' : $batch['page_path']; @endphp
                                @if($batch['page_title'] && $batch['page_path'] && $batch['page_title'] !== $batch['page_path'])
                                    <p class="text-[11px] font-mono text-slate-400 dark:text-slate-500 mt-0.5 truncate">
                                        {{ \Illuminate\Support\Str::limit($displayPath, 80) }}
                                    </p>
                                @endif

                                <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px]">
                                    <span class="text-slate-400 dark:text-slate-500 font-mono">
                                        {{ $bStart->format('H:i:s') }}
                                        @if($bDurSec > 0)
                                            <span class="mx-0.5 opacity-40">→</span>{{ $bEnd->format('H:i:s') }}
                                            <span class="ml-1 font-semibold text-slate-500">({{ $bDurStr }})</span>
                                        @endif
                                    </span>
                                    @if($pageActiveMs > 0)
                                        <span class="text-slate-500 dark:text-slate-400 font-semibold">🕐 {{ round($pageActiveMs / 1000) }}s active</span>
                                    @endif
                                    @if($pageScrollMax > 0)
                                        <span class="text-slate-400 dark:text-slate-500">↕ {{ $pageScrollMax }}%</span>
                                    @endif
                                    {{-- Source shown on landing page (last in reversed list) or when non-direct --}}
                                    @if($bi === $totalPages - 1 || $utm || $ref)
                                        <span class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded-full border text-[10px] font-semibold {{ $srcBg }}">
                                            {{ $srcIcon }} {{ $srcLabel }}
                                        </span>
                                    @endif
                                    <span class="text-slate-400 dark:text-slate-500">⚡ {{ $bEvCount }} events</span>
                                    @if($batch['clicks'] > 0)
                                        <span class="text-slate-400 dark:text-slate-500">🖱 {{ $batch['clicks'] }}</span>
                                    @endif
                                </div>

                                {{-- UTM detail block for landing page --}}
                                @if($bi === $totalPages - 1 && ($utm || $camp || $ref))
                                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-[11px]">
                                        @if($utm)<span><span class="text-slate-400">UTM Source:</span> <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $utm }}</span></span>@endif
                                        @if($med)<span><span class="text-slate-400">UTM Medium:</span> <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $med }}</span></span>@endif
                                        @if($camp)<span><span class="text-slate-400">Campaign:</span> <span class="font-semibold text-slate-600 dark:text-slate-300">{{ $camp }}</span></span>@endif
                                        @if($ref)<span><span class="text-slate-400">Referrer:</span> <span class="font-semibold text-slate-600 dark:text-slate-300 font-mono">{{ \Illuminate\Support\Str::limit($ref, 60) }}</span></span>@endif
                                    </div>
                                @endif
                            </div>

                            <svg id="{{ $batchId }}-icon"
                                 class="batch-toggle-icon shrink-0 w-4 h-4 text-slate-400 dark:text-slate-500 mt-1 group-hover:text-slate-600 dark:group-hover:text-slate-300"
                                 fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        {{-- ── EVENT LIST ────────────────────────────────────────── --}}
                        <div id="{{ $batchId }}-body" class="border-t border-slate-100 dark:border-slate-700">
                            @if(!empty($visibleEvents))
                                <div class="px-4 py-3 ev-line space-y-0.5">
                                    @foreach($visibleEvents as $ev)
                                        @php
                                            $evName = $ev['event_name'];
                                            $props  = [];
                                            if (!empty($ev['properties']) && $ev['properties'] !== '{}') {
                                                $props = json_decode($ev['properties'], true) ?? [];
                                            }
                                            $evTs = \Carbon\Carbon::parse($ev['event_timestamp']);

                                            [$dot, $evBg, $icon] = match(true) {
                                                $evName === 'order_placed'          => ['bg-green-500',   'bg-green-50 dark:bg-green-900/20',       '🛍️'],
                                                $evName === 'add_to_cart'           => ['bg-blue-500',    'bg-blue-50 dark:bg-blue-900/20',         '🛒'],
                                                $evName === 'remove_from_cart'      => ['bg-orange-400',  'bg-orange-50 dark:bg-orange-900/20',     '🗑️'],
                                                $evName === 'checkout_started'      => ['bg-yellow-500',  'bg-yellow-50 dark:bg-yellow-900/20',     '💳'],
                                                $evName === 'checkout_step'         => ['bg-yellow-400',  'bg-yellow-50 dark:bg-yellow-900/15',     '➡️'],
                                                $evName === 'product_viewed'        => ['bg-purple-500',  'bg-purple-50 dark:bg-purple-900/20',     '👁️'],
                                                $evName === 'page_viewed'           => ['bg-sky-400',     'bg-sky-50 dark:bg-sky-900/20',           '📄'],
                                                $evName === 'session_started'       => ['bg-slate-400',   'bg-slate-100 dark:bg-slate-700/50',      '🚀'],
                                                $evName === 'session_ended'         => ['bg-slate-400',   'bg-slate-100 dark:bg-slate-700/50',      '🏁'],
                                                $evName === 'page_transition'       => ['bg-teal-500',    'bg-teal-50 dark:bg-teal-900/20',         '➡️'],
                                                $evName === 'scroll_depth_final'    => ['bg-indigo-400',  'bg-indigo-50 dark:bg-indigo-900/20',     '↕️'],
                                                $evName === 'element_click'         => ['bg-orange-500',  'bg-orange-50 dark:bg-orange-900/20',     '🖱️'],
                                                $evName === 'rage_click'            => ['bg-red-500',     'bg-red-50 dark:bg-red-900/20',           '😡'],
                                                $evName === 'exit_intent'           => ['bg-red-400',     'bg-red-50 dark:bg-red-900/15',           '🚪'],
                                                $evName === 'autofill_detected'     => ['bg-cyan-500',    'bg-cyan-50 dark:bg-cyan-900/20',         '🤖'],
                                                $evName === 'share_clicked'         => ['bg-sky-500',     'bg-sky-50 dark:bg-sky-900/20',           '🔗'],
                                                $evName === 'newsletter_signup'     => ['bg-emerald-500', 'bg-emerald-50 dark:bg-emerald-900/20',   '✉️'],
                                                $evName === 'image_attention'       => ['bg-amber-400',   'bg-amber-50 dark:bg-amber-900/20',       '🖼️'],
                                                $evName === 'image_swipe'           => ['bg-amber-400',   'bg-amber-50 dark:bg-amber-900/20',       '👆'],
                                                $evName === 'image_hover'           => ['bg-amber-300',   'bg-amber-50 dark:bg-amber-900/15',       '🖱️'],
                                                $evName === 'image_zoom'            => ['bg-amber-500',   'bg-amber-50 dark:bg-amber-900/20',       '🔍'],
                                                $evName === 'description_read'      => ['bg-teal-400',    'bg-teal-50 dark:bg-teal-900/20',         '📖'],
                                                $evName === 'review_read'           => ['bg-yellow-500',  'bg-yellow-50 dark:bg-yellow-900/20',     '📊'],
                                                $evName === 'accordion_opened'      => ['bg-violet-400',  'bg-violet-50 dark:bg-violet-900/20',     '📂'],
                                                $evName === 'accordion_closed'      => ['bg-slate-400',   'bg-slate-50 dark:bg-slate-700/30',       '📂'],
                                                $evName === 'notify_me'             => ['bg-pink-500',    'bg-pink-50 dark:bg-pink-900/20',         '🔔'],
                                                str_contains($evName, 'search')     => ['bg-pink-500',    'bg-pink-50 dark:bg-pink-900/20',         '🔍'],
                                                str_contains($evName, 'video')      => ['bg-rose-500',    'bg-rose-50 dark:bg-rose-900/20',         '🎬'],
                                                str_contains($evName, 'wishlist')   => ['bg-fuchsia-500', 'bg-fuchsia-50 dark:bg-fuchsia-900/20',   '❤️'],
                                                str_contains($evName, 'variant')    => ['bg-violet-500',  'bg-violet-50 dark:bg-violet-900/20',     '🎨'],
                                                str_contains($evName, 'recommend')  => ['bg-sky-500',     'bg-sky-50 dark:bg-sky-900/20',           '📊'],
                                                default                             => ['bg-slate-400',   'bg-slate-50 dark:bg-slate-700/30',       '•'],
                                            };

                                            $labelMap = [
                                                'session_started'      => 'Session started',
                                                'session_ended'        => 'Session ended',
                                                'page_viewed'          => 'Page viewed',
                                                'product_viewed'       => 'Viewed product',
                                                'add_to_cart'          => 'Added to cart',
                                                'remove_from_cart'     => 'Removed from cart',
                                                'checkout_started'     => 'Started checkout',
                                                'checkout_step'        => 'Checkout step',
                                                'order_placed'         => 'Order placed',
                                                'element_click'        => 'Clicked',
                                                'rage_click'           => 'Rage clicked',
                                                'scroll_depth_final'   => 'Scrolled page',
                                                'page_transition'      => 'Navigated to',
                                                'search'               => 'Searched',
                                                'search_click'         => 'Clicked search result',
                                                'search_refine'        => 'Refined search',
                                                'search_results'       => 'Search results',
                                                'quick_view'           => 'Quick viewed',
                                                'variant_selected'     => 'Selected variant',
                                                'add_to_wishlist'      => 'Added to wishlist',
                                                'notify_me'            => 'Requested "Notify me"',
                                                'product_interaction'  => 'Product interaction',
                                                'exit_intent'          => 'Exit intent',
                                                'autofill_detected'    => 'Autofill detected',
                                                'share_clicked'        => 'Share clicked',
                                                'newsletter_signup'    => 'Newsletter signup',
                                                'image_attention'      => 'Image attention',
                                                'image_swipe'          => 'Image swiped',
                                                'image_hover'          => 'Image hover',
                                                'image_zoom'           => 'Image zoomed',
                                                'description_read'     => 'Description read',
                                                'review_read'          => 'Review read',
                                                'accordion_opened'     => 'Accordion opened',
                                                'accordion_closed'     => 'Accordion closed',
                                                'video_played'         => 'Video played',
                                                'video_paused'         => 'Video paused',
                                                'video_ended'          => 'Video ended',
                                                'recommendation_click' => 'Recommendation click',
                                            ];
                                            $readableLabel = $labelMap[$evName] ?? str_replace('_', ' ', ucfirst($evName));

                                            $elemText = !empty($ev['target_element_text']) ? $ev['target_element_text'] : (!empty($props['text']) ? $props['text'] : '');
                                            $trackKey = !empty($ev['target_data_track'])   ? $ev['target_data_track']   : (!empty($props['data_track']) ? $props['data_track'] : '');
                                            $prodName = $ev['product_name'] ?? $props['product_name'] ?? '';
                                            $prodId   = $ev['product_id']   ?? $props['product_id']   ?? '';
                                            $prodSku  = $ev['sku']          ?? $props['sku']          ?? '';
                                            $varColor = $ev['variant_color']?? $props['variant_color']?? '';
                                            $varSize  = $ev['variant_size'] ?? $props['variant_size'] ?? $props['variant'] ?? '';
                                            $prodPrice= (float)($ev['price'] ?? $ev['event_value'] ?? $props['price'] ?? 0);
                                            $prodQty  = (int)($ev['quantity'] ?? $props['quantity'] ?? 0);
                                            $searchQ  = $props['query']   ?? '';
                                            $toPath   = $props['to_path'] ?? '';

                                            $isProductEvent = in_array($evName, [
                                                'product_viewed','add_to_cart','remove_from_cart',
                                                'quick_view','variant_selected','add_to_wishlist',
                                                'search_click','recommendation_click',
                                            ], true);

                                            $headline = match(true) {
                                                in_array($evName, ['element_click','rage_click'], true) =>
                                                    ($elemText ? '"'.ucfirst(\Illuminate\Support\Str::limit($elemText, 50)).'"' : ($trackKey ?: null)),
                                                $isProductEvent =>
                                                    ($prodName ? \Illuminate\Support\Str::limit($prodName, 45) : null),
                                                $evName === 'order_placed' =>
                                                    '£'.number_format((float)($ev['event_value'] ?? 0), 2),
                                                in_array($evName, ['search','search_click','search_refine','search_results'], true) =>
                                                    ($searchQ ? '"'.ucfirst(\Illuminate\Support\Str::limit($searchQ, 50)).'"' : null),
                                                $evName === 'scroll_depth_final' =>
                                                    ((int)($ev['scroll_depth_pct'] ?? $props['depth_percent'] ?? 0) > 0
                                                        ? (int)($ev['scroll_depth_pct'] ?? $props['depth_percent'] ?? 0).'% of page'
                                                        : null),
                                                $evName === 'page_transition' =>
                                                    ($toPath ?: null),
                                                $evName === 'session_started' =>
                                                    (!empty($ev['utm_source'])
                                                        ? $ev['utm_source'].($ev['utm_medium'] ? ' / '.$ev['utm_medium'] : '')
                                                        : (!empty($ev['referrer']) ? 'from '.\Illuminate\Support\Str::limit($ev['referrer'],40) : 'Direct visit')),
                                                $evName === 'checkout_started' =>
                                                    ((float)($ev['event_value'] ?? 0) > 0 ? '£'.number_format((float)$ev['event_value'],2) : null),
                                                $evName === 'checkout_step' =>
                                                    (!empty($props['step']) ? 'Step '.$props['step'].(!empty($props['step_name']) ? ' — '.$props['step_name'] : '') : null),
                                                $evName === 'image_attention' =>
                                                    (!empty($props['image_index']) ? 'Image #'.$props['image_index'].(!empty($props['duration_ms']) ? ' ('.round($props['duration_ms']/1000).'s)' : '') : null),
                                                $evName === 'image_swipe' =>
                                                    (!empty($props['direction']) ? 'Swiped '.$props['direction'].(!empty($props['to_image']) ? ' to image #'.$props['to_image'] : '') : null),
                                                $evName === 'image_hover' =>
                                                    (!empty($props['image_index']) ? 'Image #'.$props['image_index'].(!empty($props['duration_ms']) ? ' ('.round($props['duration_ms']/1000,1).'s)' : '') : null),
                                                $evName === 'image_zoom' =>
                                                    (!empty($props['image_index']) ? 'Image #'.$props['image_index'].(!empty($props['duration_ms']) ? ' ('.round($props['duration_ms']/1000).'s)' : '') : null),
                                                $evName === 'description_read' =>
                                                    (!empty($props['section']) ? '"'.$props['section'].'"'.(!empty($props['read_depth_pct']) ? ' — '.$props['read_depth_pct'].'%' : '') : null),
                                                $evName === 'review_read' =>
                                                    (!empty($props['average_rating']) ? $props['average_rating'].' ★'.(!empty($props['reviews_read']) ? ' ('.$props['reviews_read'].' reviews)' : '') : null),
                                                $evName === 'accordion_opened' || $evName === 'accordion_closed' =>
                                                    (!empty($props['label']) ? '"'.$props['label'].'"'.(!empty($props['open_duration_ms']) ? ' — '.round($props['open_duration_ms']/1000).'s' : '') : null),
                                                str_contains($evName, 'video') =>
                                                    (!empty($props['watched_pct']) ? 'Watched: '.$props['watched_pct'].'%'.(!empty($props['at_seconds']) ? ' ('.round($props['at_seconds'],0).'s)' : '') : null),
                                                $evName === 'exit_intent' =>
                                                    (!empty($props['description']) ? $props['description'] : null),
                                                $evName === 'autofill_detected' =>
                                                    (!empty($props['description']) ? $props['description'] : null),
                                                $evName === 'share_clicked' =>
                                                    (!empty($props['platform']) ? 'Shared on '.$props['platform'] : null),
                                                $evName === 'newsletter_signup' =>
                                                    (!empty($props['email']) ? $props['email'] : null),
                                                default => null,
                                            };
                                        @endphp

                                        <div class="relative flex gap-2.5 py-1.5 pl-6">
                                            <div class="absolute left-1.5 top-3.5 w-2.5 h-2.5 rounded-full {{ $dot }} ring-2 ring-white dark:ring-slate-800 shrink-0 z-10"></div>

                                            <div class="flex-1 min-w-0 {{ $evBg }} rounded-lg px-3 py-2">
                                                {{-- Top row: label + headline + time --}}
                                                <div class="flex items-baseline justify-between gap-2 flex-wrap">
                                                    <div class="flex items-center gap-1.5 min-w-0 flex-wrap">
                                                        <span class="text-[13px] leading-none">{{ $icon }}</span>
                                                        <span class="text-[12px] font-semibold text-slate-700 dark:text-slate-200">{{ $readableLabel }}</span>
                                                        @if($headline)
                                                            <span class="text-[12px] font-medium text-slate-600 dark:text-slate-300 truncate max-w-xs">{{ $headline }}</span>
                                                        @endif
                                                        @if($ev['is_rage_click'])
                                                            <span class="px-1 py-0.5 rounded text-[9px] font-bold bg-red-200 dark:bg-red-900/60 text-red-700 dark:text-red-300">RAGE</span>
                                                        @endif
                                                        @if($ev['is_dead_click'])
                                                            <span class="px-1 py-0.5 rounded text-[9px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-500">DEAD</span>
                                                        @endif
                                                    </div>
                                                    <span class="text-[10px] text-slate-400 dark:text-slate-500 whitespace-nowrap font-mono shrink-0">{{ $evTs->format('H:i:s') }}</span>
                                                </div>

                                                {{-- ── Structured product detail block ─────────── --}}
                                                @if($isProductEvent && ($prodName || $prodId || $prodSku))
                                                    <div class="prod-detail text-slate-600 dark:text-slate-300 mt-1.5">
                                                        @if($prodName)<div><span class="pd-lbl">Title: </span><span class="pd-val">{{ $prodName }}</span></div>@endif
                                                        @if($prodId)<div><span class="pd-lbl">ID: </span><span class="pd-val font-mono text-[10px]">{{ $prodId }}</span></div>@endif
                                                        @if($prodSku)<div><span class="pd-lbl">SKU: </span><span class="pd-val font-mono text-[10px]">{{ $prodSku }}</span></div>@endif
                                                        @if($varColor)<div><span class="pd-lbl">Color: </span><span class="pd-val">{{ $varColor }}</span></div>@endif
                                                        @if($varSize)<div><span class="pd-lbl">Variant: </span><span class="pd-val">{{ $varSize }}</span></div>@endif
                                                        @if($prodPrice > 0)<div><span class="pd-lbl">Price: </span><span class="pd-val text-green-600 dark:text-green-400">£{{ number_format($prodPrice, 2) }}</span></div>@endif
                                                        @if($prodQty > 0)<div><span class="pd-lbl">Qty: </span><span class="pd-val">{{ $prodQty }}</span></div>@endif
                                                    </div>
                                                @endif

                                                {{-- Secondary details --}}
                                                <div class="mt-0.5 flex flex-wrap gap-x-3 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">

                                                    @if(!$isProductEvent && (float)($ev['event_value'] ?? 0) > 0 && $evName !== 'order_placed')
                                                        <span class="font-semibold text-green-600 dark:text-green-400">£{{ number_format((float)$ev['event_value'],2) }}@if((int)($ev['quantity'] ?? 0) > 1) × {{ $ev['quantity'] }}@endif</span>
                                                    @endif

                                                    @if($evName === 'order_placed')
                                                        @if((int)($ev['quantity'] ?? 0) > 0)<span>{{ $ev['quantity'] }} item{{ (int)$ev['quantity'] > 1 ? 's' : ''}}</span>@endif
                                                        @if(!empty($ev['order_id']))<span class="font-mono opacity-60">{{ $ev['order_id'] }}</span>@endif
                                                    @endif

                                                    @if((int)($ev['active_time_ms'] ?? 0) >= 1000 && !in_array($evName, ['element_click','rage_click','scroll_depth_final'], true))
                                                        <span><span class="opacity-60">Active:</span> {{ round($ev['active_time_ms']/1000,1) }}s</span>
                                                    @endif

                                                    @if(in_array($evName, ['element_click','rage_click'], true))
                                                        @php $tagInfo = trim(($ev['target_element_tag'] ?? $props['tag'] ?? '').' '.(!empty($ev['target_element_id']) ? '#'.$ev['target_element_id'] : '')); @endphp
                                                        @if($tagInfo)<span class="font-mono text-[10px] opacity-70">{{ $tagInfo }}</span>@endif
                                                        @if($trackKey && !$elemText)<span><span class="opacity-60">data-track:</span> {{ $trackKey }}</span>@endif
                                                    @endif

                                                    @if(str_contains($evName, 'search') && isset($props['results_count']))
                                                        <span>{{ number_format((int)$props['results_count']) }} results</span>
                                                    @endif

                                                    @if($evName === 'scroll_depth_final' && (int)($ev['scroll_depth_px'] ?? $props['depth_px'] ?? 0) > 0)
                                                        <span class="opacity-60">{{ number_format((int)($ev['scroll_depth_px'] ?? $props['depth_px'])) }}px</span>
                                                    @endif

                                                    @if($evName === 'page_transition' && (int)($props['time_on_from_ms'] ?? 0) > 0)
                                                        <span><span class="opacity-60">Spent:</span> {{ round($props['time_on_from_ms']/1000) }}s on previous page</span>
                                                    @endif

                                                    @if($evName === 'session_started' && !empty($ev['utm_campaign']))
                                                        <span><span class="opacity-60">Campaign:</span> {{ $ev['utm_campaign'] }}</span>
                                                    @endif

                                                    @if(!empty($props) && count($props) > 0)
                                                        <button onclick="this.nextElementSibling.classList.toggle('hidden')"
                                                                class="text-indigo-400 hover:text-indigo-600 cursor-pointer underline underline-offset-2 text-[10px]">+props</button>
                                                        <div class="hidden w-full mt-1 font-mono text-[10px] text-slate-500 dark:text-slate-400 break-all bg-white/60 dark:bg-slate-800/60 rounded px-2 py-1">
                                                            @foreach($props as $pk => $pv)
                                                                <span class="mr-3"><span class="opacity-60">{{ $pk }}:</span> {{ is_array($pv) ? json_encode($pv) : \Illuminate\Support\Str::limit((string)$pv, 80) }}</span>
                                                            @endforeach
                                                        </div>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                {{-- P2: Page was visited but all events are internal/noise.
                                     Show a minimal "page loaded" indicator so the card is never blank. --}}
                                <div class="px-4 py-3 ev-line">
                                    <div class="relative flex gap-2.5 py-1 pl-6">
                                        <div class="absolute left-1.5 top-3 w-2.5 h-2.5 rounded-full bg-sky-400 ring-2 ring-white dark:ring-slate-800 shrink-0 z-10"></div>
                                        <div class="flex-1 bg-sky-50 dark:bg-sky-900/20 rounded-lg px-3 py-2 text-[12px]">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="flex items-center gap-1.5 font-semibold text-slate-700 dark:text-slate-200">
                                                    <span>📄</span> Page visited
                                                </span>
                                                <span class="text-[10px] text-slate-400 font-mono">{{ $bStart->format('H:i:s') }}</span>
                                            </div>
                                            @if($pageActiveMs > 0 || $pageScrollMax > 0)
                                                <div class="mt-0.5 text-[11px] text-slate-400 dark:text-slate-500">
                                                    @if($pageActiveMs > 0)<span>🕐 {{ round($pageActiveMs/1000) }}s active</span>@endif
                                                    @if($pageScrollMax > 0)<span class="ml-2">↕ {{ $pageScrollMax }}% scrolled</span>@endif
                                                </div>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>{{-- /event list --}}
                    </div>{{-- /batch card --}}

                    {{-- Session start marker at the bottom --}}
                    @if($bi === $totalPages - 1)
                        <div class="flex items-center gap-2 pl-9 mt-2 text-[11px] text-slate-400 dark:text-slate-500">
                            <div class="w-0.5 h-4 bg-slate-200 dark:bg-slate-600 ml-0.5"></div>
                            <span class="font-semibold">🚀 Session start — {{ \Carbon\Carbon::parse($sess['start_time'])->format('d M Y, H:i:s') }}</span>
                        </div>
                    @endif

                </div>{{-- /page-batch --}}
            @empty
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-8 text-center">
                    <p class="text-slate-400 dark:text-slate-500">No page batches found for this session.</p>
                </div>
            @endforelse

        </div>{{-- /session-panel --}}
    @empty
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
            <p class="text-slate-400 dark:text-slate-500">No sessions found for this visitor.</p>
        </div>
    @endforelse

</div>
@endsection

@push('js')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const selector = document.getElementById('session-selector');
    const panels   = Array.from(document.querySelectorAll('.session-panel'));

    function activateSession(sessionId) {
        panels.forEach(p => p.classList.toggle('hidden', p.dataset.sessionId !== sessionId));
    }

    if (selector) {
        selector.addEventListener('change', function () {
            activateSession(this.value);
            const panel = document.querySelector('[data-session-id="' + this.value + '"]');
            // if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
        // Activate selected session on load
        activateSession(selector.value);
    }
});

function toggleBatch(batchId) {
    const body = document.getElementById(batchId + '-body');
    const icon = document.getElementById(batchId + '-icon');
    if (!body) return;
    const hide = body.style.display !== 'none';
    body.style.display = hide ? 'none' : '';
    if (icon) icon.style.transform = hide ? 'rotate(-90deg)' : '';
}
</script>
@endpush

