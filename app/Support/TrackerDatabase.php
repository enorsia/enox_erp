<?php

namespace App\Support;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

final class TrackerDatabase
{
    public static function connectionName(): string
    {
        return (string) config('tracker.database_connection', 'tracking');
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
