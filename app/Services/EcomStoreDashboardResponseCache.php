<?php

namespace App\Services;

use App\Support\EcomDailyRollupDayStatus;
use App\Support\TrackerRedisCache;
use App\Support\TrackerTime;
use Carbon\Carbon;

class EcomStoreDashboardResponseCache
{
    public function __construct(
        private TrackerRedisCache $cache,
    ) {}

    /**
     * @param  callable(): array<string, mixed>  $loader
     * @return array<string, mixed>
     */
    public function rememberClosedRange(array $filters, Carbon $from, Carbon $to, callable $loader): array
    {
        if (! config('tracker.analytics_cache_enabled', false)) {
            return $loader();
        }

        $key = $this->closedKey($filters, $from, $to);
        $ttl = max(60, (int) config('tracker.analytics_cache_ttl_seconds', 300));
        $cached = $this->cache->remember($key, $ttl, $loader);

        return is_array($cached['payload'] ?? null) ? $cached['payload'] : $this->cache->payload($cached);
    }

    /**
     * @param  callable(): array<string, mixed>  $loader
     * @return array<string, mixed>
     */
    public function rememberTodaySlice(array $filters, callable $loader): array
    {
        if (! config('tracker.analytics_cache_enabled', false)) {
            return $loader();
        }

        $ttl = min(60, max(15, (int) config('tracker.analytics_cache_today_ttl_seconds', 60)));
        $key = 'store_dashboard:today:'.md5(json_encode($filters) ?: '');
        $cached = $this->cache->remember($key, $ttl, $loader);

        return is_array($cached['payload'] ?? null) ? $cached['payload'] : $this->cache->payload($cached);
    }

    private function closedKey(array $filters, Carbon $from, Carbon $to): string
    {
        $version = $this->rollupVersionToken($from, $to);

        return 'store_dashboard:closed:'.$version.':'.md5(json_encode([
            'filters' => $filters,
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ]) ?: '');
    }

    private function rollupVersionToken(Carbon $from, Carbon $to): string
    {
        if (! EcomDailyRollupDayStatus::hasTable()) {
            return 'no_status';
        }

        $max = \Illuminate\Support\Facades\DB::table(EcomDailyRollupDayStatus::table())
            ->whereBetween('metric_date', [$from->toDateString(), $to->toDateString()])
            ->max('rolled_up_at');

        return $max ? (string) $max : 'none';
    }

    public function cacheMeta(): array
    {
        return [
            'enabled' => (bool) config('tracker.analytics_cache_enabled', false),
            'closed_ttl_seconds' => (int) config('tracker.analytics_cache_ttl_seconds', 300),
            'today_ttl_seconds' => (int) config('tracker.analytics_cache_today_ttl_seconds', 60),
            'timezone' => TrackerTime::timezone(),
        ];
    }
}
