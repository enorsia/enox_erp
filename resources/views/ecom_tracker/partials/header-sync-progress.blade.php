@props([
    'cancelUrl' => null,
])

<div id="etdHeaderSyncProgressRow" class="etd-header-sync-progress-row etd-print-hide" hidden>
    <div id="etdHeaderSyncProgress" class="etd-header-sync-progress">
        <div id="etdHeaderSyncTrack" class="etd-header-sync-progress__track" aria-hidden="true">
            <div id="etdHeaderSyncBar" class="etd-header-sync-progress__bar" style="width: 0%"></div>
        </div>
        <div class="etd-header-sync-progress__message-row">
            <button
                type="button"
                id="etdHeaderSyncCancel"
                class="etd-header-sync-progress__cancel"
                data-cancel-url="{{ $cancelUrl }}"
            >Cancel</button>
            <p id="etdHeaderSyncMessage" class="etd-header-sync-progress__message"></p>
        </div>
    </div>
</div>
