<?php

namespace App\Models\Concerns;

use App\Support\TrackerDatabase;

trait UsesTrackingDatabase
{
    public function getConnectionName(): ?string
    {
        return TrackerDatabase::connectionName();
    }
}
