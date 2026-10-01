<?php

namespace App\Jobs;

use App\Services\ActivityEcomUserActionSyncService;
use App\Support\EcomTrackerLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SyncActivityEcomUserActionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct()
    {
        $this->onConnection((string) config('tracker.action_sync_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.action_sync_queue_name', 'default'));
    }

    public function handle(ActivityEcomUserActionSyncService $syncService): void
    {
        if ($syncService->isSyncPaused()) {
            $this->release(30);

            return;
        }

        $result = $syncService->syncPendingBatch();
        $syncService->advanceSyncProgress($result);

        EcomTrackerLogger::backend()->info('job.action_sync.batch', 'Action sync batch finished', $result);

        $remaining = (int) ($result['queue_remaining'] ?? 0);
        $pendingJobs = $syncService->countPendingSyncJobsInQueue();

        if ($remaining === 0 && $pendingJobs === 0) {
            $syncService->clearSyncProgress();
        }
    }

    public function failed(Throwable $exception): void
    {
        app(ActivityEcomUserActionSyncService::class)->clearSyncProgress();

        EcomTrackerLogger::backend()->error('job.action_sync.failed', 'Action sync job failed', [
            'message' => $exception->getMessage(),
        ]);
    }
}
