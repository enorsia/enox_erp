<?php

namespace App\Models\Concerns;

use App\Support\Enox;

trait UsesEnoxDatabase
{
    public function getConnectionName(): ?string
    {
        return Enox::connectionName();
    }
}