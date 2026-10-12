<?php

namespace App\Console\Commands;

use App\Jobs\TrackerDashboardSyncJob;
use App\Services\TrackerDashboardSyncService;
use Illuminate\Console\Command;

class SyncTrackerDashboard extends Command
{
    protected $signature = 'tracker:sync-dashboard';

    protected $description = 'Queue ecom activity → tracking dashboard sync (same as the Ecom Tracker dashboard Sync button).';

    public function handle(TrackerDashboardSyncService $syncService): int
    {
        $result = TrackerDashboardSyncJob::start($syncService);

        if ($result['status'] === TrackerDashboardSyncJob::ALREADY_RUNNING) {
            $this->error('Sync is already running. Please wait until it finishes.');

            return self::FAILURE;
        }

        if ($result['status'] === TrackerDashboardSyncJob::NOTHING_TO_SYNC) {
            $this->warn('No more data to sync.');

            return self::FAILURE;
        }

        $connection = (string) config('tracker.dashboard_sync_queue_connection', 'database');
        $queue = (string) config('tracker.dashboard_sync_queue_name', 'default');

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
