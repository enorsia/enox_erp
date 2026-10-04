<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Sync one user action into commerce line items, daily metric tables, and orders.
 */
class ActivityEcomActionSyncWriter
{
    /** @var list<string> */
    public const SYNCABLE_TYPES = [
        'category_view',
        'product_view',
        'product_view_popup',
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
    ];

    public static function isSyncableActionType(string $actionType): bool
    {
        return in_array($actionType, self::SYNCABLE_TYPES, true);
    }

    /**
     * @return array{processed: int, skipped: int, last_action_id: int|null}
     */
    public function syncBatch(array $actions): array
    {
        $processed = 0;
        $skipped = 0;
        $lastId = null;

        foreach ($actions as $action) {
            if (! $action instanceof ActivityEcomUserAction) {
                continue;
            }

            try {
                $this->syncFromAction($action);
                $processed++;
                $lastId = (int) $action->id;
            } catch (Throwable) {
                $skipped++;
            }
        }

        return [
            'processed' => $processed,
            'skipped' => $skipped,
            'last_action_id' => $lastId,
        ];
    }

    public function syncFromAction(ActivityEcomUserAction $action): void
    {
        if (! self::isSyncableActionType((string) $action->action_type)) {
            return;
        }

        $session = ActivityEcomUser::query()->where('session_id', $action->session_id)->first();
        $visitorId = $session?->visitor_id;
        $metricDate = $this->metricDate($action);
        $stagedAt = $action->created_at?->format('Y-m-d H:i:s') ?? now()->format('Y-m-d H:i:s');
        $now = now()->format('Y-m-d H:i:s');

        DB::transaction(function () use ($action, $session, $visitorId, $metricDate, $stagedAt, $now): void {
            DB::table('activity_ecom_commerce_line_items')->where('event_id', $action->event_id)->delete();

            $type = (string) $action->action_type;

            if ($type === 'category_view') {
                $this->insertLine($action, $visitorId, 'category_view', $stagedAt, $now, [
                    'department_name' => (string) ($action->department_name ?? ''),
                    'category_name' => (string) ($action->category_name ?? ''),
                    'product_code' => null,
                    'product_name' => null,
                    'qty' => 1,
                ]);
                $this->bumpSite($metricDate, $now, categoryViews: 1);
                $this->bumpCategory($metricDate, $now, (string) ($action->department_name ?? ''), (string) ($action->category_name ?? ''), categoryViews: 1);
            } elseif ($type === 'product_view' || $type === 'product_view_popup') {
                $stage = $type === 'product_view_popup' ? 'product_view_popup' : 'product_view';
                $this->insertLine($action, $visitorId, $stage, $stagedAt, $now, [
                    'department_name' => (string) ($action->department_name ?? ''),
                    'category_name' => (string) ($action->category_name ?? ''),
                    'product_code' => (string) ($action->product_code ?? ''),
                    'product_name' => (string) ($action->product_name ?? ''),
                    'sku' => (string) ($action->sku ?? ''),
                    'qty' => 1,
                    'unit_price' => $action->product_price,
                ]);
                $this->bumpSite($metricDate, $now, productViews: 1);
                $code = trim((string) ($action->product_code ?? ''));
                if ($code !== '') {
                    $this->bumpProduct($metricDate, $now, $code, $action, views: 1);
                }
                $this->bumpCategory(
                    $metricDate,
                    $now,
                    (string) ($action->department_name ?? ''),
                    (string) ($action->category_name ?? ''),
                    productViews: 1,
                );
            } elseif ($type === 'add_to_cart') {
                $lineNo = 0;
                foreach ($this->cartItems($action->add_to_cart) as $item) {
                    $lineNo++;
                    $this->insertLine($action, $visitorId, 'add_to_cart', $stagedAt, $now, $item, null, $lineNo);
                    $this->bumpSite($metricDate, $now, addToCart: 1);
                    $code = trim((string) ($item['product_code'] ?? ''));
                    if ($code !== '') {
                        $this->bumpProduct($metricDate, $now, $code, $action, adds: 1);
                    }
                    $this->bumpCategory(
                        $metricDate,
                        $now,
                        (string) ($item['department_name'] ?? $action->department_name ?? ''),
                        (string) ($item['category_name'] ?? $action->category_name ?? ''),
                        adds: 1,
                    );
                }
            } elseif ($type === 'begin_checkout') {
                $lineNo = 0;
                foreach ($this->cartItems($action->begin_checkout) as $item) {
                    $lineNo++;
                    $this->insertLine($action, $visitorId, 'begin_checkout', $stagedAt, $now, $item, null, $lineNo);
                    $this->bumpSite($metricDate, $now, beginCheckout: 1);
                    $this->bumpProduct($metricDate, $now, (string) ($item['product_code'] ?? ''), $action, beginCheckout: 1);
                }
            } elseif ($type === 'proceed_checkout') {
                $lineNo = 0;
                foreach ($this->cartItems($action->proceed_to_checkout) as $item) {
                    $lineNo++;
                    $this->insertLine($action, $visitorId, 'proceed_checkout', $stagedAt, $now, $item, null, $lineNo);
                    $this->bumpSite($metricDate, $now, proceedCheckout: 1);
                    $this->bumpProduct($metricDate, $now, (string) ($item['product_code'] ?? ''), $action, proceedCheckout: 1);
                }
            } elseif ($type === 'payment_success') {
                $payload = is_array($action->payment_success) ? $action->payment_success : [];
                $orderId = trim((string) ($payload['order_id'] ?? $action->order_id ?? ''));
                $amount = (float) ($payload['amount_paid'] ?? $action->amount_paid ?? 0);
                $qty = (float) ($action->item_qty ?? 0);

                if ($orderId !== '') {
                    DB::table('activity_ecom_commerce_line_items')
                        ->where('order_id', $orderId)
                        ->where('funnel_stage', 'payment_success')
                        ->delete();
                }

                ActivityEcomUser::query()
                    ->where('session_id', $action->session_id)
                    ->update(['has_payment_success' => true]);

                $lineNo = 0;
                foreach ($this->cartItems($payload) as $item) {
                    $lineNo++;
                    $item['qty'] = $item['qty'] ?? 1;
                    $item['line_total'] = $item['line_total'] ?? $amount;
                    $this->insertLine($action, $visitorId, 'payment_success', $stagedAt, $now, $item, $orderId, $lineNo);
                    $this->bumpProduct($metricDate, $now, (string) ($item['product_code'] ?? ''), $action, payments: 1, units: (float) $item['qty'], revenue: (float) ($item['line_total'] ?? 0));
                    $this->bumpCategory(
                        $metricDate,
                        $now,
                        (string) ($item['department_name'] ?? ''),
                        (string) ($item['category_name'] ?? ''),
                        payments: 1,
                        units: (float) $item['qty'],
                        revenue: (float) ($item['line_total'] ?? 0),
                    );
                }

                if ($orderId !== '') {
                    DB::table('activity_ecom_orders')->updateOrInsert(
                        ['order_id' => $orderId],
                        [
                            'event_id' => $action->event_id,
                            'session_id' => $action->session_id,
                            'visitor_id' => $visitorId,
                            'amount_paid' => $amount,
                            'ordered_at' => $stagedAt,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ],
                    );
                }

                $this->bumpSite($metricDate, $now, paymentSuccess: 1, orders: $orderId !== '' ? 1 : 0, revenue: $amount, itemsSold: $qty);
            }

            $this->bumpVisitors($metricDate, $now, $visitorId, $stagedAt);
            $this->bumpVisitorMetrics($metricDate, $now, $visitorId, $stagedAt, $type === 'payment_success' ? (float) ($action->amount_paid ?? 0) : 0);
            $this->bumpDimensions($metricDate, $now, $session, $type === 'payment_success' ? (float) ($action->amount_paid ?? 0) : 0);
        });
    }

    private function metricDate(ActivityEcomUserAction $action): string
    {
        $tz = (string) config('tracker.visitor_timezone', 'UTC');
        $at = $action->created_at ?? now();

        return $at->copy()->timezone($tz)->toDateString();
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @return list<array<string, mixed>>
     */
    private function cartItems(?array $payload): array
    {
        if (! is_array($payload)) {
            return [[
                'department_name' => '',
                'category_name' => '',
                'product_code' => '',
                'product_name' => '',
                'qty' => 1,
            ]];
        }

        $items = $payload['items'] ?? $payload['line_items'] ?? null;

        if (! is_array($items) || $items === []) {
            return [[
                'department_name' => (string) ($payload['department_name'] ?? ''),
                'category_name' => (string) ($payload['category_name'] ?? ''),
                'product_code' => (string) ($payload['product_code'] ?? ''),
                'product_name' => (string) ($payload['product_name'] ?? ''),
                'qty' => (float) ($payload['qty'] ?? 1),
                'unit_price' => $payload['unit_price'] ?? null,
                'line_total' => $payload['line_total'] ?? null,
            ]];
        }

        $rows = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $rows[] = [
                'department_name' => (string) ($item['department_name'] ?? ''),
                'category_name' => (string) ($item['category_name'] ?? ''),
                'product_code' => (string) ($item['product_code'] ?? $item['sku'] ?? ''),
                'product_name' => (string) ($item['product_name'] ?? $item['name'] ?? ''),
                'sku' => (string) ($item['sku'] ?? ''),
                'qty' => (float) ($item['qty'] ?? $item['quantity'] ?? 1),
                'unit_price' => $item['unit_price'] ?? $item['price'] ?? null,
                'line_total' => $item['line_total'] ?? null,
            ];
        }

        return $rows === [] ? [[
            'department_name' => '',
            'category_name' => '',
            'product_code' => '',
            'product_name' => '',
            'qty' => 1,
        ]] : $rows;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function insertLine(
        ActivityEcomUserAction $action,
        ?string $visitorId,
        string $funnelStage,
        string $stagedAt,
        string $now,
        array $row,
        ?string $orderId = null,
        int $lineNo = 1,
    ): void {
        DB::table('activity_ecom_commerce_line_items')->insert([
            'event_id' => $action->event_id,
            'session_id' => $action->session_id,
            'visitor_id' => $visitorId,
            'funnel_stage' => $funnelStage,
            'order_id' => $orderId,
            'line_no' => $lineNo,
            'product_code' => $row['product_code'] ?? null,
            'sku' => $row['sku'] ?? null,
            'product_name' => $row['product_name'] ?? null,
            'department_name' => $row['department_name'] ?? null,
            'category_name' => $row['category_name'] ?? null,
            'category_code' => $action->category_code,
            'qty' => $row['qty'] ?? 1,
            'unit_price' => $row['unit_price'] ?? null,
            'line_total' => $row['line_total'] ?? null,
            'staged_at' => $stagedAt,
            'created_at' => $now,
        ]);
    }

    private function bumpSite(
        string $metricDate,
        string $now,
        int $categoryViews = 0,
        int $productViews = 0,
        int $addToCart = 0,
        int $beginCheckout = 0,
        int $proceedCheckout = 0,
        int $paymentSuccess = 0,
        int $orders = 0,
        float $revenue = 0,
        float $itemsSold = 0,
    ): void {
        $this->incrementMetrics('activity_ecom_daily_site_metrics', ['metric_date' => $metricDate], [
            'action_count' => 1,
            'category_view_count' => $categoryViews,
            'product_view_count' => $productViews,
            'add_to_cart_count' => $addToCart,
            'begin_checkout_count' => $beginCheckout,
            'proceed_checkout_count' => $proceedCheckout,
            'payment_success_count' => $paymentSuccess,
            'order_count' => $orders,
            'revenue_total' => $revenue,
            'items_sold_qty' => $itemsSold,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function bumpProduct(
        string $metricDate,
        string $now,
        string $productCode,
        ActivityEcomUserAction $action,
        int $views = 0,
        int $adds = 0,
        int $beginCheckout = 0,
        int $proceedCheckout = 0,
        int $payments = 0,
        float $units = 0,
        float $revenue = 0,
    ): void {
        if ($productCode === '') {
            return;
        }

        $this->incrementMetrics('activity_ecom_daily_product_metrics', [
            'metric_date' => $metricDate,
            'product_code' => $productCode,
        ], [
            'product_name' => $action->product_name,
            'sku' => $action->sku,
            'department_name' => $action->department_name,
            'category_name' => $action->category_name,
            'view_count' => $views,
            'add_to_cart_count' => $adds,
            'begin_checkout_count' => $beginCheckout,
            'proceed_checkout_count' => $proceedCheckout,
            'payment_count' => $payments,
            'units_sold' => $units,
            'revenue' => $revenue,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function bumpCategory(
        string $metricDate,
        string $now,
        string $department,
        string $category,
        int $categoryViews = 0,
        int $productViews = 0,
        int $adds = 0,
        int $payments = 0,
        float $units = 0,
        float $revenue = 0,
    ): void {
        if ($department === '' && $category === '') {
            return;
        }

        $this->incrementMetrics('activity_ecom_daily_category_metrics', [
            'metric_date' => $metricDate,
            'department_name' => $department,
            'category_name' => $category,
        ], [
            'category_view_count' => $categoryViews,
            'product_view_count' => $productViews,
            'add_to_cart_count' => $adds,
            'payment_count' => $payments,
            'units_sold' => $units,
            'revenue' => $revenue,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function bumpVisitors(string $metricDate, string $now, ?string $visitorId, string $seenAt): void
    {
        if ($visitorId === null || $visitorId === '') {
            return;
        }

        $this->incrementMetrics('activity_ecom_daily_visitors', [
            'visitor_id' => $visitorId,
            'visit_date' => $metricDate,
        ], [
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
            'session_count' => 1,
            'total_duration_seconds' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['last_seen_at' => $seenAt]);
    }

    private function bumpVisitorMetrics(string $metricDate, string $now, ?string $visitorId, string $seenAt, float $revenue): void
    {
        if ($visitorId === null || $visitorId === '') {
            return;
        }

        $payments = $revenue > 0 ? 1 : 0;

        $this->incrementMetrics('activity_ecom_daily_visitor_metrics', [
            'metric_date' => $metricDate,
            'visitor_id' => $visitorId,
        ], [
            'session_count' => 1,
            'payment_count' => $payments,
            'revenue' => $revenue,
            'first_seen_at' => $seenAt,
            'last_seen_at' => $seenAt,
            'created_at' => $now,
            'updated_at' => $now,
        ], ['last_seen_at' => $seenAt]);
    }

    private function bumpDimensions(string $metricDate, string $now, ?ActivityEcomUser $session, float $revenue): void
    {
        if ($session === null) {
            return;
        }

        $source = trim((string) ($session->list_traffic_utm_source ?? ''));
        $medium = trim((string) ($session->list_traffic_utm_medium ?? ''));
        $value = ($source !== '' ? $source : '(direct)')."\0".($medium !== '' ? $medium : 'none');

        $this->incrementMetrics('activity_ecom_daily_dimension_metrics', [
            'metric_date' => $metricDate,
            'dimension_type' => 'list_traffic',
            'dimension_value' => $value,
        ], [
            'session_count' => 1,
            'payment_count' => $revenue > 0 ? 1 : 0,
            'revenue' => $revenue,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $device = trim((string) ($session->device_type ?? ''));
        if ($device !== '') {
            $this->incrementMetrics('activity_ecom_daily_dimension_metrics', [
                'metric_date' => $metricDate,
                'dimension_type' => 'device',
                'dimension_value' => $device,
            ], [
                'session_count' => 1,
                'payment_count' => 0,
                'revenue' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $keys
     * @param  array<string, int|float|string|null>  $values
     * @param  array<string, mixed>  $overwriteOnUpdate
     */
    private function incrementMetrics(string $table, array $keys, array $values, array $overwriteOnUpdate = []): void
    {
        $query = DB::table($table);
        foreach ($keys as $column => $value) {
            $query->where($column, $value);
        }

        if ($query->exists()) {
            $update = ['updated_at' => $values['updated_at'] ?? now()];
            foreach ($overwriteOnUpdate as $column => $value) {
                $update[$column] = $value;
            }
            foreach ($values as $column => $amount) {
                if (in_array($column, ['created_at', 'updated_at', 'product_name', 'sku', 'department_name', 'category_name', 'first_seen_at'], true)) {
                    continue;
                }
                if (is_numeric($amount)) {
                    $update[$column] = DB::raw($column.' + '.$amount);
                }
            }
            $fresh = DB::table($table);
            foreach ($keys as $column => $value) {
                $fresh->where($column, $value);
            }
            $fresh->update($update);

            return;
        }

        DB::table($table)->insert(array_merge($keys, $values));
    }
}
