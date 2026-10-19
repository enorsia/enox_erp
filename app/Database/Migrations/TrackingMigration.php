<?php

namespace App\Database\Migrations;

use Illuminate\Database\Migrations\Migration;

/**
 * @see https://laravel.com/docs/migrations#setting-the-migration-connection
 */
abstract class TrackingMigration extends Migration
{
    public function getConnection(): ?string
    {
        return (string) config('tracker.database_connection', 'tracking');
    }
}
