<?php

namespace App\Console\Commands;

use App\Services\TrackingCtrBatchDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncTrackingCtr extends Command
{
    protected $signature = 'tracker:sync-ctr';

    protected $description = 'Queue CTR batch jobs (MySQL jobs table) for pending grid impressions and product clicks.';

    public function handle(TrackingCtrBatchDispatcher $dispatcher): int
    {
        $result = $dispatcher->dispatchPendingBatches();

        if ($result['action_count'] === 0) {
            $this->info('CTR sync: no pending actions (ctr_tracking_status is null).');

            return self::SUCCESS;
        }

        $connection = (string) config('tracker.ctr_queue_connection', 'database');
        $queue = (string) config('tracker.ctr_queue_name', 'default');

        $this->info(sprintf(
            'CTR sync: queued %d job(s) for %d action(s) → connection [%s], queue [%s].',
            $result['queued_jobs'],
            $result['action_count'],
            $connection,
            $queue,
        ));
        $this->line(sprintf('`jobs` table: %d row(s) waiting.', DB::table('jobs')->count()));
        $this->line('Process with: php artisan queue:work ' . $connection . ' --queue=' . $queue);

        return self::SUCCESS;
    }
}
