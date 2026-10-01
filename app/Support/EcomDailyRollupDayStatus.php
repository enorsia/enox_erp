<?php

namespace App\Support;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class EcomDailyRollupDayStatus
{
    public const SUCCESS = 'success';

    public const FAILED = 'failed';

    public const PENDING = 'pending';

    public static function table(): string
    {
        return 'activity_ecom_rollup_day_status';
    }

    public static function hasTable(): bool
    {
        return Schema::hasTable(self::table());
    }

    public static function markAttempt(string $metricDate): void
    {
        if (! self::hasTable()) {
            return;
        }

        $now = now();
        $existing = DB::table(self::table())->where('metric_date', $metricDate)->first();

        if ($existing === null) {
            DB::table(self::table())->insert([
                'metric_date' => $metricDate,
                'status' => self::PENDING,
                'last_attempted_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table(self::table())->where('metric_date', $metricDate)->update([
            'status' => self::PENDING,
            'last_attempted_at' => $now,
            'last_error' => null,
            'updated_at' => $now,
        ]);
    }

    public static function markSuccess(string $metricDate): void
    {
        if (! self::hasTable()) {
            return;
        }

        $now = now();
        DB::table(self::table())->updateOrInsert(
            ['metric_date' => $metricDate],
            [
                'status' => self::SUCCESS,
                'last_attempted_at' => $now,
                'rolled_up_at' => $now,
                'last_error' => null,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public static function markFailed(string $metricDate, string $error): void
    {
        if (! self::hasTable()) {
            return;
        }

        $now = now();
        DB::table(self::table())->updateOrInsert(
            ['metric_date' => $metricDate],
            [
                'status' => self::FAILED,
                'last_attempted_at' => $now,
                'last_error' => mb_substr($error, 0, 65000),
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public static function isSuccess(string $metricDate): bool
    {
        if (! self::hasTable()) {
            return false;
        }

        return DB::table(self::table())
            ->where('metric_date', $metricDate)
            ->where('status', self::SUCCESS)
            ->exists();
    }

    /**
     * @return list<string> Y-m-d dates in range that are not rolled up successfully
     */
    public static function datesNeedingRollup(Carbon $fromLocal, Carbon $toLocal, bool $force = false): array
    {
        $dates = [];
        $cursor = $fromLocal->copy()->startOfDay();

        while ($cursor->lte($toLocal)) {
            $date = $cursor->toDateString();

            if ($force || ! self::isSuccess($date)) {
                $dates[] = $date;
            }

            $cursor->addDay();
        }

        return $dates;
    }
}
