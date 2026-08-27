<?php

namespace App\Support;

use App\Models\ActivityEcomUserAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Normalized commerce reads for dashboard and activity list views.
 * JSON payloads on actions are view-only (session show timeline).
 */
final class CommerceReadSupport
{
    /** @return list<string> */
    public static function scalarActionColumns(): array
    {
        return [
            'id',
            'event_id',
            'session_id',
            'action_type',
            'created_at',
            'commerce_total',
            'commerce_subtotal',
            'commerce_shipping',
            'commerce_discount',
            'coupon_code',
            'discount_type',
            'line_count',
            'order_id',
            'amount_paid',
            'item_qty',
            'product_name',
            'product_code',
            'product_color_id',
            'general_color_name',
            'sku',
            'category_name',
            'category_code',
            'department_name',
            'page_url',
        ];
    }

    public static function amountForAction(object $action): ?float
    {
        $actionType = (string) ($action->action_type ?? '');

        if ($actionType === 'payment_success') {
            foreach ([self::readScalar($action, 'amount_paid'), self::readScalar($action, 'commerce_total')] as $candidate) {
                if (is_numeric($candidate) && (float) $candidate > 0) {
                    return round((float) $candidate, 2);
                }
            }

            return null;
        }

        $total = self::readScalar($action, 'commerce_total');

        if (! is_numeric($total)) {
            return null;
        }

        $amount = round((float) $total, 2);

        return $amount > 0 ? $amount : null;
    }

    public static function orderIdForAction(object $action): string
    {
        return trim((string) (self::readScalar($action, 'order_id') ?? ''));
    }

    public static function itemQtyForAction(object $action): int
    {
        $itemQty = (int) (self::readScalar($action, 'item_qty') ?? 0);

        if ($itemQty > 0) {
            return $itemQty;
        }

        return max(1, (int) (self::readScalar($action, 'line_count') ?? 0));
    }

    private static function readScalar(object $action, string $key): mixed
    {
        return $action->{$key} ?? null;
    }

    /**
     * @param  list<string>  $eventIds
     * @return Collection<string, Collection<int, object>>
     */
    public static function linesGroupedByEventId(array $eventIds): Collection
    {
        if ($eventIds === []) {
            return collect();
        }

        $eventIds = array_values(array_unique(array_filter($eventIds, fn ($id) => filled($id))));

        if ($eventIds === []) {
            return collect();
        }

        $rows = collect();

        foreach (array_chunk($eventIds, 1000) as $chunk) {
            $rows = $rows->concat(
                DB::table('activity_ecom_commerce_line_items')
                    ->whereIn('event_id', $chunk)
                    ->orderBy('line_no')
                    ->get()
            );
        }

        return $rows->groupBy('event_id');
    }

    /**
     * @return Collection<int, object>
     */
    public static function linesForEvent(string $eventId): Collection
    {
        return DB::table('activity_ecom_commerce_line_items')
            ->where('event_id', $eventId)
            ->orderBy('line_no')
            ->get();
    }

    public static function orderForEvent(string $eventId): ?object
    {
        return DB::table('activity_ecom_orders')->where('event_id', $eventId)->first();
    }

    /**
     * @return list<array{name: string, code: string, sku: string, category: string, department_name: string, color: string, size: string, qty: float, unit_price: ?float, line_total: ?float}>
     */
    public static function catalogLinesForAction(
        object $action,
        ?Collection $linesByEvent = null,
    ): array {
        if (in_array($action->action_type, ['product_view', 'product_view_popup'], true)) {
            return [[
                'name' => (string) ($action->product_name ?? ''),
                'code' => (string) ($action->product_code ?? ''),
                'sku' => trim((string) ($action->sku ?? '')),
                'category' => (string) ($action->category_name ?? ''),
                'department_name' => TrackerCategoryIdentity::normalizeDepartmentName((string) ($action->department_name ?? '')),
                'color' => (string) ($action->general_color_name ?? ''),
                'size' => '',
                'qty' => 1,
                'unit_price' => null,
                'line_total' => null,
            ]];
        }

        if ($action->action_type === 'category_view') {
            return [[
                'name' => '',
                'code' => '',
                'sku' => '',
                'category' => (string) ($action->category_name ?? ''),
                'department_name' => TrackerCategoryIdentity::normalizeDepartmentName((string) ($action->department_name ?? '')),
                'color' => '',
                'size' => '',
                'qty' => 1,
                'unit_price' => null,
                'line_total' => null,
            ]];
        }

        $lines = $linesByEvent === null
            ? self::linesForEvent((string) $action->event_id)
            : ($linesByEvent->get($action->event_id) ?? collect());

        if ($lines->isEmpty()) {
            return [[
                'name' => (string) ($action->product_name ?? ''),
                'code' => (string) ($action->product_code ?? ''),
                'sku' => trim((string) ($action->sku ?? '')),
                'category' => (string) ($action->category_name ?? ''),
                'department_name' => TrackerCategoryIdentity::normalizeDepartmentName((string) ($action->department_name ?? '')),
                'color' => (string) ($action->general_color_name ?? ''),
                'size' => '',
                'qty' => (float) self::itemQtyForAction($action),
                'unit_price' => null,
                'line_total' => self::amountForAction($action),
            ]];
        }

        return $lines->map(fn ($row) => [
            'name' => trim((string) ($row->product_name ?? '')),
            'code' => trim((string) ($row->product_code ?? '')),
            'sku' => trim((string) ($row->sku ?? '')),
            'category' => trim((string) ($row->category_name ?? '')),
            'department_name' => TrackerCategoryIdentity::normalizeDepartmentName((string) ($row->department_name ?? '')),
            'color' => trim((string) ($row->color_name ?? '')),
            'size' => trim((string) ($row->size_name ?? '')),
            'qty' => (float) ($row->qty ?? 0),
            'unit_price' => isset($row->unit_price) ? (float) $row->unit_price : null,
            'line_total' => isset($row->line_total) ? (float) $row->line_total : null,
        ])->values()->all();
    }

    /**
     * @param  array<string, mixed>  $options
     * @return array{revenue: float, qty: int}
     */
    public static function sumCatalogPaymentLines(
        object $action,
        array $options,
        callable $lineMatchesOptions,
        callable $extractPurchaseLine,
    ): array {
        if ($action->action_type !== 'payment_success') {
            return ['revenue' => 0.0, 'qty' => 0];
        }

        $lines = self::catalogLinesForAction($action);
        $revenue = 0.0;
        $qty = 0;

        foreach ($lines as $line) {
            if (! $lineMatchesOptions($line, $options)) {
                continue;
            }

            $purchaseLine = $extractPurchaseLine($line);

            if ($purchaseLine !== null) {
                $revenue += $purchaseLine['revenue'];
                $qty += $purchaseLine['qty'];
            }
        }

        if ($revenue <= 0 && $lineMatchesOptions([
            'name' => (string) ($action->product_name ?? ''),
            'code' => (string) ($action->product_code ?? ''),
            'sku' => trim((string) ($action->sku ?? '')),
            'category' => (string) ($action->category_name ?? ''),
            'department_name' => TrackerCategoryIdentity::normalizeDepartmentName((string) ($action->department_name ?? '')),
            'color' => (string) ($action->general_color_name ?? ''),
            'size' => '',
        ], $options)) {
            $revenue = (float) (self::amountForAction($action) ?? 0);
            $qty = self::itemQtyForAction($action);
        }

        return [
            'revenue' => $revenue > 0 ? round($revenue, 2) : 0.0,
            'qty' => $qty,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function displayProductsForAction(ActivityEcomUserAction $action): array
    {
        $lines = self::linesForEvent((string) $action->event_id);

        if ($lines->isEmpty()) {
            return [];
        }

        return array_values(array_map(function ($row) {
            $qty = (int) max(1, (float) ($row->qty ?? 1));
            $price = is_numeric($row->unit_price ?? null)
                ? round((float) $row->unit_price, 2)
                : (is_numeric($row->line_total ?? null) ? round((float) $row->line_total, 2) : null);
            $title = trim((string) ($row->product_name ?? ''));
            $code = trim((string) ($row->product_code ?? $row->sku ?? ''));

            if ($title !== '' && $code !== '') {
                $title .= ' ('.$code.')';
            } elseif ($title === '' && $code !== '') {
                $title = $code;
            }

            return array_filter([
                'title' => $title !== '' ? $title : 'Product',
                'size' => trim((string) ($row->size_name ?? '')),
                'color_po' => trim((string) ($row->color_name ?? '')),
                'color_ecommerce' => trim((string) ($row->color_name ?? '')),
                'qty' => (string) $qty,
                'price' => $price !== null ? '£'.number_format($price, 2) : '—',
                'image_url' => '',
            ], fn ($value) => $value !== '' && $value !== null);
        }, $lines->all()));
    }

    /**
     * @param  Collection<int, string>  $sessionIds
     */
    public static function sumPaymentMetricsForSessions(
        Collection $sessionIds,
        Carbon $from,
        Carbon $to,
    ): Collection {
        if ($sessionIds->isEmpty()) {
            return collect();
        }

        return DB::table('activity_ecom_orders')
            ->selectRaw('session_id, SUM(amount_paid) as order_value, SUM(COALESCE(item_qty, 0)) as order_qty')
            ->whereIn('session_id', $sessionIds->all())
            ->whereBetween('ordered_at', TrackerTime::storageRange($from, $to))
            ->groupBy('session_id')
            ->get()
            ->keyBy('session_id');
    }
}
