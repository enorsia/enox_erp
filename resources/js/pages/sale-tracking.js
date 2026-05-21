/**
 * Sale Tracking Page Module
 *
 * window.ST = {
 *   mode    : 'create' | 'edit',
 *   platforms : [...],   // getParentOptions() output
 *   entries   : [...],   // initial rows (edit mode pre-fills these)
 *   deleteIds : [...],   // ids queued for deletion (edit mode)
 * };
 */

import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

(function () {

    // ── Index page: custom date range pickers ─────────────────────
    const fromEl = document.getElementById('filter-date-from');
    const toEl   = document.getElementById('filter-date-to');

    if (fromEl || toEl) {
        const commonOpts = {
            dateFormat  : 'Y-m-d',
            allowInput  : true,
            disableMobile: true,
            theme       : 'light',
        };

        const fpTo = toEl ? flatpickr(toEl, {
            ...commonOpts,
            defaultDate: toEl.getAttribute('data-default') || null,
        }) : null;

        const fpFrom = fromEl ? flatpickr(fromEl, {
            ...commonOpts,
            defaultDate: fromEl.getAttribute('data-default') || null,
            onChange([date]) {
                if (fpTo && date) fpTo.set('minDate', date);
            },
        }) : null;

        if (fpTo && fpFrom) {
            fpTo._fp_onChange_orig = fpTo.config.onChange;
            fpTo.config.onChange = [function([date]) {
                if (fpFrom && date) fpFrom.set('maxDate', date);
            }];
        }

        // Expose globally so Alpine can clear them
        window._fpFrom = fpFrom;
        window._fpTo   = fpTo;
    }

    // ── Create / Edit page ─────────────────────────────────────────
    const ST = window.ST || {};
    if (!ST.platforms) return;

    const platforms = ST.platforms;
    const isEdit    = ST.mode === 'edit';
    let   rowIndex  = 0;
    const tomMap    = {};

    // ── Helpers ────────────────────────────────────────────────

    function n(tag, cls, attrs) {
        const el = document.createElement(tag);
        if (cls)   el.className = cls;
        if (attrs) Object.assign(el, attrs);
        return el;
    }

    function inp(name, val, type, step, min, placeholder) {
        const el       = n('input', 'tbl-input w-full');
        el.type        = type || 'number';
        el.name        = name;
        el.placeholder = placeholder || '';
        if (step !== undefined && step !== null) el.step = step;
        if (min  !== undefined && min  !== null) el.min  = min;
        el.value = (val !== null && val !== undefined && val !== '') ? val : '';
        return el;
    }

    function fieldCell(labelText, inputEl, extraCellCls) {
        const cell = n('div', extraCellCls || '');
        const lbl  = n('label', 'f-label text-[11px] xl:hidden !text-[11px]');
        lbl.textContent = labelText;
        cell.appendChild(lbl);
        cell.appendChild(inputEl);
        return cell;
    }

    function updateCount() {
        const count = document.querySelectorAll('#entries-container .st-entry-card').length;
        const el    = document.getElementById('row-count-label');
        if (el) el.textContent = count > 1 ? count + ' platform entries' : '';
    }

    // ── Build a single entry card ─────────────────────────────

    function createCard(data, idx) {
        data = data || {};

        const card  = n('div', 'st-entry-card section-card !p-2.5 !mb-2');
        const outer = n('div', 'flex flex-col xl:flex-row xl:items-end gap-2');

        if (data.id) {
            card.appendChild(n('input', '', { type: 'hidden', name: `entries[${idx}][id]`, value: data.id }));
        }

        // ── Platform selector ──
        const platWrap = n('div', 'xl:w-[200px] xl:shrink-0');
        const platLbl  = n('label', 'f-label text-[11px] xl:hidden !text-[11px]');
        platLbl.textContent = 'Platform';
        platWrap.appendChild(platLbl);

        const selectEl = n('select', 'w-full');
        selectEl.name  = `entries[${idx}][sale_platform_id]`;
        const blankOpt = n('option', '', { value: '' });
        blankOpt.textContent = 'Select platform…';
        selectEl.appendChild(blankOpt);
        platforms.forEach(p => {
            const opt       = n('option', '', { value: p.id });
            opt.textContent = p.label;
            if (String(p.id) === String(data.sale_platform_id)) opt.selected = true;
            selectEl.appendChild(opt);
        });
        platWrap.appendChild(selectEl);
        outer.appendChild(platWrap);

        // ── Fields grid ──
        const grid = n('div', 'flex-1 grid grid-cols-3 sm:grid-cols-4 lg:grid-cols-7 gap-2');

        // Engagement (6)
        const reachEl    = inp(`entries[${idx}][reach]`,            data.reach,            'number', '1',    '0', '0');
        const impressEl  = inp(`entries[${idx}][impressions]`,      data.impressions,      'number', '1',    '0', '0');
        const clicksEl   = inp(`entries[${idx}][clicks]`,           data.clicks,           'number', '1',    '0', '0');
        const sessEl     = inp(`entries[${idx}][sessions]`,         data.sessions,         'number', '1',    '0', '0');
        const engSessEl  = inp(`entries[${idx}][engaged_sessions]`, data.engaged_sessions, 'number', '1',    '0', '0');
        const usersEl    = inp(`entries[${idx}][users]`,            data.users,            'number', '1',    '0', '0');

        // Ads Tax (direct entry)
        const adsTaxEl   = inp(`entries[${idx}][ads_tax_payments]`, data.ads_tax_payments, 'number', '0.01', '0', '0.00');

        // Row: reach impressions clicks sessions engaged users ads_tax
        grid.appendChild(fieldCell('Reach',            reachEl));
        grid.appendChild(fieldCell('Impressions',      impressEl));
        grid.appendChild(fieldCell('Clicks',           clicksEl));
        grid.appendChild(fieldCell('Sessions',         sessEl));
        grid.appendChild(fieldCell('Engaged Sessions', engSessEl));
        grid.appendChild(fieldCell('Users',            usersEl));
        grid.appendChild(fieldCell('Ads Tax (£)',      adsTaxEl));


        outer.appendChild(grid);

        // ── Remove button ──
        const removeBtn = n('button', 'xl:mb-0.5 xl:shrink-0 flex justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors', { type: 'button', title: 'Remove' });
        removeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>';
        removeBtn.addEventListener('click', () => {
            if (isEdit && data.id) {
                const cont = document.getElementById('delete-ids-container');
                if (cont) {
                    cont.appendChild(n('input', '', { type: 'hidden', name: 'entries_delete[]', value: data.id }));
                }
            }
            if (tomMap[idx]) { try { tomMap[idx].destroy(); } catch (_) {} delete tomMap[idx]; }
            card.remove();
            updateCount();
        });
        outer.appendChild(removeBtn);
        card.appendChild(outer);

        // Init TomSelect
        requestAnimationFrame(() => {
            if (typeof TomSelect === 'undefined') return;
            tomMap[idx] = new TomSelect(selectEl, {
                create: false, searchField: 'text', maxOptions: 200,
                placeholder: 'Select platform…',
            });
        });

        return card;
    }

    function addCard(data) {
        document.getElementById('entries-container').appendChild(createCard(data, rowIndex++));
        updateCount();
    }

    // ── Boot ─────────────────────────────────────────────────

    function init() {
        const initial = (ST.entries && ST.entries.length) ? ST.entries : [{}];
        initial.forEach(e => addCard(e));

        const addBtn = document.getElementById('add-more-btn');
        if (addBtn) addBtn.addEventListener('click', () => addCard({}));

        // Re-inject delete IDs on validation re-run
        if (isEdit && ST.deleteIds && ST.deleteIds.length) {
            const cont = document.getElementById('delete-ids-container');
            if (cont) {
                ST.deleteIds.forEach(id => {
                    cont.appendChild(n('input', '', { type: 'hidden', name: 'entries_delete[]', value: id }));
                });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
