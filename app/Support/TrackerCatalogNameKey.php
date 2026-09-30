<?php

namespace App\Support;

final class TrackerCatalogNameKey
{
    public static function fromDisplayName(string $name): string
    {
        $normalized = TrackerCategoryIdentity::displayName($name);
        $collapsed = preg_replace('/\s+/u', ' ', $normalized) ?? $normalized;

        return mb_strtolower(trim($collapsed));
    }

    public static function departmentParentKey(string $departmentName): string
    {
        $key = self::fromDisplayName($departmentName);

        return $key !== '' ? $key : TrackerCatalogSentinels::UNCATEGORIZED_NAME_KEY;
    }
}
