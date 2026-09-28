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
        $day = $this->argument('date')
            ? Carbon::parse((string) $this->argument('date'), $timezone)->startOfDay()
            : Carbon::now($timezone)->subDay()->startOfDay();

        $rollup->rollupDateWithStatus($day->toDateString());
        $this->info('Rolled up '.$day->toDateString());

        return self::SUCCESS;
    }
}
