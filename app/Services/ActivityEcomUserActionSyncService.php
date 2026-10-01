<?php

namespace App\Services;

use App\Jobs\SyncActivityEcomUserActionsJob;
use App\Models\ActivityEcomUserAction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class ActivityEcomUserActionSyncService
{
    public const CHAIN_ACTIVE_CACHE_KEY = 'tracker:action_sync_chain_active';

    public const CHAIN_PROGRESS_CACHE_KEY = 'tracker:action_sync_progress';

    public function isSyncPaused(): bool
    {
        $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

        return is_array($stored) && (bool) ($stored['paused'] ?? false);
    }

    /**
     * @return array{paused: bool, message: string, progress: array<string, mixed>}
     */
    public function pauseQueuedSync(): array
    {
        if (! $this->isSyncChainActive()) {
            return [
                'paused' => false,
                'message' => 'No sync in progress.',
                'progress' => $this->syncProgress(),
            ];
        }

        $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

        if (! is_array($stored)) {
            $stored = [];
        }

        $stored['paused'] = true;
        Cache::put(self::CHAIN_PROGRESS_CACHE_KEY, $stored, now()->addHours(12));

        return [
            'paused' => true,
            'message' => 'Sync paused. Resume when ready.',
            'progress' => $this->syncProgress(),
        ];
    }

    /**
     * @return array{resumed: bool, message: string, progress: array<string, mixed>}
     */
    public function resumeQueuedSync(): array
    {
        if (! $this->isSyncChainActive()) {
            $summary = $this->summary();

            if ((int) ($summary['queue'] ?? 0) === 0) {
                return [
                    'resumed' => false,
                    'message' => 'Already up to date.',
                    'progress' => $this->syncProgress(),
                ];
            }

            $started = $this->startQueuedSync();

            return [
                'resumed' => (bool) ($started['started'] ?? false),
                'message' => $started['message'],
                'progress' => $started['progress'],
            ];
        }

        $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

        if (is_array($stored)) {
            $stored['paused'] = false;
            Cache::put(self::CHAIN_PROGRESS_CACHE_KEY, $stored, now()->addHours(12));
        }

        return [
            'resumed' => true,
            'message' => 'Sync resumed. Next step will run on the next poll.',
            'progress' => $this->syncProgress(),
        ];
    }

    /**
     * @return array{cancelled: bool, message: string, progress: array<string, mixed>}
     */
    public function cancelQueuedSync(): array
    {
        $this->removePendingSyncJobsFromQueue();
        $this->clearSyncProgress();

        return [
            'cancelled' => true,
            'message' => 'Sync cancelled. You can start again from the sync button.',
            'progress' => $this->syncProgress(),
        ];
    }

    public function hasPendingSyncJobInQueue(): bool
    {
        $connection = (string) config('tracker.action_sync_queue_connection', 'database');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return false;
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $queue = (string) config('tracker.action_sync_queue_name', 'default');

        return DB::table($table)
            ->where('queue', $queue)
            ->where('payload', 'like', '%SyncActivityEcomUserActionsJob%')
            ->exists();
    }

    public function removePendingSyncJobsFromQueue(): void
    {
        $connection = (string) config('tracker.action_sync_queue_connection', 'database');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return;
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $queue = (string) config('tracker.action_sync_queue_name', 'default');

        DB::table($table)
            ->where('queue', $queue)
            ->where('payload', 'like', '%SyncActivityEcomUserActionsJob%')
            ->delete();
    }

    /**
     * @return array{
     *     started: bool,
     *     queue: int,
     *     batch_size: int,
     *     estimated_jobs: int,
     *     chain_active: bool,
     *     message: string,
     *     progress: array<string, mixed>
     * }
     */
    public function startQueuedSync(): array
    {
        $summary = $this->summary();
        $queue = (int) ($summary['queue'] ?? 0);
        $batchSize = max(1, (int) config('tracker.action_sync_batch_size', 25));
        $estimatedJobs = $queue > 0 ? (int) ceil($queue / $batchSize) : 0;

        if ($queue === 0) {
            return [
                'started' => false,
                'queue' => 0,
                'batch_size' => $batchSize,
                'estimated_jobs' => 0,
                'chain_active' => false,
                'message' => 'Already up to date.',
                'progress' => $this->syncProgress(),
            ];
        }

        if ($this->isSyncChainActive()) {
            return [
                'started' => false,
                'queue' => $queue,
                'batch_size' => $batchSize,
                'estimated_jobs' => $estimatedJobs,
                'chain_active' => true,
                'message' => 'Sync is already in progress.',
                'progress' => $this->syncProgress(),
            ];
        }

        if ($this->hasPendingSyncJobInQueue()) {
            return [
                'started' => false,
                'queue' => $queue,
                'batch_size' => $batchSize,
                'estimated_jobs' => $estimatedJobs,
                'chain_active' => true,
                'message' => 'Sync jobs are already in the database queue.',
                'progress' => $this->syncProgress(),
            ];
        }

        $this->removePendingSyncJobsFromQueue();
        $this->initializeSyncProgress($queue, $estimatedJobs, $batchSize);
        $storedJobs = $this->enqueueAllSyncJobs($estimatedJobs);

        $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

        if (is_array($stored)) {
            $stored['step_message'] = $storedJobs === 1
                ? '1 job queued — run worker'
                : sprintf('%s jobs queued — run worker', number_format($storedJobs));
            Cache::put(self::CHAIN_PROGRESS_CACHE_KEY, $stored, now()->addHours(12));
        }

        return [
            'started' => true,
            'queue' => $queue,
            'batch_size' => $batchSize,
            'estimated_jobs' => $estimatedJobs,
            'jobs_stored' => $storedJobs,
            'chain_active' => true,
            'message' => 'Sync jobs stored in the database queue.',
            'progress' => $this->syncProgress(),
        ];
    }

    public function enqueueAllSyncJobs(int $jobCount): int
    {
        $jobCount = max(0, $jobCount);

        if ($jobCount === 0) {
            return 0;
        }

        @set_time_limit(0);

        for ($i = 0; $i < $jobCount; $i++) {
            SyncActivityEcomUserActionsJob::dispatch();
        }

        return $jobCount;
    }

    public function countPendingSyncJobsInQueue(): int
    {
        return $this->countSyncJobsInQueueTable(reserved: null);
    }

    public function countReservedSyncJobsInQueue(): int
    {
        return $this->countSyncJobsInQueueTable(reserved: true);
    }

    public function countUnreservedSyncJobsInQueue(): int
    {
        return $this->countSyncJobsInQueueTable(reserved: false);
    }

    /**
     * @param  bool|null  $reserved  null = all rows, true = reserved only, false = waiting only
     */
    private function countSyncJobsInQueueTable(?bool $reserved): int
    {
        $connection = (string) config('tracker.action_sync_queue_connection', 'database');

        if (config("queue.connections.{$connection}.driver") !== 'database') {
            return 0;
        }

        $table = (string) config("queue.connections.{$connection}.table", 'jobs');
        $queue = (string) config('tracker.action_sync_queue_name', 'default');

        $query = DB::table($table)
            ->where('queue', $queue)
            ->where('payload', 'like', '%SyncActivityEcomUserActionsJob%');

        if ($reserved === true) {
            $query->whereNotNull('reserved_at');
        } elseif ($reserved === false) {
            $query->whereNull('reserved_at');
        }

        return (int) $query->count();
    }

    /**
     * Run a single queued batch when no worker has reserved a job (local / small installs).
     */
    public function processNextQueuedSyncJobIfIdle(): void
    {
        if (! config('tracker.action_sync_process_on_status_poll', true)) {
            return;
        }

        if (! $this->isSyncChainActive() || $this->isSyncPaused()) {
            return;
        }

        if ($this->countUnreservedSyncJobsInQueue() === 0) {
            return;
        }

        if ($this->countReservedSyncJobsInQueue() > 0) {
            return;
        }

        $connection = (string) config('tracker.action_sync_queue_connection', 'database');
        $queue = (string) config('tracker.action_sync_queue_name', 'default');

        Artisan::call('queue:work', [
            'connection' => $connection,
            '--queue' => $queue,
            '--once' => true,
            '--stop-when-empty' => true,
        ]);
    }

    /**
     * @return array{complete: bool, queue: int, jobs_in_queue: int, progress: array<string, mixed>}
     */
    public function queueSyncStatus(): array
    {
        $this->processNextQueuedSyncJobIfIdle();

        $summary = $this->summary();
        $queue = (int) ($summary['queue'] ?? 0);
        $progress = $this->syncProgress();
        $jobsInQueue = $this->countPendingSyncJobsInQueue();
        $completedJobs = max(0, (int) ($progress['completed_jobs'] ?? 0));
        $totalJobs = max(1, (int) ($progress['total_jobs'] ?? 1));
        $batchesFinished = $jobsInQueue === 0 && $completedJobs >= $totalJobs;

        if (! ($progress['active'] ?? false)) {
            $complete = true;
        } else {
            $complete = $batchesFinished && $queue === 0;

            if (! $complete && $batchesFinished && $queue > 0) {
                $complete = true;
            }
        }

        if ($complete && $this->isSyncChainActive()) {
            $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

            if (is_array($stored)) {
                $initial = max(0, (int) ($stored['initial_queue'] ?? 0));
                $progress = $this->formatSyncProgress($stored);
                $progress['active'] = false;
                $progress['done_count'] = $initial;
                $progress['total_count'] = $initial;
                $progress['lock_sync_button'] = false;
                $progress['sync_succeeded'] = true;
                $progress['step_message'] = $initial === 0 ? 'Already up to date.' : 'Sync complete';
            }

            $this->clearSyncProgress();
        }

        return [
            'complete' => $complete,
            'queue' => $queue,
            'jobs_in_queue' => $jobsInQueue,
            'progress' => $progress,
        ];
    }

    public function isSyncChainActive(): bool
    {
        return (bool) Cache::get(self::CHAIN_ACTIVE_CACHE_KEY);
    }

    /**
     * @return array{
     *     active: bool,
     *     progress_step: int,
     *     progress_total: int,
     *     completed_jobs: int,
     *     total_jobs: int,
     *     queue_remaining: int,
     *     initial_queue: int,
     *     batch_size: int
     * }
     */
    public function syncProgress(): array
    {
        $idle = [
            'active' => false,
            'paused' => false,
            'done_count' => 0,
            'total_count' => 0,
            'step_message' => '',
            'completed_jobs' => 0,
            'total_jobs' => 0,
            'queue_remaining' => 0,
            'initial_queue' => 0,
            'batch_size' => max(1, (int) config('tracker.action_sync_batch_size', 25)),
            'lock_sync_button' => false,
        ];

        if (! $this->isSyncChainActive()) {
            return $idle;
        }

        $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

        if (! is_array($stored)) {
            $summary = $this->summary();
            $queue = (int) ($summary['queue'] ?? 0);
            $batchSize = $idle['batch_size'];
            $totalJobs = max(1, (int) ceil($queue / $batchSize));

            $stored = [
                'total_jobs' => $totalJobs,
                'completed_jobs' => 0,
                'initial_queue' => $queue,
                'queue_remaining' => $queue,
                'batch_size' => $batchSize,
                'started_at' => now()->toIso8601String(),
            ];
        }

        return $this->formatSyncProgress($stored);
    }

    /**
     * @param  array{processed?: int, synced?: int, failed?: int, skipped?: int, queue_remaining?: int}  $batchResult
     */
    public function advanceSyncProgress(array $batchResult): void
    {
        if (! $this->isSyncChainActive()) {
            return;
        }

        $stored = Cache::get(self::CHAIN_PROGRESS_CACHE_KEY);

        if (! is_array($stored)) {
            return;
        }

        $stored['completed_jobs'] = (int) ($stored['completed_jobs'] ?? 0) + 1;
        $stored['queue_remaining'] = (int) ($batchResult['queue_remaining'] ?? $this->summary()['queue']);

        $batchNum = (int) $stored['completed_jobs'];
        $totalJobs = max(1, (int) ($stored['total_jobs'] ?? 1));
        $initialQueue = max(0, (int) ($stored['initial_queue'] ?? 0));
        $doneCount = max(0, $initialQueue - (int) $stored['queue_remaining']);
        $jobsLeft = max(0, $totalJobs - $batchNum);

        $stored['step_message'] = $this->buildBatchStepMessage(
            $batchNum,
            $totalJobs,
            $doneCount,
            $initialQueue,
            $jobsLeft,
        );

        Cache::put(self::CHAIN_PROGRESS_CACHE_KEY, $stored, now()->addHours(12));
    }

    public function clearSyncProgress(): void
    {
        Cache::forget(self::CHAIN_ACTIVE_CACHE_KEY);
        Cache::forget(self::CHAIN_PROGRESS_CACHE_KEY);
    }

    private function initializeSyncProgress(int $queue, int $totalJobs, int $batchSize): void
    {
        Cache::put(self::CHAIN_ACTIVE_CACHE_KEY, true, now()->addHours(12));
        Cache::put(self::CHAIN_PROGRESS_CACHE_KEY, [
            'mode' => 'database',
            'total_jobs' => max(1, $totalJobs),
            'completed_jobs' => 0,
            'initial_queue' => $queue,
            'queue_remaining' => $queue,
            'batch_size' => $batchSize,
            'started_at' => now()->toIso8601String(),
            'paused' => false,
            'step_message' => 'Queuing…',
        ], now()->addHours(12));
    }

    /**
     * @param  array<string, mixed>  $stored
     * @return array{
     *     active: bool,
     *     paused: bool,
     *     done_count: int,
     *     total_count: int,
     *     step_message: string,
     *     completed_jobs: int,
     *     total_jobs: int,
     *     queue_remaining: int,
     *     initial_queue: int,
     *     batch_size: int,
     *     lock_sync_button: bool
     * }
     */
    private function formatSyncProgress(array $stored): array
    {
        $completedJobs = max(0, (int) ($stored['completed_jobs'] ?? 0));
        $totalJobs = max(1, (int) ($stored['total_jobs'] ?? 1));
        $initialQueue = max(0, (int) ($stored['initial_queue'] ?? 0));
        $queueRemaining = max(0, (int) ($stored['queue_remaining'] ?? 0));
        $paused = (bool) ($stored['paused'] ?? false);
        $doneCount = max(0, $initialQueue - $queueRemaining);
        $jobsInQueue = $this->countPendingSyncJobsInQueue();
        $stepMessage = (string) ($stored['step_message'] ?? '');

        if ($completedJobs > 0) {
            $jobsLeft = max(0, $totalJobs - $completedJobs);
            $stepMessage = $this->buildBatchStepMessage(
                $completedJobs,
                $totalJobs,
                $doneCount,
                $initialQueue,
                $jobsLeft,
            );
        } elseif ($stepMessage === '' && $jobsInQueue > 0) {
            $stepMessage = $jobsInQueue === 1
                ? '1 job waiting — run worker'
                : sprintf('%s jobs waiting — run worker', number_format($jobsInQueue));
        }

        return [
            'active' => true,
            'paused' => $paused,
            'done_count' => $doneCount,
            'total_count' => $initialQueue,
            'step_message' => $stepMessage,
            'completed_jobs' => $completedJobs,
            'total_jobs' => $totalJobs,
            'jobs_in_queue' => $jobsInQueue,
            'queue_remaining' => $queueRemaining,
            'initial_queue' => $initialQueue,
            'batch_size' => max(1, (int) ($stored['batch_size'] ?? config('tracker.action_sync_batch_size', 25))),
            'lock_sync_button' => ! $paused,
        ];
    }

    /**
     * @return array{
     *     queue: int,
     *     pending: int,
     *     failed: int,
     *     abandoned: int,
     *     last_synced_at: ?string
     * }
     */
    public function summary(): array
    {
        $lastSyncedAt = ActivityEcomUserAction::query()
            ->where('sync_status', ActivityEcomUserAction::SYNC_SYNCED)
            ->max('sync_claimed_at');

        $pending = $this->syncableQuery()
            ->where('sync_status', ActivityEcomUserAction::SYNC_PENDING)
            ->count();

        $failed = $this->syncableQuery()
            ->where('sync_status', ActivityEcomUserAction::SYNC_FAILED)
            ->count();

        return [
            'queue' => $pending + $failed,
            'pending' => $pending,
            'failed' => $failed,
            'abandoned' => $this->abandonedQuery()->count(),
            'last_synced_at' => $lastSyncedAt ? (string) $lastSyncedAt : null,
        ];
    }

    /**
     * @return array{
     *     processed: int,
     *     synced: int,
     *     failed: int,
     *     skipped: int,
     *     queue_remaining: int,
     *     last_synced_at: ?string
     * }
     */
    public function syncPendingBatch(?int $limit = null): array
    {
        $limit = max(1, $limit ?? (int) config('tracker.action_sync_batch_size', 25));
        $writer = app(CommerceIngestWriter::class);

        $synced = 0;
        $failed = 0;
        $skipped = 0;

        DB::transaction(function () use ($limit, $writer, &$synced, &$failed, &$skipped): void {
            $actions = $this->syncableQuery()
                ->orderBy('created_at')
                ->orderBy('id')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            $claimedAt = now()->format('Y-m-d H:i:s');

            foreach ($actions as $action) {
                $action->sync_attempts = (int) $action->sync_attempts + 1;
                $action->sync_claimed_at = $claimedAt;

                try {
                    if (CommerceIngestWriter::isSyncableActionType($action->action_type)) {
                        $result = $writer->syncFromAction($action, true);

                        $status = (string) ($result['status'] ?? '');

                        if ($status === 'ok') {
                            $action->sync_status = ActivityEcomUserAction::SYNC_SYNCED;
                            $synced++;
                        } elseif ($status === 'skipped') {
                            $action->sync_status = ActivityEcomUserAction::SYNC_FAILED;
                            $skipped++;
                            $failed++;
                        } else {
                            $action->sync_status = ActivityEcomUserAction::SYNC_FAILED;
                            $failed++;
                        }
                    } else {
                        $action->sync_status = ActivityEcomUserAction::SYNC_SYNCED;
                        $synced++;
                    }
                } catch (Throwable) {
                    $action->sync_status = ActivityEcomUserAction::SYNC_FAILED;
                    $failed++;
                }

                $action->save();
            }
        });

        $summary = $this->summary();

        return [
            'processed' => $synced + $failed,
            'synced' => $synced,
            'failed' => $failed,
            'skipped' => $skipped,
            'queue_remaining' => $summary['queue'],
            'last_synced_at' => $summary['last_synced_at'],
        ];
    }

    private function buildBatchStepMessage(
        int $batchNum,
        int $totalJobs,
        int $doneCount,
        int $initialQueue,
        int $jobsLeft,
    ): string {
        return sprintf(
            'Batch %s/%s · %s/%s synced · %s jobs left',
            number_format($batchNum),
            number_format($totalJobs),
            number_format($doneCount),
            number_format($initialQueue),
            number_format($jobsLeft),
        );
    }

    private function maxSyncAttempts(): int
    {
        return max(1, (int) config('tracker.action_sync_max_attempts', 5));
    }

    /**
     * Pending + failed rows still allowed to sync (attempts ≤ max).
     */
    private function syncableQuery(): Builder
    {
        return ActivityEcomUserAction::query()
            ->whereIn('sync_status', [
                ActivityEcomUserAction::SYNC_PENDING,
                ActivityEcomUserAction::SYNC_FAILED,
            ])
            ->where('sync_attempts', '<=', $this->maxSyncAttempts());
    }

    /**
     * Pending/failed rows skipped after too many attempts.
     */
    private function abandonedQuery(): Builder
    {
        return ActivityEcomUserAction::query()
            ->whereIn('sync_status', [
                ActivityEcomUserAction::SYNC_PENDING,
                ActivityEcomUserAction::SYNC_FAILED,
            ])
            ->where('sync_attempts', '>', $this->maxSyncAttempts());
    }
}
