@php
    $displayValue = $displayValue ?? $value;
@endphp
<div class="flex items-center justify-between gap-3 min-w-[140px]">
    <div class="ssr-progress ssr-progress--{{ $type }} ssr-progress--{{ $level }} flex-1 {{ !empty($percent) && $percent > 0 ? '' : 'ssr-progress--empty' }}">
        <div class="ssr-progress-bar" style="width: {{ $percent }}%;"
             role="progressbar"
             aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100"></div>
        <span class="ssr-progress-label">{{ $percent }}%</span>
    </div>
    <span class="ssr-value shrink-0">{{ $displayValue }}</span>
</div>
