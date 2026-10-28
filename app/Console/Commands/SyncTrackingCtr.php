<?php

namespace App\Console\Commands;

use App\Services\TrackingCtrBatchDispatcher;
use App\Services\TrackingCtrSyncSiteSettingsService;
use App\Support\TrackerQueueSupport;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTrackingCtr extends Command
{
    protected $signature = 'tracker:sync-ctr
                            {--force : Re-roll up CTR for a date range (resets rollup flags and clears daily summaries for those days)}
                            {--from= : Required with --force: range start (visitor timezone calendar day)}
                            {--to= : Optional with --force: range end day (defaults to --from)}';

    protected $description = 'Queue CTR rollup jobs for pending rows in tracking_ctr_categories_impression (ctr_rollup_status null).';

    public function handle(
        TrackingCtrBatchDispatcher $dispatcher,
        TrackingCtrSyncSiteSettingsService $syncSiteSettings,
    ): int {
        $force = (bool) $this->option('force');
        $from = null;
        $to = null;

        if ($force) {
            if (! filled($this->option('from'))) {
                $this->error('--from is required with --force (example: --force --from=2026-10-27).');

                return self::FAILURE;
            }

            $from = Carbon::parse((string) $this->option('from'), TrackerTime::timezone())->startOfDay();
            $toOption = $this->option('to');
            $to = filled($toOption)
                ? Carbon::parse((string) $toOption, TrackerTime::timezone())->startOfDay()
                : null;

            $endDay = $to ?? $from;

            $this->info(sprintf(
                'CTR force: preparing %s to %s (%s).',
                $from->toDateString(),
                $endDay->toDateString(),
                TrackerTime::timezone(),
            ));
        }

        $rollupSettings = $syncSiteSettings->fetchForSync();
        $this->info(sprintf(
            'CTR sync: product sorting (%s) — ctr_days=%d, min_clicks=%d.',
            $rollupSettings['source'],
            $rollupSettings['ctr_days'],
            $rollupSettings['min_clicks'],
        ));

        $result = $dispatcher->dispatchPendingBatches($force, $from, $to, $rollupSettings);

        if ($force) {
            $this->info(sprintf(
                'CTR force: reset %d impression row(s); removed %d daily summary row(s).',
                $result['force_reset_impressions'],
                $result['force_daily_rows_deleted'],
            ));
        }

        if ($result['category_impression_count'] === 0) {
            $this->info('CTR sync: nothing pending (tracking_ctr_categories_impression.ctr_rollup_status null).');

            return self::SUCCESS;
        }

        $connection = TrackerQueueSupport::connection();
        $queue = TrackerQueueSupport::queue();
        $jobsConnection = (string) config("queue.connections.{$connection}.connection", 'tracking');
        $jobsTable = (string) config("queue.connections.{$connection}.table", 'jobs');

        $this->info(sprintf(
            'CTR sync: queued %d job(s) — %d category impression row(s) → connection [%s], queue [%s].',
            $result['queued_jobs'],
            $result['category_impression_count'],
            $connection,
            $queue,
        ));
        $this->line(sprintf(
            '`jobs` table: %d row(s) waiting.',
            DB::connection($jobsConnection)->table($jobsTable)->count(),
        ));
        $this->line('Process with: php artisan queue:work '.$connection.' --queue='.$queue);

        return self::SUCCESS;
    }
}
