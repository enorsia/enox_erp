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
use Illuminate\Support\Facades\Log;
use Throwable;

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
            Log::warning('tracker.dashboard.sync: planner skipped (sync already running)');

            return;
        }

        try {
            $chunkSize = max(1, (int) config('tracker.dashboard_sync_batch_size', 25));

            $chunks = $syncService->planChunks($chunkSize);

            if ($chunks === []) {
                Log::info('tracker.dashboard.sync: planner found no eligible sessions');
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
                    Cache::forget(self::RUNNING_LOCK);
                })
                ->dispatch();

            Log::info('tracker.dashboard.sync: planner queued chunk batch', [
                'chunk_jobs' => count($chunks),
                'batch_size' => $chunkSize,
            ]);
        } catch (Throwable $e) {
            Cache::forget(self::RUNNING_LOCK);

            throw $e;
        }
    }
}
