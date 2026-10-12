<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class TrackerCatalogAliasMap
{
    /** @var array<string, string>|null */
    private static ?array $cache = null;

    /**
     * @return array<string, string> mapKey => canonical_name_key
     */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        if (! Schema::hasTable('tracker_category_name_aliases')) {
            self::$cache = [];

            return self::$cache;
        }

        $map = [];
        foreach (DB::table('tracker_category_name_aliases')->get() as $row) {
            $key = self::mapKey(
                (string) $row->type,
                (string) $row->parent_key,
                (string) $row->alias_name_key,
            );
            $map[$key] = (string) $row->canonical_name_key;
        }

        self::$cache = $map;

        return $map;
    }

    public static function clearCache(): void
    {
        self::$cache = null;
    }

    public static function resolveCanonicalNameKey(string $type, string $parentKey, string $nameKey): string
    {
        $key = self::mapKey($type, $parentKey, $nameKey);
        $map = self::all();

        return $map[$key] ?? $nameKey;
    }

    public static function mapKey(string $type, string $parentKey, string $nameKey): string
    {
        return strtolower($type).'|'.$parentKey.'|'.$nameKey;
    }
}
