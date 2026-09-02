<?php

namespace App\Console\Commands;

use App\Services\TrackerDataCleanupService;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Console\Command;

class CleanupTrackerHistoricalData extends Command
{
    protected $signature = 'tracker:cleanup-historical-data
                            {--dry-run : Report changes without writing}
                            {--before= : Optional; only rows on/before this date (Europe/London). Omit to process all data}
                            {--skip-customer-backfill : Skip restoring name/email/phone from checkout actions}
                            {--skip-dedupe-payments : Skip removing duplicate payment_success rows for the same order}
                            {--skip-orphan-sessions : Skip removing sessions that only contain a lone payment_success action}
                            {--include-empty-sessions : Also remove sessions with zero actions}
                            {--skip-actions-count-backfill : Skip recounting actions_count from user_actions}';

    protected $description = 'Clean tracker data: ghost payment-only sessions, duplicate orders, and missing checkout customer identity.';

    public function handle(TrackerDataCleanupService $cleanup): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $beforeOption = $this->option('before');
        $before = filled($beforeOption)
            ? Carbon::parse((string) $beforeOption, TrackerTime::timezone())->endOfDay()
            : null;

        $this->info(sprintf(
            '%s tracker cleanup%s',
            $dryRun ? '[dry-run]' : '[live]',
            $before === null ? ' (all data)' : ' (on/before '.$before->toDateString().', '.TrackerTime::timezone().')',
        ));

        if (! $this->option('skip-actions-count-backfill')) {
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

        if (! $this->option('skip-orphan-sessions')) {
            $orphans = $cleanup->removePaymentOnlySessions($before, $dryRun);
            $this->line(sprintf(
                'Payment-only sessions: scanned %d, deleted %d',
                $orphans['scanned'],
                $orphans['deleted_sessions'],
            ));
        }

        if (! $this->option('skip-dedupe-payments')) {
            $payments = $cleanup->dedupePaymentSuccessActions($before, $dryRun);
            $this->line(sprintf(
                'Duplicate payment_success: scanned %d, groups %d, deleted %d, kept %d',
                $payments['scanned'],
                $payments['duplicate_groups'],
                $payments['deleted_actions'],
                $payments['kept_actions'],
            ));
        }

        if ($this->option('include-empty-sessions')) {
            $empty = $cleanup->removeEmptySessions($before, $dryRun);
            $this->line(sprintf(
                'Empty sessions: scanned %d, deleted %d',
                $empty['scanned'],
                $empty['deleted_sessions'],
            ));
        }

        if (! $this->option('skip-customer-backfill') && ! $dryRun) {
            $updated = $cleanup->backfillSessionCustomerFields();
            $this->line(sprintf('Customer identity backfill: updated %d session(s)', $updated));
        } elseif (! $this->option('skip-customer-backfill')) {
            $this->line('Customer identity backfill: skipped in dry-run mode');
        }

        if (! $this->option('skip-actions-count-backfill') && ! $dryRun) {
            $actions = $cleanup->backfillSessionActionsCounts($before, false);
            if ($actions['skipped']) {
                $this->warn('Actions count backfill skipped: actions_count column is missing (run migrations first).');
            } else {
                $this->line(sprintf(
                    'Actions count reconcile: updated %d session(s)',
                    $actions['updated'],
                ));
            }
        }

        $this->info('Tracker cleanup complete.');

        return self::SUCCESS;
    }
}
