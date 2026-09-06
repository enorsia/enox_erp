<?php

namespace App\Services;

use App\Exceptions\CommerceParseException;
use App\Models\ActivityEcomCommerceLineItem;
use App\Models\ActivityEcomOrder;
use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\CommerceLineItemParser;
use App\Support\CommercePricingExtractor;
use App\Support\CommerceSqlHelper;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerTime;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CommerceIngestWriter
{
    /** @var list<string> */
    public const COMMERCE_ACTION_TYPES = [
        'add_to_cart',
        'begin_checkout',
        'proceed_checkout',
        'payment_success',
    ];

    /** @var list<string> */
    public const CATALOG_INTEREST_ACTION_TYPES = [
        'category_view',
        'product_view',
        'product_view_popup',
    ];

    /** @var list<string> */
    public const SYNCABLE_ACTION_TYPES = [
        ...self::COMMERCE_ACTION_TYPES,
        ...self::CATALOG_INTEREST_ACTION_TYPES,
    ];

    public static function isSyncableActionType(string $actionType): bool
    {
        return in_array($actionType, self::SYNCABLE_ACTION_TYPES, true);
    }

    /**
     * @return array{status: string, skipped?: bool, reason?: string}
     */
    public function syncFromAction(ActivityEcomUserAction $action, bool $skipOnParseError = false, bool $useInsertOrIgnoreOrders = false): array
    {
        if (! self::isSyncableActionType($action->action_type)) {
            return ['status' => 'ignored'];
        }

        try {
            if (in_array($action->action_type, self::CATALOG_INTEREST_ACTION_TYPES, true)) {
                $this->writeCatalogInterest($action);
            } else {
                $this->writeActionCommerce($action, $useInsertOrIgnoreOrders);
            }

            return ['status' => 'ok'];
        } catch (CommerceParseException $e) {
            EcomTrackerLogger::frontend()->warning('commerce.sync.skipped', 'Commerce parse skipped', [
                'event_id' => $action->event_id,
                'action_type' => $action->action_type,
                'payload_signature' => $e->payloadSignature,
                'message' => $e->getMessage(),
            ]);

            if ($skipOnParseError) {
                return ['status' => 'skipped', 'skipped' => true, 'reason' => $e->getMessage()];
            }

            throw $e;
        }
    }

    /**
     * @param  list<ActivityEcomUserAction>  $actions
     * @return array{processed: int, skipped: int, last_action_id: int|null}
     */
    public function syncBatch(array $actions, bool $skipOnParseError = true, bool $useInsertOrIgnoreOrders = false): array
    {
        $processed = 0;
        $skipped = 0;
        $lastActionId = null;

        $parsed = [];
        foreach ($actions as $action) {
            if (! self::isSyncableActionType($action->action_type)) {
                continue;
            }

            try {
                if (in_array($action->action_type, self::CATALOG_INTEREST_ACTION_TYPES, true)) {
                    $parsed[] = $this->prepareCatalogInterestWrite($action);
                } else {
                    $parsed[] = $this->prepareActionWrite($action);
                }
            } catch (CommerceParseException $e) {
                $skipped++;
                $this->logParseSkip($action, $e);
                if (! $skipOnParseError) {
                    throw $e;
                }
            }
        }

        if ($parsed === []) {
            return ['processed' => 0, 'skipped' => $skipped, 'last_action_id' => null];
        }

        DB::transaction(function () use ($parsed, $useInsertOrIgnoreOrders, &$processed, &$lastActionId) {
            foreach ($parsed as $item) {
                if ($item['catalog_interest'] ?? false) {
                    $this->applyCatalogInterestWrite($item['action'], $item['lines'], $item['funnel_stage']);
                } else {
                    $this->applyPreparedWrite($item['action'], $item['lines'], $item['pricing'], $item['funnel_stage'], $useInsertOrIgnoreOrders);
                }
                $processed++;
                $lastActionId = (int) $item['action']->id;
            }
        });

        EcomTrackerLogger::frontend()->info('commerce.sync.batch.commit', 'Commerce batch committed', [
            'processed' => $processed,
            'last_action_id' => $lastActionId,
        ]);

        return ['processed' => $processed, 'skipped' => $skipped, 'last_action_id' => $lastActionId];
    }

    public function rebuildSessionFlags(string $sessionId): void
    {
        $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();
        if ($session === null) {
            return;
        }

        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $sessionId)
            ->whereIn('action_type', self::COMMERCE_ACTION_TYPES)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['action_type', 'created_at', 'commerce_total', 'amount_paid']);

        $hasAddToCart = $actions->contains(fn ($a) => $a->action_type === 'add_to_cart');
        $hasBeginCheckout = $actions->contains(fn ($a) => $a->action_type === 'begin_checkout');
        $hasProceedCheckout = $actions->contains(fn ($a) => $a->action_type === 'proceed_checkout');
        $hasPayment = $actions->contains(fn ($a) => $a->action_type === 'payment_success');

        $paymentActions = $actions->where('action_type', 'payment_success');
        $maxOrderValue = $paymentActions->max(fn ($a) => (float) ($a->amount_paid ?? $a->commerce_total ?? 0));
        $firstPayment = $paymentActions->sortBy(fn ($a) => $a->created_at)->first();

        $latestStage = null;
        $rank = [
            'payment_success' => 4,
            'proceed_checkout' => 3,
            'begin_checkout' => 2,
            'add_to_cart' => 1,
        ];
        $maxRank = 0;
        foreach ($actions as $action) {
            $r = $rank[$action->action_type] ?? 0;
            if ($r >= $maxRank) {
                $maxRank = $r;
                $latestStage = match ($action->action_type) {
                    'proceed_checkout' => 'proceed_checkout',
                    'begin_checkout' => 'begin_checkout',
                    'add_to_cart' => 'add_to_cart',
                    'payment_success' => 'payment_success',
                    default => $latestStage,
                };
            }
        }

        $session->update([
            'has_add_to_cart' => $hasAddToCart,
            'has_begin_checkout' => $hasBeginCheckout,
            'has_proceed_checkout' => $hasProceedCheckout,
            'has_payment_success' => $hasPayment,
            'max_order_value' => $maxOrderValue > 0 ? $maxOrderValue : null,
            'first_payment_at' => $firstPayment?->created_at
                ? TrackerTime::formatUtc($firstPayment->created_at)
                : null,
            'latest_funnel_stage' => $latestStage,
        ]);
    }

    /**
     * @return array{action: ActivityEcomUserAction, lines: list<array<string, mixed>>, pricing: array<string, mixed>, funnel_stage: string}
     */
    public function prepareActionWrite(ActivityEcomUserAction $action): array
    {
        $payload = $this->payloadForAction($action);
        $funnelStage = CommerceLineItemParser::funnelStage($action->action_type);
        $pricing = CommercePricingExtractor::fromPayload($action->action_type, $payload ?? [], $action->event_id);
        $lines = CommerceLineItemParser::parseFromAction($action);

        return [
            'action' => $action,
            'lines' => $lines,
            'pricing' => $pricing,
            'funnel_stage' => $funnelStage,
            'catalog_interest' => false,
        ];
    }

    /**
     * @return array{action: ActivityEcomUserAction, lines: list<array<string, mixed>>, funnel_stage: string, catalog_interest: true}
     */
    public function prepareCatalogInterestWrite(ActivityEcomUserAction $action): array
    {
        $funnelStage = CommerceLineItemParser::funnelStage($action->action_type);
        $lines = CommerceLineItemParser::parseFromAction($action);

        if ($lines === []) {
            throw new CommerceParseException(
                'Catalog interest action produced no line items',
                'empty:catalog_interest',
                $action->event_id,
                $action->action_type,
            );
        }

        return [
            'action' => $action,
            'lines' => $lines,
            'funnel_stage' => $funnelStage,
            'catalog_interest' => true,
        ];
    }

    private function writeCatalogInterest(ActivityEcomUserAction $action): void
    {
        $prepared = $this->prepareCatalogInterestWrite($action);
        $this->applyCatalogInterestWrite($prepared['action'], $prepared['lines'], $prepared['funnel_stage']);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function applyCatalogInterestWrite(
        ActivityEcomUserAction $action,
        array $lines,
        string $funnelStage,
    ): void {
        DB::table('activity_ecom_commerce_line_items')->where('event_id', $action->event_id)->delete();
        $this->insertLines($lines, false);

        EcomTrackerLogger::frontend()->info('commerce.catalog_interest.written', 'Catalog interest line written', [
            'event_id' => $action->event_id,
            'funnel_stage' => $funnelStage,
        ]);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $pricing
     */
    private function writeActionCommerce(ActivityEcomUserAction $action, bool $useInsertOrIgnoreOrders): void
    {
        $prepared = $this->prepareActionWrite($action);
        $this->applyPreparedWrite(
            $prepared['action'],
            $prepared['lines'],
            $prepared['pricing'],
            $prepared['funnel_stage'],
            $useInsertOrIgnoreOrders,
        );
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @param  array<string, mixed>  $pricing
     */
    private function applyPreparedWrite(
        ActivityEcomUserAction $action,
        array $lines,
        array $pricing,
        string $funnelStage,
        bool $useInsertOrIgnoreOrders,
    ): void {
        $this->updateActionScalars($action, $pricing, $lines);

        if ($funnelStage === CommerceLineItemParser::STAGE_PAYMENT_SUCCESS) {
            $canonicalEventId = $this->upsertCanonicalOrder($action, $pricing, $lines, $useInsertOrIgnoreOrders);
            if ($canonicalEventId === $action->event_id) {
                $orderId = $pricing['order_id'] ?? null;
                if ($orderId) {
                    DB::table('activity_ecom_commerce_line_items')
                        ->where('order_id', $orderId)
                        ->where('funnel_stage', CommerceLineItemParser::STAGE_PAYMENT_SUCCESS)
                        ->delete();
                    $this->insertLines($lines, false);
                }
            }
            $this->rebuildSessionFlags($action->session_id);

            return;
        }

        if ($funnelStage === CommerceLineItemParser::STAGE_ADD_TO_CART) {
            $this->insertLines($lines, true);
            $this->rebuildSessionFlags($action->session_id);

            return;
        }

        DB::table('activity_ecom_commerce_line_items')->where('event_id', $action->event_id)->delete();
        $this->insertLines($lines, false);
        $this->rebuildSessionFlags($action->session_id);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  list<array<string, mixed>>  $lines
     */
    private function upsertCanonicalOrder(
        ActivityEcomUserAction $action,
        array $pricing,
        array $lines,
        bool $useInsertOrIgnoreOrders,
    ): string {
        $orderId = $pricing['order_id'] ?? null;
        if ($orderId === null || $orderId === '') {
            throw new CommerceParseException('payment_success missing order_id', 'missing:order_id', $action->event_id, $action->action_type);
        }

        $orderedAt = TrackerTime::formatUtc($action->created_at ?? TrackerTime::nowUtc());
        $session = ActivityEcomUser::query()->where('session_id', $action->session_id)->first();
        $checkout = is_array($action->payment_success['checkout_info'] ?? null) ? $action->payment_success['checkout_info'] : [];
        $customer = is_array($checkout['customer'] ?? null) ? $checkout['customer'] : [];

        $row = [
            'order_id' => $orderId,
            'order_pk' => trim((string) ($checkout['order_pk'] ?? '')) ?: null,
            'event_id' => $action->event_id,
            'session_id' => $action->session_id,
            'visitor_id' => $session?->visitor_id,
            'amount_paid' => $pricing['amount_paid'] ?? 0,
            'subtotal' => $pricing['subtotal'],
            'grand_total' => $pricing['grand_total'],
            'currency' => $pricing['currency'],
            'payment_method' => $pricing['payment_method'],
            'item_qty' => (int) array_sum(array_map(fn ($l) => (float) ($l['qty'] ?? 0), $lines)),
            'shipping_charge' => $pricing['shipping_charge'],
            'service_charge' => $pricing['service_charge'],
            'extra_charges_total' => $pricing['extra_charges_total'],
            'discount_amount' => $pricing['discount_amount'],
            'coupon_discount' => $pricing['coupon_discount'],
            'scs_discount' => $pricing['scs_discount'],
            'sms_discount' => $pricing['sms_discount'],
            'discount_type' => $pricing['discount_type'],
            'coupon_code' => $pricing['coupon_code'],
            'customer_email' => trim((string) ($customer['email'] ?? '')) ?: null,
            'customer_phone' => trim((string) ($customer['phone'] ?? $customer['mobile'] ?? '')) ?: null,
            'ordered_at' => $orderedAt,
            'updated_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
        ];

        if ($useInsertOrIgnoreOrders) {
            $insertRow = array_merge($row, ['created_at' => TrackerTime::formatUtc(TrackerTime::nowUtc())]);
            DB::table('activity_ecom_orders')->insertOrIgnore($insertRow);
            DB::table('activity_ecom_orders')
                ->where('order_id', $orderId)
                ->where('ordered_at', '>', $orderedAt)
                ->update(array_diff_key($row, array_flip(['order_id', 'created_at'])));
        } else {
            try {
                DB::table('activity_ecom_orders')->insert(array_merge($row, [
                    'created_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
                ]));
            } catch (QueryException $e) {
                if (! CommerceSqlHelper::isDuplicateKeyException($e, 'uq_orders_order_id')) {
                    throw $e;
                }

                DB::table('activity_ecom_orders')
                    ->where('order_id', $orderId)
                    ->where('ordered_at', '>', $orderedAt)
                    ->update(array_diff_key($row, array_flip(['order_id', 'created_at'])));
            }
        }

        $canonical = DB::table('activity_ecom_orders')->where('order_id', $orderId)->first(['event_id']);

        EcomTrackerLogger::frontend()->info('commerce.order.upsert', 'Canonical order upserted', [
            'order_id' => $orderId,
            'event_id' => $canonical?->event_id,
        ]);

        return (string) ($canonical?->event_id ?? $action->event_id);
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     */
    private function insertLines(array $lines, bool $ignoreDuplicates): void
    {
        if ($lines === []) {
            return;
        }

        $rows = array_map(function (array $line) {
            if (isset($line['product_snapshot_json']) && is_string($line['product_snapshot_json'])) {
                return $line;
            }

            return $line;
        }, $lines);

        if ($ignoreDuplicates) {
            DB::table('activity_ecom_commerce_line_items')->insertOrIgnore($rows);
        } else {
            DB::table('activity_ecom_commerce_line_items')->insert($rows);
        }

        EcomTrackerLogger::frontend()->info('commerce.lines.written', 'Commerce lines written', [
            'count' => count($rows),
            'event_id' => $rows[0]['event_id'] ?? null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $pricing
     * @param  list<array<string, mixed>>  $lines
     */
    private function updateActionScalars(ActivityEcomUserAction $action, array $pricing, array $lines): void
    {
        $itemQty = $action->action_type === 'payment_success'
            ? (int) array_sum(array_map(fn ($l) => (float) ($l['qty'] ?? 0), $lines))
            : null;

        ActivityEcomUserAction::query()->where('event_id', $action->event_id)->update([
            'commerce_total' => $pricing['commerce_total'],
            'commerce_subtotal' => $pricing['commerce_subtotal'],
            'commerce_shipping' => $pricing['commerce_shipping'],
            'commerce_discount' => $pricing['commerce_discount'],
            'coupon_code' => $pricing['coupon_code'],
            'discount_type' => $pricing['discount_type'],
            'line_count' => count($lines),
            'order_id' => $pricing['order_id'],
            'amount_paid' => $pricing['amount_paid'],
            'item_qty' => $itemQty,
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function payloadForAction(ActivityEcomUserAction $action): ?array
    {
        $payload = match ($action->action_type) {
            'add_to_cart' => $action->add_to_cart,
            'begin_checkout' => $action->begin_checkout,
            'proceed_checkout' => $action->proceed_to_checkout,
            'payment_success' => $action->payment_success,
            default => null,
        };

        return is_array($payload) ? $payload : null;
    }

    private function logParseSkip(ActivityEcomUserAction $action, CommerceParseException $e): void
    {
        EcomTrackerLogger::frontend()->warning('commerce.sync.skipped', 'Commerce parse skipped in batch', [
            'action_id' => $action->id,
            'event_id' => $action->event_id,
            'action_type' => $action->action_type,
            'payload_signature' => $e->payloadSignature,
            'message' => $e->getMessage(),
        ]);
    }
}
