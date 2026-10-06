@props(['session'])

@if ($session->isRegisteredUser())
    <div>{{ $session->user_name ?: 'User #'.$session->user_id }}</div>
    @if ($session->user_email)
        <div class="etd-subtle">{{ $session->user_email }}</div>
    @endif
    @if ($session->user_phone)
        <div class="etd-subtle">{{ $session->user_phone }}</div>
    @endif
@elseif ($session->isGuestCheckout())
    <div class="etd-session-identity__name-row">
        <span class="etd-session-identity__name">{{ $session->user_name ?: '—' }}</span>
        <span class="etd-badge low etd-session-identity__badge">Guest</span>
    </div>
    @if ($session->user_email)
        <div class="etd-subtle">{{ $session->user_email }}</div>
    @endif
    @if ($session->user_phone)
        <div class="etd-subtle">{{ $session->user_phone }}</div>
    @endif
@elseif ($session->is_logged_in && $session->user_id)
    <span class="etd-badge mid">User #{{ $session->user_id }}</span>
@else
    <span class="etd-badge low">Guest</span>
@endif
