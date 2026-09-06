<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;

final class SessionDurationBuckets
{
    /**
     * @return array<int, array{key: string, label: string, min: int, max: int}>
     */
    public static function definitions(): array
    {
        return [
            ['key' => '0-1', 'label' => '0–1 min', 'min' => 0, 'max' => 60],
            ['key' => '1-3', 'label' => '1–3 min', 'min' => 61, 'max' => 180],
            ['key' => '3-5', 'label' => '3–5 min', 'min' => 181, 'max' => 300],
            ['key' => '5-7', 'label' => '5–7 min', 'min' => 301, 'max' => 420],
            ['key' => '7-9', 'label' => '7–9 min', 'min' => 421, 'max' => 540],
            ['key' => '9-11', 'label' => '9–11 min', 'min' => 541, 'max' => 660],
            ['key' => '11-13', 'label' => '11–13 min', 'min' => 661, 'max' => 780],
            ['key' => '13-15', 'label' => '13–15 min', 'min' => 781, 'max' => 900],
            ['key' => '15-30', 'label' => '15–30 min', 'min' => 901, 'max' => 1800],
            ['key' => '30-plus', 'label' => '30+ min', 'min' => 1801, 'max' => PHP_INT_MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function optionLabels(): array
    {
        $options = ['' => 'All'];

        foreach (self::definitions() as $bucket) {
            $options[$bucket['key']] = $bucket['label'];
        }

        return $options;
    }

    /**
     * @return array{key: string, label: string, min: int, max: int}|null
     */
    public static function definitionByKey(?string $key): ?array
    {
        if (! filled($key)) {
            return null;
        }

        foreach (self::definitions() as $bucket) {
            if ($bucket['key'] === $key) {
                return $bucket;
            }
        }

        return null;
    }

    public static function labelForKey(?string $key): ?string
    {
        return self::definitionByKey($key)['label'] ?? null;
    }

    /**
     * @param  EloquentBuilder<*>|QueryBuilder  $query
     */
    public static function applyToQuery($query, ?string $key): void
    {
        $bucket = self::definitionByKey($key);

        if ($bucket === null) {
            return;
        }

        $table = $query instanceof EloquentBuilder ? $query->getModel()->getTable() : null;
        $column = $table ? "{$table}.session_duration_seconds" : 'session_duration_seconds';
        $expr = "COALESCE({$column}, 0)";

        if ($bucket['max'] === PHP_INT_MAX) {
            $query->whereRaw("{$expr} >= ?", [$bucket['min']]);

            return;
        }

        $query->whereRaw("{$expr} between ? and ?", [$bucket['min'], $bucket['max']]);
    }

    public static function bucketLabelForSeconds(int $seconds): string
    {
        $seconds = max(0, $seconds);

        foreach (self::definitions() as $bucket) {
            if ($seconds >= $bucket['min'] && $seconds <= $bucket['max']) {
                return $bucket['label'];
            }
        }

        return self::definitions()[array_key_last(self::definitions())]['label'];
    }

    /**
     * @param  iterable<int|float|string|null>  $durations
     * @return array{
     *     buckets: array<int, array{key: string, label: string, min: int, max: int, count: int, pct: float}>,
     *     total_sessions: int,
     *     median_seconds: int
     * }
     */
    public static function withCounts(iterable $durations): array
    {
        $buckets = array_map(
            fn (array $bucket) => array_merge($bucket, ['count' => 0]),
            self::definitions(),
        );

        $allSeconds = [];

        foreach ($durations as $duration) {
            $seconds = max(0, (int) $duration);
            $allSeconds[] = $seconds;

            foreach ($buckets as &$bucket) {
                if ($seconds >= $bucket['min'] && $seconds <= $bucket['max']) {
                    $bucket['count']++;
                    break;
                }
            }
            unset($bucket);
        }

        $total = count($allSeconds);
        $median = self::medianSeconds($allSeconds);

        $bucketsWithPct = array_map(function (array $bucket) use ($total) {
            return [
                'key' => $bucket['key'],
                'label' => $bucket['label'],
                'min' => $bucket['min'],
                'max' => $bucket['max'],
                'count' => $bucket['count'],
                'pct' => $total > 0 ? round(($bucket['count'] / $total) * 100, 1) : 0.0,
            ];
        }, $buckets);

        return [
            'buckets' => $bucketsWithPct,
            'total_sessions' => $total,
            'median_seconds' => $median,
        ];
    }

    /**
     * @param  array<int, int>  $seconds
     */
    private static function medianSeconds(array $seconds): int
    {
        if ($seconds === []) {
            return 0;
        }

        sort($seconds);
        $count = count($seconds);
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return (int) round(($seconds[$middle - 1] + $seconds[$middle]) / 2);
        }

        return $seconds[$middle];
    }
}
