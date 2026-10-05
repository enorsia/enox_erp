<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;

/**
 * PLANNER job: runs every minute (scheduler) or from the Sync button.
 * It only splits pending sessions into groups of 25 and queues one chunk job per group.
 */
class TrackerDashboardSyncJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    private const RUNNING_LOCK = 'tracker-dashboard-sync-running';

    public int $timeout = 120;

    public int $uniqueFor = 600;

    public function __construct()
    {
        $this->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'));
    }

    public function uniqueId(): string
    {
        return 'tracker-dashboard-sync';
    }

    public function handle(TrackerDashboardSyncService $syncService): void
    {
        // Previous plan is still running (its chunk jobs are queued or working): skip this minute.
        if (! Cache::add(self::RUNNING_LOCK, 1, now()->addMinutes(60))) {
            return;
        }

        $chunkSize = max(1, (int) config('tracker.dashboard_sync_batch_size', 25));
        $maxSessions = max(1, (int) config('tracker.dashboard_sync_max_per_run', 5000));

        $chunks = $syncService->planChunks($chunkSize, $maxSessions);

        if ($chunks === []) {
            Cache::forget(self::RUNNING_LOCK);

            return;
        }

        $jobs = array_map(fn (array $ids) => new TrackerDashboardSyncChunkJob($ids), $chunks);

        Bus::batch($jobs)
            ->name('tracker-dashboard-sync')
            ->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'))
            ->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'))
            ->allowFailures()
            ->finally(function () {
                // Runs after ALL chunk jobs ended: the next minute may plan again.
                Cache::forget('tracker-dashboard-sync-running');
            })
            ->dispatch();
    }
}
