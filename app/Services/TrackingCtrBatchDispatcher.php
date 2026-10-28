<?php

namespace App\Services;

use App\Jobs\ProcessTrackingCtrBatchJob;
use App\Models\TrackingCtrCategoryImpression;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;

class TrackingCtrBatchDispatcher
{
    private const BATCH_SIZE = 50;

    private const MAX_BATCHES_PER_RUN = 20;

    /**
     * @return array{
     *     queued_jobs: int,
     *     category_impression_count: int,
     *     force_reset_impressions: int,
     *     force_daily_rows_deleted: int,
     * }
     */
    public function dispatchPendingBatches(
        bool $force = false,
        ?Carbon $from = null,
        ?Carbon $to = null,
        array $rollupSettings = [],
    ): array {
        $ctrRollupDays = max(1, (int) ($rollupSettings['ctr_days'] ?? config('tracker.ctr_rollup_days', 90)));
        $minClicks = max(1, (int) ($rollupSettings['min_clicks'] ?? config('tracker.ctr_min_clicks_fallback', 1)));

        $forceStats = [
            'force_reset_impressions' => 0,
            'force_daily_rows_deleted' => 0,
        ];

        if ($force && $from !== null) {
            $forceStats = $this->prepareForceResync($from, $to);
        }

        $batchSize = self::BATCH_SIZE;
        $maxBatches = self::MAX_BATCHES_PER_RUN;
        $maxRows = $batchSize * $maxBatches;

        $pendingCategoryImpressionIds = TrackingCtrCategoryImpression::query()
            ->whereNull('ctr_rollup_status')
            ->orderBy('id')
            ->limit($maxRows)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if ($pendingCategoryImpressionIds === []) {
            return array_merge([
                'queued_jobs' => 0,
                'category_impression_count' => 0,
            ], $forceStats);
        }

        $queuedJobs = 0;

        foreach (array_chunk($pendingCategoryImpressionIds, $batchSize) as $impressionIds) {
            ProcessTrackingCtrBatchJob::dispatch($impressionIds, $ctrRollupDays, $minClicks);
            $queuedJobs++;
        }

        return array_merge([
            'queued_jobs' => $queuedJobs,
            'category_impression_count' => count($pendingCategoryImpressionIds),
        ], $forceStats);
    }

    /**
     * @return array{force_reset_impressions: int, force_daily_rows_deleted: int}
     */
    private function prepareForceResync(Carbon $from, ?Carbon $to): array
    {
        $tz = TrackerTime::timezone();
        $rangeStart = $from->copy()->timezone($tz)->startOfDay();
        $rangeEnd = ($to ?? $from)->copy()->timezone($tz)->endOfDay();

        if ($rangeEnd->lt($rangeStart)) {
            [$rangeStart, $rangeEnd] = [$rangeEnd->copy()->startOfDay(), $rangeStart->copy()->endOfDay()];
        }

        $fromDate = $rangeStart->toDateString();
        $toDate = $rangeEnd->toDateString();

        $utcStart = $rangeStart->copy()->utc();
        $utcEnd = $rangeEnd->copy()->utc();

        $resetImpressions = TrackingCtrCategoryImpression::query()
            ->whereBetween('created_at', [$utcStart, $utcEnd])
            ->update(['ctr_rollup_status' => null]);

        $deletedDaily = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->delete();

        return [
            'force_reset_impressions' => (int) $resetImpressions,
            'force_daily_rows_deleted' => (int) $deletedDaily,
        ];
    }
}
