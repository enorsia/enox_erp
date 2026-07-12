<?php

namespace App\Support;

class DateOptions
{
    public static function years(int $before = 5, int $after = 5): array
    {
        $currentYear = now()->year;

        return range($currentYear - $before, $currentYear + $after);
    }
}
