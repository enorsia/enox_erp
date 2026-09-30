<?php

namespace App\Console\Commands;

use App\Services\TrackerCatalogBackfillService;
use Illuminate\Console\Command;

class BackfillTrackerCatalog extends Command
{
    protected $signature = 'tracker:backfill-catalog
                            {--limit= : Max line items to stamp (default all)}
                            {--rollup-date= : Also stamp daily rollup rows for YYYY-MM-DD}';

    protected $description = 'Build catalog from line items and stamp tracker_* snapshot IDs.';

    public function handle(TrackerCatalogBackfillService $backfill): int
    {
        $limit = $this->option('limit');
        $limitInt = ($limit === null || $limit === '') ? null : max(1, (int) $limit);

        $this->line('Stamping commerce line item catalog snapshot IDs...');
        $count = $backfill->stampLineItems($limitInt);
        $this->info("Stamped {$count} line item(s).");

        $rollupDate = $this->option('rollup-date');
        if ($rollupDate !== null && $rollupDate !== '') {
            $backfill->stampRollupRowsForDate((string) $rollupDate);
            $this->info('Stamped rollup rows for '.$rollupDate);
        }

        return self::SUCCESS;
    }
}
