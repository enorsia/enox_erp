<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class TrackerDashboardSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 300;

    public int $uniqueFor = 3600;

    /** Rows per job run — change here. */
    public int $batchSize = 25;

    public function __construct(
        public ?int $afterId = null,
    ) {
        $this->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'));
    }

    public function uniqueId(): string
    {
        return 'tracker-dashboard-sync';
    }

    public function handle(TrackerDashboardSyncService $syncService): void
    {
        $nextAfterId = $syncService->processBatch($this->afterId, $this->batchSize);

        if ($nextAfterId !== null) {
            self::dispatch($nextAfterId);
        }
    }
}
