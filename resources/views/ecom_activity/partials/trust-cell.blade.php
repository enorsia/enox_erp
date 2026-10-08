<div class="visitor-classification-badge inline-flex flex-col items-start gap-0.5">
    <div class="flex items-center gap-1.5 flex-wrap">
        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $trust['badge_class'] }}"
              title="{{ $trust['help'] }}">
            {{ $trust['label'] }}
        </span>
        @if ($trust['country_code'] && $trust['country_code'] !== 'GB')
            <span class="etd-country-tag" title="{{ $trust['country_label'] ?? $trust['country_code'] }}">{{ $trust['country_code'] }}</span>
        @endif
    </div>
    @if ($trust['ips'] !== [])
        <span class="etd-visitor-ip" title="{{ count($trust['ips']) > 1 ? 'Visitor IPs' : 'Visitor IP' }}">@foreach ($trust['ips'] as $ipLines)<span @class(['etd-visitor-ip__item', 'etd-visitor-ip__item--split' => count($ipLines) > 1])>{!! collect($ipLines)->map(fn ($part) => e($part))->implode('<wbr>') !!}@unless ($loop->last),@endunless</span>@endforeach</span>
    @endif
</div>
