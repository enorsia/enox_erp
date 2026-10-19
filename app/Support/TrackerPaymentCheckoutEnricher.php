<?php

namespace App\Support;

use App\Models\ActivityEcomUserAction;
use Illuminate\Support\Collection;

class TrackerPaymentCheckoutEnricher
{
    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrichPayloadForAction(ActivityEcomUserAction $paymentAction): array
    {
        $payload = is_array($paymentAction->payment_success) ? $paymentAction->payment_success : [];

        return $this->enrichPayload(
            (string) $paymentAction->session_id,
            $payload,
            TrackerTime::formatUtc($paymentAction->created_at),
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function enrichPayload(string $sessionId, array $payload, ?string $beforeAt = null): array
    {
        $items = $payload['checkout_info']['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return $payload;
        }

        $sessionActions = $this->sessionActions($sessionId, $beforeAt);
        $enrichedItems = [];
        $orderId = trim((string) ($payload['order_id'] ?? $payload['checkout_info']['order_number'] ?? ''));

        foreach ($items as $item) {
            if (! is_array($item)) {
                $enrichedItems[] = $item;

                continue;
            }

            $enriched = $this->enrichLineItem($item, $sessionActions);
            $this->logMissingLineMeta($sessionId, $enriched, $orderId);
            $enrichedItems[] = $enriched;
        }

        $payload['checkout_info'] ??= [];
        $payload['checkout_info']['items'] = $enrichedItems;

        return $this->mergeCheckoutTotalsFromSession($payload, $sessionActions);
    }

    /**
     * Storefront payment_success often ships net totals only; copy discount breakdown from the last checkout step.
     *
     * @param  array<string, mixed>  $payload
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     * @return array<string, mixed>
     */
    private function mergeCheckoutTotalsFromSession(array $payload, Collection $sessionActions): array
    {
        $checkoutAction = $sessionActions
            ->filter(fn (ActivityEcomUserAction $action) => in_array($action->action_type, ['proceed_checkout', 'begin_checkout'], true))
            ->sortByDesc(fn (ActivityEcomUserAction $action) => sprintf('%s|%010d', TrackerTime::formatUtc($action->created_at) ?? '', (int) $action->id))
            ->first();

        if ($checkoutAction === null) {
            return $payload;
        }

        $checkoutPayload = match ($checkoutAction->action_type) {
            'proceed_checkout' => is_array($checkoutAction->proceed_to_checkout) ? $checkoutAction->proceed_to_checkout : [],
            default => is_array($checkoutAction->begin_checkout) ? $checkoutAction->begin_checkout : [],
        };

        if ($checkoutPayload === []) {
            return $payload;
        }

        $sourceTotals = CheckoutPayloadTotals::totals($checkoutPayload);
        $sourceDiscount = $this->discountTotalFromTotals($sourceTotals);

        if ($sourceDiscount <= 0) {
            return $payload;
        }

        $payload['checkout_info'] ??= [];
        $paymentTotals = CheckoutPayloadTotals::totals($payload);

        if ($this->discountTotalFromTotals($paymentTotals) > 0) {
            return $payload;
        }

        $mergedTotals = array_merge($paymentTotals, array_filter([
            'subtotal' => $sourceTotals['subtotal'] ?? null,
            'coupon_discount' => $sourceTotals['coupon_discount'] ?? null,
            'scs_discount' => $sourceTotals['scs_discount'] ?? null,
            'sms_discount' => $sourceTotals['sms_discount'] ?? null,
            'service_charge' => $paymentTotals['service_charge'] ?? $sourceTotals['service_charge'] ?? null,
            'delivery_charge' => $paymentTotals['delivery_charge'] ?? $sourceTotals['delivery_charge'] ?? null,
            'shipping_cost' => $paymentTotals['shipping_cost'] ?? $sourceTotals['shipping_cost'] ?? null,
            'extra_charges_total' => $paymentTotals['extra_charges_total'] ?? $sourceTotals['extra_charges_total'] ?? null,
            'grand_total' => $paymentTotals['grand_total'] ?? $sourceTotals['grand_total'] ?? CheckoutPayloadTotals::grandTotal($payload),
        ], fn (mixed $value) => $value !== null && $value !== ''));

        $payload['checkout_info']['totals'] = $mergedTotals;

        $couponCode = trim((string) ($payload['checkout_info']['coupon_code'] ?? $payload['coupon_code'] ?? ''));
        if ($couponCode === '') {
            $fromCheckout = trim((string) ($checkoutPayload['coupon_code'] ?? ''));
            if ($fromCheckout !== '') {
                $payload['checkout_info']['coupon_code'] = $fromCheckout;
            }
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $totals
     */
    private function discountTotalFromTotals(array $totals): float
    {
        return round(
            (float) ($totals['coupon_discount'] ?? 0)
            + (float) ($totals['scs_discount'] ?? 0)
            + (float) ($totals['sms_discount'] ?? 0),
            2,
        );
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     * @return array<string, mixed>
     */
    public function enrichLineItem(array $line, Collection $sessionActions): array
    {
        if (TrackerCategoryIdentity::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $line = $this->mergeCategoryFields($line, $this->metaFromProductActions($line, $sessionActions));

        if (! TrackerCategoryIdentity::lineHasCategoryIdentity($line)) {
            $line = $this->mergeCategoryFields($line, $this->metaFromCartLines($line, $sessionActions));
        }

        if (trim((string) ($line['department_name'] ?? '')) === '') {
            $departmentName = $this->departmentFromProductActions($line, $sessionActions);

            if ($departmentName !== '') {
                $line['department_name'] = $departmentName;
            }
        }

        return $line;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return list<string>
     */
    public function missingMetaFields(array $line): array
    {
        $missing = [];

        if (trim((string) ($line['category_name'] ?? '')) === '') {
            $missing[] = 'category_name';
        }

        if (trim((string) ($line['department_name'] ?? '')) === '') {
            $missing[] = 'department_name';
        }

        return $missing;
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function logMissingLineMeta(string $sessionId, array $line, string $orderId = ''): void
    {
        $missing = $this->missingMetaFields($line);

        if ($missing === []) {
            return;
        }

        EcomTrackerLogger::frontend()->warning('ingest.payment_line_missing_category_meta', 'Payment checkout line still missing category metadata after session enrichment', [
            'session_id' => $sessionId,
            'order_id' => $orderId,
            'product_id' => trim((string) ($line['product_id'] ?? '')),
            'product_code' => trim((string) ($line['product_code'] ?? '')),
            'sku' => trim((string) ($line['sku'] ?? '')),
            'product_name' => trim((string) ($line['product_name'] ?? '')),
            'missing' => $missing,
        ]);
    }

    /**
     * @return Collection<int, ActivityEcomUserAction>
     */
    private function sessionActions(string $sessionId, ?string $beforeAt): Collection
    {
        $query = ActivityEcomUserAction::query()
            ->where('session_id', $sessionId)
            ->where('action_type', '!=', 'payment_success')
            ->orderBy('created_at')
            ->orderBy('id');

        if ($beforeAt !== null && $beforeAt !== '') {
            $query->where('created_at', '<=', $beforeAt);
        }

        return $query->get();
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     * @return array<string, string>
     */
    private function metaFromProductActions(array $line, Collection $sessionActions): array
    {
        foreach ($sessionActions as $action) {
            if (! in_array($action->action_type, ['product_view', 'product_view_popup', 'add_to_cart'], true)) {
                continue;
            }

            if (! $this->lineMatchesActionProduct($line, $action)) {
                continue;
            }

            $meta = $this->metaFieldsFromContext([
                'category_name' => (string) ($action->category_name ?? ''),
                'category_code' => (string) ($action->category_code ?? ''),
                'category_id' => (string) ($action->category_id ?? ''),
                'department_name' => TrackerCategoryIdentity::resolveDepartmentName([
                    'department_name' => (string) ($action->department_name ?? ''),
                    'page_url' => (string) ($action->page_url ?? ''),
                ]),
            ]);

            if ($meta !== []) {
                return $meta;
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     * @return array<string, string>
     */
    private function metaFromCartLines(array $line, Collection $sessionActions): array
    {
        foreach ($sessionActions as $action) {
            if (! in_array($action->action_type, ['add_to_cart', 'proceed_checkout'], true)) {
                continue;
            }

            foreach ($this->lineItemsFromAction($action) as $candidate) {
                if (! $this->lineMatchesCartLine($line, $candidate)) {
                    continue;
                }

                $meta = $this->metaFieldsFromContext($this->normalizedCartLine($candidate));

                if ($meta !== []) {
                    return $meta;
                }
            }
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  Collection<int, ActivityEcomUserAction>  $sessionActions
     */
    private function departmentFromProductActions(array $line, Collection $sessionActions): string
    {
        $productCode = trim((string) ($line['product_code'] ?? ''));
        $productName = trim((string) ($line['product_name'] ?? ''));

        foreach ($sessionActions as $action) {
            if (! in_array($action->action_type, ['product_view', 'product_view_popup', 'add_to_cart'], true)) {
                continue;
            }

            $actionMatches = ($productCode !== '' && strcasecmp((string) ($action->product_code ?? ''), $productCode) === 0)
                || ($productName !== '' && strcasecmp(trim((string) ($action->product_name ?? '')), $productName) === 0);

            if (! $actionMatches) {
                continue;
            }

            $departmentName = TrackerCategoryIdentity::resolveDepartmentName([
                'department_name' => (string) ($action->department_name ?? ''),
                'page_url' => (string) ($action->page_url ?? ''),
            ]);

            if ($departmentName !== '') {
                return $departmentName;
            }
        }

        return '';
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lineItemsFromAction(ActivityEcomUserAction $action): array
    {
        if ($action->action_type === 'add_to_cart' && is_array($action->add_to_cart)) {
            $items = $action->add_to_cart['items'] ?? $action->add_to_cart['cart_items'] ?? [];
        } elseif ($action->action_type === 'proceed_checkout' && is_array($action->proceed_to_checkout)) {
            $items = $action->proceed_to_checkout['cart_items'] ?? $action->proceed_to_checkout['items'] ?? [];
        } else {
            return [];
        }

        return is_array($items)
            ? array_values(array_filter($items, 'is_array'))
            : [];
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    private function normalizedCartLine(array $line): array
    {
        $options = is_array($line['options'] ?? null) ? $line['options'] : [];

        return array_merge($options, $line);
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $candidate
     */
    private function lineMatchesCartLine(array $line, array $candidate): bool
    {
        $normalized = $this->normalizedCartLine($candidate);

        return $this->lineMatchesProduct(
            $line,
            trim((string) ($normalized['product_id'] ?? '')),
            trim((string) ($normalized['product_code'] ?? '')),
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function lineMatchesActionProduct(array $line, ActivityEcomUserAction $action): bool
    {
        return $this->lineMatchesProduct(
            $line,
            trim((string) ($action->product_id ?? '')),
            trim((string) ($action->product_code ?? '')),
        ) || (
            trim((string) ($line['product_name'] ?? '')) !== ''
            && strcasecmp(trim((string) ($line['product_name'] ?? '')), trim((string) ($action->product_name ?? ''))) === 0
        );
    }

    /**
     * @param  array<string, mixed>  $line
     */
    private function lineMatchesProduct(array $line, string $productId, string $productCode): bool
    {
        $lineProductId = trim((string) ($line['product_id'] ?? ''));
        $lineProductCode = trim((string) ($line['product_code'] ?? ''));

        if ($productId !== '' && $lineProductId !== '' && $productId === $lineProductId) {
            return true;
        }

        return $productCode !== '' && $lineProductCode !== '' && strcasecmp($productCode, $lineProductCode) === 0;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, string>
     */
    private function metaFieldsFromContext(array $context): array
    {
        $meta = TrackerCategoryIdentity::metaFromLine($context);

        if ($meta === null) {
            return [];
        }

        return array_filter([
            'department_name' => $meta['department_name'],
            'category_name' => $meta['category_name'],
            'category_code' => $meta['category_code'],
            'category_id' => $meta['category_id'],
        ], fn (string $value) => $value !== '');
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, string>  $meta
     * @return array<string, mixed>
     */
    private function mergeCategoryFields(array $line, array $meta): array
    {
        foreach (['department_name', 'category_name', 'category_code', 'category_id'] as $field) {
            if (($line[$field] ?? '') === '' && ($meta[$field] ?? '') !== '') {
                $line[$field] = $meta[$field];
            }
        }

        return $line;
    }
}
