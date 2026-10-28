<?php

namespace App\Services;

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
     * @param  array<int, int>  $impressionIds
     * @return array{processed: int, skipped: int, touched_keys: array<int, array{department_id: int, category_id: int, product_code: string}>}
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
                            'department_id' => $dimension['department_id'],
                            'category_id' => $dimension['category_id'],
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

                    $summaryKey = $dimension['department_id'] . '|' . $dimension['category_id'] . '|' . $dimension['product_code'];
                    $touchedKeys[$summaryKey] = [
                        'department_id' => $dimension['department_id'],
                        'category_id' => $dimension['category_id'],
                        'product_code' => $dimension['product_code'],
                    ];
                }

                foreach ($dailyDeltas as $delta) {
                    $this->applyDailyDeltaAtomic($delta);
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

                foreach ($touchedKeys as $key) {
                    $this->refreshSummaryForProductCode(
                        $key['department_id'],
                        $key['category_id'],
                        $key['product_code'],
                        $ctrRollupDays,
                        $minClicks,
                    );
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
     * @return array{metric_date: string, department_id: int, category_id: int, product_code: string}|null
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

        return [
            'metric_date' => $this->metricDateForTimestamp($row->created_at),
            'department_id' => $departmentId,
            'category_id' => $categoryId,
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
     * @param  array{metric_date: string, department_id: int, category_id: int, product_code: string, impressions: int, clicks: int}  $delta
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
            ->where('department_id', $delta['department_id'])
            ->where('category_id', $delta['category_id'])
            ->where('product_code', $delta['product_code']);

        $updated = (clone $query)->update([
            'total_impression' => DB::raw('total_impression + ' . $impressions),
            'total_click' => DB::raw('total_click + ' . $clicks),
            'ctr' => DB::raw(
                'CASE WHEN (total_impression + ' . $impressions . ') > 0 '
                . 'THEN ROUND((total_click + ' . $clicks . ') / (total_impression + ' . $impressions . '), 6) '
                . 'ELSE 0 END',
            ),
            'ctr_average' => DB::raw(
                'CASE WHEN (total_impression + ' . $impressions . ') > 0 '
                . 'THEN ROUND((total_click + ' . $clicks . ') / (total_impression + ' . $impressions . '), 6) '
                . 'ELSE 0 END',
            ),
            'updated_at' => now(),
        ]);

        if ($updated > 0) {
            return;
        }

        $ctr = $this->ctrValue($clicks, $impressions);

        TrackerDatabase::table('tracking_ctr_daily_summaries')->insert([
            'metric_date' => $delta['metric_date'],
            'department_id' => $delta['department_id'],
            'category_id' => $delta['category_id'],
            'product_code' => $delta['product_code'],
            'total_click' => $clicks,
            'total_impression' => $impressions,
            'ctr' => $ctr,
            'ctr_average' => $ctr,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function refreshSummaryForProductCode(
        int $departmentId,
        int $categoryId,
        string $productCode,
        int $ctrRollupDays,
        int $minClicks,
    ): void {
        $rollupDays = max(1, $ctrRollupDays);
        $minClicks = max(1, $minClicks);
        $fromDate = Carbon::now(TrackerTime::timezone())
            ->subDays($rollupDays - 1)
            ->startOfDay()
            ->toDateString();

        $aggregate = TrackerDatabase::table('tracking_ctr_daily_summaries')
            ->where('department_id', $departmentId)
            ->where('category_id', $categoryId)
            ->where('product_code', $productCode)
            ->where('metric_date', '>=', $fromDate)
            ->selectRaw('COALESCE(SUM(total_click), 0) as total_click')
            ->selectRaw('COALESCE(SUM(total_impression), 0) as total_impression')
            ->selectRaw('COALESCE(AVG(ctr), 0) as ctr_average')
            ->first();

        $totalClick = (int) ($aggregate->total_click ?? 0);
        $totalImpression = (int) ($aggregate->total_impression ?? 0);
        $ctr = $this->ctrValue($totalClick, $totalImpression);
        $ctrAverage = round((float) ($aggregate->ctr_average ?? 0), 6);

        if ($totalClick < $minClicks) {
            $ctr = 0.0;
            $ctrAverage = 0.0;
        }

        TrackerDatabase::table('tracking_ctr_summaries')->upsert(
            [[
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'product_code' => $productCode,
                'total_click' => $totalClick,
                'total_impression' => $totalImpression,
                'ctr' => $ctr,
                'ctr_average' => $ctrAverage,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['department_id', 'category_id', 'product_code'],
            ['total_click', 'total_impression', 'ctr', 'ctr_average', 'updated_at'],
        );
    }

    private function ctrValue(int $clicks, int $impressions): float
    {
        if ($impressions <= 0) {
            return 0.0;
        }

        return round($clicks / $impressions, 6);
    }

    /**
     * @param  array{metric_date: string, department_id: int, category_id: int, product_code: string}  $dimension
     */
    private function dailyDeltaKey(array $dimension): string
    {
        return implode('|', [
            $dimension['metric_date'],
            $dimension['department_id'],
            $dimension['category_id'],
            $dimension['product_code'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $context
     */
    private function logError(string $message, array $context = []): void
    {
        Log::error('CTR:tracking ' . $message, $context);
    }
}
