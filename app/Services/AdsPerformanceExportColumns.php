<?php

namespace App\Services;

class AdsPerformanceExportColumns
{
    public const AD_PERFORMANCE       = 'ad_performance';
    public const MONTHLY_SUMMARY      = 'monthly_summary';
    public const OVERVIEW_CHARTS      = 'overview_charts';
    public const PLATFORM_ENGAGEMENT  = 'platform_engagement';
    public const PLATFORM_CHARTS      = 'platform_charts';

    public const OVERVIEW_CHART_REVENUE = 'overview_revenue_cost';
    public const OVERVIEW_CHART_ORDERS  = 'overview_orders';
    public const OVERVIEW_CHART_ROI     = 'overview_avg_roi';
    public const OVERVIEW_CHART_ROAS    = 'overview_avg_roas';

    public function buildSections(array $exportPlatforms): array
    {
        return [
            [
                'key'        => self::AD_PERFORMANCE,
                'label'      => 'Ad Performance',
                'desc'       => 'Month-by-month platform reach, spend, revenue & ROI',
                'chart_only' => false,
                'groups'     => $this->defsToGroups($this->performanceDefs()),
            ],
            [
                'key'        => self::MONTHLY_SUMMARY,
                'label'      => 'Monthly Performance Summary',
                'desc'       => 'Aggregated monthly revenue, cost, orders & engagement',
                'chart_only' => false,
                'groups'     => $this->defsToGroups($this->summaryDefs()),
            ],
            [
                'key'        => self::OVERVIEW_CHARTS,
                'label'      => 'Overview Charts',
                'desc'       => 'Monthly summary charts — revenue, orders, ROI & ROAS',
                'chart_only' => false,
                'groups'     => $this->overviewChartGroups(),
            ],
            [
                'key'        => self::PLATFORM_ENGAGEMENT,
                'label'      => 'Platform Engagement',
                'desc'       => 'Per-platform monthly reach, impressions, clicks & sessions',
                'chart_only' => false,
                'groups'     => $this->platformEngagementGroups($exportPlatforms),
            ],
            [
                'key'        => self::PLATFORM_CHARTS,
                'label'      => 'Platform Charts',
                'desc'       => 'Per-platform engagement bar charts',
                'chart_only' => false,
                'groups'     => $this->platformChartGroups($exportPlatforms),
            ],
        ];
    }

    public function defaultSelection(array $sections): array
    {
        $selection = [];
        foreach ($sections as $section) {
            $selection[$section['key']] = $this->allKeysFromSection($section);
        }

        return $selection;
    }

    public function defaultTables(): array
    {
        return [
            self::AD_PERFORMANCE      => true,
            self::MONTHLY_SUMMARY     => true,
            self::OVERVIEW_CHARTS     => true,
            self::PLATFORM_ENGAGEMENT => true,
            self::PLATFORM_CHARTS     => true,
        ];
    }

    public function parseTables(?string $param, ?string $exportColumnsJson = null): array
    {
        $defaults = array_keys(array_filter($this->defaultTables()));
        $valid    = array_keys($this->defaultTables());

        if ($param === null) {
            if ($exportColumnsJson !== null && trim($exportColumnsJson) !== '' && trim($exportColumnsJson) !== '{}') {
                $decoded = json_decode($exportColumnsJson, true);
                if (is_array($decoded) && $decoded !== []) {
                    return array_values(array_intersect($valid, array_keys($decoded)));
                }
            }

            return $defaults;
        }

        if (trim($param) === '') {
            return [];
        }

        $submitted = array_filter(array_map('trim', explode(',', $param)));

        return array_values(array_intersect($valid, $submitted));
    }

    public function parseSelection(?string $json, array $sections, array $activeTables = []): array
    {
        $defaults  = $this->defaultSelection($sections);
        $activeSet = $activeTables !== [] ? array_flip($activeTables) : null;

        $selectionForActiveTables = function () use ($sections, $defaults, $activeSet): array {
            $selection = [];
            foreach ($sections as $section) {
                if ($activeSet !== null && !isset($activeSet[$section['key']])) {
                    continue;
                }
                $selection[$section['key']] = $defaults[$section['key']] ?? $this->allKeysFromSection($section);
            }

            return $selection;
        };

        if ($json === null || trim($json) === '') {
            return $activeSet !== null ? $selectionForActiveTables() : $defaults;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $activeSet !== null ? $selectionForActiveTables() : $defaults;
        }

        $parsed = [];
        foreach ($sections as $section) {
            $tableKey = $section['key'];

            if ($activeSet !== null && !isset($activeSet[$tableKey])) {
                continue;
            }

            $validKeys = $this->allKeysFromSection($section);
            $submitted = $decoded[$tableKey] ?? null;

            if ($submitted === null) {
                $parsed[$tableKey] = $defaults[$tableKey] ?? $validKeys;
                continue;
            }

            if (!is_array($submitted)) {
                $parsed[$tableKey] = $validKeys;
                continue;
            }

            $parsed[$tableKey] = array_values(array_intersect($validKeys, $submitted));

            if ($parsed[$tableKey] === [] && !$this->allowsEmptySelection($tableKey)) {
                $parsed[$tableKey] = $validKeys;
            }
        }

        return $parsed;
    }

    public function hasPlatformEngagementSelection(array $selection): bool
    {
        foreach ($selection[self::PLATFORM_ENGAGEMENT] ?? [] as $key) {
            if ($this->parsePlatformColumnKey($key)) {
                return true;
            }
        }

        return false;
    }

    private function allowsEmptySelection(string $tableKey): bool
    {
        return in_array($tableKey, [
            self::PLATFORM_ENGAGEMENT,
            self::PLATFORM_CHARTS,
            self::OVERVIEW_CHARTS,
        ], true);
    }

    public function overviewChartDefs(): array
    {
        return [
            $this->def(self::OVERVIEW_CHART_REVENUE, 'Revenue vs Total Cost vs Net Revenue', null, 'overview_chart'),
            $this->def(self::OVERVIEW_CHART_ORDERS, 'Orders by Month', null, 'overview_chart'),
            $this->def(self::OVERVIEW_CHART_ROI, 'Avg ROI by Month', null, 'overview_chart'),
            $this->def(self::OVERVIEW_CHART_ROAS, 'Avg ROAS by Month', null, 'overview_chart'),
        ];
    }

    public function selectedOverviewCharts(array $selection): array
    {
        return $selection[self::OVERVIEW_CHARTS] ?? array_column($this->overviewChartDefs(), 'key');
    }

    public function selectedPlatformChartIds(array $selection): array
    {
        $ids = [];
        foreach ($selection[self::PLATFORM_CHARTS] ?? [] as $key) {
            $parsed = $this->parsePlatformChartKey($key);
            if ($parsed) {
                $ids[] = $parsed['platform_id'];
            }
        }

        return $ids;
    }

    public function platformChartKey(int|string $platformId): string
    {
        return "plat_chart_{$platformId}";
    }

    public function parsePlatformChartKey(string $key): ?array
    {
        if (!preg_match('/^plat_chart_(\d+)$/', $key, $m)) {
            return null;
        }

        return ['platform_id' => (int) $m[1]];
    }

    public function performanceDefs(): array
    {
        return [
            $this->def('sl', 'Sl. No', null, 'fixed'),
            $this->def('month_label', 'Month', null, 'fixed', ['merge_month' => true]),
            $this->def('platform', 'Platform', null, 'fixed'),
            $this->def('reach', 'Reach', null, 'metric'),
            $this->def('impressions', 'Impressions', null, 'metric'),
            $this->def('clicks', 'Clicks', null, 'metric'),
            $this->def('sessions', 'Sessions', null, 'metric'),
            $this->def('engaged_sessions', 'Engaged Sessions', null, 'metric'),
            $this->def('users', 'Users', null, 'metric'),
            $this->def('net_cost', 'Net Cost (£)', null, 'metric'),
            $this->def('ads_tax', 'Ads Tax (£)', null, 'metric'),
            $this->def('total_cost', 'Total Cost (£)', null, 'metric', ['merge_month' => true]),
            $this->def('orders', 'Orders', null, 'metric'),
            $this->def('products', 'Products', null, 'metric'),
            $this->def('sales_growth', 'Sales Growth %', null, 'metric', ['merge_month' => true]),
            $this->def('revenue', 'Revenue (£)', null, 'metric'),
            $this->def('total_revenue', 'Total Revenue (£)', null, 'metric', ['merge_month' => true]),
            $this->def('total_return', 'Total Return (£)', null, 'metric', ['merge_month' => true]),
            $this->def('net_revenue', 'Net Revenue (£)', null, 'metric', ['merge_month' => true]),
            $this->def('roi', 'ROI', null, 'metric', ['merge_month' => true]),
            $this->def('roas', 'ROAS', null, 'metric', ['merge_month' => true]),
        ];
    }

    public function summaryDefs(): array
    {
        return [
            $this->def('month', 'Month', null, 'summary'),
            $this->def('revenue', 'Revenue (£)', null, 'summary'),
            $this->def('total_cost', 'Total Cost (£)', null, 'summary'),
            $this->def('net_revenue', 'Net Revenue (£)', null, 'summary'),
            $this->def('orders', 'Orders', null, 'summary'),
            $this->def('clicks', 'Clicks', null, 'summary'),
            $this->def('impressions', 'Impressions', null, 'summary'),
            $this->def('avg_roi', 'Avg ROI', null, 'summary'),
            $this->def('avg_roas', 'Avg ROAS', null, 'summary'),
        ];
    }

    public function filterDefs(string $table, array $defs, array $selection): array
    {
        $allowed = $selection[$table] ?? null;
        if ($allowed === null || $allowed === []) {
            return $defs;
        }

        $allowed = array_flip($allowed);

        return array_values(array_filter($defs, fn (array $def) => isset($allowed[$def['key']])));
    }

    public function platformColumnKey(int|string $platformId, string $metric): string
    {
        return "plat_{$platformId}_{$metric}";
    }

    public function parsePlatformColumnKey(string $key): ?array
    {
        if (!preg_match('/^plat_(\d+)_(.+)$/', $key, $m)) {
            return null;
        }

        return ['platform_id' => (int) $m[1], 'metric' => $m[2]];
    }

    public function selectedPlatformMetrics(array $selection, int|string $platformId): array
    {
        $keys = $selection[self::PLATFORM_ENGAGEMENT] ?? [];
        $metrics = [];

        foreach ($keys as $key) {
            $parsed = $this->parsePlatformColumnKey($key);
            if ($parsed && (int) $parsed['platform_id'] === (int) $platformId) {
                $metrics[] = $parsed['metric'];
            }
        }

        return $metrics;
    }

    public function platformDisplayName(array $platform): string
    {
        $name = $platform['name'] ?? '—';

        if (!empty($platform['parent_name'])) {
            return "{$name} · {$platform['parent_name']}";
        }

        return $name;
    }

    private function overviewChartGroups(): array
    {
        return $this->defsToGroups($this->overviewChartDefs());
    }

    private function platformChartGroups(array $exportPlatforms): array
    {
        $groups = [];

        foreach ($exportPlatforms as $platform) {
            $platformId = $platform['platform_id'] ?? null;
            if ($platformId === null) {
                continue;
            }

            $groups[] = [
                'header'  => $platform['name'] ?? '—',
                'parent'  => $platform['parent_name'] ?? null,
                'columns' => [[
                    'key'    => $this->platformChartKey($platformId),
                    'header' => $platform['name'] ?? '—',
                    'sub'    => null,
                    'label'  => $platform['name'] ?? '—',
                ]],
            ];
        }

        return $groups;
    }

    private function platformEngagementGroups(array $exportPlatforms): array
    {
        $groups = [];

        foreach ($exportPlatforms as $platform) {
            $platformId = $platform['platform_id'] ?? null;
            if ($platformId === null) {
                continue;
            }

            $columns = [];
            foreach ($platform['columns'] ?? [] as $metric => $label) {
                $columns[] = [
                    'key'    => $this->platformColumnKey($platformId, $metric),
                    'header' => $platform['name'] ?? '—',
                    'sub'    => $label,
                    'label'  => $label,
                ];
            }

            if ($columns !== []) {
                $groups[] = [
                    'header'  => $platform['name'] ?? '—',
                    'parent'  => $platform['parent_name'] ?? null,
                    'columns' => $columns,
                ];
            }
        }

        return $groups;
    }

    private function defsToGroups(array $defs): array
    {
        $groups = [];

        foreach ($defs as $def) {
            $groupKey = $def['sub'] === null
                ? ($def['header'] ?: 'Columns')
                : $def['header'];

            if (!isset($groups[$groupKey])) {
                $groups[$groupKey] = [
                    'header'  => $groupKey,
                    'columns' => [],
                ];
            }

            $groups[$groupKey]['columns'][] = [
                'key'    => $def['key'],
                'header' => $def['header'],
                'sub'    => $def['sub'],
                'label'  => $def['sub'] ?? $def['header'],
            ];
        }

        return array_values($groups);
    }

    private function allKeysFromSection(array $section): array
    {
        $keys = [];
        foreach ($section['groups'] as $group) {
            foreach ($group['columns'] as $column) {
                $keys[] = $column['key'];
            }
        }

        return $keys;
    }

    private function def(string $key, string $header, ?string $sub, string $type, array $meta = []): array
    {
        return array_merge([
            'key'    => $key,
            'header' => $header,
            'sub'    => $sub,
            'type'   => $type,
        ], $meta);
    }
}
