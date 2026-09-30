<?php

namespace App\Console\Commands;

use App\Services\TrackerAttributionBackfillService;
use Illuminate\Console\Command;

class BackfillTrackerAttribution extends Command
{
    protected $signature = 'tracker:backfill-attribution';

    protected $description = 'Safe post-deploy backfill: fix only mis-merged sessions, fill empty session UTMs, refresh list_traffic where needed, fill missing conversion_* (does not overwrite good data).';

    public function handle(TrackerAttributionBackfillService $attributionBackfill): int
    {
        $attributionBackfill->run($this->output);
        $this->info('Done.');

        return self::SUCCESS;
    }
}
