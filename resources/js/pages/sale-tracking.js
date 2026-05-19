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

(function () {
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

    function autoCell(labelText, inputEl, extraCellCls) {
        inputEl.classList.add('bg-slate-50', 'dark:bg-slate-700/40');
        return fieldCell(labelText + ' (auto)', inputEl, extraCellCls);
    }

    function updateCount() {
        const count = document.querySelectorAll('#entries-container .st-entry-card').length;
        const el    = document.getElementById('row-count-label');
        if (el) el.textContent = count > 1 ? count + ' platform entries' : '';
    }

    // ── Derived field computation within a card ───────────────

    function computeCard(card, idx) {
        const g   = id => card.querySelector(`[name="entries[${idx}][${id}]"]`);
        const net = parseFloat(g('net_cost')?.value)        || 0;
        const tax = parseFloat(g('ads_tax_payments')?.value) || 0;
        const rev = parseFloat(g('revenue')?.value)          || 0;
        const ret = parseFloat(g('total_return')?.value)     || 0;

        let totalCost = parseFloat(g('total_cost')?.value) || 0;
        if ((net > 0 || tax > 0) && g('total_cost')) {
            totalCost = net + tax;
            g('total_cost').value = totalCost.toFixed(2);
        }
        if (g('total_revenue')) {
            g('total_revenue').value = rev > 0 ? rev.toFixed(2) : '';
        }
        if (g('net_revenue')) {
            g('net_revenue').value = rev > 0 ? (rev - ret).toFixed(2) : '';
        }
        if (totalCost > 0 && rev > 0) {
            if (g('roi'))  g('roi').value  = ((rev / totalCost) * 100).toFixed(4);
            if (g('roas')) g('roas').value = (rev / totalCost).toFixed(4);
        }
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

        // ── All fields flat grid (max 2 rows at lg: 10 + 9) ──
        const grid = n('div', 'flex-1 grid grid-cols-3 sm:grid-cols-5 lg:grid-cols-10 gap-2');

        // Reach & Engagement (6)
        const reachEl    = inp(`entries[${idx}][reach]`,            data.reach,            'number', '1',      '0', '0');
        const impressEl  = inp(`entries[${idx}][impressions]`,      data.impressions,      'number', '1',      '0', '0');
        const clicksEl   = inp(`entries[${idx}][clicks]`,           data.clicks,           'number', '1',      '0', '0');
        const sessEl     = inp(`entries[${idx}][sessions]`,         data.sessions,         'number', '1',      '0', '0');
        const engSessEl  = inp(`entries[${idx}][engaged_sessions]`, data.engaged_sessions, 'number', '1',      '0', '0');
        const usersEl    = inp(`entries[${idx}][users]`,            data.users,            'number', '1',      '0', '0');

        // Cost (3)
        const netCostEl   = inp(`entries[${idx}][net_cost]`,         data.net_cost,         'number', '0.01', '0', '0.00');
        const adsTaxEl    = inp(`entries[${idx}][ads_tax_payments]`, data.ads_tax_payments, 'number', '0.01', '0', '0.00');
        const totalCostEl = inp(`entries[${idx}][total_cost]`,       data.total_cost,       'number', '0.01', '0', 'Auto');

        // Orders & Revenue (7)
        const ordersEl    = inp(`entries[${idx}][number_of_orders]`,   data.number_of_orders,   'number', '1',      '0',   '0');
        const prodsEl     = inp(`entries[${idx}][number_of_products]`,  data.number_of_products, 'number', '1',      '0',   '0');
        const growthEl    = inp(`entries[${idx}][sales_grow_percent]`,  data.sales_grow_percent, 'number', '0.0001', null,  '0.00');
        const revenueEl   = inp(`entries[${idx}][revenue]`,             data.revenue,            'number', '0.01',   '0',   '0.00');
        const totRevEl    = inp(`entries[${idx}][total_revenue]`,       data.total_revenue,      'number', '0.01',   '0',   'Auto');
        const returnEl    = inp(`entries[${idx}][total_return]`,        data.total_return,       'number', '0.01',   '0',   '0.00');
        const netRevEl    = inp(`entries[${idx}][net_revenue]`,         data.net_revenue,        'number', '0.01',   null,  'Auto');

        // Performance & Notes (3)
        const roiEl   = inp(`entries[${idx}][roi]`,   data.roi,   'number', '0.0001', null, 'Auto');
        const roasEl  = inp(`entries[${idx}][roas]`,  data.roas,  'number', '0.0001', null, 'Auto');
        const notesEl = inp(`entries[${idx}][notes]`, data.notes, 'text',   null,     null, 'Notes…');

        // Wire auto-compute
        netCostEl.addEventListener('input',  () => computeCard(card, idx));
        adsTaxEl.addEventListener('input',   () => computeCard(card, idx));
        revenueEl.addEventListener('input',  () => computeCard(card, idx));
        returnEl.addEventListener('input',   () => computeCard(card, idx));

        // Row 1: reach(1) impressions(2) clicks(3) sessions(4) engaged(5) users(6) net_cost(7) ads_tax(8) total_cost(9) orders(10)
        grid.appendChild(fieldCell('Reach',            reachEl));
        grid.appendChild(fieldCell('Impressions',      impressEl));
        grid.appendChild(fieldCell('Clicks',           clicksEl));
        grid.appendChild(fieldCell('Sessions',         sessEl));
        grid.appendChild(fieldCell('Engaged Sessions', engSessEl));
        grid.appendChild(fieldCell('Users',            usersEl));
        grid.appendChild(fieldCell('Net Cost',         netCostEl));
        grid.appendChild(fieldCell('Ads Tax',          adsTaxEl));
        grid.appendChild(autoCell( 'Total Cost',       totalCostEl));
        grid.appendChild(fieldCell('Orders',           ordersEl));

        // Row 2: products(1) growth(2) revenue(3) total_rev(4) total_return(5) net_rev(6) roi(7) roas(8) notes(9)
        grid.appendChild(fieldCell('Products',        prodsEl));
        grid.appendChild(fieldCell('Sales Growth %',  growthEl));
        grid.appendChild(fieldCell('Revenue (£)',     revenueEl));
        grid.appendChild(autoCell( 'Total Revenue',   totRevEl));
        grid.appendChild(fieldCell('Total Return',    returnEl));
        grid.appendChild(autoCell( 'Net Revenue',     netRevEl));
        grid.appendChild(autoCell( 'ROI (%)',         roiEl));
        grid.appendChild(autoCell( 'ROAS',            roasEl));
        grid.appendChild(fieldCell('Notes',           notesEl));

        outer.appendChild(grid);

        // ── Remove button ──
        const removeBtn = n('button', 'xl:mb-0.5 xl:shrink-0 flex items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors', { type: 'button', title: 'Remove' });
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
