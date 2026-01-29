<?php

use Illuminate\Support\Facades\Cache;

if (! function_exists('avaiablePermissions')) {
    function avaiablePermissions(bool $flat = false)
    {
        $cacheKey = 'permissions.available';
        $data = Cache::rememberForever($cacheKey, function () {
            $permissions = config('permissions.map');

            $grouped  = [];
            $flatList = [];
            $prefix   = [];

            foreach ($permissions as $module => $entities) {

                // Define prefix once per module
                $modulePrefix = "{$module}_";

                foreach ($entities as $entity => $config) {
                    $groupKey = "{$module}_{$entity}";

                    foreach ($config['actions'] as $action) {
                        $permission = "{$module}.{$entity}.{$action}";

                        // Flat (key-based)
                        $flatList[$permission] = true;

                        // Grouped (key-based)
                        $grouped[$groupKey][$permission] = true;

                        // Prefix-based (value list for @canany)
                        $prefix[$modulePrefix][] = $permission;
                    }
                }
            }

            // Remove duplicate permissions inside prefixes
            foreach ($prefix as $key => $items) {
                $prefix[$key] = array_values(array_unique($items));
            }

            return [
                'grouped' => $grouped,
                'flat'    => $flatList,
                'prefix'  => $prefix,
            ];
        });

        return $flat ? $data['flat'] : $data['grouped'];
    }
}

if (!function_exists('cloudflareImage')) {
    function cloudflareImage($imagePath, $width = null)
    {
        if (!$imagePath) return null;
        $cloudflareBaseUrl = config('cloudflare.image_base_url');
        $imageId = basename($imagePath);
        if ($width === null) {
            return $cloudflareBaseUrl . $imageId . '/public';
        } else {
            return $cloudflareBaseUrl . $imageId . '/w=' . $width;
        }
    }
}

if (!function_exists('zeroToString')) {
    function zeroToString($value)
    {
        return $value == 0 ? '0' : $value;
    }
}
