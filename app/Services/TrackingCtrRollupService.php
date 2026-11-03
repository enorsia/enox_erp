<?php

namespace App\Services;

use App\Models\TrackingCtrCategory;
use App\Models\TrackingCtrCategoryImpression;
use App\Support\TrackerDatabase;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackingCtrRollupService
{
    /**
     * Rebuild tracking_ctr_summaries from existing daily rows in the date range.
     * Reads tracking_ctr_daily_summaries only; does not insert, update, or delete that table.
     *
     * @return array{upserted: int, categories: int}
     */
    public function rebuildSummariesFromDailyRange(string $fromDate, string $toDate, int $minClicks): array
    {
        $minClicks = max(1, $minClicks);
        $upserted = 0;
        $categories = 0;

        $categoryIds = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->whereBetween('metric_date', [$fromDate, $toDate])
            ->distinct()
            ->orderBy('tracking_ctr_category_id')
            ->pluck('tracking_ctr_category_id');

        foreach ($categoryIds as $categoryId) {
            $trackingCtrCategoryId = (int) $categoryId;
            $rows = TrackerDatabase::table('tracking_ctr_daily_summaries')
                ->where('tracking_ctr_category_id', $trackingCtrCategoryId)
                ->whereBetween('metric_date', [$fromDate, $toDate])
                ->groupBy('tracking_ctr_category_id', 'product_code')
                ->select('tracking_ctr_category_id', 'product_code')
                ->selectRaw('MAX(department_id) as department_id')
                ->selectRaw('MAX(department_slug) as department_slug')
                ->selectRaw('MAX(category_id) as category_id')
                ->selectRaw('MAX(category_slug) as category_slug')
                ->selectRaw('MAX(micro_category_id) as micro_category_id')
                ->selectRaw('MAX(micro_category_slug) as micro_category_slug')
                ->selectRaw('MAX(type) as type')
                ->selectRaw('COALESCE(SUM(total_click), 0) as total_click')
                ->selectRaw('COALESCE(SUM(total_impression), 0) as total_impression')
                ->get();

            if ($rows->isEmpty()) {
                continue;
            }

            $categories++;
            $categoryClicks = 0;
            $categoryImpressions = 0;

            foreach ($rows as $row) {
                $row->total_click = $this->clicksWithinImpressions(
                    (int) $row->total_click,
                    (int) $row->total_impression,
                );
                $categoryClicks += (int) $row->total_click;
                $categoryImpressions += (int) $row->total_impression;
            }

            $ctrAverage = $this->ctrValue($categoryClicks, $categoryImpressions);
            $now = now();
            $payload = [];

            foreach ($rows as $row) {
                $productCode = trim((string) $row->product_code);

                if ($productCode === '') {
                    continue;
                }

                $totalClick = (int) $row->total_click;
                $totalImpression = (int) $row->total_impression;
                $ctr = $totalClick < $minClicks
                    ? 0.0
                    : $this->ctrValue($totalClick, $totalImpression);

                $payload[] = [
                    'tracking_ctr_category_id' => $trackingCtrCategoryId,
                    'department_id' => (int) ($row->department_id ?? 0),
                    'department_slug' => $this->nullableTrimmedString($row->department_slug),
                    'category_id' => (int) ($row->category_id ?? 0),
                    'category_slug' => $this->nullableTrimmedString($row->category_slug),
                    'micro_category_id' => $row->micro_category_id !== null ? (int) $row->micro_category_id : null,
                    'micro_category_slug' => $this->nullableTrimmedString($row->micro_category_slug),
                    'type' => $this->nullableTrimmedString($row->type) ?? TrackingCtrCategory::TYPE_CATEGORY,
                    'product_code' => $productCode,
                    'total_click' => $totalClick,
                    'total_impression' => $totalImpression,
                    'ctr' => $ctr,
                    'ctr_average' => $totalClick > 0 ? $ctrAverage : 0.0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($payload, 200) as $chunk) {
                TrackerDatabase::table('tracking_ctr_summaries')->upsert(
                    $chunk,
                    ['tracking_ctr_category_id', 'product_code'],
                    [
                        'department_id',
                        'department_slug',
                        'category_id',
                        'category_slug',
                        'micro_category_id',
                        'micro_category_slug',
                        'type',
                        'total_click',
                        'total_impression',
                        'ctr',
                        'ctr_average',
                        'updated_at',
                    ],
                );
                $upserted += count($chunk);
            }
        }

        return [
            'upserted' => $upserted,
            'categories' => $categories,
        ];
    }

    /**
     * @param  array<int, int>  $impressionIds
     * @return array{processed: int, skipped: int, touched_keys: array<int, array{tracking_ctr_category_id: int, product_code: string, department_id: int, category_id: int, micro_category_id: ?int, department_slug: ?string, category_slug: ?string, micro_category_slug: ?string, type: ?string}>}
     */
    public function processCategoryImpressionIds(
        array $impressionIds,
        int $ctrRollupDays = 90,
        int $minClicks = 1,
    ): array {
        $ids = array_values(array_unique(array_filter(array_map('intval', $impressionIds))));

        if ($ids === []) {
            return ['processed' => 0, 'skipped' => 0, 'touched_keys' => []];
        }

        $ctrRollupDays = max(1, $ctrRollupDays);
        $minClicks = max(1, $minClicks);

        try {
            return TrackerDatabase::connection()->transaction(function () use ($ids, $ctrRollupDays, $minClicks) {
                $rows = TrackingCtrCategoryImpression::query()
                    ->whereIn('id', $ids)
                    ->whereNull('ctr_rollup_status')
                    ->with('ctrCategory')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $processedIds = [];
                $skipped = 0;
                $skippedUnprocessableIds = [];
                $touchedKeys = [];
                $dailyDeltas = [];

                foreach ($rows as $row) {
                    $dimension = $this->dimensionForCategoryImpression($row);

                    if ($dimension === null) {
                        $skipped++;
                        $skippedUnprocessableIds[] = (int) $row->id;

                        continue;
                    }

                    $deltaKey = $this->dailyDeltaKey($dimension);

                    if (! isset($dailyDeltas[$deltaKey])) {
                        $dailyDeltas[$deltaKey] = [
                            'metric_date' => $dimension['metric_date'],
                            'tracking_ctr_category_id' => $dimension['tracking_ctr_category_id'],
                            'department_id' => $dimension['department_id'],
                            'department_slug' => $dimension['department_slug'],
                            'category_id' => $dimension['category_id'],
                            'category_slug' => $dimension['category_slug'],
                            'micro_category_id' => $dimension['micro_category_id'],
                            'micro_category_slug' => $dimension['micro_category_slug'],
                            'type' => $dimension['type'],
                            'product_code' => $dimension['product_code'],
                            'impressions' => 0,
                            'clicks' => 0,
                        ];
                    }

                    if ((int) $row->type === TrackingCtrCategoryImpression::TYPE_IMPRESSION) {
                        $dailyDeltas[$deltaKey]['impressions']++;
                    } elseif ((int) $row->type === TrackingCtrCategoryImpression::TYPE_CLICK) {
                        $dailyDeltas[$deltaKey]['clicks']++;
                    }

                    $processedIds[] = (int) $row->id;

                    $summaryKey = $dimension['tracking_ctr_category_id'].'|'.$dimension['product_code'];
                    $touchedKeys[$summaryKey] = [
                        'tracking_ctr_category_id' => $dimension['tracking_ctr_category_id'],
                        'department_id' => $dimension['department_id'],
                        'department_slug' => $dimension['department_slug'],
                        'category_id' => $dimension['category_id'],
                        'category_slug' => $dimension['category_slug'],
                        'micro_category_id' => $dimension['micro_category_id'],
                        'micro_category_slug' => $dimension['micro_category_slug'],
                        'type' => $dimension['type'],
                        'product_code' => $dimension['product_code'],
                    ];
                }

                $dailyCategoryDates = [];

                foreach ($dailyDeltas as $delta) {
                    $this->applyDailyDeltaAtomic($delta);
                    $dailyCategoryDates[$delta['tracking_ctr_category_id'].'|'.$delta['metric_date']] = [
                        'tracking_ctr_category_id' => (int) $delta['tracking_ctr_category_id'],
                        'metric_date' => $delta['metric_date'],
                    ];
                }

                foreach ($dailyCategoryDates as $categoryDate) {
                    $this->refreshDailyCtrAverageForCategory(
                        $categoryDate['tracking_ctr_category_id'],
                        $categoryDate['metric_date'],
                    );
                }

                if ($processedIds !== []) {
                    TrackingCtrCategoryImpression::query()
                        ->whereIn('id', $processedIds)
                        ->whereNull('ctr_rollup_status')
                        ->update(['ctr_rollup_status' => true]);
                }

                if ($skippedUnprocessableIds !== []) {
                    TrackingCtrCategoryImpression::query()
                        ->whereIn('id', $skippedUnprocessableIds)
                        ->whereNull('ctr_rollup_status')
                        ->update(['ctr_rollup_status' => false]);
                }

                $summaryCategoryIds = [];

                foreach ($touchedKeys as $key) {
                    $this->refreshSummaryForProductCode(
                        (int) $key['tracking_ctr_category_id'],
                        $key['product_code'],
                        $ctrRollupDays,
                        $minClicks,
                        $key['department_id'],
                        $key['category_id'],
                        $key['department_slug'],
                        $key['category_slug'],
                        $key['type'],
                        $key['micro_category_id'],
                        $key['micro_category_slug'],
                    );
                    $summaryCategoryIds[(int) $key['tracking_ctr_category_id']] = true;
                }

                $fromDate = Carbon::now(TrackerTime::timezone())
                    ->subDays(max(1, $ctrRollupDays) - 1)
                    ->startOfDay()
                    ->toDateString();

                foreach (array_keys($summaryCategoryIds) as $trackingCtrCategoryId) {
                    $this->refreshSummaryCtrAverageForCategory((int) $trackingCtrCategoryId, $fromDate);
                }

                return [
                    'processed' => count($processedIds),
                    'skipped' => $skipped,
                    'touched_keys' => array_values($touchedKeys),
                ];
            });
        } catch (Throwable $exception) {
            $this->logError('category impression batch transaction rolled back', [
                'impression_ids' => $ids,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array{
     *     metric_date: string,
     *     tracking_ctr_category_id: int,
     *     department_id: int,
     *     department_slug: ?string,
     *     category_id: int,
     *     category_slug: ?string,
     *     micro_category_id: ?int,
     *     micro_category_slug: ?string,
     *     type: ?string,
     *     product_code: string,
     * }|null
     */
    private function dimensionForCategoryImpression(TrackingCtrCategoryImpression $row): ?array
    {
        $productCode = trim((string) ($row->product_code ?? ''));

        if ($productCode === '') {
            return null;
        }

        $category = $row->ctrCategory;

        if ($category === null) {
            return null;
        }

        $departmentId = max(0, (int) ($category->department_id ?? 0));
        $categoryId = max(0, (int) ($category->category_id ?? 0));

        if ($categoryId <= 0) {
            return null;
        }

        $trackingCtrCategoryId = (int) ($row->tracking_ctr_category_id ?? $category->id);
        if ($trackingCtrCategoryId <= 0) {
            return null;
        }

        $microCategoryId = $category->micro_category_id ?? $row->micro_category_id;
        $microCategoryId = $microCategoryId === null || $microCategoryId === ''
            ? null
            : (int) $microCategoryId;

        return [
            'metric_date' => $this->metricDateForTimestamp($row->created_at),
            'tracking_ctr_category_id' => $trackingCtrCategoryId,
            'department_id' => $departmentId,
            'department_slug' => $this->nullableTrimmedString($category->department_slug),
            'category_id' => $categoryId,
            'category_slug' => $this->nullableTrimmedString($category->category_slug),
            'micro_category_id' => $microCategoryId,
            'micro_category_slug' => $this->nullableTrimmedString($category->micro_category_slug ?? $row->micro_category_slug),
            'type' => $this->nullableTrimmedString($category->type)
                ?? TrackingCtrCategory::TYPE_CATEGORY,
            'product_code' => $productCode,
        ];
    }

    private function metricDateForTimestamp(mixed $candidate): string
    {
        $utc = TrackerTime::toUtc($candidate);

        if ($utc !== null) {
            return $utc->timezone(TrackerTime::timezone())->toDateString();
        }

        return TrackerTime::localNow()->toDateString();
    }

    /**
     * @param  array{
     *     metric_date: string,
     *     tracking_ctr_category_id: int,
     *     department_id: int,
     *     department_slug: ?string,
     *     category_id: int,
     *     category_slug: ?string,
     *     micro_category_id: ?int,
     *     micro_category_slug: ?string,
     *     type: ?string,
     *     product_code: string,
     *     impressions: int,
     *     clicks: int,
     * }  $delta
     */
    private function applyDailyDeltaAtomic(array $delta): void
    {
        $impressions = max(0, (int) $delta['impressions']);
        $clicks = max(0, (int) $delta['clicks']);

        if ($impressions === 0 && $clicks === 0) {
            return;
        }

        $query = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('metric_date', $delta['metric_date'])
            ->where('tracking_ctr_category_id', $delta['tracking_ctr_category_id'])
            ->where('product_code', $delta['product_code']);

        $updated = (clone $query)->update([
            'department_slug' => $delta['department_slug'],
            'category_slug' => $delta['category_slug'],
            'micro_category_slug' => $delta['micro_category_slug'],
            'type' => $delta['type'],
            'total_impression' => DB::raw('total_impression + ' . $impressions),
            'total_click' => DB::raw('total_click + ' . $clicks),
            'ctr' => DB::raw(
                'CASE WHEN (total_impression + ' . $impressions . ') > 0 '
                . 'THEN ROUND((total_click + ' . $clicks . ') / (total_impression + ' . $impressions . '), 6) '
                . 'ELSE 0 END',
            ),
            'updated_at' => now(),
        ]);

        if ($updated > 0) {
            $this->reconcileDailyProductCtr($delta);

            return;
        }

        $ctr = $this->ctrValue($clicks, $impressions);

        TrackerDatabase::table('tracking_ctr_daily_summaries')->insert([
            'metric_date' => $delta['metric_date'],
            'tracking_ctr_category_id' => $delta['tracking_ctr_category_id'],
            'department_id' => $delta['department_id'],
            'department_slug' => $delta['department_slug'],
            'category_id' => $delta['category_id'],
            'category_slug' => $delta['category_slug'],
            'micro_category_id' => $delta['micro_category_id'],
            'micro_category_slug' => $delta['micro_category_slug'],
            'type' => $delta['type'],
            'product_code' => $delta['product_code'],
            'total_click' => $clicks,
            'total_impression' => $impressions,
            'ctr' => $ctr,
            'ctr_average' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->reconcileDailyProductCtr($delta);
    }

    /**
     * @param  array{metric_date: string, tracking_ctr_category_id: int, product_code: string}  $delta
     */
    private function reconcileDailyProductCtr(array $delta): void
    {
        TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('metric_date', $delta['metric_date'])
            ->where('tracking_ctr_category_id', $delta['tracking_ctr_category_id'])
            ->where('product_code', $delta['product_code'])
            ->update([
                'total_click' => DB::raw('LEAST(total_click, total_impression)'),
                'ctr' => DB::raw(
                    'CASE WHEN total_impression > 0 THEN ROUND(LEAST(total_click, total_impression) / total_impression, 6) ELSE 0 END',
                ),
                'updated_at' => now(),
            ]);
    }

    /**
     * Stored clicks must not exceed impressions. Extra clicks are set equal to the impression count.
     *
     * @return array{daily: int, summaries: int}
     */
    public function capClicksToImpressions(): array
    {
        $clickCap = 'LEAST(total_click, total_impression)';
        $ctr = 'CASE WHEN total_impression > 0 THEN ROUND('.$clickCap.' / total_impression, 6) ELSE 0 END';

        $daily = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->whereColumn('total_click', '>', 'total_impression')
            ->update([
                'total_click' => DB::raw($clickCap),
                'ctr' => DB::raw($ctr),
                'updated_at' => now(),
            ]);

        $summaries = TrackerDatabase::table('tracking_ctr_summaries')
            ->whereColumn('total_click', '>', 'total_impression')
            ->update([
                'total_click' => DB::raw($clickCap),
                'ctr' => DB::raw($ctr),
                'updated_at' => now(),
            ]);

        return [
            'daily' => (int) $daily,
            'summaries' => (int) $summaries,
        ];
    }

    private function refreshSummaryForProductCode(
        int $trackingCtrCategoryId,
        string $productCode,
        int $ctrRollupDays,
        int $minClicks,
        int $departmentId,
        int $categoryId,
        ?string $departmentSlug = null,
        ?string $categorySlug = null,
        ?string $type = null,
        ?int $microCategoryId = null,
        ?string $microCategorySlug = null,
    ): void {
        $rollupDays = max(1, $ctrRollupDays);
        $minClicks = max(1, $minClicks);
        $fromDate = Carbon::now(TrackerTime::timezone())
            ->subDays($rollupDays - 1)
            ->startOfDay()
            ->toDateString();

        $aggregate = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('tracking_ctr_category_id', $trackingCtrCategoryId)
            ->where('product_code', $productCode)
            ->where('metric_date', '>=', $fromDate)
            ->selectRaw('COALESCE(SUM(total_click), 0) as total_click')
            ->selectRaw('COALESCE(SUM(total_impression), 0) as total_impression')
            ->first();

        $totalImpression = (int) ($aggregate->total_impression ?? 0);
        $totalClick = $this->clicksWithinImpressions(
            (int) ($aggregate->total_click ?? 0),
            $totalImpression,
        );
        $ctr = $this->ctrValue($totalClick, $totalImpression);

        if ($totalClick < $minClicks) {
            $ctr = 0.0;
        }

        TrackerDatabase::table('tracking_ctr_summaries')->upsert(
            [[
                'tracking_ctr_category_id' => $trackingCtrCategoryId,
                'department_id' => $departmentId,
                'department_slug' => $departmentSlug,
                'category_id' => $categoryId,
                'category_slug' => $categorySlug,
                'micro_category_id' => $microCategoryId,
                'micro_category_slug' => $microCategorySlug,
                'type' => $type ?? TrackingCtrCategory::TYPE_CATEGORY,
                'product_code' => $productCode,
                'total_click' => $totalClick,
                'total_impression' => $totalImpression,
                'ctr' => $ctr,
                'ctr_average' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['tracking_ctr_category_id', 'product_code'],
            [
                'department_slug',
                'category_slug',
                'micro_category_slug',
                'type',
                'total_click',
                'total_impression',
                'ctr',
                'ctr_average',
                'updated_at',
            ],
        );
    }

    private function refreshDailyCtrAverageForCategory(int $trackingCtrCategoryId, string $metricDate): void
    {
        $aggregate = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('tracking_ctr_category_id', $trackingCtrCategoryId)
            ->where('metric_date', $metricDate)
            ->selectRaw('COALESCE(SUM(total_click), 0) as total_click')
            ->selectRaw('COALESCE(SUM(total_impression), 0) as total_impression')
            ->first();

        $ctrAverage = $this->ctrValue(
            (int) ($aggregate->total_click ?? 0),
            (int) ($aggregate->total_impression ?? 0),
        );

        TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('tracking_ctr_category_id', $trackingCtrCategoryId)
            ->where('metric_date', $metricDate)
            ->update([
                'ctr_average' => DB::raw(
                    'CASE WHEN total_click > 0 THEN '.sprintf('%.6F', $ctrAverage).' ELSE 0 END',
                ),
                'updated_at' => now(),
            ]);
    }

    private function refreshSummaryCtrAverageForCategory(int $trackingCtrCategoryId, string $fromDate): void
    {
        $aggregate = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('tracking_ctr_category_id', $trackingCtrCategoryId)
            ->where('metric_date', '>=', $fromDate)
            ->selectRaw('COALESCE(SUM(total_click), 0) as total_click')
            ->selectRaw('COALESCE(SUM(total_impression), 0) as total_impression')
            ->first();

        $ctrAverage = $this->ctrValue(
            (int) ($aggregate->total_click ?? 0),
            (int) ($aggregate->total_impression ?? 0),
        );

        TrackerDatabase::table('tracking_ctr_summaries')
            ->where('tracking_ctr_category_id', $trackingCtrCategoryId)
            ->update([
                'ctr_average' => DB::raw(
                    'CASE WHEN total_click > 0 THEN '.sprintf('%.6F', $ctrAverage).' ELSE 0 END',
                ),
                'updated_at' => now(),
            ]);
    }

    private function clicksWithinImpressions(int $clicks, int $impressions): int
    {
        $clicks = max(0, $clicks);
        $impressions = max(0, $impressions);

        return $clicks > $impressions ? $impressions : $clicks;
    }

    private function ctrValue(int $clicks, int $impressions): float
    {
        if ($impressions <= 0) {
            return 0.0;
        }

        return round($clicks / $impressions, 6);
    }

    /**
     * @param  array{metric_date: string, tracking_ctr_category_id: int, product_code: string}  $dimension
     */
    private function dailyDeltaKey(array $dimension): string
    {
        return implode('|', [
            $dimension['metric_date'],
            $dimension['tracking_ctr_category_id'],
            $dimension['product_code'],
        ]);
    }

    private function nullableTrimmedString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error('CTR:tracking ' . $message, $context);
    }
}
