<?php

namespace App\Services;

use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class TrackingCtrRollupService
{
    /**
     * @param  array<int, int>  $actionIds
     * @return array{processed: int, skipped: int, touched_keys: array<int, array{department_id: int, category_id: int, sku: string}>}
     */
    public function processActionIds(array $actionIds): array
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $actionIds))));

        if ($ids === []) {
            return ['processed' => 0, 'skipped' => 0, 'touched_keys' => []];
        }

        try {
            return DB::transaction(function () use ($ids) {
                $actions = ActivityEcomUserAction::query()
                    ->whereIn('id', $ids)
                    ->whereNull('ctr_tracking_status')
                    ->whereIn('action_type', $this->ctrActionTypes())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();

                $processedIds = [];
                $skipped = 0;
                $touchedKeys = [];
                $dailyDeltas = [];

                foreach ($actions as $action) {
                    $dimension = $this->dimensionForAction($action);

                    if ($dimension === null) {
                        $skipped++;

                        continue;
                    }

                    $deltaKey = $this->dailyDeltaKey($dimension);

                    if (! isset($dailyDeltas[$deltaKey])) {
                        $dailyDeltas[$deltaKey] = [
                            'metric_date' => $dimension['metric_date'],
                            'department_id' => $dimension['department_id'],
                            'category_id' => $dimension['category_id'],
                            'product_code' => $dimension['product_code'],
                            'sku' => $dimension['sku'],
                            'impressions' => 0,
                            'clicks' => 0,
                        ];
                    }

                    if ($action->action_type === 'grid_impression') {
                        $dailyDeltas[$deltaKey]['impressions']++;
                    } elseif ($action->action_type === 'product_click') {
                        $dailyDeltas[$deltaKey]['clicks']++;
                    }

                    $processedIds[] = (int) $action->id;

                    $summaryKey = $dimension['department_id'] . '|' . $dimension['category_id'] . '|' . $dimension['product_code'] . '|' . $dimension['sku'];
                    $touchedKeys[$summaryKey] = [
                        'department_id' => $dimension['department_id'],
                        'category_id' => $dimension['category_id'],
                        'product_code' => $dimension['product_code'],
                        'sku' => $dimension['sku'],
                    ];
                }

                foreach ($dailyDeltas as $delta) {
                    $this->applyDailyDeltaAtomic($delta);
                }

                if ($processedIds !== []) {
                    ActivityEcomUserAction::query()
                        ->whereIn('id', $processedIds)
                        ->whereNull('ctr_tracking_status')
                        ->update(['ctr_tracking_status' => true]);
                }

                foreach ($touchedKeys as $key) {
                    $this->refreshSummaryForSku(
                        $key['department_id'],
                        $key['category_id'],
                        $key['product_code'],
                        $key['sku'],
                    );
                }

                return [
                    'processed' => count($processedIds),
                    'skipped' => $skipped,
                    'touched_keys' => array_values($touchedKeys),
                ];
            });
        } catch (Throwable $exception) {
            $this->logError('batch transaction rolled back', [
                'action_ids' => $ids,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }

    /**
     * @return array<int, string>
     */
    public function ctrActionTypes(): array
    {
        return ['grid_impression', 'product_click'];
    }

    /**
     * @return array{metric_date: string, department_id: int, category_id: int, sku: string}|null
     */
    private function dimensionForAction(ActivityEcomUserAction $action): ?array
    {
        $productCode = trim((string) ($action->product_code ?? ''));
        $sku = trim((string) ($action->sku ?? ''));

        if ($productCode === '' || $sku === '') {
            return null;
        }

        $departmentId = max(0, (int) trim((string) ($action->department_id ?? '')));
        $categoryId = max(0, (int) trim((string) ($action->category_id ?? '')));

        $createdAt = TrackerTime::toUtc($action->created_at);

        if (! $createdAt) {
            return null;
        }

        $metricDate = $createdAt
            ->timezone(TrackerTime::timezone())
            ->toDateString();

        return [
            'metric_date' => $metricDate,
            'department_id' => $departmentId,
            'category_id' => $categoryId,
            'product_code' => $productCode,
            'sku' => $sku,
        ];
    }

    /**
     * @param  array{metric_date: string, department_id: int, category_id: int, product_code: string, sku: string, impressions: int, clicks: int}  $delta
     */
    private function applyDailyDeltaAtomic(array $delta): void
    {
        $impressions = max(0, (int) $delta['impressions']);
        $clicks = max(0, (int) $delta['clicks']);

        if ($impressions === 0 && $clicks === 0) {
            return;
        }

        $query = DB::table('tracking_ctr_daily_summaries')
            ->where('metric_date', $delta['metric_date'])
            ->where('department_id', $delta['department_id'])
            ->where('category_id', $delta['category_id'])
            ->where('product_code', $delta['product_code'])
            ->where('sku', $delta['sku']);

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

        DB::table('tracking_ctr_daily_summaries')->insert([
            'metric_date' => $delta['metric_date'],
            'department_id' => $delta['department_id'],
            'category_id' => $delta['category_id'],
            'product_id' => null,
            'product_code' => $delta['product_code'],
            'sku' => $delta['sku'],
            'total_click' => $clicks,
            'total_impression' => $impressions,
            'ctr' => $ctr,
            'ctr_average' => $ctr,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function refreshSummaryForSku(
        int $departmentId,
        int $categoryId,
        string $productCode,
        string $sku,
    ): void {
        $rollupDays = max(1, (int) config('tracker.ctr_rollup_days', 90));
        $fromDate = Carbon::now(TrackerTime::timezone())
            ->subDays($rollupDays - 1)
            ->startOfDay()
            ->toDateString();

        $aggregate = DB::table('tracking_ctr_daily_summaries')
            ->where('department_id', $departmentId)
            ->where('category_id', $categoryId)
            ->where('product_code', $productCode)
            ->where('sku', $sku)
            ->where('metric_date', '>=', $fromDate)
            ->selectRaw('COALESCE(SUM(total_click), 0) as total_click')
            ->selectRaw('COALESCE(SUM(total_impression), 0) as total_impression')
            ->selectRaw('COALESCE(AVG(ctr), 0) as ctr_average')
            ->first();

        $totalClick = (int) ($aggregate->total_click ?? 0);
        $totalImpression = (int) ($aggregate->total_impression ?? 0);
        $ctr = $this->ctrValue($totalClick, $totalImpression);
        $ctrAverage = round((float) ($aggregate->ctr_average ?? 0), 6);

        DB::table('tracking_ctr_summaries')->upsert(
            [[
                'department_id' => $departmentId,
                'category_id' => $categoryId,
                'product_id' => null,
                'product_code' => $productCode,
                'sku' => $sku,
                'total_click' => $totalClick,
                'total_impression' => $totalImpression,
                'ctr' => $ctr,
                'ctr_average' => $ctrAverage,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['department_id', 'category_id', 'product_code', 'sku'],
            ['product_id', 'total_click', 'total_impression', 'ctr', 'ctr_average', 'updated_at'],
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
     * @param  array{metric_date: string, department_id: int, category_id: int, sku: string}  $dimension
     */
    private function dailyDeltaKey(array $dimension): string
    {
        return implode('|', [
            $dimension['metric_date'],
            $dimension['department_id'],
            $dimension['category_id'],
            $dimension['product_code'],
            $dimension['sku'],
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
