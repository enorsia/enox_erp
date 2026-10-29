<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class TrackingCtrSyncSiteSettingsService
{
    public const CTR_SUMMARIES_SETTINGS_CACHE_KEY = 'tracking:ctr_summaries_settings';

    /**
     * @return list<string>
     */
    public static function extraSettingKeys(): array
    {
        return [
            'product_sorting_ctr_days',
            'product_sorting_min_clicks',
        ];
    }

    /**
     * @return array{product_sorting_ctr_days: int, product_sorting_min_clicks: int}
     */
    public function getExtraSettings(): array
    {
        $cached = Cache::get(self::CTR_SUMMARIES_SETTINGS_CACHE_KEY);

        if (is_array($cached) && $cached !== []) {
            return $this->mergeWithConfigDefaults($cached);
        }

        Log::info('CTR sync: no pushed site settings in cache; using config defaults');

        return $this->configFallbackExtra();
    }

    /**
     * @return array{
     *     ctr_days: int,
     *     min_clicks: int,
     *     source: 'push_cache'|'config_fallback',
     * }
     */
    public function fetchForSync(): array
    {
        $extra = $this->getExtraSettings();
        $normalized = $this->normalizeFromExtra($extra);
        $fromCache = $this->hasPushCache();

        return [
            'ctr_days' => $normalized['ctr_days'],
            'min_clicks' => $normalized['min_clicks'],
            'source' => $fromCache ? 'push_cache' : 'config_fallback',
        ];
    }

    /**
     * @param  array{ctr_days?: int, min_clicks?: int, source?: string}  $rollupSettings
     * @return array{ctr_days: int, min_clicks: int, source: string}
     */
    public function resolveRollupSettings(array $rollupSettings = []): array
    {
        $defaults = $this->fetchForSync();

        return [
            'ctr_days' => max(
                1,
                (int) ($rollupSettings['ctr_days'] ?? $defaults['ctr_days']),
            ),
            'min_clicks' => max(
                1,
                (int) ($rollupSettings['min_clicks'] ?? $defaults['min_clicks']),
            ),
            'source' => (string) ($rollupSettings['source'] ?? $defaults['source']),
        ];
    }

    public function hasPushCache(): bool
    {
        $cached = Cache::get(self::CTR_SUMMARIES_SETTINGS_CACHE_KEY);

        return is_array($cached) && $cached !== [];
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array{product_sorting_ctr_days: int, product_sorting_min_clicks: int}
     */
    public function mergeWithConfigDefaults(array $values): array
    {
        $filtered = array_intersect_key($values, array_flip(self::extraSettingKeys()));

        return array_merge($this->configFallbackExtra(), $filtered);
    }

    /**
     * @return array{product_sorting_ctr_days: int, product_sorting_min_clicks: int}
     */
    private function configFallbackExtra(): array
    {
        return [
            'product_sorting_ctr_days' => (int) config('tracker.ctr_rollup_days', 90),
            'product_sorting_min_clicks' => (int) config('tracker.ctr_min_clicks_fallback', 1),
        ];
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array{ctr_days: int, min_clicks: int}
     */
    private function normalizeFromExtra(array $extra): array
    {
        return [
            'ctr_days' => max(1, (int) ($extra['product_sorting_ctr_days'] ?? config('tracker.ctr_rollup_days', 90))),
            'min_clicks' => max(1, (int) ($extra['product_sorting_min_clicks'] ?? config('tracker.ctr_min_clicks_fallback', 1))),
        ];
    }
}
