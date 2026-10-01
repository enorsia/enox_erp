@props([
    'id' => 'etdDashboardSyncBtn',
    'syncUrl',
    'syncStatusUrl' => null,
    'pending' => 0,
    'lastSyncedAt' => null,
    'chainActive' => false,
])

<button
    type="button"
    id="{{ $id }}"
    class="etd-header-btn etd-header-btn--icon-only etd-header-btn--sync etd-print-hide{{ $pending > 0 ? ' etd-header-btn--has-badge' : '' }}"
    data-sync-url="{{ $syncUrl }}"
    data-sync-status-url="{{ $syncStatusUrl }}"
    data-sync-chain-active="{{ $chainActive ? '1' : '0' }}"
    aria-label="Sync tracker actions"
    title="@if ($pending > 0){{ $pending }} to sync (pending + failed, ≤5 attempts) @endif{{ $lastSyncedAt ? ' · Last sync '.$lastSyncedAt : '' }}"
>
    <svg class="etd-header-btn-icon" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h5M20 20v-5h-5"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M20 9a8 8 0 00-14.9-3M4 15a8 8 0 0014.9 3"/>
    </svg>
    @if ($pending > 0)
        <span class="etd-header-btn-badge etd-header-btn-badge--corner" aria-hidden="true">{{ $pending > 99 ? '99+' : $pending }}</span>
    @endif
</button>
