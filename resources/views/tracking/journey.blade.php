@extends('layouts.app')

@section('title', 'User Journey')

@push('css')
<style>
    .timeline-line { position: relative; }
    .timeline-line::before {
        content: '';
        position: absolute;
        left: 15px;
        top: 0;
        bottom: 0;
        width: 2px;
        background: rgba(148,163,184,.2);
    }
    .dark .timeline-line::before { background: rgba(71,85,105,.4); }

    .session-tab.session-active {
        background: rgb(79 70 229) !important;
        border-color: rgb(79 70 229) !important;
        color: #fff !important;
        box-shadow: 0 8px 20px rgba(79, 70, 229, .22);
        transform: translateY(-1px);
    }

    .dark .session-tab.session-active {
        background: rgb(99 102 241) !important;
        border-color: rgb(99 102 241) !important;
        color: #fff !important;
        box-shadow: 0 8px 20px rgba(99, 102, 241, .26);
    }
</style>
@endpush

@section('content')
<div class="p-5 lg:p-6 max-w-6xl mx-auto">

    {{-- ── HEADER ── --}}
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

    {{-- ── SUMMARY CARDS ── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
        @php
            $cards = [
                ['label' => 'Total Events',   'value' => number_format($summary['total_events'] ?? 0),   'color' => 'blue'],
                ['label' => 'Sessions',        'value' => number_format($summary['total_sessions'] ?? 0), 'color' => 'purple'],
                ['label' => 'Page Views',      'value' => number_format($summary['page_views'] ?? 0),     'color' => 'sky'],
                ['label' => 'Orders',          'value' => number_format($summary['orders'] ?? 0),         'color' => 'green'],
                ['label' => 'Revenue',         'value' => '£' . number_format((float)($summary['revenue'] ?? 0), 2), 'color' => 'emerald'],
                ['label' => 'Active Since',    'value' => $summary['first_seen'] ? \Carbon\Carbon::parse($summary['first_seen'])->format('d M Y') : '—', 'color' => 'amber'],
            ];
            $colorMap = [
                'blue'    => 'bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300',
                'purple'  => 'bg-purple-50 dark:bg-purple-900/20 text-purple-700 dark:text-purple-300',
                'sky'     => 'bg-sky-50 dark:bg-sky-900/20 text-sky-700 dark:text-sky-300',
                'green'   => 'bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300',
                'emerald' => 'bg-emerald-50 dark:bg-emerald-900/20 text-emerald-700 dark:text-emerald-300',
                'amber'   => 'bg-amber-50 dark:bg-amber-900/20 text-amber-700 dark:text-amber-300',
            ];
        @endphp
        @foreach($cards as $card)
            <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4">
                <p class="text-[11px] text-slate-400 dark:text-slate-500 mb-1">{{ $card['label'] }}</p>
                <p class="text-[18px] font-bold {{ explode(' ', $colorMap[$card['color']])[2] ?? 'text-slate-700' }} dark:{{ explode(' ', $colorMap[$card['color']])[3] ?? '' }}">
                    {{ $card['value'] }}
                </p>
            </div>
        @endforeach
    </div>

    {{-- ── DEVICE / GEO INFO ── --}}
    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-6">
        <p class="text-[11px] font-semibold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-3">Visitor Details</p>
        <div class="flex flex-wrap gap-x-6 gap-y-2 text-[13px]">
            @if($summary['device_type'])
                <div><span class="text-slate-400">Device</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ ucfirst($summary['device_type']) }}</span></div>
            @endif
            @if($summary['browser'])
                <div><span class="text-slate-400">Browser</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ $summary['browser'] }}</span></div>
            @endif
            @if($summary['os'])
                <div><span class="text-slate-400">OS</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ $summary['os'] }}</span></div>
            @endif
            @if($summary['screen_resolution'])
                <div><span class="text-slate-400">Screen</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ $summary['screen_resolution'] }}</span></div>
            @endif
            @if($summary['country'] || $summary['city'])
                <div><span class="text-slate-400">Location</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ implode(', ', array_filter([$summary['city'], $summary['country']])) }}</span></div>
            @endif
            @if($summary['ip_address'])
                <div><span class="text-slate-400">IP</span> <span class="text-slate-700 dark:text-slate-200 font-medium font-mono ml-1">{{ $summary['ip_address'] }}</span></div>
            @endif
            @if($summary['language'])
                <div><span class="text-slate-400">Language</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ $summary['language'] }}</span></div>
            @endif
            @if($summary['first_seen'])
                <div><span class="text-slate-400">Entry</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ \Carbon\Carbon::parse($summary['first_seen'])->format('d M Y, H:i') }}</span></div>
            @endif
            @if($summary['last_seen'])
                <div><span class="text-slate-400">Last Seen</span> <span class="text-slate-700 dark:text-slate-200 font-medium ml-1">{{ \Carbon\Carbon::parse($summary['last_seen'])->format('d M Y, H:i') }}</span></div>
            @endif
        </div>
    </div>

    {{-- ── SESSION TABS ── --}}
    @if(!empty($sessions))
        <div id="session-tabs" class="mb-4 flex items-center gap-2 flex-wrap">
            <span class="text-[12px] text-slate-400 dark:text-slate-500 font-semibold">Sessions:</span>
            @foreach($sessions as $si => $sess)
                @php
                    $isActiveTab = $loop->last;
                    $hasOrderPlaced = (int) ($sess['order_placed'] ?? 0) > 0;
                @endphp
                <a href="javascript:void(0)"
                   data-session="{{ $sess['session_id'] }}"
                   class="session-tab inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-[11px] font-semibold border transition-colors
                          {{ $hasOrderPlaced
                              ? 'border-green-300 dark:border-green-700 bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-300'
                              : 'border-slate-200 dark:border-slate-600 bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700' }}
                          {{ $isActiveTab ? ' session-active' : '' }}">
                    <span>Session {{ $si + 1 }}</span>
                    {!! $hasOrderPlaced ? '<span>✓ Ordered</span>' : '' !!}
                </a>
            @endforeach
        </div>
    @endif

    {{-- ── JOURNEY TIMELINE (grouped by session) ── --}}
    <?php if (!empty($sessions)): ?>
        <?php foreach ($sessions as $si => $sess): ?>
        <?php
            $sessEvents = $eventsBySession[$sess['session_id']] ?? [];
            $duration   = (int) $sess['duration_seconds'];
            $durationStr = $duration >= 3600
                ? gmdate('H:i:s', $duration)
                : ($duration >= 60 ? gmdate('i:s', $duration) . 'm' : $duration . 's');
            $isActivePanel = $si === array_key_last($sessions);
            $hasOrderPlaced = (int) ($sess['order_placed'] ?? 0) > 0;
        ?>
        <div id="session-{{ $sess['session_id'] }}"
             data-session-id="{{ $sess['session_id'] }}"
             class="session-panel bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl mb-5 overflow-hidden {{ $isActivePanel ? '' : 'hidden' }}">

            {{-- Session header --}}
            <div class="flex items-center justify-between px-5 py-3 bg-slate-50 dark:bg-slate-800/80 border-b border-slate-100 dark:border-slate-700">
                <div class="flex items-center gap-3 flex-wrap">
                    <span class="text-[12px] font-bold text-slate-700 dark:text-slate-200">Session {{ $si + 1 }}</span>
                    <span class="text-[11px] font-mono text-slate-400 dark:text-slate-500">{{ substr($sess['session_id'], 0, 36) }}</span>
                    {!! $hasOrderPlaced ? '<span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 dark:bg-green-900/40 text-green-700 dark:text-green-300 uppercase tracking-wide">✓ Ordered</span>' : '' !!}
                </div>
                <div class="flex items-center gap-4 text-[12px] text-slate-400 dark:text-slate-500 flex-wrap">
                    <span>⏱ {{ $durationStr }}</span>
                    <span>📄 {{ $sess['page_count'] }} pages</span>
                    <span>⚡ {{ $sess['event_count'] }} events</span>
                    <span class="hidden sm:inline">{{ \Carbon\Carbon::parse($sess['start_time'])->format('d M Y, H:i') }}</span>
                </div>
            </div>

            {{-- Events timeline --}}
            <?php if (!empty($sessEvents)): ?>
                <div class="px-5 py-4 timeline-line space-y-0.5">
                    @foreach($sessEvents as $ev)
                        @php
                            $evName = $ev['event_name'];
                            // Colour + icon per event type
                            [$dotColor, $dotBg, $icon] = match(true) {
                                $evName === 'order_placed'      => ['bg-green-500',  'bg-green-100 dark:bg-green-900/40',   '🛍️'],
                                $evName === 'add_to_cart'        => ['bg-blue-500',   'bg-blue-100 dark:bg-blue-900/40',     '🛒'],
                                $evName === 'checkout_started'   => ['bg-yellow-500', 'bg-yellow-100 dark:bg-yellow-900/40', '💳'],
                                $evName === 'product_viewed'     => ['bg-purple-500', 'bg-purple-100 dark:bg-purple-900/40', '👁️'],
                                $evName === 'page_viewed'        => ['bg-sky-500',    'bg-sky-100 dark:bg-sky-900/40',       '📄'],
                                $evName === 'session_started'    => ['bg-slate-500',  'bg-slate-100 dark:bg-slate-700',      '🚀'],
                                $evName === 'session_ended'      => ['bg-slate-400',  'bg-slate-100 dark:bg-slate-700',      '🏁'],
                                str_contains($evName, 'click')   => ['bg-orange-500', 'bg-orange-100 dark:bg-orange-900/40', '🖱️'],
                                str_contains($evName, 'scroll')  => ['bg-indigo-500', 'bg-indigo-100 dark:bg-indigo-900/40', '↕️'],
                                str_contains($evName, 'search')  => ['bg-pink-500',   'bg-pink-100 dark:bg-pink-900/40',     '🔍'],
                                default                          => ['bg-slate-400',  'bg-slate-100 dark:bg-slate-700',      '•'],
                            };

                            $props = [];
                            if (!empty($ev['properties']) && $ev['properties'] !== '{}') {
                                $props = json_decode($ev['properties'], true) ?? [];
                            }
                        @endphp

                        <div class="relative flex gap-3 py-1.5 pl-8 group">
                            {{-- Dot --}}
                            <div class="absolute left-2.5 top-3.5 w-3 h-3 rounded-full {{ $dotColor }} ring-2 ring-white dark:ring-slate-800 shrink-0 z-10"></div>

                            {{-- Card --}}
                            <div class="flex-1 min-w-0 {{ $dotBg }} rounded-lg px-3 py-2">
                                <div class="flex items-center justify-between gap-2 flex-wrap">
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-base leading-none">{{ $icon }}</span>
                                        <span class="text-[13px] font-semibold text-slate-700 dark:text-slate-200">
                                            {{ str_replace('_', ' ', ucfirst($evName)) }}
                                        </span>
                                        @if($ev['is_rage_click'])
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-red-100 dark:bg-red-900/40 text-red-600 dark:text-red-300">RAGE</span>
                                        @endif
                                        @if($ev['is_dead_click'])
                                            <span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-200 dark:bg-slate-700 text-slate-500 dark:text-slate-400">DEAD</span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-slate-400 dark:text-slate-500 whitespace-nowrap shrink-0">
                                        {{ \Carbon\Carbon::parse($ev['event_timestamp'])->format('H:i:s') }}
                                    </span>
                                </div>

                                {{-- Contextual sub-info --}}
                                <div class="mt-1 flex flex-wrap gap-x-4 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                                    @if(!empty($ev['page_path']))
                                        <span title="{{ $ev['page_url'] }}">
                                            <span class="opacity-60">Page:</span>
                                            {{ Str::limit($ev['page_path'], 60) }}
                                        </span>
                                    @endif
                                    @if(!empty($ev['page_title']))
                                        <span class="hidden sm:inline">
                                            <span class="opacity-60">Title:</span>
                                            {{ Str::limit($ev['page_title'], 50) }}
                                        </span>
                                    @endif
                                    @if(!empty($ev['product_name']))
                                        <span>
                                            <span class="opacity-60">Product:</span>
                                            {{ Str::limit($ev['product_name'], 40) }}
                                        </span>
                                    @endif
                                    @if((float)$ev['event_value'] > 0)
                                        <span class="font-semibold text-green-600 dark:text-green-400">
                                            £{{ number_format((float)$ev['event_value'], 2) }}
                                        </span>
                                    @endif
                                    @if((int)$ev['active_time_ms'] > 0)
                                        <span>
                                            <span class="opacity-60">Active:</span>
                                            {{ round($ev['active_time_ms'] / 1000, 1) }}s
                                        </span>
                                    @endif
                                    @if((int)$ev['scroll_depth_pct'] > 0)
                                        <span>
                                            <span class="opacity-60">Scroll:</span>
                                            {{ $ev['scroll_depth_pct'] }}%
                                        </span>
                                    @endif

                                    {{-- UTM / referrer on session_started only --}}
                                    @if($evName === 'session_started')
                                        @if(!empty($ev['utm_source']))
                                            <span>
                                                <span class="opacity-60">Source:</span>
                                                {{ $ev['utm_source'] }}{{ $ev['utm_medium'] ? '/' . $ev['utm_medium'] : '' }}
                                                {{ $ev['utm_campaign'] ? '(' . $ev['utm_campaign'] . ')' : '' }}
                                            </span>
                                        @elseif(!empty($ev['referrer']))
                                            <span>
                                                <span class="opacity-60">Referrer:</span>
                                                {{ Str::limit($ev['referrer'], 50) }}
                                            </span>
                                        @else
                                            <span><span class="opacity-60">Source:</span> Direct</span>
                                        @endif
                                    @endif

                                    {{-- Extra properties on hover --}}
                                    @if(!empty($props) && count($props) > 0)
                                        <button
                                            onclick="this.nextElementSibling.classList.toggle('hidden')"
                                            class="text-accent-400 hover:text-accent-600 cursor-pointer underline underline-offset-2">
                                            +props
                                        </button>
                                        <div class="hidden w-full mt-1 font-mono text-[10px] text-slate-500 dark:text-slate-400 break-all">
                                            @foreach($props as $pk => $pv)
                                                <span class="mr-3"><span class="opacity-70">{{ $pk }}:</span>
                                                {{ is_array($pv) ? json_encode($pv) : Str::limit((string)$pv, 80) }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            <?php else: ?>
                <p class="px-5 py-4 text-[13px] text-slate-400 dark:text-slate-500 italic">No events recorded for this session.</p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-10 text-center">
            <p class="text-slate-400 dark:text-slate-500">No sessions found for this visitor.</p>
        </div>
    <?php endif; ?>

        @push('js')
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const tabs = Array.from(document.querySelectorAll('.session-tab'));
                const panels = Array.from(document.querySelectorAll('.session-panel'));

                if (!tabs.length || !panels.length) return;

                function activate(sessionId) {
                    // Toggle tabs
                    tabs.forEach(t => {
                        if (t.dataset.session === sessionId) {
                            t.classList.add('session-active');
                        } else {
                            t.classList.remove('session-active');
                        }
                    });

                    // Toggle panels
                    panels.forEach(p => {
                        if (p.dataset.sessionId === sessionId) {
                            p.classList.remove('hidden');
                        } else {
                            p.classList.add('hidden');
                        }
                    });
                }

                // Attach click handlers
                tabs.forEach(t => {
                    t.addEventListener('click', function (e) {
                        e.preventDefault();
                        const sid = this.dataset.session;
                        if (!sid) return;
                        activate(sid);
                        // optional: focus panel
                        const panel = document.querySelector('[data-session-id="' + sid + '"]');
                        if (panel) panel.scrollIntoView({ behavior: 'smooth', block: 'start' });
                    });
                });

                // Default: activate the last tab (latest session)
                const defaultTab = tabs.find(t => t.classList.contains('session-active')) || tabs[tabs.length - 1];
                if (defaultTab) activate(defaultTab.dataset.session);
            });
        </script>
        @endpush

</div>
@endsection

