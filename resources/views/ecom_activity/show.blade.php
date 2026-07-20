@extends('layouts.app')

@section('title', 'Session Activity')

@section('content')
    @php
        $badgeColors = [
            'category_view' => 'badge-blue',
            'product_view' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-300',
            'product_view_popup' => 'bg-violet-100 text-violet-700 dark:bg-violet-900/30 dark:text-violet-300',
            'add_to_cart' => 'badge-amber',
            'begin_checkout' => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-300',
            'proceed_checkout' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
            'payment_success' => 'badge-green',
        ];
    @endphp

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
                    <p class="text-[12px] font-mono text-slate-400 mt-0.5">{{ $activityUser->session_id }}</p>
                </div>
            </div>
            <a href="{{ route('admin.ecom-activity.index', $returnQuery) }}"
               class="inline-flex items-center gap-1.5 px-3 h-9 text-[13px] border border-slate-200 dark:border-slate-600 rounded-lg bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                ← Back to list
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-[1fr_320px] gap-5">

            <div class="space-y-4">
                <div class="section-card">
                    <div class="section-title flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            Action Timeline
                        </div>
                        @if ($timeline->total() > 0)
                            <span class="text-[11px] font-normal text-slate-400">
                                {{ $timeline->total() }} {{ Str::plural('event', $timeline->total()) }}
                            </span>
                        @endif
                    </div>

                    @if ($timeline->total() > 0)
                        <p class="text-[11px] text-slate-400 mb-3">
                            Showing {{ $timeline->firstItem() }}–{{ $timeline->lastItem() }} of {{ $timeline->total() }}
                        </p>
                    @endif

                    @forelse ($timeline as $item)
                        @php
                            $badgeClass = $badgeColors[$item->action_type] ?? 'badge-amber';
                            $jsonPayload = match ($item->action_type) {
                                'add_to_cart' => $item->add_to_cart,
                                'begin_checkout' => $item->begin_checkout,
                                'proceed_checkout' => $item->proceed_to_checkout,
                                'payment_success' => $item->payment_success,
                                default => null,
                            };
                        @endphp
                        <div class="border border-slate-200 dark:border-slate-700 rounded-xl p-4 mb-3 last:mb-0">
                            <div class="flex flex-wrap items-center gap-2 mb-2">
                                <span class="badge-custom {{ $badgeClass }}">{{ str_replace('_', ' ', $item->action_type) }}</span>
                                <span class="text-[12px] text-slate-400">
                                    {{ \App\Support\TrackerTime::toLocal($item->created_at)?->format('d M Y, h:i:s A') }}
                                </span>
                                @if ($item->dwell_seconds !== null)
                                    <span class="text-[11px] text-slate-500">
                                        Dwell: {{ $item->dwell_seconds }}s
                                        @if ($item->is_grouped_product_view)
                                            <span class="text-slate-400">(combined)</span>
                                        @endif
                                    </span>
                                @endif
                            </div>

                            <div class="text-[12px] text-slate-500 dark:text-slate-400 mb-2">
                                @if ($item->referer || $item->page_url)
                                    <a href="{{ $item->referer }}" target="_blank" rel="noopener" class="text-accent-500 hover:underline">{{ $item->referer }}</a>
                                    <span class="mx-1">→</span>
                                    <a href="{{ $item->page_url }}" target="_blank" rel="noopener" class="text-accent-500 hover:underline">{{ $item->page_url }}</a>
                                @endif
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[12px]">
                                @if ($item->category_name)
                                    <div><span class="text-slate-400">Category:</span> {{ $item->category_name }} ({{ $item->category_code }})</div>
                                @endif
                                @if ($item->product_name)
                                    <div><span class="text-slate-400">Product:</span> {{ $item->product_name }}</div>
                                @endif
                                @if ($item->product_code)
                                    <div><span class="text-slate-400">Code:</span> {{ $item->product_code }}</div>
                                @endif
                                @if (in_array($item->action_type, ['product_view', 'product_view_popup'], true) && $item->color_timeline)
                                    <div class="sm:col-span-2">
                                        <span class="text-slate-400">Colors:</span>
                                        <span class="text-slate-700 dark:text-slate-200">{{ $item->color_timeline }}</span>
                                    </div>
                                @endif
                                @if ($item->product_price)
                                    <div><span class="text-slate-400">Price:</span> £{{ number_format($item->product_price, 2) }}</div>
                                @endif
                                @if ($item->action_type === 'add_to_cart' && is_array($item->add_to_cart))
                                    @if (! empty($item->add_to_cart['product_id']))
                                        <div><span class="text-slate-400">Product ID:</span> {{ $item->add_to_cart['product_id'] }}</div>
                                    @endif
                                    @if (! empty($item->add_to_cart['color_name']) || ! empty($item->add_to_cart['color_id']))
                                        <div>
                                            <span class="text-slate-400">Color:</span>
                                            {{ $item->add_to_cart['color_name'] ?: '—' }}
                                            @if (! empty($item->add_to_cart['color_id']))
                                                <span class="text-slate-400">(#{{ $item->add_to_cart['color_id'] }})</span>
                                            @endif
                                        </div>
                                    @endif
                                    @if (! empty($item->add_to_cart['size_name']) || ! empty($item->add_to_cart['size_id']))
                                        <div>
                                            <span class="text-slate-400">Size:</span>
                                            {{ $item->add_to_cart['size_name'] ?: '—' }}
                                            @if (! empty($item->add_to_cart['size_id']))
                                                <span class="text-slate-400">(#{{ $item->add_to_cart['size_id'] }})</span>
                                            @endif
                                        </div>
                                    @endif
                                @endif
                            </div>

                            @if ($jsonPayload)
                                <details class="mt-3">
                                    <summary class="text-[12px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer">View JSON payload</summary>
                                    <pre class="mt-2 p-3 rounded-lg bg-slate-50 dark:bg-slate-900/50 text-[11px] overflow-x-auto text-slate-700 dark:text-slate-200">{{ json_encode($jsonPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                                </details>
                            @endif

                            @if ($item->is_grouped_product_view)
                                <details class="mt-3">
                                    <summary class="text-[12px] font-medium text-slate-600 dark:text-slate-300 cursor-pointer">
                                        View {{ $item->actions->count() }} color segments
                                    </summary>
                                    <div class="mt-2 space-y-2">
                                        @foreach ($item->actions as $segmentAction)
                                            @php
                                                $segmentSeconds = ($segmentAction->start_time && $segmentAction->end_time)
                                                    ? $segmentAction->start_time->diffInSeconds($segmentAction->end_time)
                                                    : null;
                                            @endphp
                                            <div class="rounded-lg bg-slate-50 dark:bg-slate-900/50 px-3 py-2 text-[11px] text-slate-600 dark:text-slate-300">
                                                <span class="font-medium">{{ $segmentAction->general_color_name ?: 'Unknown' }}</span>
                                                @if ($segmentSeconds !== null)
                                                    <span class="text-slate-400">· {{ $segmentSeconds }}s</span>
                                                @endif
                                                <span class="text-slate-400">· {{ \App\Support\TrackerTime::toLocal($segmentAction->created_at ?? $segmentAction->start_time)?->format('h:i:s A') }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </details>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-slate-400 dark:text-slate-500">No actions recorded for this session.</p>
                    @endforelse

                    @include('layouts.pagination', ['paginator' => $timeline])
                </div>
            </div>

            <div class="space-y-4">
                <div class="section-card">
                    <div class="section-title">Session Summary</div>
                    <div class="divide-y divide-slate-100 dark:divide-slate-700/60 text-[13px]">
                        @foreach ([
                            'IP' => $activityUser->ip,
                            'Device' => ucfirst($activityUser->device_type ?? '—') . ' · ' . ($activityUser->browser ?? '') . ' · ' . ($activityUser->os ?? ''),
                            'User' => $activityUser->user_name
                                ? $activityUser->user_name . ($activityUser->user_email ? ' (' . $activityUser->user_email . ')' : '')
                                : ($activityUser->is_logged_in ? 'Logged in #' . $activityUser->user_id : 'Guest'),
                            'First seen' => \App\Support\TrackerTime::toLocal($activityUser->created_at)?->format('d M Y, h:i A'),
                            'Last active' => \App\Support\TrackerTime::toLocal($activityUser->last_active_at)?->format('d M Y, h:i A'),
                            'Landing page' => $activityUser->landing_page,
                            'UTM' => trim(($activityUser->utm_source ?? '') . ' / ' . ($activityUser->utm_medium ?? '') . ' / ' . ($activityUser->utm_campaign ?? ''), ' /'),
                        ] as $label => $value)
                            <div class="py-2.5 first:pt-0 last:pb-0">
                                <div class="text-[11px] uppercase tracking-wide text-slate-400 mb-0.5">{{ $label }}</div>
                                <div class="text-slate-700 dark:text-slate-200 break-all">{{ $value ?: '—' }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="section-card">
                    <div class="section-title">Funnel Progress</div>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($funnelSteps as $step)
                            @php $reached = in_array($step, $reachedSteps, true); @endphp
                            <span class="badge-custom {{ $reached ? ($badgeColors[$step] ?? 'badge-green') : 'bg-slate-100 text-slate-400 dark:bg-slate-700 dark:text-slate-500' }}">
                                {{ str_replace('_', ' ', $step) }}
                            </span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection
