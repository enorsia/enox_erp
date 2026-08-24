@props([
    'events' => [],
    'sessionKey' => '',
    'hasOrder' => false,
])

@if ($events !== [])
    <div class="etd-commerce-events">
        @foreach ($events as $event)
            @php
                $eventKey = $sessionKey.':'.($event['id'] ?? $loop->index);
                $isOrder = ($event['stage'] ?? '') === 'payment_success';
            @endphp
            <button
                type="button"
                class="etd-commerce-event-trigger"
                @class(['etd-commerce-event-trigger--order' => $isOrder])
                @click="openEvent = openEvent === @js($eventKey) ? null : @js($eventKey)"
                :aria-expanded="openEvent === @js($eventKey)"
            >
                <svg class="etd-commerce-event-trigger__icon" :class="{ 'is-open': openEvent === @js($eventKey) }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                    <path stroke-linecap="round" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="etd-commerce-event-trigger__label">{{ $event['trigger_label'] ?? ($event['stage_label'] ?? 'Details') }}</span>
            </button>
        @endforeach
    </div>
@endif
