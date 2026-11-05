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

    const inFilterPanel = Boolean(element.closest('.etd-filter-panel'));

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
        appendTo: inFilterPanel ? document.body : undefined,
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
    if (select.tomselect) {
        const value = select.tomselect.getValue();

        if (Array.isArray(value)) {
            return value.filter((entry) => entry !== '');
        }

        return value === '' ? [] : [value];
    }

    if (select.multiple) {
        return Array.from(select.selectedOptions)
            .map((option) => option.value)
            .filter((value) => value !== '');
    }

    const value = select.value;

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
        select._etdSilentTomUpdate = true;

        if (select.multiple) {
            const nextValues = Array.isArray(value)
                ? value
                : (value === '' || value === null || value === undefined ? [] : [value]);
            select.tomselect.setValue(nextValues, true);
        } else {
            select.tomselect.setValue(value ?? '', true);
        }

        select._etdSilentTomUpdate = false;

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

function containerShowsAllCategories(container) {
    const value = container?.dataset?.etdShowAllCategories;

    return value === '1' || value === 'true';
}

function alwaysVisibleCategoryDepartments(container) {
    const raw = container?.dataset?.etdAlwaysVisibleCategoryDepartments?.trim();

    if (!raw) {
        return [];
    }

    return raw.split(',').map((entry) => entry.trim()).filter(Boolean);
}

function matchingCategoryOptions(options, department, showAllWhenNoDepartment = false, alwaysVisibleDepartments = []) {
    const departmentKeys = Array.isArray(department)
        ? department.filter((value) => value !== '')
        : (department === '' ? [] : [department]);

    if (departmentKeys.length === 0) {
        return showAllWhenNoDepartment ? options : [];
    }

    return options.filter((option) => (
        departmentKeys.includes(option.department)
        || alwaysVisibleDepartments.includes(option.department)
    ));
}

function selectedDepartmentKeys(departmentSelect) {
    const allValue = filterAllOptionValue(departmentSelect);
    let keys;

    if (departmentSelect.multiple) {
        keys = selectValues(departmentSelect);
    } else {
        const value = selectValue(departmentSelect);

        keys = value === '' ? [] : [value];
    }

    return keys.filter((value) => value !== '' && (allValue === '' || value !== allValue));
}

function filterAllOptionValue(select) {
    return select?.dataset?.etdFilterAllValue || '';
}

function categoryAllOptionValue(categorySelect) {
    return filterAllOptionValue(categorySelect);
}

function departmentKeysFromFilter(department) {
    return Array.isArray(department)
        ? department.filter((value) => value !== '')
        : (department === '' ? [] : [department]);
}

function writeCategorySelectOptions(categorySelect, options, department, emptyLabel, showAllWhenNoDepartment = false, alwaysVisibleDepartments = []) {
    const matching = matchingCategoryOptions(options, department, showAllWhenNoDepartment, alwaysVisibleDepartments);
    const departmentKeys = departmentKeysFromFilter(department);
    const allValue = categoryAllOptionValue(categorySelect);
    const allLabel = emptyLabel || 'All';
    const showAllOption = allValue && (departmentKeys.length > 0 || showAllWhenNoDepartment);

    let html = '';

    if (showAllOption) {
        html += `<option value="${escapeHtml(allValue)}">${escapeHtml(allLabel)}</option>`;
    }

    html += matching.map((option) => (
        `<option value="${escapeHtml(option.value)}" data-department="${escapeHtml(option.department)}">${escapeHtml(option.text)}</option>`
    )).join('');

    categorySelect.innerHTML = html;
}

function syncNativeCategoryOptions(categorySelect, options, department, selectedValues, emptyLabel, showAllWhenNoDepartment = false, alwaysVisibleDepartments = []) {
    writeCategorySelectOptions(categorySelect, options, department, emptyLabel, showAllWhenNoDepartment, alwaysVisibleDepartments);
    const departmentKeys = departmentKeysFromFilter(department);
    categorySelect.disabled = !showAllWhenNoDepartment && departmentKeys.length === 0;

    const values = Array.isArray(selectedValues)
        ? selectedValues
        : (selectedValues ? [selectedValues] : []);
    const allValue = categoryAllOptionValue(categorySelect);
    const showAllOption = allValue && (departmentKeys.length > 0 || showAllWhenNoDepartment);
    let nextValues = values.filter((value) => (
        Array.from(categorySelect.options).some((option) => option.value === value)
    ));

    if (allValue && (departmentKeys.length > 0 || showAllWhenNoDepartment)) {
        nextValues = resolveFilterTomSelectValues(nextValues, allValue);
    }

    if (nextValues.length === 0 && showAllOption) {
        nextValues = [allValue];
    }

    Array.from(categorySelect.options).forEach((option) => {
        option.selected = nextValues.includes(option.value);
    });
}

function resolveFilterTomSelectValues(values, allValue) {
    if (!allValue) {
        return values;
    }

    if (values.includes(allValue)) {
        return [allValue];
    }

    return values.filter((value) => value !== allValue);
}

function patchEtdFilterTomSelect(select) {
    if (!select?.multiple || !filterAllOptionValue(select) || !select.tomselect) {
        return false;
    }

    if (select._etdFilterTomPatched) {
        return true;
    }

    select._etdFilterTomPatched = true;
    const ts = select.tomselect;
    ts.settings.closeAfterSelect = false;
    ts.settings.hideSelected = false;
    const baseInputState = ts.inputState.bind(ts);

    ts.inputState = function inputState() {
        baseInputState();
        this.isInputHidden = false;
        this.wrapper.classList.remove('input-hidden');
    };

    ts.onClick = function onFilterTomSelectClick() {
        if (this.activeItems.length > 0) {
            this.clearActiveItems();
        }

        this.focus();

        if (!this.isOpen) {
            this.open();
        }
    };

    ts.control.addEventListener('mousedown', (event) => {
        if (event.target.closest('.remove')) {
            return;
        }

        if (!ts.isOpen) {
            event.preventDefault();
            ts.focus();
            ts.open();
        }
    });

    if (!select._etdKeepDropdownBound) {
        select._etdKeepDropdownBound = true;

        ts.on('dropdown_open', () => {
            const container = select.closest('[data-etd-department-category]');

            container?.querySelectorAll('select.tom-select').forEach((peer) => {
                if (peer !== select && peer.tomselect?.isOpen) {
                    peer.tomselect.close();
                }
            });
        });
    }

    return true;
}

window.patchEtdFilterTomSelect = patchEtdFilterTomSelect;

function syncTomSelectCategoryOptions(categorySelect, options, department, selectedValues, emptyLabel, showAllWhenNoDepartment = false, alwaysVisibleDepartments = []) {
    const ts = categorySelect.tomselect;
    const matching = matchingCategoryOptions(options, department, showAllWhenNoDepartment, alwaysVisibleDepartments);
    const allValue = categoryAllOptionValue(categorySelect);
    const allLabel = emptyLabel || 'All';
    const departmentKeys = departmentKeysFromFilter(department);
    const showAllOption = allValue && (departmentKeys.length > 0 || showAllWhenNoDepartment);

    if (!ts) {
        writeCategorySelectOptions(categorySelect, options, department, emptyLabel, showAllWhenNoDepartment, alwaysVisibleDepartments);

        return;
    }

    ts.clearOptions();

    if (showAllOption) {
        ts.addOption({
            value: allValue,
            text: allLabel,
        });
    }

    matching.forEach((option) => {
        ts.addOption({
            value: option.value,
            text: option.text,
        });
    });
    ts.refreshOptions(false);
    setSelectDisabled(categorySelect, !showAllWhenNoDepartment && departmentKeys.length === 0);

    const values = Array.isArray(selectedValues)
        ? selectedValues
        : (selectedValues ? [selectedValues] : []);
    let nextValues = values.filter((value) => (
        value === allValue || matching.some((option) => option.value === value)
    ));

    if (allValue && (departmentKeys.length > 0 || showAllWhenNoDepartment)) {
        nextValues = resolveFilterTomSelectValues(nextValues, allValue);
    }

    if (nextValues.length === 0 && showAllOption) {
        nextValues = [allValue];
    }

    categorySelect._etdSilentTomUpdate = true;
    ts.setValue(nextValues, true);
    categorySelect._etdSilentTomUpdate = false;
    patchEtdFilterTomSelect(categorySelect);
}

function bindFilterAllOption(select) {
    const allValue = filterAllOptionValue(select);

    if (!allValue || select._etdAllOptionBound) {
        return;
    }

    let previousList = selectValues(select);

    const applyAllRules = (rawValues) => {
        if (select._etdApplyingAllRules) {
            return;
        }

        if (!select.multiple) {
            const list = Array.isArray(rawValues) ? rawValues : (rawValues ? [rawValues] : []);
            const value = list[0] ?? '';

            if (value === '' || value === allValue) {
                if (value !== allValue) {
                    select._etdApplyingAllRules = true;
                    setSelectValue(select, [allValue]);
                    select._etdApplyingAllRules = false;
                }
            }

            previousList = selectValues(select);

            return;
        }

        const list = Array.isArray(rawValues)
            ? rawValues.filter((entry) => entry !== '')
            : (rawValues ? [rawValues] : []);
        const specifics = list.filter((value) => value !== allValue);

        if (list.includes(allValue)) {
            const allWasAdded = !previousList.includes(allValue);

            select._etdApplyingAllRules = true;
            if (allWasAdded || specifics.length === 0) {
                setSelectValue(select, [allValue]);
            } else {
                setSelectValue(select, specifics);
            }
            select._etdApplyingAllRules = false;
            previousList = selectValues(select);

            return;
        }

        if (specifics.length === 0) {
            select._etdApplyingAllRules = true;
            setSelectValue(select, [allValue]);
            select._etdApplyingAllRules = false;
            previousList = selectValues(select);

            return;
        }

        previousList = selectValues(select);
    };

    const bindTom = () => {
        if (!select.tomselect || select._etdAllOptionTomBound) {
            return Boolean(select.tomselect);
        }

        select._etdAllOptionTomBound = true;
        select._etdAllOptionBound = true;
        previousList = selectValues(select);
        select.tomselect.on('change', applyAllRules);
        patchEtdFilterTomSelect(select);

        if (select.multiple && previousList.length === 0) {
            select._etdApplyingAllRules = true;
            setSelectValue(select, [allValue]);
            select._etdApplyingAllRules = false;
            previousList = selectValues(select);
        }

        return true;
    };

    if (bindTom()) {
        return;
    }

    const startedAt = Date.now();
    const timer = window.setInterval(() => {
        if (bindTom() || Date.now() - startedAt > 3000) {
            window.clearInterval(timer);
        }
    }, 50);
}

function bindCategoryAllOption(categorySelect) {
    bindFilterAllOption(categorySelect);
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
                ?.trim() || 'All';
        }

        bindFilterAllOption(departmentSelect);
        bindCategoryAllOption(categorySelect);

        const categoryOptions = container._etdCategoryOptions || snapshot;
        const emptyLabel = container._etdCategoryEmptyLabel;
        const showAllCategories = containerShowsAllCategories(container);
        const alwaysVisibleDepartments = alwaysVisibleCategoryDepartments(container);

        const syncCategoryOptions = (resetCategory = false) => {
            const departmentKeys = selectedDepartmentKeys(departmentSelect);

            if (departmentKeys.length === 0 && !showAllCategories) {
                resetCategory = true;
            }

            const deptKey = JSON.stringify(departmentKeys);
            const canSkipResync = !resetCategory
                && container._etdCategoryDeptKey === deptKey
                && categorySelect.tomselect;

            if (canSkipResync) {
                return;
            }

            container._etdCategoryDeptKey = deptKey;

            const previousValues = resetCategory ? [] : selectValues(categorySelect);
            const filteredPrevious = previousValues.filter((value) => value !== filterAllOptionValue(categorySelect));
            const selectedValues = matchingCategoryOptions(
                categoryOptions,
                departmentKeys,
                showAllCategories,
                alwaysVisibleDepartments,
            )
                .filter((option) => filteredPrevious.includes(option.value))
                .map((option) => option.value);

            if (categorySelect.tomselect) {
                syncTomSelectCategoryOptions(
                    categorySelect,
                    categoryOptions,
                    departmentKeys,
                    selectedValues,
                    emptyLabel,
                    showAllCategories,
                    alwaysVisibleDepartments,
                );
            } else {
                syncNativeCategoryOptions(
                    categorySelect,
                    categoryOptions,
                    departmentKeys,
                    selectedValues,
                    emptyLabel,
                    showAllCategories,
                    alwaysVisibleDepartments,
                );
            }
        };

        if (!container._etdDepartmentCategoryBound) {
            container._etdDepartmentCategoryBound = true;

            departmentSelect.addEventListener('change', () => {
                if (departmentSelect._etdSilentTomUpdate || departmentSelect._etdApplyingAllRules) {
                    return;
                }

                container._etdCategoryDeptKey = null;

                if (!departmentSelect.multiple) {
                    setSelectValue(categorySelect, '');
                }
                syncCategoryOptions(!departmentSelect.multiple);
            });

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
        }
    });
}

window.initDepartmentCategoryFilters = initDepartmentCategoryFilters;

window.refreshEtdFilterControls = function (root) {
    initEtdFlatpickr(root);
    initDepartmentCategoryFilters(root);

    if (typeof window.refreshTomSelectIn === 'function') {
        window.refreshTomSelectIn(root);
    }

    if (typeof window.initEtdTipPositioning === 'function') {
        window.initEtdTipPositioning(root);
    }
};

function readFilterPeriodConfig(section) {
    return {
        period: section.dataset.etdPeriod || '24h',
        today: section.dataset.etdToday || '',
        yesterday: section.dataset.etdYesterday || '',
        rangeFrom: section.dataset.etdRangeFrom || '',
        rangeTo: section.dataset.etdRangeTo || '',
        todayLabel: section.dataset.etdTodayLabel || 'Today',
        yesterdayLabel: section.dataset.etdYesterdayLabel || 'Yesterday',
    };
}

function setFilterPeriodCustomOpen(section, open) {
    const customPanel = section.querySelector('.etd-filter-period__custom');

    if (!customPanel) {
        return;
    }

    customPanel.classList.toggle('etd-filter-period__custom--closed', !open);
    customPanel.querySelectorAll('[name="date_from"], [name="date_to"]').forEach((input) => {
        input.disabled = !open;
    });

    if (typeof window.syncEtdFlatpickrEnabled === 'function') {
        window.syncEtdFlatpickrEnabled(customPanel, open);
    }

    if (open && typeof window.refreshEtdFilterControls === 'function') {
        window.refreshEtdFilterControls(customPanel);
    }
}

function updateFilterPeriodPresetActive(section, period) {
    section.querySelectorAll('.etd-filter-period__preset[data-period]').forEach((button) => {
        button.classList.toggle('is-active', button.dataset.period === period);
    });
}

function updateFilterPeriodNav(section, config, from, to) {
    const label = section.querySelector('.js-etd-filter-period-nav-label');
    const nextBtn = section.querySelector('.js-etd-filter-period-shift[data-shift="1"]');

    if (label) {
        label.textContent = formatRangeLabel(from, to, config);
    }

    if (nextBtn) {
        const canGoNext = config.today && to < config.today;
        nextBtn.classList.toggle('is-disabled', !canGoNext);
        nextBtn.toggleAttribute('disabled', !canGoNext);
        nextBtn.setAttribute('aria-disabled', canGoNext ? 'false' : 'true');
    }
}

function applyFilterPeriodPreset(section, periodKey, config) {
    let state;

    if (periodKey === '24h') {
        state = { period: '24h', dateFrom: '', dateTo: '' };
    } else if (periodKey === 'yesterday') {
        state = { period: 'yesterday', dateFrom: '', dateTo: '' };
    } else if (periodKey === '7d') {
        state = { period: '7d', dateFrom: '', dateTo: '' };
    } else if (periodKey === '30d') {
        state = { period: '30d', dateFrom: '', dateTo: '' };
    } else if (periodKey === 'custom') {
        const root = section.closest('form') || section;
        const from = root.querySelector('[name="date_from"]')?.value || config.rangeFrom;
        const to = root.querySelector('[name="date_to"]')?.value || config.rangeTo;

        applyPeriodStateToForm(root, { period: 'custom', dateFrom: from, dateTo: to });
        setFilterPeriodCustomOpen(section, true);
        updateFilterPeriodPresetActive(section, 'custom');
        updateFilterPeriodNav(section, config, from || config.rangeFrom, to || config.rangeTo);

        return;
    } else {
        return;
    }

    applyPeriodStateToForm(section.closest('form') || section, state);
    setFilterPeriodCustomOpen(section, false);
    updateFilterPeriodPresetActive(section, state.period);

    const range = resolveRangeFromForm(section.closest('form') || section, config);
    updateFilterPeriodNav(section, config, range.from, range.to);
}

function shiftFilterPeriodDay(section, delta, config) {
    const root = section.closest('form') || section;
    const current = resolveRangeFromForm(root, config);
    const newFrom = addLocalDays(current.from, delta);
    const newTo = addLocalDays(current.to, delta);

    if (config.today && newTo > config.today) {
        return;
    }

    const state = resolvePeriodState(newFrom, newTo, config);

    applyPeriodStateToForm(root, state);
    setFilterPeriodCustomOpen(section, state.period === 'custom');
    updateFilterPeriodPresetActive(section, state.period);
    updateFilterPeriodNav(section, config, newFrom, newTo);
}

window.initEtdFilterDrawerPeriod = function initEtdFilterDrawerPeriod(drawerRoot) {
    const drawer = drawerRoot?.closest?.('.etd-filter-panel') || drawerRoot;

    if (!drawer) {
        return;
    }

    const section = drawer.querySelector('[data-etd-filter-period]');

    if (!section || section.dataset.etdFilterPeriodBound === '1') {
        return;
    }

    section.dataset.etdFilterPeriodBound = '1';

    const config = readFilterPeriodConfig(section);
    const form = drawer.querySelector('form.etd-filter-panel__form');

    section.querySelectorAll('.js-etd-filter-period-shift').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();

            if (button.classList.contains('is-disabled') || button.disabled) {
                return;
            }

            const delta = Number(button.dataset.shift || '0');
            shiftFilterPeriodDay(section, delta, config);
        });
    });

    section.querySelectorAll('.etd-filter-period__preset[data-period]').forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            applyFilterPeriodPreset(section, button.dataset.period, config);
        });
    });

    if (form && form.dataset.etdFilterSubmitBound !== '1') {
        form.dataset.etdFilterSubmitBound = '1';

        form.addEventListener('submit', () => {
            const period = form.querySelector('[name="period"]')?.value;

            form.querySelectorAll('[name="date_from"], [name="date_to"]').forEach((input) => {
                if (period !== 'custom') {
                    input.disabled = true;
                }
            });
        });
    }

    const initialRange = resolveRangeFromForm(form || section, config);
    updateFilterPeriodNav(section, config, initialRange.from, initialRange.to);
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
