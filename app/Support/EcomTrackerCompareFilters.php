<?php

namespace App\Support;

use App\Services\EcomActivityListService;

/**
 * Map compare column query params (left_* / right_*) to User activity filter shape.
 */
final class EcomTrackerCompareFilters
{
    /** @var list<string> */
    public const FILTER_KEYS = [
        'device_type', 'logged_in', 'has_order', 'visitor_type', 'utm_source', 'utm_medium',
    ];

    /**
     * @param  array<string, mixed>  $side  period, date_*, device_type, utm_*, etc.
     */
    public static function activeCount(array $side): int
    {
        $count = 0;
        foreach (self::FILTER_KEYS as $key) {
            if (filled($side[$key] ?? null)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    public static function withoutSessionFilters(array $side): array
    {
        return collect($side)->except(self::FILTER_KEYS)->all();
    }

    /**
     * @param  array<string, mixed>  $side  period, date_*, device_type, utm_*, etc.
     * @return array<string, mixed>
     */
    public static function toActivityFilters(array $side): array
    {
        $deviceId = (int) ($side['device_type'] ?? 0);
        $utmSourceId = (int) ($side['utm_source'] ?? 0);
        $utmMedium = trim((string) ($side['utm_medium'] ?? ''));
        $loggedIn = in_array($side['logged_in'] ?? null, ['0', '1'], true)
            ? ($side['logged_in'] === '1')
            : null;
        $hasOrder = in_array($side['has_order'] ?? null, ['0', '1'], true)
            ? ($side['has_order'] === '1')
            : null;
        $visitorType = $side['visitor_type'] ?? null;
        $visitorType = in_array($visitorType, ['human', 'bot', 'unclassified'], true) ? $visitorType : null;

        return [
            'search' => '',
            'funnel' => [],
            'has_order' => $hasOrder,
            'devices' => $deviceId > 0 ? [$deviceId] : [],
            'logged_in' => $loggedIn,
            'durations' => [],
            'duration' => null,
            'utm_sources' => $utmSourceId > 0 ? [$utmSourceId] : [],
            'utm_mediums' => $utmMedium !== '' ? [$utmMedium] : [],
            'department' => null,
            'categories' => [],
            'visitor_type' => $visitorType,
        ];
    }

    /**
     * Query params for the activity index route (multi-select keys as arrays).
     *
     * @param  array<string, mixed>  $side
     * @return array<string, mixed>
     */
    public static function activityIndexQuery(array $side, string $backUrl): array
    {
        $period = EcomActivityListService::normalizePeriod((string) ($side['period'] ?? '24h'));
        $range = EcomActivityListService::periodRange(
            $period,
            (string) ($side['date_from'] ?? ''),
            (string) ($side['date_to'] ?? ''),
        );
        $activity = self::toActivityFilters($side);

        $query = array_filter([
            'period' => $period,
            'date_from' => $period === 'custom' ? $range['from']->toDateString() : null,
            'date_to' => $period === 'custom' ? $range['to']->toDateString() : null,
            'logged_in' => ($activity['logged_in'] ?? null) === null ? null : ($activity['logged_in'] ? '1' : '0'),
            'has_order' => ($activity['has_order'] ?? null) === null ? null : ($activity['has_order'] ? '1' : '0'),
            'visitor_type' => $activity['visitor_type'] ?? null,
            'back' => $backUrl,
        ], fn ($value) => $value !== null && $value !== '');

        foreach ($activity['devices'] as $deviceId) {
            $query['device_type'][] = $deviceId;
        }
        foreach ($activity['utm_sources'] as $sourceId) {
            $query['utm_source'][] = $sourceId;
        }
        foreach ($activity['utm_mediums'] as $medium) {
            $query['utm_medium'][] = $medium;
        }

        return $query;
    }

    /**
     * @param  array<int, string>  $options  id => label
     * @return list<array{value: string, label: string, selected: bool}>
     */
    public static function idSelectOptions(array $options, mixed $current, bool $ucfirstLabel = false): array
    {
        $current = (string) $current;
        $rows = [
            ['value' => '', 'label' => 'All', 'selected' => $current === ''],
        ];
        foreach ($options as $id => $label) {
            $text = $ucfirstLabel ? ucfirst((string) $label) : (string) $label;
            $value = (string) $id;
            $rows[] = [
                'value' => $value,
                'label' => $text,
                'selected' => $current === $value,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<string>  $mediums
     * @return list<array{value: string, label: string, selected: bool}>
     */
    public static function stringSelectOptions(array $mediums, mixed $current): array
    {
        $current = (string) $current;
        $rows = [
            ['value' => '', 'label' => 'All', 'selected' => $current === ''],
        ];
        foreach ($mediums as $medium) {
            $value = (string) $medium;
            $rows[] = [
                'value' => $value,
                'label' => $value,
                'selected' => $current === $value,
            ];
        }

        return $rows;
    }
}
