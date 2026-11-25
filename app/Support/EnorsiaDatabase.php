<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Query helpers for the ENORSIA shop read replica (enorsia_v2).
 */
final class EnorsiaDatabase
{
    public static function connectionName(): string
    {
        return (string) config('enorsia.database_connection', 'enorsia');
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