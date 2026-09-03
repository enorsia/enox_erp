<?php

namespace App\Support;

use App\Exceptions\CommerceParseException;
use App\Models\ActivityEcomUserAction;

final class CommerceLineItemParser
{
    public const STAGE_ADD_TO_CART = 'add_to_cart';

    public const STAGE_BEGIN_CHECKOUT = 'begin_checkout';

    public const STAGE_PROCEED_CHECKOUT = 'proceed_checkout';

    public const STAGE_PAYMENT_SUCCESS = 'payment_success';

    public const STAGE_CATEGORY_VIEW = 'category_view';

    public const STAGE_PRODUCT_VIEW = 'product_view';

    public const STAGE_PRODUCT_VIEW_POPUP = 'product_view_popup';

    /**
     * @return list<string>
     */
    public static function catalogInterestStages(): array
    {
        return [
            self::STAGE_CATEGORY_VIEW,
            self::STAGE_PRODUCT_VIEW,
            self::STAGE_PRODUCT_VIEW_POPUP,
        ];
    }

    public static function isCatalogInterestStage(string $stage): bool
    {
        return in_array($stage, self::catalogInterestStages(), true);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function parseFromAction(ActivityEcomUserAction $action): array
    {
        $stage = self::funnelStage($action->action_type);

        if (self::isCatalogInterestStage($stage)) {
            $action->loadMissing('session');

            return self::parseCatalogInterestLines($action, $stage);
        }

        $payload = self::payloadForAction($action);

        if ($payload === null) {
            throw new CommerceParseException(
                'Missing commerce payload for '.$action->action_type,
                'missing:'.$action->action_type,
                $action->event_id,
                $action->action_type,
            );
        }

        $action->loadMissing('session');

        return self::parseLines($stage, $payload, $action);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return list<array<string, mixed>>
     */
    public static function parseLines(string $funnelStage, array $payload, ActivityEcomUserAction $action): array
    {
        $items = self::normalizeItems($payload, $funnelStage);

        if ($items === [] && $funnelStage !== self::STAGE_ADD_TO_CART) {
            return [];
        }

        $currency = trim((string) ($payload['currency'] ?? '')) ?: null;
        $orderId = $funnelStage === self::STAGE_PAYMENT_SUCCESS
            ? CommercePricingExtractor::orderId($payload)
            : null;

        $lines = [];
        $lineNo = 1;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $parsed = self::mapLine($item, $lineNo, $funnelStage, $action, $currency, $orderId);
            if ($parsed !== null) {
                $lines[] = $parsed;
                $lineNo++;
            }
        }

        if ($lines === [] && $funnelStage === self::STAGE_ADD_TO_CART) {
            $single = self::singleLineFromCartPayload($payload, $action, $currency);
            if ($single !== null) {
                $lines[] = $single;
            }
        }

        return $lines;
    }

    public static function funnelStage(string $actionType): string
    {
        return match ($actionType) {
            'add_to_cart' => self::STAGE_ADD_TO_CART,
            'begin_checkout' => self::STAGE_BEGIN_CHECKOUT,
            'proceed_checkout' => self::STAGE_PROCEED_CHECKOUT,
            'payment_success' => self::STAGE_PAYMENT_SUCCESS,
            'category_view' => self::STAGE_CATEGORY_VIEW,
            'product_view' => self::STAGE_PRODUCT_VIEW,
            'product_view_popup' => self::STAGE_PRODUCT_VIEW_POPUP,
            default => throw new CommerceParseException(
                'Unsupported commerce action type: '.$actionType,
                'unsupported:'.$actionType,
                null,
                $actionType,
            ),
        };
    }

    /**
     * @return array<string, mixed>|null
     */
    private static function payloadForAction(ActivityEcomUserAction $action): ?array
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

    /**
     * @param  array<string, mixed>  $payload
     * @return list<mixed>
     */
    private static function normalizeItems(array $payload, string $funnelStage): array
    {
        if ($funnelStage === self::STAGE_PAYMENT_SUCCESS) {
            $checkout = is_array($payload['checkout_info'] ?? null) ? $payload['checkout_info'] : [];
            $items = $checkout['items'] ?? [];
        } else {
            $items = $payload['items'] ?? $payload['cart_items'] ?? [];
        }

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, mixed>|null
     */
    private static function mapLine(
        array $item,
        int $lineNo,
        string $funnelStage,
        ActivityEcomUserAction $action,
        ?string $currency,
        ?string $orderId,
    ): ?array {
        $productCode = trim((string) ($item['product_code'] ?? $item['sku'] ?? ''));
        $productName = trim((string) ($item['product_name'] ?? $item['name'] ?? ''));
        $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 1);
        $unitPrice = self::money($item['unit_price'] ?? $item['price'] ?? null);
        $lineTotal = self::money($item['line_total'] ?? null);
        if ($lineTotal <= 0 && $unitPrice > 0) {
            $lineTotal = round($unitPrice * max($qty, 1), 2);
        }

        if ($productCode === '' && $productName === '' && $qty <= 0) {
            return null;
        }

        $snapshot = array_filter([
            'image_url' => $item['image_url'] ?? $item['product_image'] ?? null,
            'variant_id' => $item['variant_id'] ?? null,
            'options' => $item['options'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return [
            'event_id' => $action->event_id,
            'session_id' => $action->session_id,
            'visitor_id' => $action->session?->visitor_id,
            'funnel_stage' => $funnelStage,
            'order_id' => $orderId,
            'line_no' => $lineNo,
            'product_id' => isset($item['product_id']) ? (int) $item['product_id'] : null,
            'product_code' => $productCode !== '' ? self::limit('product_code', $productCode) : ($action->product_code ?: null),
            'sku' => trim((string) ($item['sku'] ?? '')) ?: ($action->sku ?: null),
            'product_name' => $productName !== '' ? self::limit('product_name', $productName) : ($action->product_name ?: null),
            'department_name' => self::limit('department_name', (string) ($item['department_name'] ?? $action->department_name ?? '')),
            'category_name' => self::limit('category_name', (string) ($item['category_name'] ?? $action->category_name ?? '')),
            'category_code' => self::limit('category_code', (string) ($item['category_code'] ?? $action->category_code ?? '')),
            'color_name' => self::limit('general_color_name', (string) ($item['color_name'] ?? $item['general_color_name'] ?? $action->general_color_name ?? '')),
            'size_name' => self::limit('product_name', (string) ($item['size_name'] ?? '')),
            'qty' => max($qty, 0),
            'unit_price' => $unitPrice > 0 ? $unitPrice : null,
            'line_total' => $lineTotal > 0 ? $lineTotal : null,
            'currency' => $currency,
            'product_snapshot_json' => $snapshot !== [] ? json_encode($snapshot) : null,
            'staged_at' => TrackerTime::formatUtc($action->created_at ?? TrackerTime::nowUtc()),
            'created_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    /**
     * @return list<array<string, mixed>>
     */
    private static function parseCatalogInterestLines(ActivityEcomUserAction $action, string $stage): array
    {
        $department = trim((string) ($action->department_name ?? ''));
        $category = trim((string) ($action->category_name ?? ''));

        if ($department === '' && $category === '') {
            throw new CommerceParseException(
                'Catalog interest action missing department and category',
                'missing:catalog_interest_identity',
                $action->event_id,
                $action->action_type,
            );
        }

        $productCode = trim((string) ($action->product_code ?? ''));
        $productName = trim((string) ($action->product_name ?? ''));

        if ($stage !== self::STAGE_CATEGORY_VIEW && $productCode === '' && $productName === '') {
            throw new CommerceParseException(
                'Product view action missing product identity',
                'missing:catalog_interest_product',
                $action->event_id,
                $action->action_type,
            );
        }

        return [[
            'event_id' => $action->event_id,
            'session_id' => $action->session_id,
            'visitor_id' => $action->session?->visitor_id,
            'funnel_stage' => $stage,
            'order_id' => null,
            'line_no' => 1,
            'product_id' => null,
            'product_code' => $productCode !== '' ? self::limit('product_code', $productCode) : null,
            'sku' => $action->sku ?: null,
            'product_name' => $productName !== '' ? self::limit('product_name', $productName) : null,
            'department_name' => self::limit('department_name', $department),
            'category_name' => self::limit('category_name', $category),
            'category_code' => self::limit('category_code', (string) ($action->category_code ?? '')),
            'color_name' => self::limit('general_color_name', (string) ($action->general_color_name ?? '')),
            'size_name' => null,
            'qty' => 1,
            'unit_price' => $action->product_price ? (float) $action->product_price : null,
            'line_total' => null,
            'currency' => null,
            'product_snapshot_json' => null,
            'staged_at' => TrackerTime::formatUtc($action->created_at ?? TrackerTime::nowUtc()),
            'created_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
        ]];
    }

    private static function singleLineFromCartPayload(array $payload, ActivityEcomUserAction $action, ?string $currency): ?array
    {
        if (! filled($action->product_code) && ! filled($action->product_name)) {
            return null;
        }

        return [
            'event_id' => $action->event_id,
            'session_id' => $action->session_id,
            'visitor_id' => $action->session?->visitor_id,
            'funnel_stage' => self::STAGE_ADD_TO_CART,
            'order_id' => null,
            'line_no' => 1,
            'product_id' => null,
            'product_code' => $action->product_code,
            'sku' => $action->sku,
            'product_name' => $action->product_name,
            'department_name' => $action->department_name,
            'category_name' => $action->category_name,
            'category_code' => $action->category_code,
            'color_name' => $action->general_color_name,
            'size_name' => null,
            'qty' => max((float) ($payload['qty'] ?? 1), 1),
            'unit_price' => $action->product_price ? (float) $action->product_price : null,
            'line_total' => self::money($payload['cart_total'] ?? $action->product_price),
            'currency' => $currency,
            'product_snapshot_json' => null,
            'staged_at' => TrackerTime::formatUtc($action->created_at ?? TrackerTime::nowUtc()),
            'created_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
        ];
    }

    private static function money(mixed $value): float
    {
        if (! is_numeric($value)) {
            return 0.0;
        }

        return round((float) $value, 2);
    }

    private static function limit(string $field, string $value): ?string
    {
        $text = trim($value);
        if ($text === '') {
            return null;
        }

        $limit = config("tracker.scalar_field_limits.{$field}");
        if (is_int($limit) && $limit > 0 && mb_strlen($text) > $limit) {
            return mb_substr($text, 0, $limit);
        }

        return $text;
    }
}
