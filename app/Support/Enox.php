<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query helpers for the Enorsia shop read replica (enorsia_v2).
 */
final class Enox
{
    public static function connectionName(): string
    {
        return (string) config('enox.database_connection', 'enox');
    }

    public static function connection(): Connection
    {
        return DB::connection(self::connectionName());
    }

    public static function table(string $table): Builder
    {
        return self::connection()->table($table);
    }

    public static function query(): Builder
    {
        return self::connection()->query();
    }
}