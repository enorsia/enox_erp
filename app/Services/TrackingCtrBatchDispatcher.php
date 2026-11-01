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

    public function __construct(
        private TrackingCtrSyncSiteSettingsService $siteSettings,
    ) {}

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
        $rollup = $this->siteSettings->resolveRollupSettings($rollupSettings);
        $ctrRollupDays = $rollup['ctr_days'];
        $minClicks = $rollup['min_clicks'];

        $forceStats = [
            'force_reset_impressions' => 0,
            'force_daily_rows_deleted' => 0,
        ];

        if ($force && $from !== null) {
            $forceStats = $this->prepareForceResync($from, $to);
        }

        $queuedJobs = 0;
        $categoryImpressionCount = 0;

        TrackingCtrCategoryImpression::query()
            ->whereNull('ctr_rollup_status')
            ->select('id')
            ->chunkById(
                self::BATCH_SIZE,
                function ($records) use (
                    $ctrRollupDays,
                    $minClicks,
                    &$queuedJobs,
                    &$categoryImpressionCount
                ): void {
                    $impressionIds = $records
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if ($impressionIds === []) {
                        return;
                    }

                    ProcessTrackingCtrBatchJob::dispatch(
                        $impressionIds,
                        $ctrRollupDays,
                        $minClicks
                    );

                    $queuedJobs++;
                    $categoryImpressionCount += count($impressionIds);
                },
                'id'
            );

        return array_merge([
            'queued_jobs' => $queuedJobs,
            'category_impression_count' => $categoryImpressionCount,
        ], $forceStats);
    }

    /**
     * Same pending CTR rows as {@see dispatchPendingBatches}, but runs each batch job inline (no queue worker).
     *
     * @return array{
     *     processed_batches: int,
     *     category_impression_count: int,
     *     force_reset_impressions: int,
     *     force_daily_rows_deleted: int,
     * }
     */
    public function processPendingBatchesSynchronously(
        bool $force = false,
        ?Carbon $from = null,
        ?Carbon $to = null,
        array $rollupSettings = [],
    ): array {
        $rollup = $this->siteSettings->resolveRollupSettings($rollupSettings);
        $ctrRollupDays = $rollup['ctr_days'];
        $minClicks = $rollup['min_clicks'];

        $forceStats = [
            'force_reset_impressions' => 0,
            'force_daily_rows_deleted' => 0,
        ];

        if ($force && $from !== null) {
            $forceStats = $this->prepareForceResync($from, $to);
        }

        $processedBatches = 0;
        $categoryImpressionCount = 0;

        TrackingCtrCategoryImpression::query()
            ->whereNull('ctr_rollup_status')
            ->select('id')
            ->chunkById(
                self::BATCH_SIZE,
                function ($records) use (
                    $ctrRollupDays,
                    $minClicks,
                    &$processedBatches,
                    &$categoryImpressionCount
                ): void {
                    $impressionIds = $records
                        ->pluck('id')
                        ->map(fn ($id) => (int) $id)
                        ->all();

                    if ($impressionIds === []) {
                        return;
                    }

                    ProcessTrackingCtrBatchJob::dispatchSync(
                        $impressionIds,
                        $ctrRollupDays,
                        $minClicks,
                    );

                    $processedBatches++;
                    $categoryImpressionCount += count($impressionIds);
                },
                'id'
            );

        return array_merge([
            'processed_batches' => $processedBatches,
            'category_impression_count' => $categoryImpressionCount,
        ], $forceStats);
    }

    /**
     * Run {@see processPendingBatchesSynchronously} until no pending impression rows remain.
     *
     * @return array{
     *     processed_batches: int,
     *     category_impression_count: int,
     *     force_reset_impressions: int,
     *     force_daily_rows_deleted: int,
     * }
     */
    public function processAllPendingSynchronously(
        bool $force = false,
        ?Carbon $from = null,
        ?Carbon $to = null,
        array $rollupSettings = [],
    ): array {
        $totals = [
            'processed_batches' => 0,
            'category_impression_count' => 0,
            'force_reset_impressions' => 0,
            'force_daily_rows_deleted' => 0,
        ];

        $maxPasses = 500;
        $pass = 0;

        while (
            $pass < $maxPasses
            && TrackingCtrCategoryImpression::query()->whereNull('ctr_rollup_status')->exists()
        ) {
            $pass++;
            $result = $this->processPendingBatchesSynchronously($force, $from, $to, $rollupSettings);

            $totals['processed_batches'] += (int) $result['processed_batches'];
            $totals['category_impression_count'] += (int) $result['category_impression_count'];
            $totals['force_reset_impressions'] += (int) $result['force_reset_impressions'];
            $totals['force_daily_rows_deleted'] += (int) $result['force_daily_rows_deleted'];

            if ((int) $result['category_impression_count'] === 0) {
                break;
            }

            $force = false;
            $from = null;
            $to = null;
        }

        return $totals;
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
