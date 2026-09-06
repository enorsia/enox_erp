<div class="etd-segmented etd-segmented--compact etd-device-browser-toggle" role="group" aria-label="Device or browser breakdown">
    <button type="button"
            class="etd-segmented-btn"
            :class="{ 'active': view === 'device' }"
            @click="view = 'device'">
        Device
    </button>
    <button type="button"
            class="etd-segmented-btn"
            :class="{ 'active': view === 'browser' }"
            @click="view = 'browser'">
        Browser
    </button>
</div>
