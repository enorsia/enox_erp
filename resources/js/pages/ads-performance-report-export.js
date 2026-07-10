document.addEventListener('alpine:init', () => {
    Alpine.data('adsPerformanceReportPage', () => {
        const cfg = window.adsPerformanceExportConfig || {};

        const defaultTables = {};
        (cfg.sections || []).forEach(section => {
            defaultTables[section.key] = true;
        });

        const defaultExpanded = {};
        (cfg.sections || []).forEach(section => {
            defaultExpanded[section.key] = false;
        });

        const CHART_SECTION_KEYS = ['overview_charts', 'platform_charts'];

        const FILTER_PARAM_KEYS = [
            'sale_platform_id',
            'period',
            'from_year_month',
            'to_year_month',
            'date_range',
            'date_from',
            'date_to',
        ];

        return {
            exportOpen: false,
            sections: cfg.sections ?? [],
            tables: defaultTables,
            expanded: defaultExpanded,
            columns: cfg.columns ?? {},
            exportBaseUrl: cfg.exportBaseUrl ?? '',

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
                this.tables[sectionKey] = selected.size > 0;
            },

            setTableIncluded(sectionKey, checked) {
                if (checked) {
                    this.tables[sectionKey] = true;
                    if ((this.columns[sectionKey] || []).length === 0) {
                        this.columns[sectionKey] = this.sectionColumnKeys(sectionKey);
                    }
                    return;
                }

                this.tables[sectionKey] = false;
                this.columns[sectionKey] = [];
            },

            toggleExpanded(sectionKey) {
                this.expanded[sectionKey] = !this.expanded[sectionKey];
            },

            selectAllColumns(sectionKey) {
                this.columns[sectionKey] = this.sectionColumnKeys(sectionKey);
                this.tables[sectionKey] = true;
            },

            clearColumns(sectionKey) {
                this.columns[sectionKey] = [];
                this.tables[sectionKey] = false;
            },

            toggleGroupColumns(sectionKey, group, checked) {
                const keys = group.columns.map(c => c.key);
                const selected = new Set(this.columns[sectionKey] || []);
                keys.forEach(k => (checked ? selected.add(k) : selected.delete(k)));
                this.columns[sectionKey] = [...selected];
                this.tables[sectionKey] = selected.size > 0;
            },

            activeSectionKeys() {
                return Object.keys(this.tables).filter(k => this.isSectionActive(k));
            },

            isSectionActive(sectionKey) {
                return this.tables[sectionKey] && this.sectionSelectedCount(sectionKey) > 0;
            },

            sectionSelectedCount(sectionKey) {
                return (this.columns[sectionKey] || []).length;
            },

            sectionTotalCount(sectionKey) {
                return this.sectionColumnKeys(sectionKey).length;
            },

            sectionCountLabel(sectionKey) {
                return `${this.sectionSelectedCount(sectionKey)} / ${this.sectionTotalCount(sectionKey)}`;
            },

            groupHeaderLabel(group) {
                if (group.parent) {
                    return `${group.header} · ${group.parent}`;
                }
                return group.header;
            },

            singleColumnChipLabel(group, col) {
                if (group.parent) {
                    return this.groupHeaderLabel(group);
                }
                return this.columnChipLabel(col);
            },

            exportUrl() {
                const url = new URL(this.exportBaseUrl, window.location.origin);
                const page = new URL(window.location.href);

                FILTER_PARAM_KEYS.forEach(key => {
                    const val = page.searchParams.get(key);
                    if (val) url.searchParams.set(key, val);
                });

                const selectedTables = this.activeSectionKeys();

                url.searchParams.set('tables', selectedTables.join(','));

                const columnPayload = {};
                selectedTables.forEach(k => {
                    columnPayload[k] = this.columns[k];
                });
                url.searchParams.set('export_columns', JSON.stringify(columnPayload));

                return url.toString();
            },

            canExport() {
                return this.activeSectionKeys().length > 0;
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
                if (section.key === 'platform_engagement') {
                    return [];
                }
                return (section.groups || []).filter(g => g.columns.length === 1);
            },

            multiColumnGroups(section) {
                if (section.key === 'platform_engagement') {
                    return section.groups || [];
                }
                return (section.groups || []).filter(g => g.columns.length > 1);
            },

            exportSummary() {
                const selected = this.activeSectionKeys();
                const tableSections = selected.filter(k => !CHART_SECTION_KEYS.includes(k));
                const chartItems = selected.reduce((n, k) => {
                    if (CHART_SECTION_KEYS.includes(k)) {
                        return n + (this.columns[k] || []).length;
                    }
                    return n;
                }, 0);

                const colCount = selected.reduce((n, k) => n + (this.columns[k] || []).length, 0) - chartItems;
                const parts = [];
                if (tableSections.length > 0) {
                    parts.push(`${tableSections.length} table${tableSections.length === 1 ? '' : 's'}`);
                }
                if (chartItems > 0) {
                    parts.push(`${chartItems} chart${chartItems === 1 ? '' : 's'}`);
                }
                if (colCount > 0) {
                    parts.push(`${colCount} column${colCount === 1 ? '' : 's'}`);
                }
                return parts.join(' · ') || 'Nothing selected';
            },
        };
    });
});
