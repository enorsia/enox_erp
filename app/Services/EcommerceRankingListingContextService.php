<?php

namespace App\Services;

use App\Http\Clients\EnoxApiClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class EcommerceRankingListingContextService
{
    private const CACHE_KEY = 'ecommerce.ranking_listing_contexts';

    private const CACHE_TTL_SECONDS = 300;

    /**
     * @return list<array{key: string, label: string, listing_scope: string, listing_scope_id: int}>
     */
    public function contexts(): array
    {
        $cached = Cache::get(self::CACHE_KEY);
        if (is_array($cached) && $cached !== []) {
            return $cached;
        }

        $items = $this->fetchFromApi();
        if ($items !== []) {
            Cache::put(self::CACHE_KEY, $items, self::CACHE_TTL_SECONDS);
        }

        return $items;
    }

    /**
     * @return list<array{key: string, label: string, listing_scope: string, listing_scope_id: int}>
     */
    private function fetchFromApi(): array
    {
        if (rtrim((string) config('enox.base_url'), '/') === '') {
            return [];
        }

        $endpoint = (string) config('enox.endpoints.tracking_listing_contexts', 'tracking/listing-contexts');

        try {
            $response = app(EnoxApiClient::class)->get($endpoint);
        } catch (\Throwable $exception) {
            Log::warning('Ranking listing contexts: request failed.', [
                'error' => $exception->getMessage(),
            ]);

            return [];
        }

        if (! $response->successful()) {
            Log::warning('Ranking listing contexts: HTTP error.', [
                'http_status' => $response->status(),
            ]);

            return [];
        }

        $payload = $response->json();
        if (! is_array($payload) || ! ($payload['status'] ?? false)) {
            return [];
        }

        $items = [];
        foreach ($payload['data'] ?? [] as $row) {
            if (! is_array($row)) {
                continue;
            }

            $key = trim((string) ($row['key'] ?? ''));
            if ($key === '' || ! str_contains($key, ':')) {
                continue;
            }

            $items[] = [
                'key' => $key,
                'label' => (string) ($row['label'] ?? $key),
                'listing_scope' => (string) ($row['listing_scope'] ?? explode(':', $key, 2)[0]),
                'listing_scope_id' => (int) ($row['listing_scope_id'] ?? explode(':', $key, 2)[1] ?? 0),
            ];
        }

        return $items;
    }

    /**
     * @param  list<array{key: string, label: string, listing_scope: string, listing_scope_id: int}>  $contexts
     */
    public function labelForKey(string $contextKey, array $contexts, ?string $fallbackScope = null, ?int $fallbackScopeId = null): string
    {
        $contextKey = trim($contextKey);
        if ($contextKey === '') {
            return 'All listing contexts';
        }

        foreach ($contexts as $context) {
            if (($context['key'] ?? '') === $contextKey) {
                return (string) ($context['label'] ?? $contextKey);
            }
        }

        if ($fallbackScope !== null && $fallbackScope !== '') {
            return app(EcommerceListingScopeCtrResolver::class)
                ->listingScopeLabel($fallbackScope, $fallbackScopeId ?? 0);
        }

        return $contextKey;
    }
}
