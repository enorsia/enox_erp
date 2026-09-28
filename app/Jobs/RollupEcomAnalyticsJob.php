<?php

namespace App\Jobs;

use App\Services\EcomDailyRollupService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RollupEcomAnalyticsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly string $metricDate,
    ) {}

    public function handle(EcomDailyRollupService $rollup): void
    {
        $rollup->rollupDateWithStatus($this->metricDate);
    }
}
