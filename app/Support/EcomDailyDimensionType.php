<?php

namespace App\Support;

final class EcomDailyDimensionType
{
    /** Traffic source + medium: dimension_value = "{source}\0{medium}" */
    public const LIST_TRAFFIC = 'list_traffic';

    public const DEVICE = 'device';

    public const BROWSER = 'browser';

    /** dimension_value = "{city}\0{country}" */
    public const GEO = 'geo';

    public const LOGGED_IN = 'logged_in';

    public const HAS_ORDER = 'has_order';

    public static function trafficDimensionValue(string $source, string $medium): string
    {
        return $source."\0".$medium;
    }

    /**
     * @return array{source: string, medium: string}
     */
    public static function parseTrafficDimensionValue(string $value): array
    {
        $parts = explode("\0", $value, 2);

        return [
            'source' => $parts[0] ?? '(direct)',
            'medium' => $parts[1] ?? 'none',
        ];
    }
}
