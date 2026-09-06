<?php

use App\Models\Platform;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

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

if (!function_exists('convertFobUsdToPound')) {
    /**
     * Convert FOB from USD ($) to GBP (£) using the expense module conversion rate.
     * Formula: FOB ($) × conversion_rate = FOB (£)
     */
    function convertFobUsdToPound(?float $fobUsd, ?float $conversionRate): float
    {
        $fobUsd = (float) ($fobUsd ?? 0);

        if ($fobUsd <= 0) {
            return 0;
        }

        if (!$conversionRate || $conversionRate <= 0) {
            return 0;
        }

        return $fobUsd * $conversionRate;
    }
}

if (!function_exists('calculatePlatformProfit')) {
    function calculatePlatformProfit($price, $platform, array $options = [])
    {
        $data = [];

        $costBasis = $options['cost_basis'] ?? 'unit';
        $conversionRate = (float) ($options['conversion_rate'] ?? 0);
        $defaultShipping = (float) ($options['default_shipping'] ?? 0);
        $originalShipping = (float) ($options['original_shipping'] ?? $defaultShipping);
        $shippingCost = array_key_exists('shipping_cost', $options) && $options['shipping_cost'] !== null && $options['shipping_cost'] !== ''
            ? (float) $options['shipping_cost']
            : $originalShipping;

        $fobPound = convertFobUsdToPound((float) $price->price_fob, $conversionRate);
        $dbUnitPrice = (float) $price->unit_price;
        $shippingChanged = abs($shippingCost - $originalShipping) >= 0.005;

        if ($costBasis === 'fob') {
            $baseCost = $fobPound + $shippingCost;
            $data['adjusted_unit_price'] = null;
        } else {
            if ($shippingChanged) {
                $adjustedUnitPrice = $dbUnitPrice - $originalShipping + $shippingCost;
                $baseCost = $adjustedUnitPrice;
                $data['adjusted_unit_price'] = round($adjustedUnitPrice, 2);
            } else {
                $baseCost = $dbUnitPrice;
                $data['adjusted_unit_price'] = null;
            }
        }

        $data['discount_percent'] = 0;
        if (!empty($options['discount_price']) && $options['discount_price'] > 0) {
            $data['discount_percent'] = $options['confirm_selling_price'] - $options['discount_price'] > 0
                ? (($options['confirm_selling_price'] - $options['discount_price']) / $options['confirm_selling_price']) * 100
                : 0;
        }
        // dd($options['discount_price'], $options['confirm_selling_price'], $data['discount_percent']);

        $data['fob_pound'] = $fobPound;
        $data['db_unit_price'] = $dbUnitPrice;
        $data['cost_basis'] = $costBasis;
        $data['original_shipping'] = $originalShipping;
        $data['shipping_charge'] = $shippingCost;
        $data['unit_price_sh_charge'] = $baseCost;

        $data['commission'] = $price->confirm_selling_price * $platform->commission;
        $data['commission_vat'] = $data['commission'] + ($data['commission'] * 0.20);
        $data['selling_price'] = $price->confirm_selling_price - $data['commission_vat'];

        $data['selling_vat'] = ($data['selling_price'] / 120) * 100;
        $data['vat_value'] = $data['selling_price'] - $data['selling_vat'];
        $data['selling_price_and_vat'] = $data['selling_vat'] + ($data['commission_vat'] - $data['commission']);
        $data['net_profit'] = $data['selling_price_and_vat'] - $baseCost;
        $data['profit_margin'] = $data['selling_price_and_vat'] > 0
            ? ($data['net_profit'] / $data['selling_price_and_vat']) * 100
            : 0;
        $data['can_sell'] = $data['net_profit'] >= $platform->min_profit ? 'Yes' : 'No';

        return $data;
    }
}
