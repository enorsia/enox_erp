<?php

namespace App\Services;

class TrackerDashboardSyncService
{
    /**
     * Read up to $batchSize rows from the source table, apply rules, write to the target table.
     *
     * @return int|null Next cursor (`afterId`) for the following job, or null when finished.
     */
    public function processBatch(?int $afterId = null, int $batchSize = 25): ?int
    {
        $limit = max(1, $batchSize);

        // TODO: fetch rows from source table (after $afterId, limit $limit)
        // TODO: update target table per your conditions

        return null;
    }
}
