<?php

namespace App\Support;

final class SessionDurationBuckets
{
    /**
     * @return array<int, array{label: string, min: int, max: int}>
     */
    public static function definitions(): array
    {
        return [
            ['label' => '0–1 min', 'min' => 0, 'max' => 60],
            ['label' => '1–5 min', 'min' => 61, 'max' => 300],
            ['label' => '5–15 min', 'min' => 301, 'max' => 900],
            ['label' => '15–30 min', 'min' => 901, 'max' => 1800],
            ['label' => '30+ min', 'min' => 1801, 'max' => PHP_INT_MAX],
        ];
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
     *     buckets: array<int, array{label: string, min: int, max: int, count: int, pct: float}>,
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
