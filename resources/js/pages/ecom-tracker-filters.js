import flatpickr from 'flatpickr';
import 'flatpickr/dist/flatpickr.min.css';

const accentColor = () => getComputedStyle(document.querySelector('.etd-page') || document.body)
    .getPropertyValue('--etd-accent')
    .trim() || '#1D9E75';

function inputClasses(element) {
    return element.dataset.fpInputClass
        || Array.from(element.classList)
            .filter((className) => !['etd-flatpickr-date', 'etd-flatpickr-datetime', 'flatpickr-input'].includes(className))
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

function initEtdFlatpickr(root = document) {
    const scope = root?.querySelectorAll ? root : document;
    const singles = root?.matches?.('.etd-flatpickr-date, .etd-flatpickr-datetime')
        ? [root]
        : [];

    scope.querySelectorAll('.etd-flatpickr-date, .etd-flatpickr-datetime').forEach((element) => {
        if (!element.closest('[data-etd-date-range]')) {
            singles.push(element);
        }
    });

    singles.forEach((element) => initFlatpickrElement(element));
    initRangeGroups(scope);

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
};

window.syncEtdFlatpickrEnabled = function (container, enabled) {
    if (!container) {
        return;
    }

    container.querySelectorAll('.etd-flatpickr-date, .etd-flatpickr-datetime').forEach((element) => {
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
