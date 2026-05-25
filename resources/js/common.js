import $ from '$';

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
window.toggleSwitch = function (id, event) {
    if (event) {
        event.stopPropagation();
        event.preventDefault(); // prevent label's native activation of the associated checkbox
    }
    const track = document.getElementById(id);
    if (!track) return;
    track.classList.toggle('on');
    // Sync the hidden checkbox (naming convention: replace 'Toggle' with 'Checkbox')
    const checkboxId = id.replace('Toggle', 'Checkbox');
    const checkbox = document.getElementById(checkboxId);
    if (checkbox) {
        checkbox.checked = track.classList.contains('on');
    }
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
            if (typeof window.saveScrollPosition === 'function') window.saveScrollPosition();
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

document.addEventListener('DOMContentLoaded', function() {
    function initTomSelect() {
        if (typeof TomSelect !== 'undefined') {
            document.querySelectorAll('.tom-select').forEach(element => {
                new TomSelect(element, {
                    create: false,
                    searchField: 'text',
                    sortField: [{field: '$order'}, {field: '$score'}],
                    placeholder: element.dataset.placeholder || 'Select an option',
                    maxOptions: 50,
                });
            });
        } else {
            setTimeout(initTomSelect, 100);
        }
    }

    initTomSelect();
});


/* ══════════════════════════════════════
   SCROLL PRESERVATION
   Uses the <main> scroll container (overflow-y-auto),
   not window — because window does not scroll in this layout.
══════════════════════════════════════ */
(function initScrollPreservation() {
    const SCROLL_KEY = 'admin_scroll_' + window.location.pathname;

    /** The real scrollable container in this layout */
    function getMain() {
        return document.querySelector('main');
    }

    function getScrollY() {
        const el = getMain();
        const y  = el ? el.scrollTop : window.scrollY;
        console.debug('[ScrollPreserve] getScrollY =', y, '(main el:', !!el, ')');
        return y;
    }

    function doScrollTo(y) {
        const el = getMain();
        console.debug('[ScrollPreserve] restoring scroll to', y, '(main el:', !!el, ')');
        if (el) {
            el.scrollTop = parseInt(y);
        } else {
            window.scrollTo({ top: parseInt(y), behavior: 'auto' });
        }
    }

    /** Expose globally so inline page scripts can call window.saveScrollPosition() */
    window.saveScrollPosition = function () {
        const y = getScrollY();
        sessionStorage.setItem(SCROLL_KEY, y);
        console.debug('[ScrollPreserve] SAVED', y, 'key:', SCROLL_KEY);
    };

    /** Save scroll on any [data-preserve-scroll] click; clear on pagination clicks */
    document.addEventListener('click', function (e) {
        const link = e.target.closest('a, button');
        if (!link) return;

        if (link.hasAttribute('data-preserve-scroll')) {
            window.saveScrollPosition();
            console.debug('[ScrollPreserve] data-preserve-scroll clicked – saved');
            return;
        }

        if (link.hasAttribute('data-pagination') || link.closest('[data-pagination-nav]')) {
            sessionStorage.removeItem(SCROLL_KEY);
            console.debug('[ScrollPreserve] pagination click – cleared key');
        }
    });

    /** Restore scroll when returning to a page that has [data-restore-scroll] */
    document.addEventListener('DOMContentLoaded', function () {
        const savedY = sessionStorage.getItem(SCROLL_KEY);
        const target = document.querySelector('[data-restore-scroll]');
        console.debug('[ScrollPreserve] DOMContentLoaded | savedY:', savedY, '| target:', !!target, '| key:', SCROLL_KEY);

        if (savedY && target) {
            // rAF ensures layout/CSS is fully applied before scrolling
            requestAnimationFrame(function () {
                doScrollTo(savedY);
                sessionStorage.removeItem(SCROLL_KEY);
            });
        }
    });
})();

/* ══════════════════════════════════════
   SESSION FLASH (iziToast notifications)
══════════════════════════════════════ */
// Flash messages are injected as data attributes on <body>
document.addEventListener('DOMContentLoaded', () => {
    const body    = document.body;
    const success = body.dataset.flashSuccess;
    const error   = body.dataset.flashError;

    if (success) {
        iziToast.success({
            title: 'Success',
            message: success,
            position: 'topRight',
            timeout: 30000,
        });
    }
    if (error) {
        iziToast.error({
            title: 'Error',
            message: error,
            position: 'topRight',
            timeout: 40000,
        });
    }
});

