<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Conditional extra columns on the user activity table (master parity).
 * Columns appear when {@see focus} is set or when matching drawer filters are active.
 */
final class EcomActivityTableFocus
{
    /** @var array<string, list<string>> */
    private const FOCUS_COLUMN_KEYS = [
        'audience' => ['device'],
        'devices' => ['device_detail'],
        'traffic' => ['traffic_source', 'traffic_medium'],
        'session_quality' => ['classification_reason'],
        'products' => ['products_viewed', 'adds', 'purchases'],
    ];

    /** @var array<string, array{key: string, label: string, class?: string, tip?: string}> */
    private const COLUMN_DEFS = [
        'device' => ['key' => 'device', 'label' => 'Device'],
        'device_detail' => ['key' => 'device_detail', 'label' => 'Device & browser'],
        'traffic_source' => ['key' => 'traffic_source', 'label' => 'Source'],
        'traffic_medium' => ['key' => 'traffic_medium', 'label' => 'Medium'],
        'classification_reason' => ['key' => 'classification_reason', 'label' => 'Classification'],
        'products_viewed' => [
            'key' => 'products_viewed',
            'label' => 'View',
            'class' => 'etd-num',
            'tip' => 'Yes if this parent style was viewed in the session (catalog click, PDP view, or popup; variant SKUs roll up to the style code)',
        ],
        'adds' => [
            'key' => 'adds',
            'label' => 'Adds',
            'class' => 'etd-num',
            'tip' => 'How many add-to-cart clicks included this parent style in the session',
        ],
        'top_category' => ['key' => 'top_category', 'label' => 'Top category'],
        'purchases' => [
            'key' => 'purchases',
            'label' => 'Purchases',
            'class' => 'etd-num',
            'tip' => 'Completed orders in this period that include this style',
        ],
    ];

    public static function isValidFocus(?string $focus): bool
    {
        return filled($focus) && isset(self::FOCUS_COLUMN_KEYS[$focus]);
    }

    /**
     * @return list<array{key: string, label: string, class?: string, tip?: string}>
     */
    public static function columnsForRequest(Request $request): array
    {
        $keys = [];
        $focus = $request->input('focus');
        if (! self::isValidFocus(is_string($focus) ? $focus : null)
            && filled(trim((string) $request->input('product_code', '')))) {
            $focus = 'products';
        }

        if (self::isValidFocus(is_string($focus) ? $focus : null)) {
            $keys = self::FOCUS_COLUMN_KEYS[$focus];
        }

        if (self::hasDeviceScope($request)) {
            $keys = self::mergeKeys($keys, ['device_detail']);
        }

        if (self::hasTrafficScope($request)) {
            $keys = self::mergeKeys($keys, ['traffic_source', 'traffic_medium']);
        }

        if (filled($request->input('visitor_type'))) {
            $keys = self::mergeKeys($keys, ['classification_reason']);
        }

        if ($request->filled('category')) {
            $keys = array_values(array_filter($keys, fn (string $key) => $key !== 'top_category'));
        }

        return array_values(array_filter(array_map(
            fn (string $key) => self::COLUMN_DEFS[$key] ?? null,
            $keys,
        )));
    }

    public static function showCatalogFilterColumn(Request $request): bool
    {
        return $request->filled('department') || $request->filled('category');
    }

    /**
     * Dashboard product performance drill-down: merge session metadata into the user column.
     */
    public static function usesProductDrillTableLayout(Request $request): bool
    {
        return filled(trim((string) $request->input('product_code', '')));
    }

    /**
     * @param  list<string>  $keys
     * @return list<string>
     */
    private static function mergeKeys(array $keys, array $extra): array
    {
        foreach ($extra as $key) {
            if (! in_array($key, $keys, true)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private static function hasDeviceScope(Request $request): bool
    {
        if ($request->input('focus') === 'devices') {
            return false;
        }

        return filled($request->input('device_type'));
    }

    private static function hasTrafficScope(Request $request): bool
    {
        if ($request->input('focus') === 'traffic') {
            return false;
        }

        return $request->filled('utm_source') || $request->filled('utm_medium');
    }
}
