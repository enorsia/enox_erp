<?php

namespace App\Services\EcomTrackerDashboard\Sections;

use App\Models\TrackingDailyAudience;

class DeviceBrowserSectionService
{
    public function __construct(
        private AudienceRollupSectionService $audiences,
    ) {}

    /**
     * @return array{by_device: list<array<string, mixed>>, by_browser: list<array<string, mixed>>}
     */
    public function forRange(string $from, string $to): array
    {
        $rollup = $this->audiences->rollup($from, $to);

        return [
            'by_device' => $rollup[TrackingDailyAudience::TYPE_DEVICE] ?? [],
            'by_browser' => $rollup[TrackingDailyAudience::TYPE_BROWSER] ?? [],
        ];
    }
}