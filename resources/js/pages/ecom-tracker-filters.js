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

window.refreshEtdFilterControls = function (root) {
    if (typeof window.refreshTomSelectIn === 'function') {
        window.refreshTomSelectIn(root);
    }

    initEtdFlatpickr(root);
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
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', boot);
} else {
    boot();
}
