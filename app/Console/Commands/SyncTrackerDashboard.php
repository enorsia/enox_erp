<?php

namespace App\Console\Commands;

use App\Jobs\TrackerDashboardSyncJob;
use App\Services\TrackerDashboardSyncService;
use App\Support\TrackerQueueSupport;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncTrackerDashboard extends Command
{
    protected $signature = 'tracker:sync-dashboard
                            {--force : Re-queue already-synced sessions in the date range, then sync}
                            {--from= : Required with --force: session created on/after this date}
                            {--to= : Optional with --force: session created on/before this date}';

    protected $description = 'Queue ecom activity → tracking dashboard sync (same as the Ecom Tracker dashboard Sync button).';

    public function handle(TrackerDashboardSyncService $syncService): int
    {
        if ($this->option('force')) {
            if (! filled($this->option('from'))) {
                $this->error('--from is required with --force (example: --force --from=2026-10-13).');

                return self::FAILURE;
            }

            $from = Carbon::parse((string) $this->option('from'), TrackerTime::timezone())->startOfDay();
            $toOption = $this->option('to');
            $to = filled($toOption)
                ? Carbon::parse((string) $toOption, TrackerTime::timezone())->endOfDay()
                : null;

            $requeued = $syncService->requeueSyncedSessionsForDashboard($from, $to);

            $this->info(sprintf(
                'Force requeue: %d session(s) with actions (%s%s).',
                $requeued,
                $from->toDateString(),
                $to !== null ? ' to '.$to->toDateString() : ' onward',
            ));
        }

        $result = TrackerDashboardSyncJob::start($syncService);

        if ($result['status'] === TrackerDashboardSyncJob::ALREADY_RUNNING) {
            $this->error('Sync is already running. Please wait until it finishes.');

            return self::FAILURE;
        }

        if ($result['status'] === TrackerDashboardSyncJob::NOTHING_TO_SYNC) {
            $this->warn('No more data to sync.');

            return self::FAILURE;
        }

        $connection = TrackerQueueSupport::connection();
        $queue = TrackerQueueSupport::queue();

        $this->info(sprintf(
            'Sync started: %d batch(es), %d session(s).',
            $result['batches'],
            $result['sessions'],
        ));
        $this->line('Ensure a queue worker is running:');
        $this->line('php artisan queue:work '.$connection.' --queue='.$queue);

        return self::SUCCESS;
    }
}
