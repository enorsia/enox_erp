<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class TrackerDashboardSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    public $maxExceptions = 1;

    public $timeout = 1200;

    public $failOnTimeout = true;

    public $backoff = 10;

    /** Rows per job run — change here. */
    public int $batchSize = 25;

    public function __construct(
        public ?int $afterId = null,
    ) {
        $this->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'));
    }

    public function handle(TrackerDashboardSyncService $syncService): void
    {
        $nextAfterId = $syncService->processBatch($this->afterId, $this->batchSize);

        if ($nextAfterId !== null) {
            self::dispatch($nextAfterId);
        }
    }
}
