<?php

namespace App\Services;

class EcomTrackerFeatureGate
{
    public function isEnabled(): bool
    {
        return (bool) config('tracker.enabled');
    }

    public function ensureEnabled(): void
    {
        abort_unless($this->isEnabled(), 404);
    }
}
