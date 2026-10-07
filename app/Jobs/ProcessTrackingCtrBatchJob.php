<?php

namespace App\Jobs;

use App\Services\TrackingCtrRollupService;
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
     */
    public function __construct(
        public readonly array $actionIds,
    ) {
        $this->onConnection((string) config('tracker.ctr_queue_connection', 'database'));
        $this->onQueue((string) config('tracker.ctr_queue_name', 'default'));
    }

    public function handle(TrackingCtrRollupService $rollupService): void
    {
        try {
            $result = $rollupService->processActionIds($this->actionIds);

            Log::info('CTR:tracking batch completed', [
                'action_ids' => $this->actionIds,
                'processed' => $result['processed'],
                'skipped' => $result['skipped'],
            ]);
        } catch (Throwable $exception) {
            Log::error('CTR:tracking job failed', [
                'action_ids' => $this->actionIds,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
