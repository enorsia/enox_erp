<?php

namespace App\Console\Commands;

use App\Services\EcomDailyRollupService;
use App\Services\TrackerDataCleanupService;
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
                            {--force : Re-roll days that already succeeded}
                            {--skip-session-merge : Skip merging duplicate visitor sessions in the backfill date range}';

    protected $description = 'Merge duplicate sessions (30m gap), then roll up missing or failed days into activity_ecom_daily_*.';

    public function handle(EcomDailyRollupService $rollup, TrackerDataCleanupService $cleanup): int
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

        if (! $this->option('skip-session-merge')) {
            $this->warn(
                'Session merge runs per rollup day (same local calendar date only). '
                .'Use tracker:backfill-attribution for cross-session clock repair, not rollup backfill.',
            );
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
                if (! $this->option('skip-session-merge')) {
                    $dayBounds = TrackerTime::localCalendarDateBoundsUtc($date);
                    $merge = $cleanup->mergeDuplicateVisitorSessionsWithinGap(
                        from: $dayBounds['from'],
                        before: $dayBounds['to'],
                    );
                    if (($merge['merge_groups'] ?? 0) > 0) {
                        $this->line(sprintf(
                            '  %s session merge: groups %d | removed %d | actions moved %d',
                            $date,
                            $merge['merge_groups'],
                            $merge['sessions_removed'],
                            $merge['actions_reassigned'],
                        ));
                    }
                }

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
