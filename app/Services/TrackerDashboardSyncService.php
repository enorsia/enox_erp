<?php

namespace App\Services;

class TrackerDashboardSyncService
{
    /**
     * Dashboard sync entry point (tracking pipeline — implement here).
     *
     * @return array{ok: bool, message: string}
     */
    public function sync(): array
    {
        return [
            'ok' => true,
            'message' => 'Sync completed.',
        ];
    }
}
