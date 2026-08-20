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
                            {--include-empty-sessions : Also remove sessions with zero actions}';

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

        $this->info('Tracker cleanup complete.');

        return self::SUCCESS;
    }
}
