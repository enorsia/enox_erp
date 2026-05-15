/**
 * Daily Returns Page Module
 *
 * window.DR = {
 *   mode     : 'create' | 'edit',
 *   platforms: [...],   // getParentOptions() result
 *   reasons  : [...],   // [{id, name}, ...]
 *   entries  : [...],   // initial rows (may include id for edit mode)
 *   deleteIds: [...],   // ids queued for deletion on validation re-run
 * };
 *
 * Key difference from daily-sales:
 *   The same platform CAN appear in multiple rows for the same date,
 *   as long as each row has a different reason type.
 *   No cross-row platform blocking is needed.
 */

(function () {
    const DR = window.DR || {};
    if (!DR.platforms) return;

    const platforms = DR.platforms;
    const reasons   = DR.reasons || [];
    const isEdit    = DR.mode === 'edit';
    let   rowIndex  = 0;
    const tomPlatMap   = {};
    const tomReasonMap = {};

    /* ── DOM helpers ── */
    function makePlatformSelect(name, selectedId) {
        const sel         = document.createElement('select');
        sel.name          = name;
        sel.required      = true;
        sel.className     = 'w-full';
        const blank       = document.createElement('option');
        blank.value       = '';
        blank.textContent = 'Select platform';
        sel.appendChild(blank);
        platforms.forEach(p => {
            const opt       = document.createElement('option');
            opt.value       = p.id;
            opt.textContent = p.label;
            if (!p.allows_direct_entry) opt.disabled = true;
            if (String(p.id) === String(selectedId)) opt.selected = true;
            sel.appendChild(opt);
        });
        return sel;
    }

    function makeReasonSelect(name, selectedId) {
        const sel         = document.createElement('select');
        sel.name          = name;
        sel.required      = true;
        sel.className     = 'w-full';
        const blank       = document.createElement('option');
        blank.value       = '';
        blank.textContent = 'Select reason';
        sel.appendChild(blank);
        reasons.forEach(r => {
            const opt       = document.createElement('option');
            opt.value       = r.id;
            opt.textContent = r.name;
            if (String(r.id) === String(selectedId)) opt.selected = true;
            sel.appendChild(opt);
        });
        return sel;
    }

    function makeNum(name, val, req) {
        const el     = document.createElement('input');
        el.type      = 'number';
        el.name      = name;
        el.min       = '0';
        el.step      = '1';
        el.className = 'tbl-input w-full';
        el.placeholder = '0';
        if (req) el.required = true;
        el.value = (val !== null && val !== undefined && val !== '') ? val : (req ? '0' : '');
        return el;
    }

    function makeAmount(name, val) {
        const el       = document.createElement('input');
        el.type        = 'number';
        el.name        = name;
        el.min         = '0';
        el.step        = '0.01';
        el.className   = 'tbl-input w-full';
        el.placeholder = '0.00';
        el.value       = (val !== null && val !== undefined && val !== '') ? val : '';
        return el;
    }

    function updateCount() {
        const n  = document.querySelectorAll('#entries-container .dr-entry-row').length;
        const el = document.getElementById('row-count-label');
        if (el) el.textContent = n > 1 ? n + ' entries' : '';
    }

    const FIELDS = [
        { label: 'Returns *',     key: 'number_of_returns',                  req: true  },
        { label: 'Return Qty *',  key: 'number_of_return_quantities',        req: true  },
        { label: 'Male Returns',  key: 'number_of_male_returns',             req: false },
        { label: 'Female Ret.',   key: 'number_of_female_returns',           req: false },
        { label: 'Kids Returns',  key: 'number_of_kids_returns',             req: false },
        { label: 'Male Qty',      key: 'number_of_male_return_quantities',   req: false },
        { label: 'Female Qty',    key: 'number_of_female_return_quantities', req: false },
        { label: 'Kids Qty',      key: 'number_of_kids_return_quantities',   req: false },
    ];

    function createRow(data, idx) {
        data = data || {};

        const row       = document.createElement('div');
        row.className   = 'dr-entry-row section-card !p-2.5 !mb-2';

        const outer     = document.createElement('div');
        outer.className = 'flex flex-col xl:flex-row xl:items-start gap-2';

        // Hidden ID for edit mode
        if (data.id) {
            const h  = document.createElement('input');
            h.type   = 'hidden';
            h.name   = `entries[${idx}][id]`;
            h.value  = data.id;
            row.appendChild(h);
        }

        // ── Platform selector ──
        const platWrap      = document.createElement('div');
        platWrap.className  = 'xl:shrink-0 xl:w-[200px]';
        const platLbl       = document.createElement('label');
        platLbl.className   = 'f-label text-[11px] xl:hidden !text-[11px]';
        platLbl.textContent = 'Platform *';
        platWrap.appendChild(platLbl);
        const platSel = makePlatformSelect(`entries[${idx}][sale_platform_id]`, data.sale_platform_id || '');
        platWrap.appendChild(platSel);
        outer.appendChild(platWrap);

        // ── Reason selector ──
        const reasonWrap      = document.createElement('div');
        reasonWrap.className  = 'xl:shrink-0 xl:w-[190px]';
        const reasonLbl       = document.createElement('label');
        reasonLbl.className   = 'f-label text-[11px] xl:hidden !text-[11px]';
        reasonLbl.textContent = 'Reason *';
        reasonWrap.appendChild(reasonLbl);
        const reasonSel = makeReasonSelect(`entries[${idx}][return_reason_type_id]`, data.return_reason_type_id || '');
        reasonWrap.appendChild(reasonSel);
        outer.appendChild(reasonWrap);

        // ── Return Amount ──
        const amountWrap      = document.createElement('div');
        amountWrap.className  = 'xl:shrink-0 xl:w-[130px]';
        const amountLbl       = document.createElement('label');
        amountLbl.className   = 'f-label text-[11px] !text-[11px]';
        amountLbl.textContent = 'Return Amount';
        amountWrap.appendChild(amountLbl);
        amountWrap.appendChild(makeAmount(`entries[${idx}][return_amount]`, data.return_amount));
        outer.appendChild(amountWrap);

        // ── Numeric grid ──
        const numGrid     = document.createElement('div');
        numGrid.className = 'flex-1 grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-8 gap-2';

        FIELDS.forEach(f => {
            const cell         = document.createElement('div');
            const lbl          = document.createElement('label');
            lbl.className      = 'f-label text-[11px] xl:hidden !text-[11px]';
            lbl.textContent    = f.label;
            cell.appendChild(lbl);
            cell.appendChild(makeNum(`entries[${idx}][${f.key}]`, data[f.key], f.req));
            numGrid.appendChild(cell);
        });
        outer.appendChild(numGrid);

        // ── Remove button ──
        const removeBtn     = document.createElement('button');
        removeBtn.type      = 'button';
        removeBtn.className = 'xl:mt-[22px] xl:shrink-0 flex items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors';
        removeBtn.title     = 'Remove row';
        removeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>';

        removeBtn.addEventListener('click', () => {
            if (isEdit && data.id) {
                const cont = document.getElementById('delete-ids-container');
                if (cont) {
                    const h  = document.createElement('input');
                    h.type   = 'hidden';
                    h.name   = 'entries_delete[]';
                    h.value  = data.id;
                    cont.appendChild(h);
                }
            }
            if (tomPlatMap[idx])   { try { tomPlatMap[idx].destroy();   } catch (_) {} delete tomPlatMap[idx];   }
            if (tomReasonMap[idx]) { try { tomReasonMap[idx].destroy(); } catch (_) {} delete tomReasonMap[idx]; }
            row.remove();
            updateCount();
        });

        outer.appendChild(removeBtn);
        row.appendChild(outer);

        // Init TomSelect after DOM insertion
        requestAnimationFrame(() => {
            if (typeof TomSelect === 'undefined') return;

            tomPlatMap[idx] = new TomSelect(platSel, {
                create:      false,
                searchField: 'text',
                sortField:   [{ field: '$order' }, { field: '$score' }],
                maxOptions:  200,
                placeholder: 'Select platform',
            });

            tomReasonMap[idx] = new TomSelect(reasonSel, {
                create:      false,
                searchField: 'text',
                sortField:   [{ field: '$order' }, { field: '$score' }],
                maxOptions:  200,
                placeholder: 'Select reason',
            });
        });

        return row;
    }

    function addRow(data) {
        document.getElementById('entries-container').appendChild(createRow(data, rowIndex++));
        updateCount();
    }

    /* ── Boot ── */
    function init() {
        const initial = (DR.entries && DR.entries.length) ? DR.entries : [{}];
        initial.forEach(e => addRow(e));

        const addBtn = document.getElementById('add-more-btn');
        if (addBtn) addBtn.addEventListener('click', () => addRow({}));

        // Re-inject delete IDs on validation re-run
        if (isEdit && DR.deleteIds && DR.deleteIds.length) {
            const cont = document.getElementById('delete-ids-container');
            if (cont) {
                DR.deleteIds.forEach(id => {
                    const h  = document.createElement('input');
                    h.type   = 'hidden';
                    h.name   = 'entries_delete[]';
                    h.value  = id;
                    cont.appendChild(h);
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

