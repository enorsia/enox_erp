<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * CHUNK job: syncs ONLY the session ids it was created with (normally 25).
 */
class TrackerDashboardSyncChunkJob implements ShouldQueue
{
    use Batchable, Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    /**
     * @param  list<int>  $sessionIds
     */
    public function __construct(public array $sessionIds)
    {
        $this->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'));
    }

    public function handle(TrackerDashboardSyncService $syncService): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $syncService->syncSessionIds($this->sessionIds);
    }
}
