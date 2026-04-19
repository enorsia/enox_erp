import 'iconify-icon';

import './lib/config.js';

import './lib/jquery-3.7.1.min.js';

import './lib/select2-v4.min.js';
import './lib/jquery.validate.min.js';

import './lib/izitoast.min.js';

import Swal from 'sweetalert2';
window.Swal = Swal;

import './lib/customSweetalert2.min.js';
import './lib/vendor.js';
import './lib/theme-app.js';
import './common.js';

import Choices from 'choices.js';
window.Choices = Choices;

// ── Page-specific dynamic imports ─────────────────────────────────────────────
// Each page has a hidden <div id="enox_PAGE_NAME"> at the top of @section('content').
// Only the matching JS module is loaded — nothing else is shipped to the browser.

const has = (id) => !!document.getElementById(id);

// Roles
if (has('enox_roles_index'))  import('./pages/roles/index.js');
if (has('enox_roles_create')) import('./pages/roles/create.js');
if (has('enox_roles_edit'))   import('./pages/roles/edit.js');

// Users & Profile (all share the same validation script)
if (has('enox_users_create') || has('enox_users_edit') ||
    has('enox_profile_edit') || has('enox_profile_change_password'))
    import('./pages/users/script.js');

// Selling Chart (shared script, routes injected via data-* on the page div)
if (document.querySelector('.enox-selling-chart-page'))
    import('./pages/selling-chart/script.js');

// Selling Chart — Fabrication
if (has('enox_selling_chart_fabrication_index') || has('enox_selling_chart_fabrication_create'))
    import('./pages/selling-chart/fabrication.js');
