<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Models\TrackingDailyAudience;

class TrafficSourcesSectionService
{
    public function __construct(
        private AudienceRollupSectionService $audiences,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function forRange(string $from, string $to): array
    {
        $rollup = $this->audiences->rollup($from, $to);

        return $rollup[TrackingDailyAudience::TYPE_TRAFFIC_SOURCE] ?? [];
    }
}