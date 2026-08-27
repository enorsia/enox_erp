<?php

namespace App\Console\Commands;

use App\Jobs\RollupEcomAnalyticsJob;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;

class RollupEcomAnalytics extends Command
{
    protected $signature = 'tracker:rollup-analytics
                            {--from= : Start date YYYY-MM-DD}
                            {--to= : End date YYYY-MM-DD}
                            {--sync : Run inline instead of queueing jobs}';

    protected $description = 'Roll up commerce analytics into daily site metrics tables.';

    public function handle(): int
    {
        $timezone = TrackerTime::timezone();
        $from = $this->option('from')
            ? Carbon::parse((string) $this->option('from'), $timezone)->startOfDay()
            : Carbon::now($timezone)->subDays(31)->startOfDay();
        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), $timezone)->endOfDay()
            : Carbon::now($timezone)->endOfDay();

        $period = CarbonPeriod::create($from->copy()->startOfDay(), $to->copy()->startOfDay());

        foreach ($period as $day) {
            $date = $day->toDateString();
            if ($this->option('sync')) {
                (new RollupEcomAnalyticsJob($date))->handle();
                $this->line("Rolled up {$date}");
            } else {
                RollupEcomAnalyticsJob::dispatch($date);
                $this->line("Queued rollup for {$date}");
            }
        }

        return self::SUCCESS;
    }
}
