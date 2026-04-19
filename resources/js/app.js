/**
 * app.js — Main Vite entry point
 * Everything is imported here — no external asset() calls needed.
 * CSS is loaded separately via @vite(['resources/css/app.css', ...]) for immediate paint.
 */

// ── Iconify Web Component (must load before vendor.js to take priority) ──────
import 'iconify-icon';

// ── Theme Config (sets data-bs-theme etc on <html>) ─────────────────────────
import './lib/config.js';

// ── jQuery (UMD → global) ───────────────────────────────────────────────────
import './lib/jquery-3.7.1.min.js';

// ── jQuery Plugins ──────────────────────────────────────────────────────────
import './lib/select2-v4.min.js';
import './lib/jquery.validate.min.js';

// ── Standalone Libraries ────────────────────────────────────────────────────
import './lib/izitoast.min.js';
import Swal from 'sweetalert2';
window.Swal = Swal;
import './lib/customSweetalert2.min.js';

// ── Vendor (Bootstrap 5 + SimpleBar + Masonry + Dragula etc) ────────────────
import './lib/vendor.js';

// ── Theme Layout (sidebar, topbar, menu config) ─────────────────────────────
import './lib/theme-app.js';

// ── Common functions (loader, deleteData, approveData, validate-form, image-preview) ──
import './common.js';

// ── Choices.js (expose globally so inline scripts can use `new Choices(...)`) ─
import Choices from 'choices.js';
window.Choices = Choices;
