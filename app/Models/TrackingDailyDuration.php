<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrackingDailyDuration extends Model
{
    /** bucket code => duration range in seconds; max is inclusive, null = no upper limit */
    public const BUCKETS = [
        1 => ['label' => '0–1 min', 'min' => 0, 'max' => 60],
        2 => ['label' => '1–3 min', 'min' => 61, 'max' => 180],
        3 => ['label' => '3–5 min', 'min' => 181, 'max' => 300],
        4 => ['label' => '5–7 min', 'min' => 301, 'max' => 420],
        5 => ['label' => '7–9 min', 'min' => 421, 'max' => 540],
        6 => ['label' => '9–11 min', 'min' => 541, 'max' => 660],
        7 => ['label' => '11–13 min', 'min' => 661, 'max' => 780],
        8 => ['label' => '13–15 min', 'min' => 781, 'max' => 900],
        9 => ['label' => '15–30 min', 'min' => 901, 'max' => 1800],
        10 => ['label' => '30+ min', 'min' => 1801, 'max' => null],
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
