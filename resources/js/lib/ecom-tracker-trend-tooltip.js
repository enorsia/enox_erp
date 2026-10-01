const TREND_TOOLTIP_GROUPS = [
    { label: 'Reach', keys: ['unique_visitors', 'sessions'] },
    { label: 'Discovery', keys: ['category_views', 'product_views'] },
    { label: 'Checkout', keys: ['add_to_cart', 'begin_checkout', 'proceed_checkout'] },
    { label: 'Sales', keys: ['purchases', 'items_sold_qty', 'conversion_rate'] },
];

let trendTooltipElement = null;

const isDark = () => document.documentElement.classList.contains('dark');

export function formatTrendTooltipValue(key, value) {
    const numeric = Number(value) || 0;

    if (key === 'conversion_rate') {
        return `${numeric.toFixed(1)}%`;
    }

    return numeric.toLocaleString('en-GB');
}

function getOrCreateTrendTooltip() {
    if (trendTooltipElement) {
        return trendTooltipElement;
    }

    trendTooltipElement = document.createElement('div');
    trendTooltipElement.className = 'etd-trend-tooltip';
    trendTooltipElement.setAttribute('role', 'tooltip');
    trendTooltipElement.innerHTML = '<div class="etd-trend-tooltip__card"></div>';
    document.body.appendChild(trendTooltipElement);

    return trendTooltipElement;
}

export function hideTrendTooltip() {
    if (!trendTooltipElement) {
        return;
    }

    trendTooltipElement.classList.remove('etd-trend-tooltip--visible', 'etd-trend-tooltip--below');
}

function trendTooltipViewportPadding() {
    const viewportWidth = window.innerWidth;

    if (viewportWidth <= 360) {
        return 8;
    }

    if (viewportWidth <= 640) {
        return 10;
    }

    return 12;
}

function trendTooltipUsesPanelWidth() {
    return window.innerWidth <= 640;
}

function trendTooltipPreferredMaxWidth(isCompare) {
    const viewportWidth = window.innerWidth;

    if (viewportWidth <= 360) {
        return viewportWidth - 12;
    }

    if (viewportWidth <= 640) {
        return null;
    }

    if (viewportWidth >= 1024) {
        return isCompare ? 320 : 384;
    }

    return isCompare ? 320 : 352;
}

function getTooltipBounds(chart) {
    const viewportPadding = trendTooltipViewportPadding();
    const bounds = {
        left: viewportPadding,
        right: window.innerWidth - viewportPadding,
        top: viewportPadding,
        bottom: window.innerHeight - viewportPadding,
    };

    const container = chart.canvas.closest('.etd-compare-column')
        || chart.canvas.closest('[data-compare-sync="trend"]')
        || chart.canvas.closest('.etd-panel');

    if (!container) {
        return bounds;
    }

    const rect = container.getBoundingClientRect();
    const inset = viewportPadding <= 8 ? 6 : 8;

    return {
        left: Math.max(bounds.left, rect.left + inset),
        right: Math.min(bounds.right, rect.right - inset),
        top: Math.max(bounds.top, rect.top + inset),
        bottom: Math.min(bounds.bottom, rect.bottom - inset),
    };
}

function positionTrendTooltip(tooltipEl, chart, tooltip) {
    const cardEl = tooltipEl.querySelector('.etd-trend-tooltip__card');
    const viewportPadding = trendTooltipViewportPadding();
    const gap = viewportPadding <= 8 ? 10 : 12;
    const bounds = getTooltipBounds(chart);
    const canvasRect = chart.canvas.getBoundingClientRect();
    const anchorX = canvasRect.left + tooltip.caretX;
    const anchorY = canvasRect.top + tooltip.caretY;
    const panelWidth = Math.max(148, bounds.right - bounds.left);
    const isCompare = Boolean(chart.canvas.closest('#ecom-tracker-compare-content'));
    const preferredMaxWidth = trendTooltipPreferredMaxWidth(isCompare);
    const viewportMaxWidth = window.innerWidth - (viewportPadding * 2);

    tooltipEl.classList.toggle('etd-trend-tooltip--compare', isCompare);

    if (trendTooltipUsesPanelWidth()) {
        const mobileWidth = Math.min(panelWidth, viewportMaxWidth);

        tooltipEl.style.maxWidth = `${mobileWidth}px`;
        tooltipEl.style.width = `${mobileWidth}px`;
    } else {
        const desktopMaxWidth = Math.min(
            preferredMaxWidth ?? panelWidth,
            panelWidth,
            viewportMaxWidth,
        );

        tooltipEl.style.maxWidth = `${desktopMaxWidth}px`;
        tooltipEl.style.removeProperty('width');
    }

    tooltipEl.classList.remove('etd-trend-tooltip--below', 'etd-trend-tooltip--visible');
    tooltipEl.classList.add('etd-trend-tooltip--positioning');
    tooltipEl.style.left = `${anchorX}px`;
    tooltipEl.style.top = `${anchorY}px`;
    tooltipEl.style.transform = 'translate(-50%, calc(-100% - 12px))';

    const { width, height } = tooltipEl.getBoundingClientRect();
    const minCenterX = bounds.left + (width / 2);
    const maxCenterX = bounds.right - (width / 2);
    const centerX = minCenterX > maxCenterX
        ? bounds.left + (panelWidth / 2)
        : Math.max(minCenterX, Math.min(maxCenterX, anchorX));
    const arrowOffset = anchorX - centerX;
    const spaceAbove = anchorY - bounds.top;
    const spaceBelow = bounds.bottom - anchorY;
    const maxTooltipHeight = Math.max(
        112,
        bounds.bottom - bounds.top - gap,
    );

    if (cardEl) {
        cardEl.style.setProperty('--etd-tooltip-arrow-offset', `${arrowOffset}px`);
        cardEl.style.maxHeight = `${maxTooltipHeight}px`;
    }

    let placement = 'above';

    if (height + gap > spaceAbove && spaceBelow > spaceAbove) {
        placement = 'below';
    } else if (height + gap > spaceAbove && height + gap > spaceBelow) {
        placement = spaceBelow >= spaceAbove ? 'below' : 'above';
    }

    tooltipEl.style.left = `${centerX}px`;
    tooltipEl.style.top = `${anchorY}px`;
    tooltipEl.style.transform = placement === 'below'
        ? `translate(-50%, ${gap}px)`
        : `translate(-50%, calc(-100% - ${gap}px))`;
    tooltipEl.classList.toggle('etd-trend-tooltip--below', placement === 'below');
    tooltipEl.classList.remove('etd-trend-tooltip--positioning');
    tooltipEl.classList.add('etd-trend-tooltip--visible');
}

function buildTrendTooltipHtml(title, seriesByKey) {
    const groups = TREND_TOOLTIP_GROUPS.map((group) => {
        const rows = group.keys
            .map((key) => seriesByKey[key])
            .filter(Boolean);

        if (!rows.length) {
            return '';
        }

        const rowHtml = rows.map((entry) => `
            <div class="etd-trend-tooltip__row">
                <span class="etd-trend-tooltip__swatch" style="--etd-trend-swatch:${entry.color}"></span>
                <span class="etd-trend-tooltip__label">${entry.label}</span>
                <span class="etd-trend-tooltip__value">${entry.formatted}</span>
            </div>
        `).join('');

        return `
            <div class="etd-trend-tooltip__group">
                <div class="etd-trend-tooltip__group-label">${group.label}</div>
                ${rowHtml}
            </div>
        `;
    }).filter(Boolean).join('');

    return `
        <div class="etd-trend-tooltip__header">
            <span class="etd-trend-tooltip__date">${title}</span>
        </div>
        <div class="etd-trend-tooltip__body">
            ${groups}
        </div>
    `;
}

export function createTrendTooltipHandler(orderedSeries, colorForKey) {
    const seriesByKey = Object.fromEntries(
        orderedSeries.map((entry) => [entry.key, entry]),
    );

    return (context) => {
        const { chart, tooltip } = context;
        const tooltipEl = getOrCreateTrendTooltip();
        const cardEl = tooltipEl.querySelector('.etd-trend-tooltip__card');

        tooltipEl.classList.toggle('etd-trend-tooltip--dark', isDark());

        if (tooltip.opacity === 0 || !tooltip.dataPoints?.length) {
            tooltipEl.classList.remove('etd-trend-tooltip--visible');

            return;
        }

        const dataIndex = tooltip.dataPoints[0].dataIndex;
        const title = tooltip.title?.[0] || tooltip.dataPoints[0].label || '';
        const valuesByKey = {};

        tooltip.dataPoints.forEach((point) => {
            const seriesEntry = orderedSeries[point.datasetIndex];

            if (!seriesEntry) {
                return;
            }

            const rawValue = point.parsed?.y ?? point.raw ?? 0;

            valuesByKey[seriesEntry.key] = {
                label: seriesEntry.label,
                color: colorForKey(seriesEntry.key),
                formatted: formatTrendTooltipValue(seriesEntry.key, rawValue),
            };
        });

        Object.entries(seriesByKey).forEach(([key, entry]) => {
            if (valuesByKey[key]) {
                return;
            }

            const rawValue = entry.data?.[dataIndex] ?? 0;

            valuesByKey[key] = {
                label: entry.label,
                color: colorForKey(key),
                formatted: formatTrendTooltipValue(key, rawValue),
            };
        });

        if (cardEl) {
            cardEl.innerHTML = buildTrendTooltipHtml(title, valuesByKey);
            cardEl.style.removeProperty('max-height');
        }

        positionTrendTooltip(tooltipEl, chart, tooltip);
    };
}

let trendTooltipDismissBound = false;

export function bindTrendTooltipDismiss(scrollElement = null) {
    const handleTrendTooltipDismiss = () => {
        hideTrendTooltip();
    };

    if (!trendTooltipDismissBound) {
        window.addEventListener('resize', handleTrendTooltipDismiss, { passive: true });
        window.addEventListener('scroll', handleTrendTooltipDismiss, { passive: true, capture: true });
        trendTooltipDismissBound = true;
    }

    scrollElement?.addEventListener('scroll', handleTrendTooltipDismiss, { passive: true });
}
