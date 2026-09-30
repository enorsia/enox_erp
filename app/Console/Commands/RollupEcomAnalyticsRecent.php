<?php

namespace App\Console\Commands;

use App\Services\EcomDailyRollupService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class RollupEcomAnalyticsRecent extends Command
{
    protected $signature = 'tracker:rollup-analytics-recent {--days=2 : Closed days ending yesterday}';

    protected $description = 'Re-roll the last N closed days (store timezone).';

    public function handle(EcomDailyRollupService $rollup): int
    {
        $timezone = TrackerTime::timezone();
        $count = max(1, (int) $this->option('days'));
        $yesterday = Carbon::now($timezone)->subDay()->startOfDay();

        for ($i = 0; $i < $count; $i++) {
            $metricDate = $yesterday->copy()->subDays($i)->toDateString();
            $this->line('Rolling '.$metricDate);
            $rollup->rollupDateWithStatus($metricDate);
        }

        $this->info("Re-rolled {$count} day(s).");

        return self::SUCCESS;
    }
}
