<?php

namespace App\Services;

use App\Support\CheckoutPayloadTotals;
use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\EcomTrackerLogger;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerCategoryIdentity;
use App\Support\TrackerPaymentCheckoutEnricher;
use App\Support\TrackerRedisSupport;
use App\Support\TrackerTime;
use App\Support\UserAgentParser;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackIngestService
{
    public function __construct(
        private VisitorSessionResolver $visitorSessionResolver,
        private TrackerClientContextResolver $clientContextResolver,
        private BotContextPersister $botContextPersister,
        private TrackerPaymentCheckoutEnricher $paymentCheckoutEnricher,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function ingest(Request $request, array $payload): array
    {
        $sessionData = $payload['session'] ?? [];
        $events = $payload['events'] ?? [];

        if (empty($events)) {
            return [];
        }

        $sessionId = $sessionData['session_id'] ?? $events[0]['session_id'] ?? null;
        $visitorId = $sessionData['visitor_id'] ?? null;

        if (! $sessionId) {
            $this->logError('ingest.missing_session', 'No session ID in request');

            throw ValidationException::withMessages([
                'session.session_id' => ['Session ID is required.'],
            ]);
        }

        TrackerRedisSupport::logFrontendHealth('track_ingest');

        $clientContext = $this->clientContextResolver->resolve($request);

        if (! empty($visitorId)) {
            $resolved = $this->visitorSessionResolver->resolveForIngest(
                $visitorId,
                $sessionId,
                $this->buildSessionContext($request, $clientContext),
            );

            $sessionId = $resolved['session_id'];
            $sessionData['visitor_id'] = $visitorId;
            $sessionData['session_id'] = $sessionId;
        }

        $this->logInfo('ingest.start', 'Saving user actions started', [
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'event_count' => count($events),
        ]);

        $this->upsertSession($request, $sessionId, $sessionData, $clientContext);

        $acceptedIds = [];

        foreach ($events as $event) {
            $event['session_id'] = $sessionId;
            $eventId = $event['id'] ?? null;

            if (! $eventId) {
                $this->logWarning('ingest.skip_event', 'Skipped one action (no ID)', [
                    'session_id' => $sessionId,
                    'action_type' => $event['action_type'] ?? null,
                ]);

                continue;
            }

            $this->validatePaymentSuccessPayload($event);

            if (! $this->hasMeaningfulCheckoutPayload($event)) {
                $acceptedIds[] = $eventId;

                $this->logWarning('ingest.skip_empty_checkout', 'Skipped checkout action without cart data', [
                    'session_id' => $sessionId,
                    'event_id' => $eventId,
                    'action_type' => $event['action_type'] ?? null,
                    'page_url' => $event['page_url'] ?? null,
                ]);

                continue;
            }

            if ($this->isDuplicatePaymentSuccess($event)) {
                $this->syncSessionUserFromPaymentSuccess($sessionId, $event);

                $acceptedIds[] = $eventId;

                $this->logWarning('ingest.skip_duplicate_payment', 'Skipped duplicate payment_success for order', [
                    'session_id' => $sessionId,
                    'event_id' => $eventId,
                    'order_id' => $this->paymentSuccessOrderId($event),
                ]);

                continue;
            }

            $row = $this->mapEventToRow($sessionId, $event);

            ActivityEcomUserAction::query()->updateOrInsert(
                ['event_id' => $eventId],
                $row
            );

            $this->backfillSessionAttribution(
                $sessionId,
                $event['page_url'] ?? null,
                $event['referer'] ?? null,
            );

            if (($event['action_type'] ?? '') === 'proceed_checkout') {
                $this->syncSessionUserFromProceedCheckout($sessionId, $event);
            }

            if (($event['action_type'] ?? '') === 'payment_success') {
                $this->syncSessionUserFromPaymentSuccess($sessionId, $event);
            }

            $acceptedIds[] = $eventId;

            $this->logInfo('ingest.event_stored', 'One action saved', [
                'session_id' => $sessionId,
                'event_id' => $eventId,
                'action_type' => $event['action_type'] ?? null,
                'page_url' => $event['page_url'] ?? null,
                'category_name' => $event['category_name'] ?? null,
                'department_name' => $event['department_name'] ?? null,
            ]);
        }

        $this->syncSessionLastActiveFromEvents($sessionId, $events);

        $this->logInfo('ingest.complete', 'All actions saved', [
            'session_id' => $sessionId,
            'accepted_count' => count($acceptedIds),
            'redis_bypass' => TrackerRedisSupport::usesMemoryBypass(),
            'redis_working' => TrackerRedisSupport::ping(),
        ]);

        return $acceptedIds;
    }

    /**
     * @param  array<string, mixed>  $sessionData
     * @param  array<string, mixed>|null  $clientContext
     */
    private function upsertSession(Request $request, string $sessionId, array $sessionData, ?array $clientContext = null): void
    {
        $userAgent = $clientContext['user_agent'] ?? $request->userAgent();
        $parsed = UserAgentParser::parse($userAgent);
        $now = TrackerTime::formatUtc(TrackerTime::nowUtc());

        $existing = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        $attributes = [
            'ip' => $clientContext['client_ip'] ?? $request->ip(),
            'user_agent' => $userAgent,
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
        ];

        if ($clientContext !== null && ! empty($clientContext['ip_country'])) {
            $attributes['country'] = $clientContext['ip_country'];
        }

        $attributes = array_merge($attributes, $this->sessionIdentityUpdates($sessionData, $existing));

        if (! empty($sessionData['visitor_id'])) {
            $attributes['visitor_id'] = $sessionData['visitor_id'];
        }

        if (! $existing) {
            $ingestAttribution = SessionTrafficAttribution::sessionAttributesFromIngest($sessionData);

            $attributes['session_id'] = $sessionId;
            $attributes['utm_source'] = $ingestAttribution['utm_source'] ?? $sessionData['utm_source'] ?? null;
            $attributes['utm_medium'] = $ingestAttribution['utm_medium'] ?? $sessionData['utm_medium'] ?? null;
            $attributes['utm_campaign'] = $ingestAttribution['utm_campaign'] ?? $sessionData['utm_campaign'] ?? null;
            $attributes['landing_page'] = $ingestAttribution['landing_page'] ?? $sessionData['landing_page'] ?? null;
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
            $attributes['session_duration_seconds'] = 0;

            $session = ActivityEcomUser::query()->create($attributes);

            $this->logInfo('ingest.session_created', 'New visitor session created', [
                'session_id' => $sessionId,
                'user_id' => $attributes['user_id'] ?? null,
                'is_logged_in' => $attributes['is_logged_in'] ?? false,
            ]);

            $this->persistBotContextIfNeeded($sessionId, $clientContext);
            $this->mergeAttributionIntoSession($session, $sessionData);

            return;
        }

        $this->mergeAttributionIntoSession($existing, $sessionData);

        $attributes['session_duration_seconds'] = $this->sessionDurationSeconds($existing);
        $attributes['updated_at'] = $now;
        $existing->update($attributes);

        $this->logInfo('ingest.session_updated', 'Visitor session updated', [
            'session_id' => $sessionId,
            'user_id' => $attributes['user_id'] ?? $existing->user_id,
            'is_logged_in' => $attributes['is_logged_in'] ?? $existing->is_logged_in,
        ]);

        $this->persistBotContextIfNeeded($sessionId, $clientContext);
    }

    /**
     * @param  array<string, mixed>  $sessionData
     */
    private function mergeAttributionIntoSession(
        ActivityEcomUser $session,
        array $sessionData,
        ?string $pageUrl = null,
        ?string $referer = null,
    ): void {
        $attribution = SessionTrafficAttribution::sessionAttributesFromIngest($sessionData, $pageUrl, $referer);

        $updates = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'landing_page'] as $field) {
            if (filled($session->{$field}) || empty($attribution[$field] ?? null)) {
                continue;
            }

            $updates[$field] = $attribution[$field];
        }

        if ($updates !== []) {
            $session->update($updates);
        }
    }

    private function backfillSessionAttribution(string $sessionId, ?string $pageUrl, ?string $referer = null): void
    {
        if (! filled($pageUrl) && ! filled($referer)) {
            return;
        }

        $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        if ($session === null) {
            return;
        }

        SessionTrafficAttribution::backfillSession($session, $pageUrl, $referer);
    }

    /**
     * @param  array<string, mixed>|null  $clientContext
     * @return array<string, mixed>
     */
    private function buildSessionContext(Request $request, ?array $clientContext = null): array
    {
        return [
            'ip' => $clientContext['client_ip'] ?? $request->ip(),
            'user_agent' => $clientContext['user_agent'] ?? $request->userAgent(),
            'country' => $clientContext['ip_country'] ?? null,
            'bot_resolved' => $clientContext,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $clientContext
     */
    private function persistBotContextIfNeeded(string $sessionId, ?array $clientContext): void
    {
        if ($clientContext === null) {
            return;
        }

        try {
            $this->botContextPersister->persistIfAbsent($sessionId, $clientContext);
        } catch (Throwable $e) {
            EcomTrackerLogger::frontend()->warning('ingest.bot_context_failed', 'Could not save bot info when saving action', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapEventToRow(string $sessionId, array $event): array
    {
        $actionType = $event['action_type'];

        $row = [
            'session_id' => $sessionId,
            'action_type' => $actionType,
            'page_url' => $event['page_url'] ?? null,
            'referer' => $event['referer'] ?? null,
            'created_at' => TrackerTime::formatUtc($event['created_at'] ?? TrackerTime::nowUtc()),
        ];

        $scalarFields = [
            'category_name',
            'category_code',
            'department_name',
            'product_name',
            'product_code',
            'sku',
            'product_color_id',
            'product_color_code',
            'general_color_name',
            'product_price',
            'start_time',
            'end_time',
        ];

        foreach ($scalarFields as $field) {
            if (! array_key_exists($field, $event) || $event[$field] === null || $event[$field] === '') {
                continue;
            }

            $value = in_array($field, ['start_time', 'end_time'], true)
                ? TrackerTime::formatUtc($event[$field])
                : $this->normalizeScalarField($field, $event[$field]);

            if ($value === null || $value === '') {
                continue;
            }

            $row[$field] = $value;
        }

        if ($actionType === 'add_to_cart' && isset($event['add_to_cart'])) {
            $row['add_to_cart'] = json_encode($event['add_to_cart']);
            $row = $this->enrichAddToCartScalars($row, $event['add_to_cart']);
        }

        if ($actionType === 'begin_checkout' && isset($event['begin_checkout'])) {
            $row['begin_checkout'] = json_encode($event['begin_checkout']);
        }

        if ($actionType === 'proceed_checkout' && isset($event['proceed_to_checkout'])) {
            $row['proceed_to_checkout'] = json_encode($event['proceed_to_checkout']);
        }

        if ($actionType === 'payment_success' && isset($event['payment_success'])) {
            $payload = is_array($event['payment_success']) ? $event['payment_success'] : [];
            $payload = $this->paymentCheckoutEnricher->enrichPayload(
                $sessionId,
                $payload,
                TrackerTime::formatUtc($event['created_at'] ?? TrackerTime::nowUtc()),
            );
            $row['payment_success'] = json_encode($payload);
        }

        if (($row['department_name'] ?? '') === '' && ($actionType === 'category_view' || ! empty($row['category_name']))) {
            $departmentName = TrackerCategoryIdentity::departmentNameFromPageUrl((string) ($event['page_url'] ?? ''));

            if ($departmentName !== '') {
                $row['department_name'] = $departmentName;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     * @param  array<string, mixed>  $cart
     * @return array<string, mixed>
     */
    private function enrichAddToCartScalars(array $row, array $cart): array
    {
        $items = $cart['items'] ?? [];
        $line = is_array($items[0] ?? null) ? $items[0] : [];

        foreach (['category_name', 'category_code', 'department_name', 'product_name', 'product_code', 'sku'] as $field) {
            if (! empty($row[$field])) {
                continue;
            }

            $value = $cart[$field] ?? $line[$field] ?? null;
            $normalized = $this->normalizeScalarField($field, $value);

            if ($normalized !== null && $normalized !== '') {
                $row[$field] = $normalized;
            }
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $sessionData
     * @return array<string, mixed>
     */
    private function sessionIdentityUpdates(array $sessionData, ?ActivityEcomUser $existing = null): array
    {
        $updates = [];

        if (! empty($sessionData['user_id'])) {
            $updates['user_id'] = $sessionData['user_id'];
            $updates['is_logged_in'] = true;
        } elseif (array_key_exists('is_logged_in', $sessionData) && ($existing === null || ! $existing->isRegisteredUser())) {
            $updates['is_logged_in'] = ! empty($sessionData['user_id']) && (bool) $sessionData['is_logged_in'];
        }

        foreach (['user_name', 'user_email', 'user_phone'] as $field) {
            if (! array_key_exists($field, $sessionData)) {
                continue;
            }

            $value = trim((string) ($sessionData[$field] ?? ''));

            if ($value === '') {
                continue;
            }

            $updates[$field] = $value;
        }

        return $updates;
    }

    /**
     * Guest checkout often has no user on the session until proceed_checkout or payment_success.
     *
     * @param  array<string, mixed>  $event
     */
    private function syncSessionUserFromProceedCheckout(string $sessionId, array $event): void
    {
        $payload = $event['proceed_to_checkout'] ?? [];

        if (! is_array($payload)) {
            return;
        }

        $customer = $payload['customer'] ?? [];

        if (! is_array($customer)) {
            return;
        }

        $this->syncSessionCustomerInfo($sessionId, $customer, $event['created_at'] ?? null);
    }

    /**
     * Guest checkout often has no user on the session until payment_success.
     *
     * @param  array<string, mixed>  $event
     */
    private function syncSessionUserFromPaymentSuccess(string $sessionId, array $event): void
    {
        $payload = $event['payment_success'] ?? [];

        if (! is_array($payload)) {
            return;
        }

        $customer = $payload['checkout_info']['customer'] ?? [];

        if (! is_array($customer)) {
            return;
        }

        $this->syncSessionCustomerInfo($sessionId, $customer, $event['created_at'] ?? null);
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function syncSessionCustomerInfo(string $sessionId, array $customer, mixed $eventCreatedAt = null): void
    {
        $updates = $this->customerFieldsFromPayload($customer);

        if ($updates === []) {
            return;
        }

        $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        if (! $session) {
            return;
        }

        if (! $session->isRegisteredUser()) {
            $updates['is_logged_in'] = false;
            $updates['user_id'] = null;
        }

        $updates['last_active_at'] = TrackerTime::formatUtc($eventCreatedAt ?? TrackerTime::nowUtc());
        $updates['updated_at'] = TrackerTime::formatUtc(TrackerTime::nowUtc());

        $session->update($updates);

        $this->logInfo('ingest.checkout_user_sync', 'Session customer updated from checkout', [
            'session_id' => $sessionId,
            'user_name' => $updates['user_name'] ?? $session->user_name,
            'user_email' => $updates['user_email'] ?? $session->user_email,
            'user_phone' => $updates['user_phone'] ?? $session->user_phone,
            'is_logged_in' => $updates['is_logged_in'] ?? $session->is_logged_in,
        ]);
    }

    /**
     * @param  array<string, mixed>  $customer
     * @return array<string, string>
     */
    private function customerFieldsFromPayload(array $customer): array
    {
        $firstName = trim((string) ($customer['first_name'] ?? $customer['firstName'] ?? ''));
        $lastName = trim((string) ($customer['last_name'] ?? $customer['lastName'] ?? ''));
        $fullName = trim((string) ($customer['full_name'] ?? $customer['fullName'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $phone = $this->extractCustomerPhone($customer);
        $name = trim($fullName !== '' ? $fullName : implode(' ', array_filter([$firstName, $lastName])));

        $updates = [];

        if ($name !== '') {
            $updates['user_name'] = $name;
        }

        if ($email !== '') {
            $updates['user_email'] = $email;
        }

        if ($phone !== null) {
            $updates['user_phone'] = $phone;
        }

        return $updates;
    }

    /**
     * @param  array<string, mixed>  $customer
     */
    private function extractCustomerPhone(array $customer): ?string
    {
        foreach (['phone', 'mobile', 'phone_number'] as $key) {
            $value = trim((string) ($customer[$key] ?? ''));

            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    public function backfillSessionPhonesFromCheckoutActions(int $chunkSize = 100): int
    {
        return $this->backfillSessionCustomerFromCheckoutActions($chunkSize);
    }

    public function backfillSessionCustomerFromCheckoutActions(int $chunkSize = 100): int
    {
        $updated = 0;

        ActivityEcomUser::query()
            ->where(function ($query) {
                $query->whereNull('user_name')->orWhere('user_name', '')
                    ->orWhereNull('user_email')->orWhere('user_email', '')
                    ->orWhereNull('user_phone')->orWhere('user_phone', '');
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($sessions) use (&$updated) {
                foreach ($sessions as $session) {
                    $fields = $this->customerFieldsFromCheckoutActions($session->session_id);

                    if ($fields === []) {
                        continue;
                    }

                    $updates = [];

                    foreach (['user_name', 'user_email', 'user_phone'] as $field) {
                        if (filled($session->{$field}) || empty($fields[$field] ?? null)) {
                            continue;
                        }

                        $updates[$field] = $fields[$field];
                    }

                    if ($updates === []) {
                        continue;
                    }

                    $session->update($updates);
                    $updated++;
                }
            });

        return $updated;
    }

    /**
     * @return array<string, string>
     */
    private function customerFieldsFromCheckoutActions(string $sessionId): array
    {
        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $sessionId)
            ->whereIn('action_type', ['proceed_checkout', 'payment_success'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get();

        $fields = [];

        foreach ($actions as $action) {
            foreach ([
                $action->proceed_to_checkout['customer'] ?? null,
                $action->payment_success['checkout_info']['customer'] ?? null,
            ] as $customer) {
                if (! is_array($customer)) {
                    continue;
                }

                foreach ($this->customerFieldsFromPayload($customer) as $field => $value) {
                    if (! isset($fields[$field]) && filled($value)) {
                        $fields[$field] = $value;
                    }
                }
            }
        }

        return $fields;
    }

    private function phoneFromCheckoutActions(string $sessionId): ?string
    {
        return $this->customerFieldsFromCheckoutActions($sessionId)['user_phone'] ?? null;
    }

    private function sessionDurationSeconds(ActivityEcomUser $session): int
    {
        $createdAt = TrackerTime::toUtc($session->getRawOriginal('created_at'));

        if ($createdAt === null) {
            return 0;
        }

        $lastActiveAt = TrackerTime::toUtc($session->getRawOriginal('last_active_at')) ?? TrackerTime::nowUtc();

        return $this->durationSecondsBetween($createdAt, $lastActiveAt);
    }

    private function durationSecondsBetween(Carbon $from, Carbon $to): int
    {
        if ($to->lessThan($from)) {
            return 0;
        }

        return max(0, (int) $from->diffInSeconds($to, absolute: true));
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    private function syncSessionLastActiveFromEvents(string $sessionId, array $events): void
    {
        $latest = null;

        foreach ($events as $event) {
            $at = TrackerTime::toUtc($event['created_at'] ?? null);

            if ($at === null) {
                continue;
            }

            if ($latest === null || $at->greaterThan($latest)) {
                $latest = $at;
            }
        }

        $ingestedAt = TrackerTime::nowUtc();

        if ($latest === null) {
            $latest = $ingestedAt;
        } elseif ($ingestedAt->greaterThan($latest)) {
            // Client event timestamps can predate session creation (view start time).
            // Admin "last active" should reflect when we last heard from this session.
            $latest = $ingestedAt;
        }

        $session = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        if ($session === null) {
            return;
        }

        $current = TrackerTime::toUtc($session->last_active_at);
        $updates = [
            'updated_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
        ];

        $createdAt = TrackerTime::toUtc($session->getRawOriginal('created_at'));

        if ($createdAt !== null && $latest->lessThan($createdAt)) {
            $latest = $createdAt->copy();
        }

        if ($current === null || $latest->greaterThan($current)) {
            $updates['last_active_at'] = TrackerTime::formatUtc($latest);
        }

        $effectiveLastActive = TrackerTime::toUtc($updates['last_active_at'] ?? $session->last_active_at) ?? $latest;

        $updates['session_duration_seconds'] = $createdAt
            ? $this->durationSecondsBetween($createdAt, $effectiveLastActive)
            : 0;

        $session->update($updates);
    }

    private function formatDateTime(mixed $value): ?string
    {
        return TrackerTime::formatUtc($value);
    }

    private function normalizeScalarField(string $field, mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        $limit = config("tracker.scalar_field_limits.{$field}");

        if (is_int($limit) && $limit > 0 && mb_strlen($text) > $limit) {
            $this->logWarning('ingest.field_truncated', 'Long text cut short', [
                'field' => $field,
                'original_length' => mb_strlen($text),
                'limit' => $limit,
            ]);

            return mb_substr($text, 0, $limit);
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function paymentSuccessOrderId(array $event): string
    {
        $payload = $event['payment_success'] ?? [];

        if (! is_array($payload)) {
            return '';
        }

        $orderId = trim((string) ($payload['order_id'] ?? ''));

        if ($orderId !== '') {
            return $orderId;
        }

        $checkoutInfo = $payload['checkout_info'] ?? [];

        if (! is_array($checkoutInfo)) {
            return '';
        }

        return trim((string) ($checkoutInfo['order_number'] ?? $checkoutInfo['order_pk'] ?? ''));
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function isDuplicatePaymentSuccess(array $event): bool
    {
        if (($event['action_type'] ?? '') !== 'payment_success') {
            return false;
        }

        $orderId = $this->paymentSuccessOrderId($event);

        if ($orderId === '') {
            return false;
        }

        return ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->where(function ($query) use ($orderId) {
                $query->where('payment_success->order_id', $orderId)
                    ->orWhere('payment_success->checkout_info->order_number', $orderId)
                    ->orWhere('payment_success->checkout_info->order_pk', $orderId);
            })
            ->exists();
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function hasMeaningfulCheckoutPayload(array $event): bool
    {
        $actionType = (string) ($event['action_type'] ?? '');

        if (! in_array($actionType, ['begin_checkout', 'proceed_checkout'], true)) {
            return true;
        }

        $payloadKey = $actionType === 'begin_checkout' ? 'begin_checkout' : 'proceed_to_checkout';
        $payload = $event[$payloadKey] ?? null;

        if (! is_array($payload)) {
            return false;
        }

        $total = (float) (CheckoutPayloadTotals::commerceAmount($payload) ?? 0);

        if ($total > 0) {
            return true;
        }

        $items = $payload['cart_items'] ?? $payload['items'] ?? [];

        if (! is_array($items) || $items === []) {
            return false;
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $qty = (float) ($item['qty'] ?? $item['quantity'] ?? 0);
            $identity = trim((string) ($item['product_code'] ?? $item['sku'] ?? $item['product_name'] ?? $item['name'] ?? ''));

            if ($qty > 0 || $identity !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function validatePaymentSuccessPayload(array $event): void
    {
        if (($event['action_type'] ?? '') !== 'payment_success') {
            return;
        }

        $payload = $event['payment_success'] ?? [];

        if (! is_array($payload)) {
            throw ValidationException::withMessages([
                'events' => ['payment_success payload must be an object.'],
            ]);
        }

        if (isset($payload['checkout_info']) && ! is_array($payload['checkout_info'])) {
            throw ValidationException::withMessages([
                'events' => ['payment_success checkout_info must be an object.'],
            ]);
        }

        $allowed = config('tracker.payment_success_allowed_keys', []);
        $extra = array_diff(array_keys($payload), $allowed);

        if ($extra !== []) {
            $this->logError('ingest.payment_success_invalid', 'Payment data has wrong fields', [
                'disallowed_fields' => $extra,
            ]);

            throw ValidationException::withMessages([
                'events' => ['payment_success contains disallowed fields: ' . implode(', ', $extra)],
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logInfo(string $step, string $message, array $context = []): void
    {
        EcomTrackerLogger::frontend()->info($step, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWarning(string $step, string $message, array $context = []): void
    {
        EcomTrackerLogger::frontend()->warning($step, $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $step, string $message, array $context = []): void
    {
        EcomTrackerLogger::frontend()->error($step, $message, $context);
    }
}
