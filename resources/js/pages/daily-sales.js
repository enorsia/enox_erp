/**
 * Daily Sales Page Module
 *
 * window.DS = {
 *   mode             : 'create' | 'edit',
 *   platforms        : [...],          // getParentOptions() with is_spent/is_sales/allows_direct_entry
 *   entries          : [...],          // initial rows
 *   deleteIds        : [...],          // ids queued for deletion on failed-validation re-run
 *   usedPlatformIds  : [...],          // CREATE only – platform IDs already in DB for the current date
 *   usedPlatformsUrl : 'string',       // CREATE only – AJAX URL to refresh usedPlatformIds on date change
 * };
 */

(function () {
    const DS = window.DS || {};
    if (!DS.platforms) return;

    const platforms = DS.platforms;
    const isEdit    = DS.mode === 'edit';
    let   rowIndex  = 0;
    const tomMap    = {};

    /* ── Registries ──────────────────────────────────
       selectedByIdx[rowIdx] = String(platformId) | null
         Tracks which platform is chosen in each form row.

       dbUsedPlatformIds = Set of String(platformId)
         Platform IDs already saved in the DB for the current date.
         Only relevant in create mode; in edit mode every DB record is
         already a row in the form so selectedByIdx covers everything.
    ─────────────────────────────────────────────────── */
    const selectedByIdx     = {};
    let   dbUsedPlatformIds = new Set((DS.usedPlatformIds || []).map(String));

    /* ─────────────────────────────────────────────────
       Core: is this platform blocked for a given row?
       A platform is blocked when any of these is true:
         1. allows_direct_entry === false (platform setting)
         2. Already in DB for this date (and not currently being edited in this form)
         3. Already selected in another row of this form
    ─────────────────────────────────────────────────── */
    function isBlocked(platId, forRowIdx) {
        const key = String(platId);
        const p   = platforms.find(p => String(p.id) === key);
        if (!p)                          return true;   // unknown
        if (!p.allows_direct_entry)      return true;   // setting #1
        if (dbUsedPlatformIds.has(key))  return true;   // setting #2
        // setting #3 – another row holds this platform
        return Object.entries(selectedByIdx).some(
            ([i, pid]) => parseInt(i) !== forRowIdx && pid === key
        );
    }

    /* ── Apply correct disabled state to ONE TomSelect ── */
    function refreshTom(tom, rowIdx) {
        const myPlatId = String(selectedByIdx[rowIdx] || '');
        let   changed  = false;

        platforms.forEach(p => {
            const key = String(p.id);
            if (!tom.options[key]) return;

            // Current selection of THIS row must never be disabled
            const shouldDisable = key !== myPlatId && isBlocked(key, rowIdx);
            if (!!tom.options[key].disabled !== shouldDisable) {
                tom.updateOption(key, { ...tom.options[key], disabled: shouldDisable });
                changed = true;
            }
        });

        if (changed) tom.refreshOptions(false);
    }

    /* ── Refresh ALL TomSelects ── */
    function refreshAllToms() {
        Object.entries(tomMap).forEach(([idxStr, tom]) => {
            refreshTom(tom, parseInt(idxStr));
        });
    }

    /* ── On platform change in a row ── */
    function onPlatformChange(rowIdx, newPlatId, oldPlatId) {
        selectedByIdx[rowIdx] = newPlatId || null;
        refreshAllToms();
    }

    /* ── On row removed ── */
    function onRowRemoved(rowIdx) {
        delete selectedByIdx[rowIdx];
        refreshAllToms();
    }

    /* ── Fetch DB-used platforms for a date (create mode only) ── */
    async function fetchUsedPlatforms(date) {
        if (!DS.usedPlatformsUrl || !date) return;
        try {
            const resp = await fetch(`${DS.usedPlatformsUrl}?date=${encodeURIComponent(date)}`);
            const data = await resp.json();
            dbUsedPlatformIds = new Set(data.map(String));
            refreshAllToms();
            updateDateHint(date);
        } catch (_) { /* ignore network errors */ }
    }

    function updateDateHint(date) {
        const hint = document.getElementById('date-platform-hint');
        if (!hint) return;
        const count = dbUsedPlatformIds.size;
        if (count > 0) {
            hint.innerHTML = `<span class="text-amber-500 dark:text-amber-400 font-medium">${count} platform${count > 1 ? 's' : ''} already recorded for this date — shown greyed out.</span>`;
        } else {
            hint.textContent = 'All platform entries below will be saved for this date.';
        }
    }

    /* ── Platform flag helpers (is_spent / is_sales cell visibility) ── */
    function getPlatform(id) {
        return platforms.find(p => String(p.id) === String(id)) || null;
    }

    function applyPlatformFlags(row, platId) {
        row.querySelectorAll('[data-field]').forEach(cell => { cell.style.display = ''; });
        row.querySelectorAll('[data-field="spent"] input, [data-field="sales"] input').forEach(el => {
            el.required = true;
        });

        const p = getPlatform(platId);
        if (!p) return;

        if (!p.is_spent) {
            const cell = row.querySelector('[data-field="spent"]');
            if (cell) {
                cell.style.display = 'none';
                const inp = cell.querySelector('input');
                if (inp) { inp.value = '0'; inp.required = false; }
            }
        }
        if (!p.is_sales) {
            const cell = row.querySelector('[data-field="sales"]');
            if (cell) {
                cell.style.display = 'none';
                const inp = cell.querySelector('input');
                if (inp) { inp.value = '0'; inp.required = false; }
            }
        }
    }

    /* ── DOM builders ── */
    function makeSelect(name, selectedId) {
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

    function makeNum(name, val, req, step) {
        const el     = document.createElement('input');
        el.type      = 'number';
        el.name      = name;
        el.min       = '0';
        el.className = 'tbl-input w-full';
        if (step) { el.step = step; el.placeholder = '0.00'; }
        else       { el.step = '1'; el.placeholder = '0'; }
        if (req) el.required = true;
        el.value = (val !== null && val !== undefined && val !== '') ? val : (req ? '0' : '');
        return el;
    }

    function updateCount() {
        const n  = document.querySelectorAll('#entries-container .ds-entry-row').length;
        const el = document.getElementById('row-count-label');
        if (el) el.textContent = n > 1 ? n + ' entries' : '';
    }

    const FIELDS = [
        { label: 'Spent *',       key: 'spent',                       req: true,  step: '0.01' },
        { label: 'Sales *',       key: 'sales',                       req: true,  step: '0.01' },
        { label: 'Orders *',      key: 'number_of_orders',            req: true,  step: null   },
        { label: 'Qty *',         key: 'number_of_quantities',        req: true,  step: null   },
        { label: 'Male Orders',   key: 'number_of_male_orders',       req: false, step: null   },
        { label: 'Female Orders', key: 'number_of_female_orders',     req: false, step: null   },
        { label: 'Kids Orders',   key: 'number_of_kids_orders',       req: false, step: null   },
        { label: 'Male Qty',      key: 'number_of_male_quantities',   req: false, step: null   },
        { label: 'Female Qty',    key: 'number_of_female_quantities', req: false, step: null   },
        { label: 'Kids Qty',      key: 'number_of_kids_quantities',   req: false, step: null   },
    ];

    function createRow(data, idx) {
        data = data || {};

        const row      = document.createElement('div');
        row.className  = 'ds-entry-row section-card !p-2.5 !mb-2';

        const outer    = document.createElement('div');
        outer.className = 'flex flex-col xl:flex-row xl:items-start gap-2';

        if (data.id) {
            const h  = document.createElement('input');
            h.type   = 'hidden';
            h.name   = `entries[${idx}][id]`;
            h.value  = data.id;
            row.appendChild(h);
        }

        // Platform selector
        const platWrap      = document.createElement('div');
        platWrap.className  = 'xl:shrink-0 xl:w-[220px]';
        const platLbl       = document.createElement('label');
        platLbl.className   = 'f-label text-[11px] xl:hidden !text-[11px]';
        platLbl.textContent = 'Platform *';
        platWrap.appendChild(platLbl);
        const selectEl = makeSelect(`entries[${idx}][sale_platform_id]`, data.sale_platform_id || '');
        platWrap.appendChild(selectEl);
        outer.appendChild(platWrap);

        // Numeric grid
        const numGrid     = document.createElement('div');
        numGrid.className = 'flex-1 grid grid-cols-2 sm:grid-cols-5 xl:grid-cols-10 gap-2';

        FIELDS.forEach(f => {
            const cell         = document.createElement('div');
            cell.dataset.field = f.key;
            const lbl          = document.createElement('label');
            lbl.className      = 'f-label text-[11px] xl:hidden !text-[11px]';
            lbl.textContent    = f.label;
            cell.appendChild(lbl);
            cell.appendChild(makeNum(`entries[${idx}][${f.key}]`, data[f.key], f.req, f.step));
            numGrid.appendChild(cell);
        });
        outer.appendChild(numGrid);

        // Remove button
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
            onRowRemoved(idx);
            if (tomMap[idx]) { try { tomMap[idx].destroy(); } catch (_) {} delete tomMap[idx]; }
            row.remove();
            updateCount();
        });

        outer.appendChild(removeBtn);
        row.appendChild(outer);

        // Init TomSelect after DOM insertion
        requestAnimationFrame(() => {
            if (typeof TomSelect === 'undefined') return;

            if (data.sale_platform_id) {
                selectedByIdx[idx] = String(data.sale_platform_id);
            }

            tomMap[idx] = new TomSelect(selectEl, {
                create:      false,
                searchField: 'text',
                sortField:   [{ field: '$order' }, { field: '$score' }],
                maxOptions:  200,
                placeholder: 'Select platform',
                onChange(value) {
                    const old = selectedByIdx[idx] || null;
                    onPlatformChange(idx, value || null, old);
                    applyPlatformFlags(row, value);
                },
            });

            if (data.sale_platform_id) {
                applyPlatformFlags(row, data.sale_platform_id);
            }
        });

        return row;
    }

    function addRow(data) {
        document.getElementById('entries-container').appendChild(createRow(data, rowIndex++));
        updateCount();
    }

    /* ── Boot ── */
    function init() {
        const initial = (DS.entries && DS.entries.length) ? DS.entries : [{}];
        initial.forEach(e => addRow(e));

        const addBtn = document.getElementById('add-more-btn');
        if (addBtn) addBtn.addEventListener('click', () => addRow({}));

        // Re-inject delete IDs on validation re-run
        if (isEdit && DS.deleteIds && DS.deleteIds.length) {
            const cont = document.getElementById('delete-ids-container');
            if (cont) {
                DS.deleteIds.forEach(id => {
                    const h  = document.createElement('input');
                    h.type   = 'hidden';
                    h.name   = 'entries_delete[]';
                    h.value  = id;
                    cont.appendChild(h);
                });
            }
        }

        // Date change handler (create mode only) – refresh which platforms are already taken
        if (!isEdit) {
            const dateInput = document.getElementById('sale-date');
            if (dateInput) {
                dateInput.addEventListener('change', function () {
                    fetchUsedPlatforms(this.value);
                });
                // Show hint for default date if there are already used platforms
                updateDateHint(dateInput.value);
            }
        }

        // After all rAFs complete, do the full initial disable pass
        // (setTimeout fires after all requestAnimationFrames)
        setTimeout(() => {
            refreshAllToms();
        }, 0);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
