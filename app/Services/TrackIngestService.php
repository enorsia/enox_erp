<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TrackIngestService
{
    /** @var array<string, string> */
    private const URL_DEPARTMENT_SLUG_MAP = [
        'men' => 'Men',
        'women' => 'Women',
        'boys' => 'Boys',
        'girls' => 'Girls',
    ];

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function ingest(array $payload): array
    {
        $events = $payload['events'] ?? [];

        if ($events === []) {
            return [];
        }

        $sessionId = ($payload['session']['session_id'] ?? null) ?: ($events[0]['session_id'] ?? null);

        if (! $sessionId) {
            throw ValidationException::withMessages([
                'session.session_id' => ['Session ID is required.'],
            ]);
        }

        $events = $this->sortEventsByClock($events);
        $acceptedIds = [];
        $sessionIds = [];

        foreach ($events as $event) {
            $eventId = $event['id'] ?? null;

            if (! $eventId) {
                continue;
            }

            $eventSessionId = (string) ($event['session_id'] ?? $sessionId);
            $sessionIds[$eventSessionId] = true;
        }

        foreach (array_keys($sessionIds) as $eventSessionId) {
            $this->ensureSessionRowExists($eventSessionId, $payload);
        }

        foreach ($events as $event) {
            $eventId = $event['id'] ?? null;

            if (! $eventId) {
                continue;
            }

            $eventSessionId = (string) ($event['session_id'] ?? $sessionId);

            ActivityEcomUserAction::query()->updateOrInsert(
                ['event_id' => $eventId],
                $this->mapEventToRow($eventSessionId, $event) + [
                    'is_sync' => ActivityEcomUserAction::SYNC_PENDING,
                ],
            );

            // Only is_sync — leave sync_try so permanently failed sessions stay out of the queue.
            DB::table('activity_ecom_user')
                ->where('session_id', $eventSessionId)
                ->update(['is_sync' => ActivityEcomUser::SYNC_PENDING]);

            $acceptedIds[] = $eventId;
        }

        return $acceptedIds;
    }

    /**
     * Parent row required by FK on activity_ecom_user_actions.session_id.
     *
     * @param  array<string, mixed>  $payload
     */
    private function ensureSessionRowExists(string $sessionId, array $payload): void
    {
        if (ActivityEcomUser::query()->where('session_id', $sessionId)->exists()) {
            return;
        }

        $now = $this->formatUtc($this->nowUtc());
        $sessionMeta = is_array($payload['session'] ?? null) ? $payload['session'] : [];

        ActivityEcomUser::query()->insertOrIgnore([
            'session_id' => $sessionId,
            'visitor_id' => $sessionMeta['visitor_id'] ?? null,
            'user_id' => $sessionMeta['user_id'] ?? null,
            'user_name' => $sessionMeta['user_name'] ?? null,
            'user_email' => $sessionMeta['user_email'] ?? null,
            'user_phone' => $sessionMeta['user_phone'] ?? null,
            'is_logged_in' => (bool) ($sessionMeta['is_logged_in'] ?? false),
            'last_active_at' => $now,
            'session_duration_seconds' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    /**
     * @param  array<int, array<string, mixed>>  $events
     * @return array<int, array<string, mixed>>
     */
    private function sortEventsByClock(array $events): array
    {
        usort($events, function (array $left, array $right): int {
            $leftAt = $this->eventActivityTimestamp($left);
            $rightAt = $this->eventActivityTimestamp($right);

            return $leftAt <=> $rightAt;
        });

        return $events;
    }

    /**
     * @param  array<string, mixed>  $event
     */
    private function eventActivityTimestamp(array $event): int
    {
        foreach (['end_time', 'created_at', 'start_time'] as $field) {
            $value = $event[$field] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $parsed = $this->parseUtc($value);

            if ($parsed !== null) {
                return $parsed->getTimestamp();
            }
        }

        return PHP_INT_MAX;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array<string, mixed>
     */
    private function mapEventToRow(string $sessionId, array $event): array
    {
        $actionType = (string) ($event['action_type'] ?? '');
        $createdAt = $this->formatUtc($event['created_at'] ?? $this->nowUtc());

        $row = [
            'session_id' => $sessionId,
            'action_type' => $actionType,
            'page_url' => $event['page_url'] ?? null,
            'referer' => $event['referer'] ?? null,
            'created_at' => $createdAt,
        ];

        foreach ([
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
        ] as $field) {
            if (! array_key_exists($field, $event) || $event[$field] === null || $event[$field] === '') {
                continue;
            }

            $value = $this->normalizeScalarField($field, $event[$field]);

            if ($value !== null && $value !== '') {
                $row[$field] = $value;
            }
        }

        foreach (['start_time', 'end_time'] as $field) {
            if (! empty($event[$field])) {
                $row[$field] = $this->formatUtc($event[$field]);
            }
        }

        if (($row['department_name'] ?? '') === '' && ($actionType === 'category_view' || ! empty($row['category_name']))) {
            $departmentName = $this->departmentNameFromPageUrl((string) ($event['page_url'] ?? ''));

            if ($departmentName !== '') {
                $row['department_name'] = $departmentName;
            }
        }

        if ($actionType === 'add_to_cart' && isset($event['add_to_cart']) && is_array($event['add_to_cart'])) {
            $row = $this->enrichAddToCartScalars($row, $event['add_to_cart']);
            $row['add_to_cart'] = $this->encodeJson($event['add_to_cart']);
        }

        if ($actionType === 'begin_checkout' && isset($event['begin_checkout']) && is_array($event['begin_checkout'])) {
            $row['begin_checkout'] = $this->encodeJson($event['begin_checkout']);
        }

        if ($actionType === 'proceed_checkout' && isset($event['proceed_to_checkout']) && is_array($event['proceed_to_checkout'])) {
            $row['proceed_to_checkout'] = $this->encodeJson($event['proceed_to_checkout']);
        }

        if ($actionType === 'payment_success' && isset($event['payment_success']) && is_array($event['payment_success'])) {
            $row['payment_success'] = $this->encodeJson($event['payment_success']);
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

    private function departmentNameFromPageUrl(string $pageUrl): string
    {
        $pageUrl = trim($pageUrl);

        if ($pageUrl === '') {
            return '';
        }

        $path = parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '';
        }

        if (! preg_match('#/c/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            && ! preg_match('#/style/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            && ! preg_match('#^/(men|women|boys|girls)(?:/|$)#i', $path, $matches)) {
            return '';
        }

        return self::URL_DEPARTMENT_SLUG_MAP[strtolower($matches[1])] ?? '';
    }

    /**
     * @param  array<string, mixed>  $value
     */
    private function encodeJson(array $value): string
    {
        return json_encode($value) ?: '{}';
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
            return mb_substr($text, 0, $limit);
        }

        return $text;
    }

    private function nowUtc(): Carbon
    {
        return Carbon::now('UTC');
    }

    private function formatUtc(mixed $value): string
    {
        $parsed = $this->parseUtc($value);

        return ($parsed ?? $this->nowUtc())->format('Y-m-d H:i:s');
    }

    private function parseUtc(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value->copy()->utc();
        }

        if ($value === null || $value === '') {
            return null;
        }

        try {
            return Carbon::parse($value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }
}
