(function () {
    "use strict";

    const EXPORT_TYPE = "ecom_activity_report";

    const ROUTES = {
        start: "/admin/exports/ecom-activity/start",
        active: `/admin/exports/active?type=${EXPORT_TYPE}`,
        dismiss: (id) => `/admin/exports/${id}/dismiss`,
    };

    let currentExportId = null;
    let refreshAbortController = null;
    let completionHandled = false;
    let exportStartInFlight = false;
    let progressPollTimer = null;
    let completionTransitionTimer = null;

    const PROGRESS_POLL_MS = 2000;
    const COMPLETION_DISPLAY_MS = 1200;

    const els = {
        wrap: () => document.getElementById("activity-export-status"),
        loading: () => document.getElementById("activity-export-loading"),
        readyGroup: () => document.getElementById("activity-export-ready-group"),
        download: () => document.getElementById("activity-export-download"),
        filename: () => document.getElementById("activity-export-filename"),
        dismiss: () => document.getElementById("activity-export-dismiss"),
        loadingMessage: () => document.getElementById("activity-export-loading-message"),
        progress: () => document.getElementById("activity-export-progress"),
        modal: () => document.getElementById("activity-export-modal"),
        filterSummary: () => document.getElementById("activity-export-modal-filter-summary"),
    };

    function isLoadingVisible() {
        const loading = els.loading();
        return loading && loading.classList.contains("is-export-loading");
    }

    function resetTrackedExportId() {
        currentExportId = null;
        els.wrap()?.removeAttribute("data-export-id");
        els.wrap()?.removeAttribute("data-initial-export");
        els.dismiss()?.removeAttribute("data-export-id");
    }

    function resolveExportId() {
        if (currentExportId) {
            return currentExportId;
        }

        const fromButton = els.dismiss()?.dataset.exportId;
        if (fromButton) {
            return Number(fromButton);
        }

        const fromWrap = els.wrap()?.dataset.exportId;
        if (fromWrap) {
            return Number(fromWrap);
        }

        const raw = els.wrap()?.dataset.initialExport;
        if (raw) {
            try {
                const data = JSON.parse(raw);
                if (data?.export_id) {
                    return Number(data.export_id);
                }
            } catch (e) {
                console.error("[Activity export] Failed to parse initial export data", e);
            }
        }

        return null;
    }

    function setExportId(exportId) {
        if (!exportId) {
            return;
        }

        currentExportId = Number(exportId);
        els.wrap()?.setAttribute("data-export-id", String(currentExportId));
        els.dismiss()?.setAttribute("data-export-id", String(currentExportId));
    }

    function bindExportInteractions() {
        const wrap = els.wrap();
        const download = els.download();

        if (wrap && !wrap.dataset.exportClickBound) {
            wrap.dataset.exportClickBound = "1";
            wrap.addEventListener("click", (event) => {
                if (event.target.closest("#activity-export-dismiss")) {
                    event.preventDefault();
                    dismissExport();
                }
            });
        }

        if (download && !download.dataset.downloadBound) {
            download.dataset.downloadBound = "1";
            download.addEventListener("click", (event) => {
                event.preventDefault();
                triggerExportDownload();
            });
        }
    }

    async function triggerExportDownload() {
        const download = els.download();
        if (!download) {
            return;
        }

        const url = download.getAttribute("href");
        const filename = download.getAttribute("download") || "export.xlsx";

        if (!url || url === "#") {
            return;
        }

        try {
            const response = await fetch(url, {
                credentials: "same-origin",
                headers: {
                    Accept: "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, text/csv, application/octet-stream",
                    "X-Requested-With": "XMLHttpRequest",
                },
            });

            if (!response.ok) {
                throw new Error(`Download failed (${response.status})`);
            }

            const blob = await response.blob();
            const objectUrl = URL.createObjectURL(blob);
            const link = document.createElement("a");
            link.href = objectUrl;
            link.download = filename;
            link.style.display = "none";
            document.body.appendChild(link);
            link.click();
            link.remove();
            URL.revokeObjectURL(objectUrl);
        } catch (e) {
            console.error("[Activity export] Download failed", e);
            toast("error", "Download failed", e.message || "Please try again.");
        }
    }

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content");
        if (meta) {
            return meta;
        }

        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
        return match ? decodeURIComponent(match[1]) : "";
    }

    function toast(type, title, message) {
        if (window.iziToast) {
            window.iziToast[type]({ title, message, position: "topRight", timeout: 5000 });
        }
    }

    async function apiFetch(url, options = {}, fetchOptions = {}) {
        const token = csrfToken();
        const method = (options.method || "GET").toUpperCase();
        const headers = {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest",
            ...(token ? { "X-CSRF-TOKEN": token, "X-XSRF-TOKEN": token } : {}),
            ...(options.headers || {}),
        };

        if (method !== "GET" && method !== "HEAD" && !headers["Content-Type"]) {
            headers["Content-Type"] = "application/json";
        }

        const response = await fetch(url, {
            ...options,
            method,
            headers,
            credentials: "same-origin",
            signal: fetchOptions.signal,
        });

        if (!response.ok) {
            const data = await response.json().catch(() => ({}));
            const message = data.message
                || (data.errors ? Object.values(data.errors).flat().join(" ") : null)
                || (response.status === 419
                    ? "Session expired. Please refresh the page and try again."
                    : `Request failed (${response.status})`);
            throw new Error(message);
        }

        return response.json();
    }

    function appendQueryValue(result, key, value) {
        if (!Object.prototype.hasOwnProperty.call(result, key) || !Array.isArray(result[key])) {
            result[key] = [];
        }

        if (Array.isArray(value)) {
            result[key].push(...value);
            return;
        }

        result[key].push(value);
    }

    function parseQueryParamKey(rawKey) {
        const indexedMatch = rawKey.match(/^([^\[]+)\[(\d*)\]$/);
        if (indexedMatch) {
            return {
                key: indexedMatch[1],
                isArray: true,
                index: indexedMatch[2] === "" ? null : Number(indexedMatch[2]),
            };
        }

        if (rawKey.endsWith("[]")) {
            return {
                key: rawKey.slice(0, -2),
                isArray: true,
                index: null,
            };
        }

        return {
            key: rawKey,
            isArray: false,
            index: null,
        };
    }

    function currentQueryParams() {
        const params = new URLSearchParams(window.location.search);
        params.delete("page");
        params.delete("fragment");

        const result = {};

        for (const [rawKey, value] of params.entries()) {
            const parsed = parseQueryParamKey(rawKey);

            if (parsed.isArray) {
                if (parsed.index === null) {
                    appendQueryValue(result, parsed.key, value);
                    continue;
                }

                if (!Object.prototype.hasOwnProperty.call(result, parsed.key) || !Array.isArray(result[parsed.key])) {
                    result[parsed.key] = [];
                }

                result[parsed.key][parsed.index] = value;
                continue;
            }

            if (Object.prototype.hasOwnProperty.call(result, parsed.key)) {
                if (parsed.key === "period" && value === "custom") {
                    result[parsed.key] = value;
                }

                continue;
            }

            result[parsed.key] = value;
        }

        for (const [key, value] of Object.entries(result)) {
            if (Array.isArray(value)) {
                result[key] = value.filter((item) => item !== undefined);
            }
        }

        return result;
    }

    function filterSummaryLabel() {
        const chips = document.querySelectorAll(".etd-active-filter-chips .etd-filter-chip");
        if (chips.length > 0) {
            return `${chips.length} active filter${chips.length === 1 ? "" : "s"}`;
        }

        const range = document.querySelector(".etd-page-range");
        return range?.textContent?.trim() || "Current list filters";
    }

    function formatDownloadLabel(data) {
        const name = data.download_filename || `User Activity Report.${data.format || "xlsx"}`;
        if (!data.completed_at) {
            return name;
        }

        const date = new Date(data.completed_at);
        const stamp = date.toLocaleString(undefined, {
            year: "numeric",
            month: "short",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
        });

        return `${name} · ${stamp}`;
    }

    function applyDownloadLabel(data) {
        const download = els.download();
        const filename = els.filename();
        const label = formatDownloadLabel(data);

        if (filename) {
            filename.textContent = label;
        }

        if (download) {
            download.setAttribute("title", label);

            let tooltip = download.querySelector(".export-download-tooltip");
            if (!tooltip) {
                tooltip = document.createElement("span");
                tooltip.className = "export-download-tooltip";
                download.appendChild(tooltip);
            }

            tooltip.textContent = label;
        }
    }

    function isRowsComplete(data) {
        const processed = Number(data?.processed_rows) || 0;
        const total = Number(data?.total_rows) || 0;

        return total > 0 && processed >= total;
    }

    function formatLoadingMessage(data) {
        if (!data) {
            return "Download generating…";
        }

        if (data.status === "queued") {
            return "Preparing export…";
        }

        if (data.status === "processing" && isRowsComplete(data)) {
            return "Making Excel…";
        }

        if (data.status === "completed") {
            return "Excel file ready";
        }

        return "Download generating…";
    }

    function formatProgressLabel(data) {
        if (!data) {
            return "";
        }

        const processed = Number(data.processed_rows) || 0;
        const total = Number(data.total_rows) || 0;

        if (total > 0) {
            const count = `${Math.min(processed, total)}/${total}`;

            if (data.status === "completed" && isRowsComplete(data)) {
                return `${count} · Complete`;
            }

            return count;
        }

        if (processed > 0) {
            return `${processed} rows`;
        }

        if (data.status === "queued") {
            return "Queued";
        }

        return "";
    }

    function updateLoadingProgress(data) {
        const message = els.loadingMessage();
        const progress = els.progress();

        if (message) {
            message.textContent = formatLoadingMessage(data);
        }

        if (!progress) {
            return;
        }

        const label = formatProgressLabel(data);
        progress.textContent = label ? ` · ${label}` : "";
    }

    function clearCompletionTransition() {
        if (completionTransitionTimer) {
            window.clearTimeout(completionTransitionTimer);
            completionTransitionTimer = null;
        }
    }

    function showCompletedTransition(data) {
        clearCompletionTransition();
        stopProgressPolling();
        abortRefreshRequest();

        const wrap = els.wrap();
        if (!wrap) {
            return;
        }

        wrap.classList.add("is-export-active");
        els.loading()?.classList.add("is-export-loading");
        els.readyGroup()?.classList.remove("is-export-ready");

        updateLoadingProgress({
            ...data,
            status: "completed",
            processed_rows: data.total_rows || data.processed_rows,
        });

        completionTransitionTimer = window.setTimeout(() => {
            completionTransitionTimer = null;
            showReadyState(data);
        }, COMPLETION_DISPLAY_MS);
    }

    function startProgressPolling() {
        stopProgressPolling();

        if (!isLoadingVisible() || completionHandled) {
            return;
        }

        progressPollTimer = window.setInterval(() => {
            if (!isLoadingVisible() || completionHandled) {
                stopProgressPolling();
                return;
            }

            refreshExportStatus();
        }, PROGRESS_POLL_MS);
    }

    function stopProgressPolling() {
        if (progressPollTimer) {
            window.clearInterval(progressPollTimer);
            progressPollTimer = null;
        }
    }

    function showLoadingState(data) {
        const wrap = els.wrap();
        if (!wrap) {
            return;
        }

        stopProgressPolling();
        wrap.classList.add("is-export-active");
        els.loading()?.classList.add("is-export-loading");
        els.readyGroup()?.classList.remove("is-export-ready");
        updateLoadingProgress(data);
        startProgressPolling();
    }

    function abortRefreshRequest() {
        if (refreshAbortController) {
            refreshAbortController.abort();
            refreshAbortController = null;
        }
    }

    function showReadyState(data) {
        abortRefreshRequest();
        stopProgressPolling();
        clearCompletionTransition();
        completionHandled = true;

        const wrap = els.wrap();
        if (!wrap) {
            return;
        }

        if (data?.export_id) {
            setExportId(data.export_id);
        }

        wrap.classList.add("is-export-active");
        els.loading()?.classList.remove("is-export-loading");
        els.readyGroup()?.classList.add("is-export-ready");

        const download = els.download();
        const filename = els.filename();
        if (download && filename) {
            const url = data.download_url || "";
            download.href = url || "#";
            download.setAttribute("download", data.download_filename || "");
            applyDownloadLabel(data);
            download.classList.add("inline-flex");

            if (!url) {
                download.classList.add("pointer-events-none", "opacity-60");
            } else {
                download.classList.remove("pointer-events-none", "opacity-60");
            }
        }

        bindExportInteractions();
    }

    function hideExportStatus() {
        stopProgressPolling();
        clearCompletionTransition();
        els.wrap()?.classList.remove("is-export-active");
        els.loading()?.classList.remove("is-export-loading");
        els.readyGroup()?.classList.remove("is-export-ready");
    }

    function applyExportState(data) {
        if (!data || data.status === "cancelled") {
            clearExportState();
            return;
        }

        if (data.status === "failed") {
            toast("error", "Export failed", data.error_message || "Please try again.");
            clearExportState();
            return;
        }

        if (data.status === "completed") {
            if (completionHandled || completionTransitionTimer) {
                return;
            }

            setExportId(data.export_id || currentExportId);
            showCompletedTransition(data);
            return;
        }

        if (completionHandled) {
            return;
        }

        showLoadingState(data);
    }

    async function refreshExportStatus() {
        if (!els.wrap() || completionHandled) {
            return;
        }

        if (exportStartInFlight) {
            return;
        }

        abortRefreshRequest();
        refreshAbortController = new AbortController();
        const signal = refreshAbortController.signal;

        try {
            const data = await apiFetch(ROUTES.active, {}, { signal });
            if (!data?.export) {
                if (isLoadingVisible() || exportStartInFlight) {
                    return;
                }

                if (resolveExportId()) {
                    clearExportState();
                }
                return;
            }

            setExportId(data.export.export_id);
            applyExportState(data.export);
        } catch (e) {
            if (e.name !== "AbortError") {
                console.error("[Activity export] Status refresh failed", e);
            }
        } finally {
            if (!refreshAbortController?.signal.aborted) {
                refreshAbortController = null;
            }
        }
    }

    function clearExportState() {
        abortRefreshRequest();
        stopProgressPolling();
        clearCompletionTransition();
        exportStartInFlight = false;
        completionHandled = false;
        resetTrackedExportId();
        hideExportStatus();
    }

    window.openActivityExportModal = function () {
        const modal = els.modal();
        if (!modal) {
            return;
        }

        if (typeof window.closeFilterDrawer === "function") {
            window.closeFilterDrawer();
        }

        if (modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        const summary = els.filterSummary();
        if (summary) {
            summary.textContent = filterSummaryLabel();
        }

        modal.classList.add("is-export-modal-open");
    };

    window.closeActivityExportModal = function () {
        els.modal()?.classList.remove("is-export-modal-open");
    };

    function startExport() {
        const format = document.querySelector('input[name="activity_export_format"]:checked')?.value || "xlsx";

        window.closeActivityExportModal();
        abortRefreshRequest();
        exportStartInFlight = true;
        completionHandled = false;
        resetTrackedExportId();
        showLoadingState();

        fetch(ROUTES.start, {
            method: "POST",
            headers: {
                Accept: "application/json",
                "Content-Type": "application/json",
                "X-Requested-With": "XMLHttpRequest",
                ...(csrfToken() ? { "X-CSRF-TOKEN": csrfToken(), "X-XSRF-TOKEN": csrfToken() } : {}),
            },
            credentials: "same-origin",
            keepalive: true,
            body: JSON.stringify({
                format,
                query: currentQueryParams(),
            }),
        })
            .then(async (response) => {
                if (!response.ok) {
                    const data = await response.json().catch(() => ({}));
                    const message = data.message
                        || (data.errors ? Object.values(data.errors).flat().join(" ") : null)
                        || `Request failed (${response.status})`;
                    throw new Error(message);
                }

                return response.json();
            })
            .then((data) => {
                setExportId(data.export_id);
                updateLoadingProgress({ status: "queued", processed_rows: 0, total_rows: data.total_rows || 0 });
            })
            .catch((e) => {
                exportStartInFlight = false;
                stopProgressPolling();
                hideExportStatus();
                toast("error", "Export failed", e.message);
            })
            .finally(() => {
                exportStartInFlight = false;
                if (resolveExportId() && isLoadingVisible()) {
                    refreshExportStatus();
                    startProgressPolling();
                }
            });
    }

    async function dismissExport() {
        const exportId = resolveExportId();
        if (!exportId) {
            clearExportState();
            return;
        }

        try {
            await apiFetch(ROUTES.dismiss(exportId), { method: "POST" });
            clearExportState();
        } catch (e) {
            toast("error", "Error", e.message);
        }
    }

    function hydrateFromServer() {
        const wrap = els.wrap();
        const raw = wrap?.dataset.initialExport;
        if (!raw) {
            return false;
        }

        try {
            const data = JSON.parse(raw);
            setExportId(data.export_id);

            if (data.status === "completed") {
                showReadyState(data);
                return true;
            }

            if (data.status === "queued" || data.status === "processing") {
                completionHandled = false;
                showLoadingState(data);
                refreshExportStatus();
                return true;
            }

            if (data.status === "failed") {
                clearExportState();
                return false;
            }
        } catch (e) {
            console.error("[Activity export] Failed to parse initial export state", e);
        }

        return false;
    }

    function init() {
        if (window.__activityExportInitialized) {
            return;
        }
        window.__activityExportInitialized = true;

        const modal = els.modal();
        if (modal && modal.parentElement !== document.body) {
            document.body.appendChild(modal);
        }

        bindExportInteractions();
        document.getElementById("activity-export-start-btn")?.addEventListener("click", startExport);

        window.addEventListener("pagehide", () => {
            abortRefreshRequest();
            stopProgressPolling();
            clearCompletionTransition();
        });

        document.addEventListener("visibilitychange", () => {
            if (document.visibilityState === "visible" && !completionHandled) {
                refreshExportStatus();
            }
        });

        hydrateFromServer();
    }

    if (document.readyState === "loading") {
        document.addEventListener("DOMContentLoaded", init);
    } else {
        init();
    }
})();
