<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerRedisSupport;
use App\Support\TrackerTime;
use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackIngestService
{
    public function __construct(
        private VisitorSessionResolver $visitorSessionResolver,
        private TrackerClientContextResolver $clientContextResolver,
        private BotContextPersister $botContextPersister,
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

            $row = $this->mapEventToRow($sessionId, $event);

            ActivityEcomUserAction::query()->updateOrInsert(
                ['event_id' => $eventId],
                $row
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
            ]);
        }

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
            'last_active_at' => $now,
        ];

        if ($clientContext !== null && ! empty($clientContext['ip_country'])) {
            $attributes['country'] = $clientContext['ip_country'];
        }

        foreach (['user_id', 'user_name', 'user_email'] as $field) {
            if (array_key_exists($field, $sessionData)) {
                $attributes[$field] = $sessionData[$field];
            }
        }

        if (! empty($sessionData['user_id'])) {
            $attributes['is_logged_in'] = true;
        } elseif (array_key_exists('is_logged_in', $sessionData)) {
            $attributes['is_logged_in'] = ! empty($sessionData['user_id']) && (bool) $sessionData['is_logged_in'];
        }

        if (! empty($sessionData['visitor_id'])) {
            $attributes['visitor_id'] = $sessionData['visitor_id'];
        }

        if (! $existing) {
            $attributes['session_id'] = $sessionId;
            $attributes['utm_source'] = $sessionData['utm_source'] ?? null;
            $attributes['utm_medium'] = $sessionData['utm_medium'] ?? null;
            $attributes['utm_campaign'] = $sessionData['utm_campaign'] ?? null;
            $attributes['landing_page'] = $sessionData['landing_page'] ?? null;
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;
            $attributes['session_duration_seconds'] = 0;

            ActivityEcomUser::query()->create($attributes);

            $this->logInfo('ingest.session_created', 'New visitor session created', [
                'session_id' => $sessionId,
                'user_id' => $attributes['user_id'] ?? null,
                'is_logged_in' => $attributes['is_logged_in'] ?? false,
            ]);

            $this->persistBotContextIfNeeded($sessionId, $clientContext);

            return;
        }

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
            'product_name',
            'product_code',
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
        }

        if ($actionType === 'begin_checkout' && isset($event['begin_checkout'])) {
            $row['begin_checkout'] = json_encode($event['begin_checkout']);
        }

        if ($actionType === 'proceed_checkout' && isset($event['proceed_to_checkout'])) {
            $row['proceed_to_checkout'] = json_encode($event['proceed_to_checkout']);
        }

        if ($actionType === 'payment_success' && isset($event['payment_success'])) {
            $row['payment_success'] = json_encode($event['payment_success']);
        }

        return $row;
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
        $firstName = trim((string) ($customer['first_name'] ?? ''));
        $lastName = trim((string) ($customer['last_name'] ?? ''));
        $fullName = trim((string) ($customer['full_name'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $name = trim($fullName !== '' ? $fullName : implode(' ', array_filter([$firstName, $lastName])));

        $updates = [];

        if ($name !== '') {
            $updates['user_name'] = $name;
        }

        if ($email !== '') {
            $updates['user_email'] = $email;
        }

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

        $this->logInfo('ingest.checkout_user_sync', 'Logged-in user updated from checkout', [
            'session_id' => $sessionId,
            'user_name' => $updates['user_name'] ?? $session->user_name,
            'user_email' => $updates['user_email'] ?? $session->user_email,
            'is_logged_in' => $updates['is_logged_in'] ?? $session->is_logged_in,
        ]);
    }

    private function sessionDurationSeconds(ActivityEcomUser $session): int
    {
        $createdAt = TrackerTime::toUtc($session->getRawOriginal('created_at'));

        if ($createdAt === null) {
            return 0;
        }

        return (int) $createdAt->diffInSeconds(TrackerTime::nowUtc());
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
