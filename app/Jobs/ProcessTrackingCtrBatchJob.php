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
     * @param  array<int, int>  $categoryImpressionIds
     */
    public function __construct(
        public readonly array $categoryImpressionIds = [],
        public readonly int $ctrRollupDays = 90,
        public readonly int $minClicks = 1,
    ) {
        $this->onConnection(TrackerQueueSupport::connection());
        $this->onQueue(TrackerQueueSupport::queue());
    }

    public function handle(TrackingCtrRollupService $rollupService): void
    {
        try {
            $impressionResult = ['processed' => 0, 'skipped' => 0];

            if ($this->categoryImpressionIds !== []) {
                $impressionResult = $rollupService->processCategoryImpressionIds(
                    $this->categoryImpressionIds,
                    $this->ctrRollupDays,
                    $this->minClicks,
                );
            }

            try {
                Log::info('CTR:tracking batch completed', [
                    'category_impression_ids' => $this->categoryImpressionIds,
                    'category_impressions_processed' => $impressionResult['processed'],
                    'category_impressions_skipped' => $impressionResult['skipped'],
                ]);
            } catch (Throwable) {
                // Rollup already committed; do not fail the job when storage/logs is not writable.
            }
        } catch (Throwable $exception) {
            try {
                Log::error('CTR:tracking job failed', [
                    'category_impression_ids' => $this->categoryImpressionIds,
                    'error' => $exception->getMessage(),
                ]);
            } catch (Throwable) {
            }

            throw $exception;
        }
    }
}
