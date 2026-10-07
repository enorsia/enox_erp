<?php

namespace App\Console\Commands;

use App\Services\TrackingCtrBatchDispatcher;
use Illuminate\Console\Command;

class SyncTrackingCtr extends Command
{
    protected $signature = 'tracker:sync-ctr
                            {--sync : Process each batch inline instead of pushing to the queue}';

    protected $description = 'Batch-sync grid impressions and product clicks into CTR summary tables.';

    public function handle(TrackingCtrBatchDispatcher $dispatcher): int
    {
        $sync = (bool) $this->option('sync');
        $result = $dispatcher->dispatchPendingBatches($sync);

        $this->info(sprintf(
            'CTR sync: %d queue job(s) for %d action(s)%s.',
            $result['queued_jobs'],
            $result['action_count'],
            $sync ? ' (processed inline)' : '',
        ));

        return self::SUCCESS;
    }
}
