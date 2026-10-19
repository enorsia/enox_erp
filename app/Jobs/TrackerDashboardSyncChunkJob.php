<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use App\Support\TrackerQueueSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * CHUNK job: syncs ONLY the session ids it was created with (normally 25).
 */
class TrackerDashboardSyncChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    /** 0 = no time limit; overrides plain `queue:work` default of 60s. */
    public int $timeout = 0;

    /**
     * @param  list<int>  $sessionIds
     */
    public function __construct(public array $sessionIds)
    {
        $this->onConnection(TrackerQueueSupport::connection());
        $this->onQueue(TrackerQueueSupport::queue());
    }

    public function handle(TrackerDashboardSyncService $syncService): void
    {
        $syncService->syncSessionIds($this->sessionIds);
    }
}
