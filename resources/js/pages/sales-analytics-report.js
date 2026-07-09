document.addEventListener('alpine:init', () => {
    Alpine.data('salesReportPage', () => {
        const cfg = window.salesReportExportConfig || {};

        return {
            exportOpen: false,
            period: cfg.period ?? 'this_month',
            fromYM: cfg.fromYM ?? '',
            toYM: cfg.toYM ?? '',
            sections: cfg.sections ?? [],
            tables: { daily_report: true, return_breakdown: true, weekly_breakdown: true },
            expanded: { daily_report: false, return_breakdown: false, weekly_breakdown: false },
            columns: cfg.columns ?? {},
            exportBaseUrl: cfg.exportBaseUrl ?? '',

            markCustomPeriod() {
                this.period = 'custom';
            },

            sectionColumnKeys(sectionKey) {
                const section = this.sections.find(s => s.key === sectionKey);
                if (!section) return [];
                return section.groups.flatMap(g => g.columns.map(c => c.key));
            },

            isColumnSelected(sectionKey, colKey) {
                return (this.columns[sectionKey] || []).includes(colKey);
            },

            toggleColumn(sectionKey, colKey) {
                const selected = new Set(this.columns[sectionKey] || []);
                if (selected.has(colKey)) selected.delete(colKey);
                else selected.add(colKey);
                this.columns[sectionKey] = [...selected];
            },

            setTableIncluded(sectionKey, checked) {
                this.tables[sectionKey] = checked;
                if (checked && (this.columns[sectionKey] || []).length === 0) {
                    this.columns[sectionKey] = this.sectionColumnKeys(sectionKey);
                }
            },

            toggleExpanded(sectionKey) {
                this.expanded[sectionKey] = !this.expanded[sectionKey];
            },

            selectAllColumns(sectionKey) {
                this.columns[sectionKey] = this.sectionColumnKeys(sectionKey);
            },

            clearColumns(sectionKey) {
                this.columns[sectionKey] = [];
            },

            toggleGroupColumns(sectionKey, group, checked) {
                const keys = group.columns.map(c => c.key);
                const selected = new Set(this.columns[sectionKey] || []);
                keys.forEach(k => (checked ? selected.add(k) : selected.delete(k)));
                this.columns[sectionKey] = [...selected];
            },

            sectionSelectedCount(sectionKey) {
                return (this.columns[sectionKey] || []).length;
            },

            sectionTotalCount(sectionKey) {
                return this.sectionColumnKeys(sectionKey).length;
            },

            isGroupFullySelected(sectionKey, group) {
                return group.columns.every(c => this.isColumnSelected(sectionKey, c.key));
            },

            submitPeriod() {
                const url = new URL(window.location.href);
                url.searchParams.set('period', this.period);
                if (this.period === 'custom') {
                    url.searchParams.set('from_year_month', this.fromYM);
                    url.searchParams.set('to_year_month', this.toYM);
                } else {
                    url.searchParams.delete('from_year_month');
                    url.searchParams.delete('to_year_month');
                }
                url.searchParams.delete('month');
                window.location.href = url.toString();
            },

            exportUrl() {
                const url = new URL(this.exportBaseUrl, window.location.origin);
                const page = new URL(window.location.href);
                const period = page.searchParams.get('period') || this.period;
                url.searchParams.set('period', period);
                if (period === 'custom') {
                    url.searchParams.set('from_year_month', page.searchParams.get('from_year_month') || this.fromYM);
                    url.searchParams.set('to_year_month', page.searchParams.get('to_year_month') || this.toYM);
                }
                const selectedTables = Object.keys(this.tables).filter(
                    k => this.tables[k] && (this.columns[k] || []).length > 0,
                );
                if (selectedTables.length > 0) {
                    url.searchParams.set('tables', selectedTables.join(','));
                }
                const columnPayload = {};
                selectedTables.forEach(k => { columnPayload[k] = this.columns[k]; });
                url.searchParams.set('export_columns', JSON.stringify(columnPayload));
                return url.toString();
            },

            canExport() {
                return Object.keys(this.tables).some(
                    k => this.tables[k] && (this.columns[k] || []).length > 0,
                );
            },

            columnLabel(col) {
                return col.sub ? `${col.header} · ${col.sub}` : (col.label || col.header);
            },

            columnChipLabel(col) {
                return col.sub || col.label || col.header;
            },

            columnChipMeta(col) {
                return col.sub ? col.header : null;
            },

            groupSelectedCount(sectionKey, group) {
                return group.columns.filter(c => this.isColumnSelected(sectionKey, c.key)).length;
            },

            singleColumnGroups(section) {
                return (section.groups || []).filter(g => g.columns.length === 1);
            },

            multiColumnGroups(section) {
                return (section.groups || []).filter(g => g.columns.length > 1);
            },

            exportSummary() {
                const tables = Object.keys(this.tables).filter(k => this.tables[k] && (this.columns[k] || []).length > 0).length;
                const cols = Object.keys(this.tables).reduce((n, k) => {
                    if (!this.tables[k]) return n;
                    return n + (this.columns[k] || []).length;
                }, 0);
                return `${tables} table${tables === 1 ? '' : 's'} · ${cols} column${cols === 1 ? '' : 's'}`;
            },
        };
    });
});
