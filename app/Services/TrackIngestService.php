<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\UserAgentParser;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TrackIngestService
{
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

        if (! $sessionId) {
            $this->logError('Session ID missing in ingest payload');

            throw ValidationException::withMessages([
                'session.session_id' => ['Session ID is required.'],
            ]);
        }

        $this->logInfo('Ingest started', [
            'session_id' => $sessionId,
            'event_count' => count($events),
        ]);

        $this->upsertSession($request, $sessionId, $sessionData);

        $acceptedIds = [];

        foreach ($events as $event) {
            $eventId = $event['id'] ?? null;

            if (! $eventId) {
                $this->logWarning('Skipped event without id', [
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

            if (($event['action_type'] ?? '') === 'payment_success') {
                $this->syncSessionUserFromPaymentSuccess($sessionId, $event);
            }

            $acceptedIds[] = $eventId;

            $this->logInfo('Event stored', [
                'session_id' => $sessionId,
                'event_id' => $eventId,
                'action_type' => $event['action_type'] ?? null,
                'page_url' => $event['page_url'] ?? null,
            ]);
        }

        $this->logInfo('Ingest completed', [
            'session_id' => $sessionId,
            'accepted_count' => count($acceptedIds),
        ]);

        return $acceptedIds;
    }

    /**
     * @param  array<string, mixed>  $sessionData
     */
    private function upsertSession(Request $request, string $sessionId, array $sessionData): void
    {
        $parsed = UserAgentParser::parse($request->userAgent());
        $now = $this->formatDateTime(now());

        $existing = ActivityEcomUser::query()->where('session_id', $sessionId)->first();

        $attributes = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'device_type' => $parsed['device_type'],
            'browser' => $parsed['browser'],
            'os' => $parsed['os'],
            'last_active_at' => $now,
        ];

        foreach (['user_id', 'user_name', 'user_email'] as $field) {
            if (array_key_exists($field, $sessionData)) {
                $attributes[$field] = $sessionData[$field];
            }
        }

        if (array_key_exists('is_logged_in', $sessionData)) {
            $attributes['is_logged_in'] = (bool) $sessionData['is_logged_in'];
        }

        if (! $existing) {
            $attributes['session_id'] = $sessionId;
            $attributes['utm_source'] = $sessionData['utm_source'] ?? null;
            $attributes['utm_medium'] = $sessionData['utm_medium'] ?? null;
            $attributes['utm_campaign'] = $sessionData['utm_campaign'] ?? null;
            $attributes['landing_page'] = $sessionData['landing_page'] ?? null;
            $attributes['created_at'] = $now;
            $attributes['updated_at'] = $now;

            ActivityEcomUser::query()->create($attributes);

            $this->logInfo('Session created', [
                'session_id' => $sessionId,
                'user_id' => $attributes['user_id'] ?? null,
                'is_logged_in' => $attributes['is_logged_in'] ?? false,
            ]);

            return;
        }

        $attributes['updated_at'] = $now;
        $existing->update($attributes);

        $this->logInfo('Session updated', [
            'session_id' => $sessionId,
            'user_id' => $attributes['user_id'] ?? $existing->user_id,
            'is_logged_in' => $attributes['is_logged_in'] ?? $existing->is_logged_in,
        ]);
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
            'created_at' => $this->formatDateTime($event['created_at'] ?? now()),
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
                ? $this->formatDateTime($event[$field])
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

        $firstName = trim((string) ($customer['first_name'] ?? ''));
        $lastName = trim((string) ($customer['last_name'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $name = trim(implode(' ', array_filter([$firstName, $lastName])));

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

        $updates['last_active_at'] = $this->formatDateTime($event['created_at'] ?? now());
        $updates['updated_at'] = $this->formatDateTime(now());

        $session->update($updates);

        $this->logInfo('Session user synced from payment_success', [
            'session_id' => $sessionId,
            'user_name' => $updates['user_name'] ?? $session->user_name,
            'user_email' => $updates['user_email'] ?? $session->user_email,
        ]);
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return Carbon::parse($value)->format('Y-m-d H:i:s');
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
            $this->logWarning('Truncated tracker scalar field', [
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
            $this->logError('payment_success payload validation failed', [
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
    private function logInfo(string $message, array $context = []): void
    {
        if (! config('tracker.logging_enabled')) {
            return;
        }

        Log::info('[EnoxTracker] ' . $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logWarning(string $message, array $context = []): void
    {
        if (! config('tracker.logging_enabled')) {
            return;
        }

        Log::warning('[EnoxTracker] ' . $message, $context);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context = []): void
    {
        if (! config('tracker.logging_enabled')) {
            return;
        }

        Log::error('[EnoxTracker] ' . $message, $context);
    }
}
