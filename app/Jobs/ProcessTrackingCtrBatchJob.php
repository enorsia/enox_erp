<?php

namespace App\Jobs;

use App\Services\TrackingCtrRollupService;
use App\Support\TrackerQueueSupport;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessTrackingCtrBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $actionIds
     * @param  array<int, int>  $categoryImpressionIds
     */
    public function __construct(
        public readonly array $actionIds = [],
        public readonly array $categoryImpressionIds = [],
    ) {
        $this->onConnection(TrackerQueueSupport::connection());
        $this->onQueue(TrackerQueueSupport::queue());
    }

    public function handle(TrackingCtrRollupService $rollupService): void
    {
        try {
            $actionResult = ['processed' => 0, 'skipped' => 0];
            $impressionResult = ['processed' => 0, 'skipped' => 0];

            if ($this->actionIds !== []) {
                $actionResult = $rollupService->processActionIds($this->actionIds);
            }

            if ($this->categoryImpressionIds !== []) {
                $impressionResult = $rollupService->processCategoryImpressionIds($this->categoryImpressionIds);
            }

            try {
                Log::info('CTR:tracking batch completed', [
                    'action_ids' => $this->actionIds,
                    'category_impression_ids' => $this->categoryImpressionIds,
                    'actions_processed' => $actionResult['processed'],
                    'actions_skipped' => $actionResult['skipped'],
                    'category_impressions_processed' => $impressionResult['processed'],
                    'category_impressions_skipped' => $impressionResult['skipped'],
                    'skip_samples' => $actionResult['skip_samples'] ?? [],
                ]);
            } catch (Throwable) {
                // Rollup already committed; do not fail the job when storage/logs is not writable.
            }
        } catch (Throwable $exception) {
            try {
                Log::error('CTR:tracking job failed', [
                    'action_ids' => $this->actionIds,
                    'category_impression_ids' => $this->categoryImpressionIds,
                    'error' => $exception->getMessage(),
                ]);
            } catch (Throwable) {
            }

            throw $exception;
        }
    }
}
