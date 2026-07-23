<?php

namespace App\Services;

class SalesReportExportColumns
{
    public const DAILY_REPORT      = 'daily_report';
    public const RETURN_BREAKDOWN  = 'return_breakdown';
    public const WEEKLY_BREAKDOWN  = 'weekly_breakdown';

    public function buildSections(array $groupedColumns, array $rootPlatforms): array
    {
        return [
            [
                'key'    => self::DAILY_REPORT,
                'label'  => 'Daily Report',
                'desc'   => 'Day-by-day sales, spend, ROAS & platform breakdown',
                'groups' => $this->dailyReportGroups($groupedColumns, $rootPlatforms),
            ],
            [
                'key'    => self::RETURN_BREAKDOWN,
                'label'  => 'Return Breakdown',
                'desc'   => 'Returns by reason per platform & gender',
                'groups' => $this->returnBreakdownGroups($rootPlatforms),
            ],
            [
                'key'    => self::WEEKLY_BREAKDOWN,
                'label'  => 'Weekly Breakdown',
                'desc'   => 'Weekly sales, spend, orders & returns',
                'groups' => $this->weeklyBreakdownGroups($rootPlatforms),
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

    public function parseSelection(?string $json, array $sections): array
    {
        $defaults = $this->defaultSelection($sections);

        if ($json === null || trim($json) === '') {
            return $defaults;
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return $defaults;
        }

        $parsed = [];
        foreach ($sections as $section) {
            $tableKey   = $section['key'];
            $validKeys  = $this->allKeysFromSection($section);
            $submitted  = $decoded[$tableKey] ?? $defaults[$tableKey] ?? $validKeys;

            if (!is_array($submitted)) {
                $parsed[$tableKey] = $validKeys;
                continue;
            }

            $parsed[$tableKey] = array_values(array_intersect($validKeys, $submitted));
            if ($parsed[$tableKey] === []) {
                $parsed[$tableKey] = $validKeys;
            }
        }

        return $parsed;
    }

    public function dailyReportDefs(array $groupedColumns, array $rootPlatforms): array
    {
        $defs = [
            $this->def('week', 'Week', null, 'fixed', ['field' => 'week']),
            $this->def('date', 'Date', null, 'fixed', ['field' => 'date']),
            $this->def('daily_sales', 'Daily Sales', null, 'fixed', ['field' => 'daily_sales']),
            $this->def('daily_roas', 'Daily ROAS', null, 'fixed', ['field' => 'daily_roas']),
            $this->def('daily_spend', 'Daily Spend', null, 'fixed', ['field' => 'daily_spend']),
        ];

        foreach ($groupedColumns as $col) {
            $defs[] = $this->def(
                $col['key'],
                $col['name'],
                $col['type_label'],
                'platform',
                [
                    'platform_id' => $col['platform_id'],
                    'col_type'    => $col['col_type'],
                    'kind'        => $col['kind'],
                    'leaf_ids'    => $col['leaf_ids'],
                    'level'       => $col['level'],
                ],
            );
        }

        foreach ($rootPlatforms as $root) {
            $defs[] = $this->def(
                "order_qty_{$root['id']}",
                'Order QTY',
                $this->shortPlatformName($root['name']),
                'order_qty_root',
                ['root_id' => $root['id']],
            );
        }
        $defs[] = $this->def('order_qty_total', 'Order QTY', 'Total', 'order_qty_total');

        foreach ($rootPlatforms as $root) {
            $defs[] = $this->def(
                "item_qty_{$root['id']}",
                'Order Item QTY',
                $this->shortPlatformName($root['name']),
                'item_qty_root',
                ['root_id' => $root['id']],
            );
        }
        $defs[] = $this->def('item_qty_total', 'Order Item QTY', 'Total', 'item_qty_total');

        foreach ([
            'gender_kids'   => 'Kids',
            'gender_female' => 'Female',
            'gender_male'   => 'Male',
        ] as $key => $label) {
            $defs[] = $this->def($key, 'Gender Order QTY', $label, 'gender', ['field' => str_replace('gender_', '', $key)]);
        }

        return $defs;
    }

    public function returnBreakdownDefs(array $rootPlatforms): array
    {
        $defs = [
            $this->def('reason', 'Reason', null, 'reason'),
        ];

        foreach ($rootPlatforms as $root) {
            $sn = $this->shortPlatformName($root['name']);
            $defs[] = $this->def("return_qty_{$root['id']}", $sn, 'Qty', 'return_root_qty', ['root_id' => $root['id']]);
            $defs[] = $this->def("return_pct_{$root['id']}", $sn, '%', 'return_root_pct', ['root_id' => $root['id']]);
        }

        foreach ([
            'return_kids'   => 'Kids',
            'return_female' => 'Female',
            'return_male'   => 'Male',
        ] as $key => $label) {
            $defs[] = $this->def($key, 'Gender', $label, 'return_gender', ['field' => str_replace('return_', '', $key)]);
        }

        $defs[] = $this->def('return_total_qty', 'Total', 'Qty', 'return_total_qty');
        $defs[] = $this->def('return_total_pct', 'Total', '%', 'return_total_pct');

        return $defs;
    }

    public function weeklyBreakdownDefs(array $rootPlatforms): array
    {
        $fixed = [
            'week'              => 'Week',
            'sales'             => 'Sales (£)',
            'spend'             => 'Spend (£)',
            'order'             => 'Order',
            'order_qty'         => 'Order Qty',
            'return_qty'        => 'Return Qty',
            'return_qty_pct'    => 'Return Qty %',
            'return_amount'     => 'Return Amount (£)',
            'return_amount_pct' => 'Return Amount %',
        ];

        $defs = [];
        foreach ($fixed as $key => $label) {
            $defs[] = $this->def($key, $label, null, 'weekly_fixed', ['field' => $key]);
        }

        $childLabels = [
            'sales'       => 'Sales (£)',
            'orders'      => 'Orders',
            'qty'         => 'Qty',
            'return'      => 'Return (£)',
            'ret_orders'  => 'Ret Orders',
            'ret_qty'     => 'Ret Qty',
        ];

        foreach ($rootPlatforms as $root) {
            $sn = $this->shortPlatformName($root['name']);
            foreach ($childLabels as $field => $sub) {
                $defs[] = $this->def(
                    "wb_{$root['id']}_{$field}",
                    $sn,
                    $sub,
                    'weekly_platform',
                    ['root_id' => $root['id'], 'field' => $field],
                );
            }
        }

        return $defs;
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

    public function groupedColumnsFromTree(array $tree, int $depth = 0): array
    {
        $cols = [];

        foreach ($tree as $node) {
            if (!empty($node['children'])) {
                $leafIds = [];
                $this->collectLeafIdsFromTree($node['children'], $leafIds);

                if ($node['is_spent']) {
                    $cols[] = $this->groupedColumnMeta($node, 'summary', 'cost', $depth, $leafIds);
                }
                if ($node['is_sales']) {
                    $cols[] = $this->groupedColumnMeta($node, 'summary', 'sales', $depth, $leafIds);
                }

                $cols = array_merge($cols, $this->groupedColumnsFromTree($node['children'], $depth + 1));
            } else {
                if ($node['is_spent']) {
                    $cols[] = $this->groupedColumnMeta($node, 'leaf', 'cost', $depth, [$node['id']]);
                }
                if ($node['is_sales']) {
                    $cols[] = $this->groupedColumnMeta($node, 'leaf', 'sales', $depth, [$node['id']]);
                }
            }
        }

        return $cols;
    }

    private function groupedColumnMeta(array $node, string $kind, string $colType, int $level, array $leafIds): array
    {
        return [
            'kind'        => $kind,
            'platform_id' => $node['id'],
            'col_type'    => $colType,
            'level'       => $level,
            'name'        => $node['name'],
            'leaf_ids'    => $leafIds,
            'key'         => "{$node['id']}_{$colType}",
            'type_label'  => $colType === 'cost' ? 'Spend' : 'Sales',
        ];
    }

    private function collectLeafIdsFromTree(array $nodes, array &$ids): void
    {
        foreach ($nodes as $node) {
            if (empty($node['children'])) {
                $ids[] = $node['id'];
            } else {
                $this->collectLeafIdsFromTree($node['children'], $ids);
            }
        }
    }

    private function dailyReportGroups(array $groupedColumns, array $rootPlatforms): array
    {
        return $this->defsToGroups($this->dailyReportDefs($groupedColumns, $rootPlatforms));
    }

    private function returnBreakdownGroups(array $rootPlatforms): array
    {
        return $this->defsToGroups($this->returnBreakdownDefs($rootPlatforms));
    }

    private function weeklyBreakdownGroups(array $rootPlatforms): array
    {
        return $this->defsToGroups($this->weeklyBreakdownDefs($rootPlatforms));
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

    private function shortPlatformName(string $name): string
    {
        $name = trim(preg_replace('/\s*(platform|marketplace|store)\s*/i', '', $name) ?? $name);

        return mb_strlen($name) > 10 ? mb_substr($name, 0, 9) . '.' : $name;
    }
}
