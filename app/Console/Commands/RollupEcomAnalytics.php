<?php

namespace App\Console\Commands;

use App\Services\EcomDailyRollupService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RollupEcomAnalytics extends Command
{
    protected $signature = 'tracker:rollup-analytics {date? : YYYY-MM-DD, default yesterday}';

    protected $description = 'Roll up one day into activity_ecom_daily_* tables.';

    public function handle(EcomDailyRollupService $rollup): int
    {
        $timezone = TrackerTime::timezone();
        $metricDate = $this->argument('date')
            ? Carbon::parse((string) $this->argument('date'), $timezone)->toDateString()
            : TrackerTime::defaultRollupMetricDate();

        [$fromUtc, $toUtc] = TrackerTime::localCalendarDateStorageRange($metricDate);
        $this->line(sprintf(
            'Metric date %s (%s) — session window %s → %s UTC',
            $metricDate,
            $timezone,
            $fromUtc,
            $toUtc,
        ));

        $rollup->rollupDateWithStatus($metricDate);
        $this->info('Rolled up '.$metricDate);

        return self::SUCCESS;
    }
}
