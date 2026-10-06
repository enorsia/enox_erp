@props(['session'])

@php $botCtx = $session->botContext; @endphp

<div class="section-card">
    <div class="section-title">Visitor trust</div>
    <div class="text-[13px]">
        <div class="mb-3">
            @include('ecom_tracker.partials.visitor-classification-badge', ['session' => $session, 'mode' => 'detailed'])
        </div>
        <p class="text-[12px] text-slate-600 dark:text-slate-300 m-0">{{ $session->marketer_reason_help }}</p>
        @if($botCtx?->cf_bot_score)
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 mb-0">{{ $botCtx->marketer_trust_score_label }}</p>
        @endif

        <details class="mt-4 group">
            <summary class="cursor-pointer text-[11px] font-semibold text-slate-500 dark:text-slate-400 list-none flex items-center gap-1.5">
                <svg class="w-3 h-3 transition-transform group-open:rotate-90" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 5l7 7-7 7"/></svg>
                Technical details
            </summary>
            <div class="mt-2 space-y-2 text-[11px] text-slate-600 dark:text-slate-300">
                <div><span class="text-slate-400">IP:</span> {{ $botCtx?->client_ip ?? $session->ip ?? '—' }}</div>
                @if($botCtx?->cf_ray)
                    <div><span class="text-slate-400">CF-Ray:</span> {{ $botCtx->cf_ray }}</div>
                @endif
                @if($botCtx?->user_agent)
                    <div class="break-all"><span class="text-slate-400">User-Agent:</span> {{ $botCtx->user_agent }}</div>
                @endif
            </div>
        </details>
    </div>
</div>
