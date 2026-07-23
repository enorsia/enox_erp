@props(['sessionId'])

<span class="etd-chip etd-chip--session etd-hover-tip" tabindex="0" aria-label="Session {{ $sessionId }}">
    <span class="etd-chip--session__text">{{ $sessionId }}</span>
    <span class="etd-hover-tip__content" role="tooltip">{{ $sessionId }}</span>
</span>
