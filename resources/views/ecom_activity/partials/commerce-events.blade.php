@props([
    'events' => [],
    'sessionKey' => '',
    'hasOrder' => false,
    'productDrill' => false,
    'detailLabel' => null,
])

@if ($events !== [])
    <div class="etd-commerce-events">
        @foreach ($events as $event)
            @php
                $eventKey = $sessionKey.':'.($event['id'] ?? $loop->index);
                $isOrder = ($event['stage'] ?? '') === 'payment_success';
                $label = filled($detailLabel)
                    ? $detailLabel
                    : ($event['trigger_label'] ?? ($event['stage_label'] ?? 'Details'));
            @endphp
            <button
                type="button"
                class="etd-commerce-event-trigger"
                @class([
                    'etd-commerce-event-trigger--order' => $isOrder && ! $productDrill,
                    'etd-commerce-event-trigger--product-drill' => $productDrill,
                    'etd-commerce-event-trigger--product-drill-sold' => $productDrill && $isOrder,
                ])
                @click="openEvent = openEvent === @js($eventKey) ? null : @js($eventKey)"
                :aria-expanded="openEvent === @js($eventKey)"
            >
                <svg class="etd-commerce-event-trigger__icon" :class="{ 'is-open': openEvent === @js($eventKey) }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="etd-commerce-event-trigger__label">{{ $label }}</span>
            </button>
        @endforeach
    </div>
@endif
