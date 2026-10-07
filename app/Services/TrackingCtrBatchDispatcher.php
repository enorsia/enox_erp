<?php

namespace App\Services;

use App\Jobs\ProcessTrackingCtrBatchJob;
use App\Models\ActivityEcomUserAction;

class TrackingCtrBatchDispatcher
{
    private const BATCH_SIZE = 50;

    private const MAX_BATCHES_PER_RUN = 20;

    public function __construct(
        private readonly TrackingCtrRollupService $rollupService,
    ) {}

    /**
     * @return array{queued_jobs: int, action_count: int}
     */
    public function dispatchPendingBatches(bool $sync = false): array
    {
        $batchSize = self::BATCH_SIZE;
        $maxBatches = self::MAX_BATCHES_PER_RUN;
        $maxActions = $batchSize * $maxBatches;

        $pendingIds = ActivityEcomUserAction::query()
            ->whereNull('ctr_tracking_status')
            ->whereIn('action_type', $this->rollupService->ctrActionTypes())
            ->orderBy('id')
            ->limit($maxActions)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pendingIds === []) {
            return ['queued_jobs' => 0, 'action_count' => 0];
        }

        $chunks = array_chunk($pendingIds, $batchSize);
        $queuedJobs = 0;

        foreach ($chunks as $actionIds) {
            if ($sync) {
                (new ProcessTrackingCtrBatchJob($actionIds))->handle($this->rollupService);
            } else {
                ProcessTrackingCtrBatchJob::dispatch($actionIds);
            }

            $queuedJobs++;
        }

        return [
            'queued_jobs' => $queuedJobs,
            'action_count' => count($pendingIds),
        ];
    }
}
