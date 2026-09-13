import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

const MONTH_LABELS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

function parseLocalDate(isoDate) {
    const [year, month, day] = isoDate.split('-').map(Number);

    return new Date(year, month - 1, day);
}

function formatLocalDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function addLocalDays(isoDate, deltaDays) {
    const date = parseLocalDate(isoDate);
    date.setDate(date.getDate() + deltaDays);

    return formatLocalDate(date);
}

function formatDisplayDate(isoDate) {
    const date = parseLocalDate(isoDate);
    const day = String(date.getDate()).padStart(2, '0');
    const month = MONTH_LABELS[date.getMonth()];

    return `${day} ${month} ${date.getFullYear()}`;
}

function formatRangeLabel(from, to, config) {
    if (from === to) {
        if (from === config.today) {
            return config.todayLabel;
        }

        if (from === config.yesterday) {
            return config.yesterdayLabel;
        }
    }

    return `${formatDisplayDate(from)} – ${formatDisplayDate(to)}`;
}

function resolvePeriodState(from, to, config) {
    if (from === to) {
        if (from === config.today) {
            return { period: '24h', dateFrom: '', dateTo: '' };
        }

        if (from === config.yesterday) {
            return { period: 'yesterday', dateFrom: '', dateTo: '' };
        }
    }

    return { period: 'custom', dateFrom: from, dateTo: to };
}

function resolveRangeFromForm(root, config) {
    const periodSelect = root.querySelector('[name="period"]');
    const period = selectValue(periodSelect) || config.period || '24h';
    const dateFrom = root.querySelector('[name="date_from"]')?.value || '';
    const dateTo = root.querySelector('[name="date_to"]')?.value || '';

    if (period === '24h') {
        return { from: config.today, to: config.today };
    }

    if (period === 'yesterday') {
        return { from: config.yesterday, to: config.yesterday };
    }

    if (period === '7d') {
        return { from: addLocalDays(config.today, -6), to: config.today };
    }

    if (period === '30d') {
        return { from: addLocalDays(config.today, -29), to: config.today };
    }

    if (period === 'custom' && dateFrom && dateTo) {
        return { from: dateFrom, to: dateTo };
    }

    return { from: config.rangeFrom, to: config.rangeTo };
}

function setFlatpickrValue(input, value) {
    if (!input) {
        return;
    }

    const fp = input._etdFlatpickr;

    if (fp) {
        if (value) {
            fp.setDate(value, true);
        } else {
            fp.clear();
            notifyInputChange(input, '');
        }

        return;
    }

    notifyInputChange(input, value || '');
}

function applyPeriodStateToForm(root, state) {
    const periodSelect = root.querySelector('[name="period"]');
    const dateFromInput = root.querySelector('[name="date_from"]');
    const dateToInput = root.querySelector('[name="date_to"]');

    setSelectValue(periodSelect, state.period);
    setFlatpickrValue(dateFromInput, state.dateFrom);
    setFlatpickrValue(dateToInput, state.dateTo);
}

const accentColor = () => getComputedStyle(document.querySelector('.etd-page') || document.body)
    .getPropertyValue('--etd-accent')
    .trim() || '#1D9E75';

function inputClasses(element) {
    return element.dataset.fpInputClass
        || Array.from(element.classList)
            .filter((className) => ![
                'etd-flatpickr-date',
                'etd-flatpickr-datetime',
                'etd-flatpickr-date-range',
                'flatpickr-input',
            ].includes(className))
            .join(' ');
}

function notifyInputChange(input, value) {
    input.value = value;
    input.dispatchEvent(new Event('input', { bubbles: true }));
    input.dispatchEvent(new Event('change', { bubbles: true }));
}

function initFlatpickrElement(element) {
    if (!element || element._etdFlatpickr) {
        return element?._etdFlatpickr ?? null;
    }

    const isDatetime = element.classList.contains('etd-flatpickr-datetime');
    const altInputClass = inputClasses(element);

    const fp = flatpickr(element, {
        allowInput: true,
        disableMobile: true,
        altInput: true,
        altFormat: isDatetime ? 'd M Y, H:i' : 'd M Y',
        dateFormat: isDatetime ? 'Y-m-d H:i' : 'Y-m-d',
        enableTime: isDatetime,
        time_24hr: isDatetime,
        defaultDate: element.value || element.dataset.default || null,
        altInputClass,
        onChange(_selectedDates, dateStr, instance) {
            notifyInputChange(instance.input, dateStr);
        },
        onReady(_selectedDates, _dateStr, instance) {
            if (instance.altInput) {
                instance.altInput.placeholder = element.dataset.placeholder
                    || (isDatetime ? 'Select date & time' : 'Select date');
            }
        },
    });

    element._etdFlatpickr = fp;

    return fp;
}

function linkRangePair(fromEl, toEl) {
    const fpFrom = fromEl?._etdFlatpickr;
    const fpTo = toEl?._etdFlatpickr;

    if (!fpFrom || !fpTo) {
        return;
    }

    fpFrom.config.onChange.push((dates) => {
        if (dates[0]) {
            fpTo.set('minDate', dates[0]);
        }
    });

    fpTo.config.onChange.push((dates) => {
        if (dates[0]) {
            fpFrom.set('maxDate', dates[0]);
        }
    });

    if (fromEl.value) {
        fpTo.set('minDate', fromEl.value);
    }

    if (toEl.value) {
        fpFrom.set('maxDate', toEl.value);
    }
}

function initRangeGroups(root) {
    const scope = root?.querySelectorAll ? root : document;

    scope.querySelectorAll('[data-etd-date-range]').forEach((group) => {
        const fromEl = group.querySelector('[data-range="from"]');
        const toEl = group.querySelector('[data-range="to"]');

        if (fromEl && !fromEl._etdFlatpickr) {
            initFlatpickrElement(fromEl);
        }

        if (toEl && !toEl._etdFlatpickr) {
            initFlatpickrElement(toEl);
        }

        linkRangePair(fromEl, toEl);
    });
}

function initSingleRangeGroups(root) {
    const scope = root?.querySelectorAll ? root : document;

    scope.querySelectorAll('[data-etd-date-range-single]').forEach((group) => {
        const displayEl = group.querySelector('.etd-flatpickr-date-range');
        const fromEl = group.querySelector('[data-range="from"]');
        const toEl = group.querySelector('[data-range="to"]');

        if (!displayEl) {
            return;
        }

        if (displayEl._etdFlatpickr) {
            displayEl._etdFlatpickr.destroy();
            displayEl._etdFlatpickr = null;
        }

        const defaultFrom = displayEl.dataset.defaultFrom || fromEl?.value || '';
        const defaultTo = displayEl.dataset.defaultTo || toEl?.value || '';
        const defaultDate = defaultFrom && defaultTo ? [defaultFrom, defaultTo] : null;
        const altInputClass = inputClasses(displayEl);

        const fp = flatpickr(displayEl, {
            mode: 'range',
            allowInput: true,
            disableMobile: true,
            altInput: true,
            altFormat: 'j M Y',
            dateFormat: 'Y-m-d',
            defaultDate,
            altInputClass,
            appendTo: document.body,
            onChange(dates, _dateStr, instance) {
                const from = dates[0] ? instance.formatDate(dates[0], 'Y-m-d') : '';
                const to = dates[1] ? instance.formatDate(dates[1], 'Y-m-d') : '';

                if (fromEl) {
                    notifyInputChange(fromEl, from);
                }

                if (toEl) {
                    notifyInputChange(toEl, to);
                }
            },
            onReady(_selectedDates, _dateStr, instance) {
                if (instance.altInput) {
                    instance.altInput.placeholder = displayEl.dataset.placeholder || 'Select date range';
                }
            },
        });

        displayEl._etdFlatpickr = fp;
    });
}

function initEtdFlatpickr(root = document) {
    const scope = root?.querySelectorAll ? root : document;
    const singles = root?.matches?.('.etd-flatpickr-date, .etd-flatpickr-datetime')
        ? [root]
        : [];

    scope.querySelectorAll('.etd-flatpickr-date, .etd-flatpickr-datetime').forEach((element) => {
        if (!element.closest('[data-etd-date-range]') && !element.closest('[data-etd-date-range-single]')) {
            singles.push(element);
        }
    });

    singles.forEach((element) => initFlatpickrElement(element));
    initRangeGroups(scope);
    initSingleRangeGroups(scope);

    document.documentElement.style.setProperty('--etd-flatpickr-accent', accentColor());
}

window.initEtdFlatpickr = initEtdFlatpickr;

function selectValues(select) {
    const value = select.tomselect ? select.tomselect.getValue() : select.value;

    if (Array.isArray(value)) {
        return value.filter((entry) => entry !== '');
    }

    return value === '' ? [] : [value];
}

function selectValue(select) {
    const values = selectValues(select);

    return values[0] ?? '';
}

let etdFilterPeriodAlpineRegistered = false;

function registerEtdFilterPeriodAlpine() {
    if (etdFilterPeriodAlpineRegistered || !window.Alpine) {
        return;
    }

    etdFilterPeriodAlpineRegistered = true;

    window.Alpine.data('etdFilterPeriod', (config) => ({
        drawerPeriod: config.period,
        navLabel: config.initialLabel,
        canGoNext: config.canGoNext,
        rangeFrom: config.rangeFrom,
        rangeTo: config.rangeTo,
        today: config.today,
        yesterday: config.yesterday,
        todayLabel: config.todayLabel,
        yesterdayLabel: config.yesterdayLabel,

        shiftDay(delta) {
            const current = resolveRangeFromForm(this.$root, config);
            const newFrom = addLocalDays(current.from, delta);
            const newTo = addLocalDays(current.to, delta);
            const state = resolvePeriodState(newFrom, newTo, config);

            this.rangeFrom = newFrom;
            this.rangeTo = newTo;
            this.drawerPeriod = state.period;
            this.navLabel = formatRangeLabel(newFrom, newTo, config);
            this.canGoNext = newTo < config.today;

            this.$nextTick(() => {
                applyPeriodStateToForm(this.$root, state);
            });
        },
    }));
}

document.addEventListener('alpine:init', registerEtdFilterPeriodAlpine);
registerEtdFilterPeriodAlpine();

function setSelectValue(select, value) {
    if (select.tomselect) {
        if (select.multiple) {
            const nextValues = Array.isArray(value)
                ? value
                : (value === '' || value === null || value === undefined ? [] : [value]);
            select.tomselect.setValue(nextValues, true);

            return;
        }

        select.tomselect.setValue(value ?? '', true);

        return;
    }

    if (select.multiple) {
        const nextValues = Array.isArray(value)
            ? value
            : (value === '' || value === null || value === undefined ? [] : [value]);

        Array.from(select.options).forEach((option) => {
            option.selected = nextValues.includes(option.value);
        });

        return;
    }

    select.value = value ?? '';
}

function setSelectDisabled(select, disabled) {
    if (select.tomselect) {
        if (disabled) {
            select.tomselect.disable();
        } else {
            select.tomselect.enable();
        }

        return;
    }

    select.disabled = disabled;
}

function escapeHtml(value) {
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function categoryOptionRecords(select) {
    const seen = new Set();
    const records = [];

    select.querySelectorAll('option[data-department]').forEach((option) => {
        const record = {
            value: option.value,
            text: option.textContent.trim(),
            department: option.dataset.department,
        };
        const key = `${record.department}|${record.value}`;

        if (seen.has(key)) {
            return;
        }

        seen.add(key);
        records.push(record);
    });

    return records;
}

function categoryCatalogFromContainer(container, categorySelect) {
    const raw = container.dataset.etdCategoryCatalog;

    if (!raw) {
        return categoryOptionRecords(categorySelect);
    }

    try {
        const byDepartment = JSON.parse(raw);

        return Object.entries(byDepartment).flatMap(([department, categories]) => (
            Array.isArray(categories)
                ? categories.map((category) => ({
                    value: String(category),
                    text: String(category),
                    department: String(department),
                }))
                : []
        ));
    } catch {
        return categoryOptionRecords(categorySelect);
    }
}

function matchingCategoryOptions(options, department) {
    if (department === '') {
        return [];
    }

    return options.filter((option) => option.department === department);
}

function writeCategorySelectOptions(categorySelect, options, department, emptyLabel) {
    const matching = matchingCategoryOptions(options, department);

    categorySelect.innerHTML = matching.map((option) => (
        `<option value="${escapeHtml(option.value)}" data-department="${escapeHtml(option.department)}">${escapeHtml(option.text)}</option>`
    )).join('');
}

function syncNativeCategoryOptions(categorySelect, options, department, selectedValues, emptyLabel) {
    writeCategorySelectOptions(categorySelect, options, department, emptyLabel);
    categorySelect.disabled = department === '';

    const values = Array.isArray(selectedValues)
        ? selectedValues
        : (selectedValues ? [selectedValues] : []);

    Array.from(categorySelect.options).forEach((option) => {
        option.selected = values.includes(option.value);
    });
}

function syncTomSelectCategoryOptions(categorySelect, options, department, selectedValues, emptyLabel) {
    const ts = categorySelect.tomselect;
    const matching = matchingCategoryOptions(options, department);

    writeCategorySelectOptions(categorySelect, options, department, emptyLabel);

    ts.clearOptions();
    matching.forEach((option) => {
        ts.addOption({
            value: option.value,
            text: option.text,
        });
    });
    ts.refreshOptions(false);
    setSelectDisabled(categorySelect, department === '');

    const values = Array.isArray(selectedValues)
        ? selectedValues
        : (selectedValues ? [selectedValues] : []);
    const nextValues = values.filter((value) => matching.some((option) => option.value === value));
    ts.setValue(nextValues, true);
}

function initDepartmentCategoryFilters(root) {
    const scope = root?.querySelectorAll ? root : document;

    scope.querySelectorAll('[data-etd-department-category]').forEach((container) => {
        const departmentSelect = container.querySelector('[data-etd-department-select]');
        const categorySelect = container.querySelector('[data-etd-category-select]');

        if (!departmentSelect || !categorySelect) {
            return;
        }

        const snapshot = categoryCatalogFromContainer(container, categorySelect);

        if (snapshot.length) {
            container._etdCategoryOptions = snapshot;
        }

        if (!container._etdCategoryEmptyLabel) {
            container._etdCategoryEmptyLabel = categorySelect.querySelector('option:not([data-department])')
                ?.textContent
                ?.trim() || 'All categories';
        }

        const categoryOptions = container._etdCategoryOptions || snapshot;
        const emptyLabel = container._etdCategoryEmptyLabel;

        const syncCategoryOptions = (resetCategory = false) => {
            const department = selectValue(departmentSelect);
            const previousValues = resetCategory ? [] : selectValues(categorySelect);
            const selectedValues = matchingCategoryOptions(categoryOptions, department)
                .filter((option) => previousValues.includes(option.value))
                .map((option) => option.value);

            if (categorySelect.tomselect) {
                syncTomSelectCategoryOptions(
                    categorySelect,
                    categoryOptions,
                    department,
                    selectedValues,
                    emptyLabel,
                );
            } else {
                syncNativeCategoryOptions(
                    categorySelect,
                    categoryOptions,
                    department,
                    selectedValues,
                    emptyLabel,
                );
            }
        };

        if (!container._etdDepartmentCategoryBound) {
            container._etdDepartmentCategoryBound = true;

            departmentSelect.addEventListener('change', () => {
                setSelectValue(categorySelect, '');
                syncCategoryOptions(true);
            });
        }

        syncCategoryOptions();

        if (categorySelect.classList.contains('tom-select') && !categorySelect.tomselect) {
            const startedAt = Date.now();
            const waitForTomSelect = window.setInterval(() => {
                if (categorySelect.tomselect || Date.now() - startedAt > 2000) {
                    window.clearInterval(waitForTomSelect);

                    if (categorySelect.tomselect) {
                        syncCategoryOptions();
                    }
                }
            }, 50);
        }
    });
}

window.initDepartmentCategoryFilters = initDepartmentCategoryFilters;

window.refreshEtdFilterControls = function (root) {
    if (typeof window.refreshTomSelectIn === 'function') {
        window.refreshTomSelectIn(root);
    }

    initEtdFlatpickr(root);
    initDepartmentCategoryFilters(root);

    if (typeof window.initEtdTipPositioning === 'function') {
        window.initEtdTipPositioning(root);
    }
};

window.syncEtdFlatpickrEnabled = function (container, enabled) {
    if (!container) {
        return;
    }

    container.querySelectorAll('.etd-flatpickr-date, .etd-flatpickr-datetime, .etd-flatpickr-date-range').forEach((element) => {
        const fp = element._etdFlatpickr;

        if (!fp) {
            return;
        }

        fp.set('clickOpens', enabled);

        if (fp.altInput) {
            fp.altInput.disabled = !enabled;
            fp.altInput.classList.toggle('is-disabled', !enabled);
        }

        fp.input.disabled = !enabled;
    });
};

function boot() {
    initEtdFlatpickr(document);
    initDepartmentCategoryFilters(document);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
