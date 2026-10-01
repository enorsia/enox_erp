@php
    $pauseUrl = $syncPauseUrl ?? null;
    $resumeUrl = $syncResumeUrl ?? null;
    $cancelUrl = $syncCancelUrl ?? null;
@endphp

<div
    id="etdSyncProgressFloat"
    class="etd-sync-progress-float etd-print-hide"
    hidden
    role="status"
    aria-live="polite"
    aria-label="Tracker sync progress"
    data-sync-pause-url="{{ $pauseUrl }}"
    data-sync-resume-url="{{ $resumeUrl }}"
    data-sync-cancel-url="{{ $cancelUrl }}"
>
    <div class="etd-sync-progress-float__head">
        <span class="etd-sync-progress-float__title">Tracker sync</span>
        <div class="etd-sync-progress-float__head-right">
            <span id="etdSyncProgressFloatCount" class="etd-sync-progress-float__step">0 / 0</span>
            <button type="button" id="etdSyncProgressFloatClose" class="etd-sync-progress-float__close" aria-label="Hide sync progress">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
    </div>
    <div class="etd-sync-progress-float__track" aria-hidden="true">
        <div id="etdSyncProgressFloatBar" class="etd-sync-progress-float__bar" style="width: 0%"></div>
    </div>
    <p id="etdSyncProgressFloatMeta" class="etd-sync-progress-float__meta">Preparing…</p>
    <div class="etd-sync-progress-float__actions">
        <button type="button" id="etdSyncProgressFloatPause" class="etd-sync-progress-float__btn">Pause</button>
        <button type="button" id="etdSyncProgressFloatResume" class="etd-sync-progress-float__btn etd-sync-progress-float__btn--primary" hidden>Resume</button>
        <button type="button" id="etdSyncProgressFloatCancel" class="etd-sync-progress-float__btn etd-sync-progress-float__btn--danger">Cancel</button>
    </div>
</div>
