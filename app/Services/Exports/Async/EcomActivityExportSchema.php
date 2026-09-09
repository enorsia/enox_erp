<?php

namespace App\Services\Exports\Async;

use App\Models\ActivityEcomUser;
use App\Support\EcomActivityFocus;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerQueryParams;
use App\Support\TrackerTime;
use Illuminate\Http\Request;

final class EcomActivityExportSchema
{
    /** @var list<string> */
    private const SESSION_MERGE_HEADINGS = [
        'SL',
        'Session started',
        'User type',
        'Name',
        'Email',
        'Phone',
        'Category',
        'Duration',
        'UTM source',
        'Traffic type',
        'Paid click ID',
    ];

    /** @var list<string> */
    private const EVENT_MERGE_HEADINGS = [
        'Commerce stage',
        'Order ID',
        'Sum qty',
        'Order total',
    ];

    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<int, string>
     */
    public static function headings(array $queryParams): array
    {
        $request = TrackerQueryParams::request($queryParams);
        $focus = $request->input('focus');

        $headings = [
            'SL',
            'Session started',
            'User type',
            'Name',
            'Email',
            'Phone',
            'Commerce stage',
            'Order ID',
        ];

        $headings = array_merge($headings, [
            'Product title',
            'Size',
            'Color',
            'Product qty',
            'Sum qty',
            'Unit price',
            'Line total',
            'Order total',
        ]);

        if ($request->filled('department') || $request->filled('category')) {
            $headings[] = 'Category';
        }

        foreach (EcomActivityFocus::exportContextColumns($focus, $request) as $column) {
            $headings[] = self::exportContextHeadingLabel(
                (string) ($column['label'] ?? $column['key'] ?? ''),
            );
        }

        $headings[] = 'Duration';
        $headings[] = 'UTM source';
        $headings[] = 'Traffic type';
        $headings[] = 'Paid click ID';

        return $headings;
    }

    /**
     * @param  array<int, string>  $headings
     * @return array<int, int>
     */
    public static function trafficColumnIndices(array $headings): array
    {
        return self::indicesForLabels($headings, ['Paid click ID']);
    }

    /**
     * @param  array<int, string>  $headings
     * @return array<int, int>
     */
    public static function centerAlignedColumnIndices(array $headings): array
    {
        return self::indicesForLabels($headings, [
            'Size',
            'Color',
            'Product qty',
            'Sum qty',
            'Unit price',
            'Line total',
            'Order total',
            'Order value',
            'Duration',
            'UTM source',
            'Traffic type',
        ]);
    }

    /**
     * @param  array<int, string>  $headings
     * @return array<int, int>
     */
    public static function quantityColumnIndices(array $headings): array
    {
        return self::indicesForLabels($headings, [
            'Product qty',
            'Sum qty',
        ]);
    }

    /**
     * @param  array<int, string>  $headings
     * @return array{session: array<int, int>, event: array<int, int>, all: array<int, int>}
     */
    public static function mergeColumnIndices(array $headings): array
    {
        $contextLabels = array_values(array_diff(
            $headings,
            [
                ...self::SESSION_MERGE_HEADINGS,
                ...self::EVENT_MERGE_HEADINGS,
                'Product title',
                'Size',
                'Color',
                'Product qty',
                'Unit price',
                'Line total',
            ],
        ));

        $sessionLabels = array_merge(self::SESSION_MERGE_HEADINGS, $contextLabels);
        $session = self::indicesForLabels($headings, $sessionLabels);
        $event = self::indicesForLabels($headings, self::EVENT_MERGE_HEADINGS);

        return [
            'session' => $session,
            'event' => $event,
            'all' => array_values(array_unique([
                ...$session,
                ...$event,
                ...self::indicesForLabels($headings, ['Product title']),
            ])),
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array{
     *     rows: array<int, array<int, mixed>>,
     *     event_row_counts: array<int, int>,
     *     product_title_merges: array<int, array{column: int, start_row: int, end_row: int}>
     * }
     */
    public static function expandSession(
        ActivityEcomUser $session,
        array $metrics,
        Request $request,
        int &$serialStart,
    ): array {
        $headings = self::headings($request->query());
        $mergeColumns = self::mergeColumnIndices($headings);
        $sessionSerial = $serialStart++;
        $events = $metrics['commerce_events'] ?? [];
        $rows = [];
        $eventRowCounts = [];
        $productTitleMerges = [];
        $eventOffset = 0;

        if ($events === []) {
            $rows[] = self::buildRow(
                $session,
                $metrics,
                $request,
                self::fallbackEventHeaderValues($metrics),
                self::emptyProductValues(),
                self::fallbackEventSummaryValues($metrics),
                $sessionSerial,
            );

            return [
                'rows' => $rows,
                'event_row_counts' => [1],
                'product_title_merges' => [],
            ];
        }

        foreach ($events as $event) {
            $eventHeaderValues = self::eventHeaderValues($event);
            $eventSummaryValues = self::eventSummaryValues($event);
            $products = is_array($event['products'] ?? null) ? $event['products'] : [];
            $eventRows = [];

            if ($products === []) {
                $eventRows[] = self::buildRow(
                    $session,
                    $metrics,
                    $request,
                    $eventHeaderValues,
                    self::emptyProductValues(),
                    $eventSummaryValues,
                    $sessionSerial,
                );
            } else {
                foreach ($products as $product) {
                    $eventRows[] = self::buildRow(
                        $session,
                        $metrics,
                        $request,
                        $eventHeaderValues,
                        self::productValues($product),
                        $eventSummaryValues,
                        $sessionSerial,
                    );
                }
            }

            self::blankContinuationCells($eventRows, $mergeColumns['event']);
            $eventMerges = self::applyProductTitleMerges($eventRows, $headings);

            foreach ($eventMerges as $merge) {
                $productTitleMerges[] = [
                    'column' => $merge['column'],
                    'start_row' => $eventOffset + $merge['start_row'],
                    'end_row' => $eventOffset + $merge['end_row'],
                ];
            }

            $eventRowCounts[] = count($eventRows);
            $eventOffset += count($eventRows);
            array_push($rows, ...$eventRows);
        }

        if (count($rows) > 1) {
            self::blankContinuationCells($rows, $mergeColumns['session']);
        }

        return [
            'rows' => $rows,
            'event_row_counts' => $eventRowCounts,
            'product_title_merges' => $productTitleMerges,
        ];
    }

    /**
     * @param  array<int, array{column: int, start_row: int, end_row: int}>  $ranges
     * @param  array<int, array{column: int, start_row: int, end_row: int}>  $productTitleMerges
     * @return array<int, array{column: int, start_row: int, end_row: int}>
     */
    public static function mergeRangesForSessionBlock(
        array $ranges,
        int $blockStartRow,
        int $sessionRowCount,
        array $eventRowCounts,
        array $headings,
        array $productTitleMerges = [],
    ): array {
        if ($sessionRowCount <= 1 && max($eventRowCounts) <= 1) {
            return $ranges;
        }

        $mergeColumns = self::mergeColumnIndices($headings);

        if ($sessionRowCount > 1) {
            foreach ($mergeColumns['session'] as $column) {
                $ranges[] = [
                    'column' => $column,
                    'start_row' => $blockStartRow,
                    'end_row' => $blockStartRow + $sessionRowCount - 1,
                ];
            }
        }

        $eventOffset = 0;

        foreach ($eventRowCounts as $eventRowCount) {
            if ($eventRowCount > 1) {
                foreach ($mergeColumns['event'] as $column) {
                    $ranges[] = [
                        'column' => $column,
                        'start_row' => $blockStartRow + $eventOffset,
                        'end_row' => $blockStartRow + $eventOffset + $eventRowCount - 1,
                    ];
                }
            }

            $eventOffset += $eventRowCount;
        }

        foreach ($productTitleMerges as $merge) {
            if ($merge['end_row'] <= $merge['start_row']) {
                continue;
            }

            $ranges[] = [
                'column' => $merge['column'],
                'start_row' => $blockStartRow + $merge['start_row'],
                'end_row' => $blockStartRow + $merge['end_row'],
            ];
        }

        return $ranges;
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @param  array<int, mixed>  $eventHeaderValues
     * @param  array<int, mixed>  $productValues
     * @param  array<int, mixed>  $eventSummaryValues
     * @return array<int, mixed>
     */
    private static function buildRow(
        ActivityEcomUser $session,
        array $metrics,
        Request $request,
        array $eventHeaderValues,
        array $productValues,
        array $eventSummaryValues,
        int $serial,
    ): array {
        $user = self::userValues($session);
        $traffic = SessionTrafficAttribution::exportTrafficFields($session);
        $row = [
            $serial,
            TrackerTime::formatFromStorage($session->created_at) ?? '—',
            $user['user_type'],
            $user['name'] !== '' ? $user['name'] : '—',
            $user['email'] !== '' ? $user['email'] : '—',
            $user['phone'] !== '' ? $user['phone'] : '—',
            ...$eventHeaderValues,
            $productValues[0],
            $productValues[1],
            $productValues[2],
            $productValues[3],
            $eventSummaryValues[0],
            $productValues[4],
            $productValues[5],
            $eventSummaryValues[1],
        ];

        if ($request->filled('department') || $request->filled('category')) {
            $catalogPath = trim((string) ($metrics['catalog_path'] ?? ''));
            $row[] = ($catalogPath !== '' && $catalogPath !== '—') ? $catalogPath : '—';
        }

        $focus = $request->input('focus');

        foreach (EcomActivityFocus::exportContextColumns($focus, $request) as $column) {
            $row[] = self::formatMetric($column['key'] ?? '', $metrics);
        }

        $row[] = format_duration((int) ($session->session_duration_seconds ?? 0));
        $row[] = $traffic['utm_source'];
        $row[] = $traffic['traffic_type'];
        $row[] = $traffic['paid_click_id'];

        return $row;
    }

    /**
     * @return array{user_type: string, name: string, email: string, phone: string}
     */
    private static function userValues(ActivityEcomUser $session): array
    {
        if ($session->isRegisteredUser()) {
            return [
                'user_type' => 'Registered',
                'name' => (string) ($session->user_name ?: 'User #'.$session->user_id),
                'email' => (string) ($session->user_email ?? ''),
                'phone' => (string) ($session->user_phone ?? ''),
            ];
        }

        if ($session->isGuestCheckout()) {
            return [
                'user_type' => 'Guest checkout',
                'name' => (string) ($session->user_name ?: '—'),
                'email' => (string) ($session->user_email ?? ''),
                'phone' => (string) ($session->user_phone ?? ''),
            ];
        }

        if ($session->is_logged_in && $session->user_id) {
            return [
                'user_type' => 'Registered',
                'name' => 'User #'.$session->user_id,
                'email' => '',
                'phone' => '',
            ];
        }

        return [
            'user_type' => 'Guest',
            'name' => '',
            'email' => '',
            'phone' => '',
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<int, mixed>
     */
    private static function eventHeaderValues(array $event): array
    {
        $stage = (string) ($event['stage_label'] ?? $event['stage'] ?? '—');
        $orderId = self::orderIdFromEvent($event);

        return [
            $stage !== '' ? $stage : '—',
            $orderId !== '' ? $orderId : '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<int, mixed>
     */
    private static function eventSummaryValues(array $event): array
    {
        $eventTotal = self::parseMoney($event['cart_total'] ?? null);

        if ($eventTotal === null) {
            $eventTotal = self::fieldMoneyFromInfoGroups($event, 'Grand total')
                ?? self::fieldMoneyFromInfoGroups($event, 'Total');
        }

        return [
            self::sumQtyFromEvent($event),
            $eventTotal ?? '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<int, mixed>
     */
    private static function fallbackEventHeaderValues(array $metrics): array
    {
        $label = trim((string) ($metrics['commerce_label'] ?? ''));
        $stage = $label !== '' ? $label : self::stageFromDisplay((string) ($metrics['commerce_display'] ?? ''));

        return [
            $stage !== '' ? $stage : '—',
            '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     * @return array<int, mixed>
     */
    private static function fallbackEventSummaryValues(array $metrics): array
    {
        $total = is_numeric($metrics['commerce_value'] ?? null)
            ? round((float) $metrics['commerce_value'], 2)
            : self::parseMoney($metrics['commerce_display'] ?? null);
        $sumQty = is_numeric($metrics['order_qty'] ?? null) ? (int) $metrics['order_qty'] : '—';

        return [
            $sumQty,
            $total ?? '—',
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function emptyProductValues(): array
    {
        return ['—', '—', '—', '—', '—', '—'];
    }

    /**
     * @param  array<string, mixed>  $product
     * @return array<int, mixed>
     */
    private static function productValues(array $product): array
    {
        $qty = is_numeric($product['qty'] ?? null) ? (int) $product['qty'] : null;
        $unitPrice = self::parseMoney($product['price'] ?? null);
        $lineTotal = ($qty !== null && $unitPrice !== null)
            ? round($qty * $unitPrice, 2)
            : null;

        $color = trim((string) ($product['color_po'] ?? $product['color_ecommerce'] ?? ''));

        return [
            filled($product['title'] ?? null) ? (string) $product['title'] : '—',
            filled($product['size'] ?? null) ? (string) $product['size'] : '—',
            $color !== '' ? $color : '—',
            $qty ?? '—',
            $unitPrice ?? '—',
            $lineTotal ?? '—',
        ];
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private static function formatMetric(string $key, array $metrics): mixed
    {
        $value = $metrics[$key] ?? '—';

        if (is_numeric($value) && in_array($key, ['cart_value', 'checkout_value', 'order_value'], true)) {
            return round((float) $value, 2);
        }

        if (is_numeric($value) && ! in_array($key, ['purchased'], true)) {
            return (int) $value;
        }

        return $value;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function sumQtyFromEvent(array $event): mixed
    {
        $products = is_array($event['products'] ?? null) ? $event['products'] : [];
        $sum = 0;
        $hasQty = false;

        foreach ($products as $product) {
            if (! is_array($product) || ! is_numeric($product['qty'] ?? null)) {
                continue;
            }

            $sum += (int) $product['qty'];
            $hasQty = true;
        }

        if ($hasQty) {
            return $sum;
        }

        if (is_numeric($event['cart_qty'] ?? null)) {
            return (int) $event['cart_qty'];
        }

        $fromGroups = self::fieldValueFromInfoGroups($event, 'Quantity');

        if ($fromGroups !== null && is_numeric($fromGroups)) {
            return (int) $fromGroups;
        }

        return '—';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function orderIdFromEvent(array $event): string
    {
        $fromGroups = self::fieldValueFromInfoGroups($event, 'Order ID');

        if ($fromGroups !== null) {
            return $fromGroups;
        }

        $id = (string) ($event['id'] ?? '');

        if (str_starts_with($id, 'payment:')) {
            return substr($id, strlen('payment:'));
        }

        return '';
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function fieldValueFromInfoGroups(array $event, string $label): ?string
    {
        foreach ($event['info_groups'] ?? [] as $group) {
            foreach ($group['fields'] ?? [] as $field) {
                if (($field['label'] ?? '') === $label && filled($field['value'] ?? null)) {
                    return (string) $field['value'];
                }
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private static function fieldMoneyFromInfoGroups(array $event, string $label): ?float
    {
        $value = self::fieldValueFromInfoGroups($event, $label);

        return self::parseMoney($value);
    }

    private static function parseMoney(mixed $value): ?float
    {
        if (is_int($value) || is_float($value)) {
            $amount = round((float) $value, 2);

            return $amount > 0 ? $amount : null;
        }

        if (! is_string($value) || trim($value) === '' || $value === '—') {
            return null;
        }

        $normalized = preg_replace('/[^\d.\-]/', '', $value) ?? '';

        if ($normalized === '' || ! is_numeric($normalized)) {
            return null;
        }

        $amount = round((float) $normalized, 2);

        return $amount > 0 ? $amount : null;
    }

    private static function stageFromDisplay(string $display): string
    {
        if ($display === '' || $display === '—') {
            return '';
        }

        return trim(explode('·', $display)[0] ?? '');
    }

    /**
     * @param  array<int, array<int, mixed>>  $eventRows
     * @param  array<int, string>  $headings
     * @return array<int, array{column: int, start_row: int, end_row: int}>
     */
    private static function applyProductTitleMerges(array &$eventRows, array $headings): array
    {
        if (count($eventRows) <= 1) {
            return [];
        }

        $titleIndex = array_search('Product title', $headings, true);

        if ($titleIndex === false) {
            return [];
        }

        $titleIndex = (int) $titleIndex;
        $merges = [];
        $start = 0;

        while ($start < count($eventRows)) {
            $end = $start;
            $baseTitle = self::normalizeProductTitleForMerge((string) ($eventRows[$start][$titleIndex] ?? ''));

            if ($baseTitle === '') {
                $start++;

                continue;
            }

            while ($end + 1 < count($eventRows)) {
                $nextTitle = self::normalizeProductTitleForMerge((string) ($eventRows[$end + 1][$titleIndex] ?? ''));

                if ($nextTitle !== $baseTitle) {
                    break;
                }

                $end++;
            }

            if ($end > $start) {
                for ($rowIndex = $start + 1; $rowIndex <= $end; $rowIndex++) {
                    $eventRows[$rowIndex][$titleIndex] = '';
                }

                $merges[] = [
                    'column' => $titleIndex,
                    'start_row' => $start,
                    'end_row' => $end,
                ];
            }

            $start = $end + 1;
        }

        return $merges;
    }

    private static function normalizeProductTitleForMerge(string $title): string
    {
        $title = trim($title);

        if ($title === '' || $title === '—') {
            return '';
        }

        return trim(preg_replace('/\s*\([^)]+\)\s*$/', '', $title) ?? $title);
    }

    /**
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, int>  $columnIndices
     */
    private static function blankContinuationCells(array &$rows, array $columnIndices): void
    {
        if ($columnIndices === [] || count($rows) <= 1) {
            return;
        }

        for ($rowIndex = 1, $count = count($rows); $rowIndex < $count; $rowIndex++) {
            foreach ($columnIndices as $columnIndex) {
                $rows[$rowIndex][$columnIndex] = '';
            }
        }
    }

    private static function exportContextHeadingLabel(string $label): string
    {
        return $label;
    }

    /**
     * @param  array<int, string>  $headings
     * @param  list<string>  $labels
     * @return array<int, int>
     */
    private static function indicesForLabels(array $headings, array $labels): array
    {
        $indices = [];

        foreach ($labels as $label) {
            $index = array_search($label, $headings, true);

            if ($index !== false) {
                $indices[] = (int) $index;
            }
        }

        return $indices;
    }
}
