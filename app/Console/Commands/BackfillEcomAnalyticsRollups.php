<?php

namespace App\Console\Commands;

use App\Services\EcomDailyRollupService;
use App\Support\EcomDailyRollupDayStatus;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillEcomAnalyticsRollups extends Command
{
    protected $signature = 'tracker:rollup-analytics-backfill
                            {from? : Start YYYY-MM-DD (default: first session day)}
                            {to? : End YYYY-MM-DD (default: today)}
                            {--force : Re-roll days that already succeeded}';

    protected $description = 'Roll up missing or failed days into activity_ecom_daily_* (skips success unless --force).';

    public function handle(EcomDailyRollupService $rollup): int
    {
        $timezone = TrackerTime::timezone();
        $today = Carbon::now($timezone)->startOfDay();

        $to = $this->argument('to')
            ? Carbon::parse((string) $this->argument('to'), $timezone)->startOfDay()
            : $today->copy();

        if ($to->gt($today)) {
            $this->warn('End date is in the future; capping at '.$today->toDateString().'.');
            $to = $today->copy();
        }

        if ($this->argument('from')) {
            $from = Carbon::parse((string) $this->argument('from'), $timezone)->startOfDay();
        } else {
            $minUtc = DB::table('activity_ecom_user')->min('created_at');
            if ($minUtc === null) {
                $this->warn('No sessions in activity_ecom_user — nothing to roll up.');

                return self::SUCCESS;
            }

            $from = Carbon::parse($minUtc, 'UTC')->timezone($timezone)->startOfDay();
        }

        if ($from->gt($to)) {
            $this->error('Start date must be on or before end date.');

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $dates = EcomDailyRollupDayStatus::datesNeedingRollup($from, $to, $force);

        if ($dates === []) {
            $this->info('All days from '.$from->toDateString().' through '.$to->toDateString().' are already rolled up (success).');

            return self::SUCCESS;
        }

        $this->info(
            'Rolling up '.count($dates).' day(s) from '.$from->toDateString().' through '.$to->toDateString()
            .' ('.$timezone.')'.($force ? ' [force]' : ' [missing or failed only]'),
        );

        $done = 0;
        $total = count($dates);

        foreach ($dates as $date) {
            try {
                $rollup->rollupDateWithStatus($date);
                $done++;
                $this->line("{$done}/{$total} {$date} — ok");
            } catch (\Throwable $exception) {
                $done++;
                $this->error("{$done}/{$total} {$date} — failed: ".$exception->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
