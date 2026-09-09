<?php

namespace App\Services\Exports\Async;

use App\Models\ActivityEcomUser;
use App\Support\EcomActivityFocus;
use App\Support\TrackerTime;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class EcomActivityAsyncRowBuilder
{
    /**
     * @param  array<string, mixed>  $queryParams
     * @return array<int, string>
     */
    public static function headings(array $queryParams): array
    {
        $request = Request::create('/', 'GET', $queryParams);
        $focus = $request->input('focus');

        $headings = [
            'SL',
            'Session ID',
            'Session started',
            'User',
            'Visitor trust',
            'Commerce',
            'Commerce detail',
            'Actions',
        ];

        if ($request->filled('department') || $request->filled('category')) {
            $headings[] = 'Category';
        }

        foreach (EcomActivityFocus::tableColumns($focus, $request) as $column) {
            $headings[] = (string) ($column['label'] ?? $column['key'] ?? '');
        }

        $headings[] = 'Duration';
        $headings[] = 'Last active';

        return $headings;
    }

    /**
     * @param  Collection<int, ActivityEcomUser>  $sessions
     * @param  array<string, array<string, mixed>>  $rowMetrics
     * @param  array<string, mixed>  $queryParams
     * @return array<int, array<int|string, mixed>>
     */
    public static function fromSessions(
        Collection $sessions,
        array $rowMetrics,
        array $queryParams,
        int &$serialStart = 1,
    ): array {
        $request = Request::create('/', 'GET', $queryParams);
        $focus = $request->input('focus');
        $focusColumns = EcomActivityFocus::tableColumns($focus, $request);
        $showCatalogColumn = $request->filled('department') || $request->filled('category');
        $rows = [];

        foreach ($sessions as $session) {
            $metrics = $rowMetrics[$session->session_id] ?? [];
            $row = [
                $serialStart,
                (string) $session->session_id,
                TrackerTime::formatFromStorage($session->created_at) ?? '—',
                self::formatUser($session),
                self::formatVisitorTrust($session),
                (string) ($metrics['commerce_display'] ?? '—'),
                self::formatCommerceDetail($metrics),
                (int) ($session->actions_count ?? $metrics['actions_count'] ?? 0),
            ];

            if ($showCatalogColumn) {
                $catalogPath = trim((string) ($metrics['catalog_path'] ?? ''));
                $row[] = ($catalogPath !== '' && $catalogPath !== '—') ? $catalogPath : '—';
            }

            foreach ($focusColumns as $column) {
                $row[] = self::formatMetric($column['key'] ?? '', $metrics);
            }

            $row[] = format_duration((int) ($session->session_duration_seconds ?? 0));
            $row[] = TrackerTime::diffForHumansLatestActivity(
                $session->updated_at,
                $session->last_active_at,
                $session->created_at,
            ) ?? '—';

            $rows[] = $row;
            $serialStart++;
        }

        return $rows;
    }

    private static function formatUser(ActivityEcomUser $session): string
    {
        if ($session->isRegisteredUser()) {
            $parts = array_filter([
                $session->user_name ?: 'User #'.$session->user_id,
                $session->user_email,
                $session->user_phone,
            ]);

            return implode(' · ', $parts) ?: '—';
        }

        if ($session->isGuestCheckout()) {
            $parts = array_filter([
                ($session->user_name ?: '—').' (Guest checkout)',
                $session->user_email,
                $session->user_phone,
            ]);

            return implode(' · ', $parts) ?: 'Guest checkout';
        }

        if ($session->is_logged_in && $session->user_id) {
            return 'User #'.$session->user_id;
        }

        return 'Guest';
    }

    private static function formatVisitorTrust(ActivityEcomUser $session): string
    {
        $label = $session->marketer_type_label;
        $botCtx = $session->botContext;
        $subtitle = $botCtx?->marketer_user_agent_hint
            ?? $botCtx?->marketer_reason_label
            ?? ($session->visitorClassification() === 'unclassified' ? null : $session->marketer_reason_label);

        if (filled($subtitle)) {
            return $label.' · '.$subtitle;
        }

        return $label;
    }

    /**
     * @param  array<string, mixed>  $metrics
     */
    private static function formatCommerceDetail(array $metrics): string
    {
        $meta = trim((string) ($metrics['commerce_meta'] ?? ''));

        return $meta !== '' ? $meta : '—';
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
}
