let activeTrigger = null;
let activeTip = null;

function getTipBounds(trigger) {
    const padding = window.innerWidth <= 360 ? 8 : 10;
    const viewport = {
        left: padding,
        right: window.innerWidth - padding,
        top: padding,
        bottom: window.innerHeight - padding,
    };

    const container = trigger.closest('.etd-compare-column')
        || trigger.closest('.etd-kpi-panel')
        || trigger.closest('.etd-panel')
        || trigger.closest('.etd-page');

    if (!container) {
        return viewport;
    }

    const rect = container.getBoundingClientRect();

    return {
        left: Math.max(viewport.left, rect.left + padding),
        right: Math.min(viewport.right, rect.right - padding),
        top: Math.max(viewport.top, rect.top + padding),
        bottom: Math.min(viewport.bottom, rect.bottom - padding),
    };
}

function restoreTipToTrigger(tip, trigger) {
    if (!tip || !trigger || tip.parentElement === trigger) {
        return;
    }

    if (tip._etdTipOriginNextSibling && tip._etdTipOriginNextSibling.parentElement === trigger) {
        trigger.insertBefore(tip, tip._etdTipOriginNextSibling);
    } else {
        trigger.appendChild(tip);
    }
}

function hideTip(trigger = activeTrigger, tip = activeTip) {
    if (!trigger || !tip) {
        return;
    }

    tip.classList.remove('etd-tip-content--floating', 'etd-tip-content--above');
    tip.style.display = 'none';
    tip.style.visibility = '';
    tip.style.left = '';
    tip.style.top = '';
    tip.style.width = '';
    tip.style.maxWidth = '';
    restoreTipToTrigger(tip, trigger);

    if (activeTip === tip) {
        activeTip = null;
        activeTrigger = null;
    }
}

function positionTip(trigger) {
    const tip = trigger.querySelector('.etd-tip-content');

    if (!tip) {
        return;
    }

    if (activeTip && activeTip !== tip) {
        hideTip(activeTrigger, activeTip);
    }

    if (!tip._etdTipOriginParent) {
        tip._etdTipOriginParent = trigger;
        tip._etdTipOriginNextSibling = tip.nextSibling;
    }

    document.body.appendChild(tip);
    activeTrigger = trigger;
    activeTip = tip;

    tip.classList.add('etd-tip-content--floating');
    tip.style.display = 'flex';
    tip.style.visibility = 'hidden';

    const triggerRect = trigger.getBoundingClientRect();
    const bounds = getTipBounds(trigger);
    const gap = 6;
    const availableWidth = Math.max(120, bounds.right - bounds.left);
    const maxWidth = Math.min(
        tip.classList.contains('etd-tip-content--kpi') ? 240 : 248,
        availableWidth,
    );

    tip.style.maxWidth = `${maxWidth}px`;
    tip.style.width = `${maxWidth}px`;

    const tipRect = tip.getBoundingClientRect();
    let left = triggerRect.left + (triggerRect.width / 2) - (tipRect.width / 2);
    let top = triggerRect.bottom + gap;

    if (left < bounds.left) {
        left = bounds.left;
    }

    if (left + tipRect.width > bounds.right) {
        left = bounds.right - tipRect.width;
    }

    if (top + tipRect.height > bounds.bottom && (triggerRect.top - gap - tipRect.height) >= bounds.top) {
        top = triggerRect.top - gap - tipRect.height;
        tip.classList.add('etd-tip-content--above');
    } else {
        tip.classList.remove('etd-tip-content--above');
    }

    if (top < bounds.top) {
        top = bounds.top;
    }

    if (top + tipRect.height > bounds.bottom) {
        top = Math.max(bounds.top, bounds.bottom - tipRect.height);
    }

    tip.style.left = `${left}px`;
    tip.style.top = `${top}px`;
    tip.style.visibility = '';
}

function bindTipTrigger(trigger) {
    if (trigger.dataset.etdTipBound === '1') {
        return;
    }

    trigger.dataset.etdTipBound = '1';

    trigger.addEventListener('mouseenter', () => {
        positionTip(trigger);
    });

    trigger.addEventListener('focus', () => {
        positionTip(trigger);
    });

    trigger.addEventListener('mouseleave', () => {
        if (activeTrigger === trigger) {
            hideTip(trigger, activeTip);
        }
    });

    trigger.addEventListener('blur', () => {
        if (activeTrigger === trigger) {
            hideTip(trigger, activeTip);
        }
    });
}

export function initEtdTipPositioning(root = document) {
    const scope = root?.querySelectorAll ? root : document;

    scope.querySelectorAll('.etd-tip-trigger:not([data-etd-tip-bound="1"])').forEach((trigger) => {
        bindTipTrigger(trigger);
    });
}

document.addEventListener('scroll', () => {
    if (activeTrigger && activeTip) {
        hideTip(activeTrigger, activeTip);
    }
}, { passive: true, capture: true });

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => initEtdTipPositioning(document), { once: true });
} else {
    initEtdTipPositioning(document);
}

window.initEtdTipPositioning = initEtdTipPositioning;
