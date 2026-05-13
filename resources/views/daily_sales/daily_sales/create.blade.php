@extends('layouts.app')

@section('title', 'Add Daily Sale')

@section('content')
<div id="daily-sales-page-content"></div>
<div class="px-5 py-6 pb-28">

    <!-- PAGE HEADER -->
    <div class="flex items-center justify-between mb-5 flex-wrap gap-3">
        <div>
            <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Add Daily Sale</h1>
            <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Record one or more daily sales entries for a single date</p>
        </div>
    </div>

    @if($errors->any())
    <div class="mb-4 p-4 rounded-xl bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800">
        <p class="text-xs font-semibold text-red-600 dark:text-red-400 mb-1">Please fix the following errors:</p>
        <ul class="text-sm text-red-600 dark:text-red-400 space-y-1">
            @foreach($errors->all() as $error)
                <li>• {{ $error }}</li>
            @endforeach
        </ul>
    </div>
    @endif

    <form method="POST" action="{{ route('admin.daily-sales.store') }}" id="validateForm">
        @csrf

        <!-- ── DATE ROW ── -->
        <div class="section-card !mb-4">
            <div class="flex flex-wrap items-end gap-5">
                <div class="w-56">
                    <label class="f-label">Date <span class="f-required">*</span></label>
                    <input type="date" name="date" id="sale-date"
                           class="f-input @error('date') border-red-400 @enderror"
                           value="{{ old('date', date('Y-m-d')) }}" required />
                    @error('date') <p class="f-error">{{ $message }}</p> @enderror
                </div>
                <p class="text-xs text-slate-400 dark:text-slate-500 pb-1.5">
                    All platform entries below will be saved for this date.
                </p>
            </div>
        </div>

        <!-- ── ENTRIES CONTAINER ── -->
        <div id="entries-container" class="space-y-2"></div>

        <!-- ── ADD MORE BUTTON ── -->
        <div class="mt-3 flex items-center gap-3">
            <button type="button" id="add-more-btn"
                    class="flex items-center gap-2 px-4 py-2 text-sm rounded-xl border border-dashed border-accent-400 text-accent-400 hover:bg-accent-50 dark:hover:bg-accent-900/20 transition-colors font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M12 4.5v15m7.5-7.5h-15"/>
                </svg>
                Add More
            </button>
            <span class="text-xs text-slate-400 dark:text-slate-500" id="row-count-label"></span>
        </div>

        <!-- ── STICKY FOOTER ── -->
        <div class="sticky-footer mt-5 -mx-5 rounded-none">
            <div class="px-5 flex items-center justify-between gap-3 flex-wrap">
                <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                    <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                    </svg>
                    Fields marked <span class="text-red-400 mx-1">*</span> are required
                </div>
                <div class="flex gap-2.5">
                    <a href="{{ route('admin.daily-sales.index') }}"
                       class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                        Cancel
                    </a>
                    <button type="submit"
                            class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                        </svg>
                        Save Daily Sale(s)
                    </button>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@push('js')
<script>
(function () {
    var platforms  = @json($salePlatforms);
    var oldEntries = @json(array_values(old('entries', [])));
    var rowIndex   = 0;
    var tomMap     = {};

    function makeSelect(name, selectedId) {
        var sel      = document.createElement('select');
        sel.name     = name;
        sel.required = true;
        sel.className = 'w-full';
        var blank       = document.createElement('option');
        blank.value     = '';
        blank.textContent = 'Select platform';
        sel.appendChild(blank);
        platforms.forEach(function (p) {
            var opt       = document.createElement('option');
            opt.value     = p.id;
            opt.textContent = p.label;
            if (String(p.id) === String(selectedId)) { opt.selected = true; }
            sel.appendChild(opt);
        });
        return sel;
    }

    function makeNum(name, val, req, step) {
        var el     = document.createElement('input');
        el.type    = 'number';
        el.name    = name;
        el.min     = '0';
        el.className = 'tbl-input w-full';
        if (step) { el.step = step; el.placeholder = '0.00'; } else { el.placeholder = '0'; }
        if (req)  { el.required = true; }
        el.value = (val !== null && val !== undefined && val !== '') ? val : (req ? '0' : '');
        return el;
    }

    function updateCount() {
        var n  = document.querySelectorAll('#entries-container .ds-entry-row').length;
        var el = document.getElementById('row-count-label');
        if (el) { el.textContent = n > 1 ? n + ' entries' : ''; }
    }

    function createRow(data, idx) {
        data = data || {};

        var row      = document.createElement('div');
        row.className = 'ds-entry-row section-card !p-2.5 !mb-2';

        var outer     = document.createElement('div');
        outer.className = 'flex flex-col xl:flex-row xl:items-start gap-2';

        /* Platform */
        var platWrap      = document.createElement('div');
        platWrap.className = 'xl:shrink-0 xl:w-[220px]';
        var platLbl        = document.createElement('label');
        platLbl.className  = 'f-label text-[11px] xl:hidden !text-[11px]';
        platLbl.textContent = 'Platform *';
        platWrap.appendChild(platLbl);
        var selectEl = makeSelect('entries[' + idx + '][sale_platform_id]', data.sale_platform_id || '');
        platWrap.appendChild(selectEl);
        outer.appendChild(platWrap);

        /* Numeric grid */
        var numGrid      = document.createElement('div');
        numGrid.className = 'flex-1 grid grid-cols-2 sm:grid-cols-5 xl:grid-cols-10 gap-2';

        var fields = [
            { label: 'Spent *',   key: 'spent',                       req: true,  step: '0.01' },
            { label: 'Sales *',   key: 'sales',                       req: true,  step: '0.01' },
            { label: 'Orders *',  key: 'number_of_orders',            req: true,  step: null   },
            { label: 'Qty *',     key: 'number_of_quantities',        req: true,  step: null   },
            { label: 'Male Orders', key: 'number_of_male_orders',       req: false, step: null   },
            { label: 'Female Orders', key: 'number_of_female_orders',     req: false, step: null   },
            { label: 'Kids Orders', key: 'number_of_kids_orders',       req: false, step: null   },
            { label: 'Male Qty',    key: 'number_of_male_quantities',   req: false, step: null   },
            { label: 'Female Qty',    key: 'number_of_female_quantities', req: false, step: null   },
            { label: 'Kids Qty',    key: 'number_of_kids_quantities',   req: false, step: null   },
        ];
        fields.forEach(function (f) {
            var cell      = document.createElement('div');
            var lbl       = document.createElement('label');
            lbl.className = 'f-label text-[11px] xl:hidden !text-[11px]';
            lbl.textContent = f.label;
            var inp = makeNum('entries[' + idx + '][' + f.key + ']', data[f.key], f.req, f.step);
            cell.appendChild(lbl);
            cell.appendChild(inp);
            numGrid.appendChild(cell);
        });
        outer.appendChild(numGrid);

        /* Remove button */
        var removeBtn     = document.createElement('button');
        removeBtn.type    = 'button';
        removeBtn.className = 'xl:mt-[22px] xl:shrink-0 flex items-center justify-center w-7 h-7 rounded-lg text-slate-400 hover:text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors';
        removeBtn.title   = 'Remove row';
        removeBtn.innerHTML = '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18L18 6M6 6l12 12"/></svg>';
        removeBtn.addEventListener('click', function () {
            if (tomMap[idx]) { try { tomMap[idx].destroy(); } catch (e) {} delete tomMap[idx]; }
            row.remove();
            updateCount();
        });
        outer.appendChild(removeBtn);
        row.appendChild(outer);

        /* Init TomSelect after element is in the DOM */
        requestAnimationFrame(function () {
            if (typeof TomSelect !== 'undefined') {
                tomMap[idx] = new TomSelect(selectEl, {
                    create: false,
                    searchField: 'text',
                    sortField:   [{ field: '$order' }, { field: '$score' }],
                    maxOptions:  200,
                    placeholder: 'Select platform',
                });
            }
        });

        return row;
    }

    function addRow(data) {
        document.getElementById('entries-container').appendChild(createRow(data, rowIndex++));
        updateCount();
    }

    document.addEventListener('DOMContentLoaded', function () {
        var initial = oldEntries.length ? oldEntries : [{}];
        initial.forEach(function (e) { addRow(e); });
        document.getElementById('add-more-btn').addEventListener('click', function () { addRow({}); });
    });
})();
</script>
@endpush

