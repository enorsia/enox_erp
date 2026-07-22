@props(['session', 'mode' => 'compact'])

@php
    $classification = $session->visitorClassification();
    $badgeClass = $session->marketer_type_badge_class;
    $label = $session->marketer_type_label;
    $botCtx = $session->botContext;
    $subtitle = $botCtx?->marketer_user_agent_hint ?? $botCtx?->marketer_reason_label ?? ($classification === 'unclassified' ? null : $session->marketer_reason_label);
    $countryLabel = $session->marketer_country_label;
    $countryCode = $session->marketer_country_code;
    $isUk = ($botCtx?->is_uk_visitor ?? strtoupper((string) ($session->country ?? '')) === 'GB');
@endphp

<div class="visitor-classification-badge {{ $mode === 'compact' ? 'flex items-center gap-1.5 flex-wrap' : '' }}">
    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $badgeClass }}"
          title="{{ $session->marketer_reason_help }}">
        {{ $label }}
        @if($mode === 'detailed' && $botCtx?->marketer_confidence_label)
            <span class="opacity-75 font-normal">· {{ $botCtx->marketer_confidence_label }}</span>
        @endif
    </span>
    @if($mode === 'compact' && $countryCode)
        <span class="etd-country-tag {{ $isUk ? 'etd-country-tag--uk' : '' }}"
              title="{{ $countryLabel ?? $countryCode }}">
            {{ $countryCode }}
        </span>
    @endif
    @if($mode === 'detailed' && $subtitle)
        <div class="text-[10px] text-slate-500 dark:text-slate-400 mt-0.5">{{ $subtitle }}</div>
    @endif
    @if($mode === 'detailed' && $countryLabel)
        <div class="mt-1">
            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $isUk ? 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/50' : 'bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 border-slate-200 dark:border-slate-600' }}">
                {{ $countryLabel }}
            </span>
        </div>
    @endif
</div>
