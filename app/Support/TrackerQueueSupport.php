<?php

namespace App\Support;

final class TrackerQueueSupport
{
    /** Database queue for dashboard sync + CTR (`jobs` on tracking MySQL). */
    public static function connection(): string
    {
        return (string) config('tracker.database_queue_connection', 'tracking_database');
    }

    public static function queue(): string
    {
        return (string) config('tracker.database_queue_name', 'default');
    }
}
