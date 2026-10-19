<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use App\Support\TrackerDatabase;
use App\Support\TrackerQueueSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * PLANNER job: runs every minute (scheduler). The Sync button calls start() directly.
 * It only splits pending sessions into groups of 25 and queues one chunk job per group.
 */
class TrackerDashboardSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public const STARTED = 'started';

    public const ALREADY_RUNNING = 'already_running';

    public const NOTHING_TO_SYNC = 'nothing_to_sync';

    private const START_LOCK = 'tracker-dashboard-sync-start';

    /** Batches older than this are treated as abandoned so they never block a new sync. */
    private const STALE_BATCH_HOURS = 24;

    public function __construct()
    {
        $this->onConnection(TrackerQueueSupport::connection());
        $this->onQueue(TrackerQueueSupport::queue());
    }

    public function handle(TrackerDashboardSyncService $syncService): void
    {
        $result = self::start($syncService);

        Log::info('tracker.dashboard.sync: planner '.$result['status'], [
            'chunk_jobs' => $result['batches'],
            'sessions' => $result['sessions'],
        ]);
    }

    /**
     * @return array{status: string, batches: int, sessions: int}
     */
    public static function start(TrackerDashboardSyncService $syncService): array
    {
        $lock = Cache::lock(self::START_LOCK, 30);

        if (! $lock->get()) {
            return ['status' => self::ALREADY_RUNNING, 'batches' => 0, 'sessions' => 0];
        }

        try {
            if (self::isRunning()) {
                return ['status' => self::ALREADY_RUNNING, 'batches' => 0, 'sessions' => 0];
            }

            $chunkSize = max(1, (int) config('tracker.dashboard_sync_batch_size', 25));
            $chunks = $syncService->planChunks($chunkSize);

            if ($chunks === []) {
                return ['status' => self::NOTHING_TO_SYNC, 'batches' => 0, 'sessions' => 0];
            }

            foreach ($chunks as $sessionIds) {
                TrackerDashboardSyncChunkJob::dispatch($sessionIds);
            }

            return [
                'status' => self::STARTED,
                'batches' => count($chunks),
                'sessions' => array_sum(array_map('count', $chunks)),
            ];
        } finally {
            $lock->release();
        }
    }

    /** Chunk or planner jobs still queued on the tracking database connection. */
    public static function isRunning(): bool
    {
        return TrackerDatabase::table('jobs')
            ->where(function ($query): void {
                $query->where('payload', 'like', '%TrackerDashboardSyncChunkJob%')
                    ->orWhere('payload', 'like', '%TrackerDashboardSyncJob%');
            })
            ->where('created_at', '>=', now()->subHours(self::STALE_BATCH_HOURS)->getTimestamp())
            ->exists();
    }
}
