<?php

namespace App\Support;

class ExportTypeRegistry
{
    public static function types(): array
    {
        return config('exports.types', []);
    }

    public static function exists(string $type): bool
    {
        return isset(self::types()[$type]);
    }

    public static function config(string $type): ?array
    {
        return self::types()[$type] ?? null;
    }

    public static function label(string $type): string
    {
        return self::config($type)['label'] ?? 'Export';
    }

    public static function permission(string $type): ?string
    {
        return self::config($type)['permission'] ?? null;
    }

    public static function filenamePrefix(string $type): string
    {
        return self::config($type)['filename_prefix'] ?? 'Export';
    }

    public static function notificationUrl(int $exportId, string $type): ?string
    {
        $config = self::config($type);

        if (! $config || empty($config['route'])) {
            return null;
        }

        $params = array_merge($config['route_params'] ?? [], ['export_id' => $exportId]);

        return route($config['route'], $params, absolute: false);
    }
}
