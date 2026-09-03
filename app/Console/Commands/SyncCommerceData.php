<?php

namespace App\Console\Commands;

use App\Models\ActivityEcomUserAction;
use App\Models\TrackerBackfillCheckpoint;
use App\Services\CommerceIngestWriter;
use App\Services\CommerceSyncValidator;
use App\Services\TrackerDataCleanupService;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Throwable;

class SyncCommerceData extends Command
{
    protected $signature = 'tracker:sync-commerce-data
                            {--dry-run : Report changes without writing}
                            {--from= : Start date YYYY-MM-DD}
                            {--to= : End date YYYY-MM-DD}
                            {--chunk-days= : Days per chunk}
                            {--batch-size= : Actions per DB transaction}
                            {--resume : Continue from checkpoints}
                            {--validate : Run integrity checks after each chunk}
                            {--only= : Limit stages: payments,cart,checkout,views}
                            {--with-cleanup : Run cleanup before sync}
                            {--skip-dedupe-payments : Skip payment dedupe during cleanup}
                            {--skip-orphan-sessions : Skip orphan session removal}
                            {--skip-customer-backfill : Skip customer backfill}
                            {--skip-actions-count-backfill : Skip recounting actions_count from user_actions}
                            {--include-empty-sessions : Remove empty sessions during cleanup}
                            {--cleanup-only : Run cleanup only}';

    protected $description = 'One-time sync of historical commerce JSON into normalized orders and line-item tables.';

    private const JOB_NAME = 'commerce_backfill';

    public function handle(
        CommerceIngestWriter $writer,
        CommerceSyncValidator $validator,
        TrackerDataCleanupService $cleanup,
    ): int {
        if ($this->option('with-cleanup') || $this->option('cleanup-only')) {
            $this->runCleanup($cleanup);
            if ($this->option('cleanup-only')) {
                return self::SUCCESS;
            }
        }

        $dryRun = (bool) $this->option('dry-run');
        $batchSize = (int) ($this->option('batch-size') ?: config('tracker.commerce_sync_batch_size', 100));
        $chunkDays = (int) ($this->option('chunk-days') ?: config('tracker.commerce_sync_chunk_days', 7));
        $actionTypes = $this->resolveActionTypes();

        if ($this->option('from')) {
            $from = Carbon::parse((string) $this->option('from'), TrackerTime::timezone())->startOfDay();
        } else {
            $minCreatedAt = ActivityEcomUserAction::query()->min('created_at');
            $from = $minCreatedAt
                ? Carbon::parse($minCreatedAt)->startOfDay()
                : Carbon::now()->startOfDay();
        }

        $to = $this->option('to')
            ? Carbon::parse((string) $this->option('to'), TrackerTime::timezone())->endOfDay()
            : Carbon::now()->endOfDay();

        $this->info(sprintf('Commerce sync %s from %s to %s', $dryRun ? '[dry-run]' : '[live]', $from->toDateString(), $to->toDateString()));

        $cursor = $from->copy();

        while ($cursor->lte($to)) {
            $chunkEnd = $cursor->copy()->addDays($chunkDays - 1)->endOfDay();
            if ($chunkEnd->gt($to)) {
                $chunkEnd = $to->copy();
            }

            $chunkKey = $cursor->toDateString().'_'.$chunkEnd->toDateString();
            $checkpoint = TrackerBackfillCheckpoint::query()->firstOrCreate(
                ['job_name' => self::JOB_NAME, 'chunk_key' => $chunkKey],
                ['status' => 'pending'],
            );

            if ($this->option('resume') && $checkpoint->status === 'completed') {
                $cursor = $chunkEnd->copy()->addSecond();
                continue;
            }

            EcomTrackerLogger::frontend()->info('commerce.sync.chunk.start', 'Processing commerce chunk', [
                'chunk_key' => $chunkKey,
            ]);

            if ($dryRun) {
                $count = $this->actionQuery($cursor, $chunkEnd, $actionTypes)->count();
                $this->line("Chunk {$chunkKey}: {$count} actions");
                $cursor = $chunkEnd->copy()->addSecond();
                continue;
            }

            $chunkSessions = [];

            try {
                $checkpoint->update(['status' => 'running', 'started_at' => now()]);

                foreach ($actionTypes as $stage) {
                    $this->actionQuery($cursor, $chunkEnd, [$stage])
                        ->orderBy('id')
                        ->chunkById($batchSize, function ($actions) use ($writer, $checkpoint, &$chunkSessions) {
                            $batch = $actions->all();
                            $result = $writer->syncBatch($batch, true, true);

                            foreach ($batch as $action) {
                                $chunkSessions[$action->session_id] = true;
                            }

                            $checkpoint->update([
                                'last_action_id' => $result['last_action_id'],
                                'records_processed' => ($checkpoint->records_processed ?? 0) + $result['processed'],
                            ]);

                            $this->line(sprintf(
                                '  batch: processed %d skipped %d last_id %s',
                                $result['processed'],
                                $result['skipped'],
                                $result['last_action_id'] ?? '—',
                            ));
                        }, 'id');
                }

                foreach (array_keys($chunkSessions) as $sessionId) {
                    $writer->rebuildSessionFlags($sessionId);
                }

                if ($this->option('validate')) {
                    $issues = $validator->validateChunk($cursor, $chunkEnd);
                    $validator->writeReport($issues, $cursor);
                }

                $checkpoint->update([
                    'status' => 'completed',
                    'completed_at' => now(),
                ]);
            } catch (Throwable $e) {
                $checkpoint->update([
                    'status' => 'failed',
                    'error_message' => $e->getMessage(),
                    'completed_at' => now(),
                ]);

                EcomTrackerLogger::frontend()->error('commerce.sync.chunk.failed', 'Commerce chunk failed', [
                    'chunk_key' => $chunkKey,
                    'message' => $e->getMessage(),
                ]);

                $this->error($e->getMessage());

                return self::FAILURE;
            }

            $cursor = $chunkEnd->copy()->addSecond();
        }

        if (! $this->option('skip-actions-count-backfill')) {
            $before = $this->option('from')
                ? Carbon::parse((string) $this->option('from'), TrackerTime::timezone())->endOfDay()
                : null;

            $actions = $cleanup->backfillSessionActionsCounts($before, $dryRun);
            if ($actions['skipped']) {
                $this->warn('Actions count backfill skipped: actions_count column is missing (run migrations first).');
            } else {
                $this->line(sprintf(
                    'Actions count backfill: scanned %d, updated %d',
                    $actions['scanned'],
                    $dryRun ? 0 : $actions['updated'],
                ));
            }
        }

        $this->info('Commerce sync complete.');

        return self::SUCCESS;
    }

    /**
     * @return list<string>
     */
    private function resolveActionTypes(): array
    {
        $only = (string) ($this->option('only') ?? '');
        if ($only === '') {
            return [
                'payment_success',
                'proceed_checkout',
                'begin_checkout',
                'add_to_cart',
                'product_view',
                'product_view_popup',
                'category_view',
            ];
        }

        $map = [
            'payments' => 'payment_success',
            'checkout' => ['proceed_checkout', 'begin_checkout'],
            'cart' => 'add_to_cart',
            'views' => ['category_view', 'product_view', 'product_view_popup'],
        ];

        $types = [];
        foreach (explode(',', $only) as $part) {
            $key = trim($part);
            $value = $map[$key] ?? $key;
            $types = array_merge($types, (array) $value);
        }

        return array_values(array_unique($types));
    }

    /**
     * @param  list<string>  $actionTypes
     */
    private function actionQuery(Carbon $from, Carbon $to, array $actionTypes)
    {
        return ActivityEcomUserAction::query()
            ->whereIn('action_type', $actionTypes)
            ->whereBetween('created_at', [
                TrackerTime::formatUtc($from),
                TrackerTime::formatUtc($to),
            ]);
    }

    private function runCleanup(TrackerDataCleanupService $cleanup): void
    {
        $before = $this->option('from')
            ? Carbon::parse((string) $this->option('from'), TrackerTime::timezone())->endOfDay()
            : null;

        if (! $this->option('skip-actions-count-backfill') && ! $this->option('dry-run')) {
            $actions = $cleanup->backfillSessionActionsCounts($before, false);
            if ($actions['skipped']) {
                $this->warn('Actions count backfill skipped: actions_count column is missing (run migrations first).');
            } else {
                $this->line(sprintf('Actions count backfill: updated %d session(s)', $actions['updated']));
            }
        }

        if (! $this->option('skip-orphan-sessions')) {
            $orphans = $cleanup->removePaymentOnlySessions($before, (bool) $this->option('dry-run'));
            $this->line("Payment-only sessions deleted: {$orphans['deleted_sessions']}");
        }

        if (! $this->option('skip-dedupe-payments')) {
            $payments = $cleanup->dedupePaymentSuccessActions($before, (bool) $this->option('dry-run'));
            $this->line("Duplicate payment actions deleted: {$payments['deleted_actions']}");
        }

        if ($this->option('include-empty-sessions')) {
            $empty = $cleanup->removeEmptySessions($before, (bool) $this->option('dry-run'));
            $this->line("Empty sessions deleted: {$empty['deleted_sessions']}");
        }

        if (! $this->option('skip-customer-backfill') && ! $this->option('dry-run')) {
            $updated = $cleanup->backfillSessionCustomerFields();
            $this->line("Customer fields backfilled: {$updated}");
        }

        if (! $this->option('skip-actions-count-backfill') && ! $this->option('dry-run')) {
            $actions = $cleanup->backfillSessionActionsCounts($before, false);
            if ($actions['skipped']) {
                $this->warn('Actions count backfill skipped: actions_count column is missing (run migrations first).');
            } else {
                $this->line(sprintf('Actions count reconcile: updated %d session(s)', $actions['updated']));
            }
        }
    }
}
