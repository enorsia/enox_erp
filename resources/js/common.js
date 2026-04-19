/**
 * ENORSIA ADMIN – COMMON JS
 * All shared scripts used across the full project.
 * Page-specific scripts go in resources/js/pages/*.js
 */

/* ══════════════════════════════════════
   DARK / LIGHT MODE
══════════════════════════════════════ */
const DARK_KEY = 'enorsia-dark';

function applyDark(dark) {
    document.documentElement.classList.toggle('dark', dark);
    const sun  = document.getElementById('iconSun');
    const moon = document.getElementById('iconMoon');
    if (sun)  sun.classList.toggle('hidden', !dark);
    if (moon) moon.classList.toggle('hidden', dark);
}

// Apply on page load before paint
(function initDarkMode() {
    const saved = localStorage.getItem(DARK_KEY);
    // if never set, detect system preference
    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const isDark = saved !== null ? saved === 'true' : prefersDark;
    applyDark(isDark);
})();

window.toggleDark = function () {
    const current = document.documentElement.classList.contains('dark');
    localStorage.setItem(DARK_KEY, String(!current));
    applyDark(!current);
};

/* ══════════════════════════════════════
   MOBILE SIDEBAR
══════════════════════════════════════ */
window.toggleSidebar = function () {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (!sidebar) return;
    const isOpen = sidebar.classList.contains('mobile-open');
    sidebar.classList.toggle('mobile-open', !isOpen);
    if (backdrop) backdrop.classList.toggle('hidden', isOpen);
};

window.closeSidebar = function () {
    const sidebar  = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    if (sidebar)  sidebar.classList.remove('mobile-open');
    if (backdrop) backdrop.classList.add('hidden');
};

/* ══════════════════════════════════════
   TOGGLE SWITCH (status toggles)
══════════════════════════════════════ */
window.toggleSwitch = function (id) {
    const track = document.getElementById(id);
    if (!track) return;
    track.classList.toggle('on');
    // Sync any associated hidden checkbox (convention: id + 'Checkbox')
    const cb = document.getElementById(id + 'Checkbox') || track.querySelector('input[type=checkbox]');
    if (cb) cb.checked = track.classList.contains('on');
};

/* ══════════════════════════════════════
   DELETE CONFIRMATION
══════════════════════════════════════ */
window.deleteData = function (id) {
    Swal.fire({
        title: 'Are you sure?',
        text: "You won't be able to revert this!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1D9E75',
        cancelButtonColor: '#ef4444',
        confirmButtonText: 'Yes, delete it!',
        cancelButtonText: 'Cancel',
    }).then((result) => {
        if (result.isConfirmed) {
            const form = document.getElementById('delete-form-' + id);
            if (form) form.submit();
        }
    });
};

/* ══════════════════════════════════════
   LOADING SPINNER (for submit buttons)
══════════════════════════════════════ */
window.loader = `<svg class="animate-spin w-4 h-4 inline-block mr-1" fill="none" viewBox="0 0 24 24">
  <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
  <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
</svg> Loading...`;

/* ══════════════════════════════════════
   IMAGE PREVIEW
══════════════════════════════════════ */
$(document).on('change', '.image-input', function () {
    const file    = this.files[0];
    const preview = $(this).siblings('.image-preview');
    if (!preview.length) return;

    if (file) {
        const reader = new FileReader();
        reader.onload = (e) => preview.attr('src', e.target.result).show();
        reader.readAsDataURL(file);
    } else {
        preview.hide();
    }
});

/* ══════════════════════════════════════
   SELECT2 GLOBAL INIT
  Initialise any element with .select2-input
══════════════════════════════════════ */
$(document).ready(function () {
    if ($.fn.select2) {
        $('.select2-input').select2({
            theme: 'default',
            width: '100%',
        });
    }
});

/* ══════════════════════════════════════
   SESSION FLASH (SweetAlert toasts)
══════════════════════════════════════ */
// Flash messages are injected as data attributes on <body>
document.addEventListener('DOMContentLoaded', () => {
    const body = document.body;
    const success = body.dataset.flashSuccess;
    const error   = body.dataset.flashError;

    if (success) {
        Swal.fire({ icon: 'success', title: 'Success', text: success, timer: 3000, showConfirmButton: false });
    }
    if (error) {
        Swal.fire({ icon: 'error', title: 'Error', text: error, timer: 4000, showConfirmButton: false });
    }
});

