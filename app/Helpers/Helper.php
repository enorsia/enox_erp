<?php

use App\Models\Platform;
use Illuminate\Support\Facades\Cache;

if (! function_exists('avaiablePermissionsMap')) {
    /**
     * Permission catalog for nav / gates. Cached forever in the store, and
     * once per request so Blade does not hit the cache table on every @canany.
     *
     * @return array{grouped: array<string, array<string, true>>, flat: array<string, true>, prefix: array<string, list<string>>}
     */
    function avaiablePermissionsMap(): array
    {
        static $data = null;

        if ($data !== null) {
            return $data;
        }

        $data = Cache::rememberForever('permissions.available', function () {
            $permissions = config('permissions.map');

            $grouped = [];
            $flatList = [];
            $prefix = [];

            foreach ($permissions as $module => $entities) {
                $modulePrefix = "{$module}_";

                foreach ($entities as $entity => $config) {
                    $groupKey = "{$module}_{$entity}";

                    foreach ($config['actions'] as $action) {
                        $permission = "{$module}.{$entity}.{$action}";
                        $flatList[$permission] = true;
                        $grouped[$groupKey][$permission] = true;
                        $prefix[$modulePrefix][] = $permission;
                    }
                }
            }

            foreach ($prefix as $key => $items) {
                $prefix[$key] = array_values(array_unique($items));
            }

            return [
                'grouped' => $grouped,
                'flat' => $flatList,
                'prefix' => $prefix,
            ];
        });

        return $data;
    }
}

if (! function_exists('avaiablePermissions')) {
    function avaiablePermissions(bool $flat = false)
    {
        $data = avaiablePermissionsMap();

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

if (!function_exists('sellingChartImage')) {
    function sellingChartImage(?string $filename, ?int $width = null, string $type = 'design'): ?string
    {
        if (!$filename) {
            return null;
        }

        if (app()->environment('production')) {
            return cloudflareImage($filename, $width);
        }

        $folder = $type === 'inspiration'
            ? 'upload/selling_images'
            : 'upload/selling_design_images';

        return asset($folder . '/' . basename($filename));
    }
}

if (!function_exists('zeroToString')) {
    function zeroToString($value)
    {
        return $value == 0 ? '0' : $value;
    }
}

if (! function_exists('format_duration')) {
    function format_duration(int $seconds): string
    {
        if ($seconds <= 0) {
            return '0s';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);
        $remaining = $seconds % 60;

        if ($hours > 0) {
            return sprintf('%dh %dm', $hours, $minutes);
        }

        if ($minutes > 0) {
            return sprintf('%dm %ds', $minutes, $remaining);
        }

        return $seconds.'s';
    }
}

if (!function_exists('calculatePlatformProfit')) {
    function calculatePlatformProfit($price, $platform)
    {
        $data = [];

        $unit_price_sh_charge = $price->unit_price + $platform->shipping_charge;

        $data["shipping_charge"] = $platform->shipping_charge;
        $data["unit_price_sh_charge"] = $unit_price_sh_charge;

        $data["commission"] = $price->confirm_selling_price * $platform->commission;
        $data["commission_vat"] = $data["commission"] + ($data["commission"] * 0.20);
        $data["selling_price"] = $price->confirm_selling_price - $data["commission_vat"];
        $data["selling_vat"] = ($data["selling_price"] / 120) * 100;
        $data["vat_value"] = $data["selling_price"] - $data["selling_vat"];
        $data["selling_price_and_vat"] = $data["selling_vat"] + ($data["commission_vat"] - $data["commission"]);
        $data["net_profit"] = $data["selling_price_and_vat"] - $unit_price_sh_charge;
        $data["profit_margin"] = $data["selling_price_and_vat"] > 0
            ? ($data["net_profit"] / $data["selling_price_and_vat"]) * 100
            : 0;
        $data["can_sell"] =  $data["net_profit"] >= $platform->min_profit ? "Yes" : "No";

        return $data;
    }
}
