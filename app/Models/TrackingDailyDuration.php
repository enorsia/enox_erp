<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDailyDuration extends Model
{
    /** bucket code => duration range in seconds; max is inclusive, null = no upper limit */
    public const BUCKETS = [
        1 => ['label' => '0–1 min', 'min' => 0, 'max' => 59],
        2 => ['label' => '1–3 min', 'min' => 60, 'max' => 179],
        3 => ['label' => '3–5 min', 'min' => 180, 'max' => 299],
        4 => ['label' => '5–7 min', 'min' => 300, 'max' => 419],
        5 => ['label' => '7–9 min', 'min' => 420, 'max' => 539],
        6 => ['label' => '9–11 min', 'min' => 540, 'max' => 659],
        7 => ['label' => '11–13 min', 'min' => 660, 'max' => 779],
        8 => ['label' => '13–15 min', 'min' => 780, 'max' => 899],
        9 => ['label' => '15–30 min', 'min' => 900, 'max' => 1799],
        10 => ['label' => '30+ min', 'min' => 1800, 'max' => null],
    ];

    protected $fillable = [
        'metric_date',
        'bucket',
        'sessions',
        'duration_seconds',
    ];

    protected function casts(): array
    {
        return [
            'metric_date' => 'date',
            'bucket' => 'integer',
            'sessions' => 'integer',
            'duration_seconds' => 'integer',
        ];
    }

    public static function bucketFor(int $seconds): int
    {
        foreach (self::BUCKETS as $code => $bucket) {
            if ($bucket['max'] === null || $seconds <= $bucket['max']) {
                return $code;
            }
        }

        return array_key_last(self::BUCKETS);
    }
}
