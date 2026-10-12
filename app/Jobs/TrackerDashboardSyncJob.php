<?php

namespace App\Jobs;

use App\Services\TrackerDashboardSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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

    private const BATCH_NAME = 'tracker-dashboard-sync';

    private const START_LOCK = 'tracker-dashboard-sync-start';

    /** Batches older than this are treated as abandoned so they never block a new sync. */
    private const STALE_BATCH_HOURS = 24;

    public function __construct()
    {
        $this->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'));
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

            $jobs = array_map(fn (array $ids) => new TrackerDashboardSyncChunkJob($ids), $chunks);

            Bus::batch($jobs)
                ->name(self::BATCH_NAME)
                ->onConnection((string) config('tracker.dashboard_sync_queue_connection', 'database'))
                ->onQueue((string) config('tracker.dashboard_sync_queue_name', 'default'))
                ->allowFailures()
                ->dispatch();

            return [
                'status' => self::STARTED,
                'batches' => count($chunks),
                'sessions' => array_sum(array_map('count', $chunks)),
            ];
        } finally {
            $lock->release();
        }
    }

    /**
     * A sync batch still has chunk jobs waiting or working (failed jobs stay counted in pending_jobs).
     */
    public static function isRunning(): bool
    {
        return DB::table('job_batches')
            ->where('name', self::BATCH_NAME)
            ->whereNull('finished_at')
            ->whereNull('cancelled_at')
            ->whereColumn('pending_jobs', '>', 'failed_jobs')
            ->where('created_at', '>=', now()->subHours(self::STALE_BATCH_HOURS)->getTimestamp())
            ->exists();
    }
}
