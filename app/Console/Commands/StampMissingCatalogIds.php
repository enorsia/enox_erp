<?php

namespace App\Console\Commands;

use App\Services\TrackerCatalogBackfillService;
use Illuminate\Console\Command;

class StampMissingCatalogIds extends Command
{
    protected $signature = 'tracker:stamp-missing-catalog-ids {--chunk=500 : Rows per chunk}';

    protected $description = 'Fill NULL tracker_* IDs on commerce lines (sentinel on failure).';

    public function handle(TrackerCatalogBackfillService $backfill): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $updated = $backfill->stampMissingLineItems($chunk);
        $this->info("Updated {$updated} line item(s).");

        return self::SUCCESS;
    }
}
