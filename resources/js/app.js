
// ── jQuery (global) ──
import $ from 'jquery';
window.$ = window.jQuery = $;

import TomSelect from 'tom-select';
import 'tom-select/dist/css/tom-select.css';
window.TomSelect = TomSelect;


// ── SweetAlert2 ──
import Swal from 'sweetalert2';
window.Swal = Swal;

// ── iziToast ──
import iziToast from 'izitoast';
import 'izitoast/dist/css/iziToast.min.css';
window.iziToast = iziToast;

import './common';

const has = (sel, byId = false) => byId ? !!document.getElementById(sel) : !!document.querySelector(sel);

if (has('#user-page-content')) import('./pages/users');