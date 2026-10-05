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

    public int $timeout = 120;

    public int $uniqueFor = 600;

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
        $batchSize = max(1, (int) config('tracker.dashboard_sync_batch_size', 25));
        $deadline = time() + 50;
        $afterId = $this->afterId;

        do {
            $afterId = $syncService->processBatch($afterId, $batchSize);
        } while ($afterId !== null && time() < $deadline);
    }
}
