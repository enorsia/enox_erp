<?php

namespace App\Services;

use App\Http\Clients\EnoxApiClient;
use Illuminate\Support\Facades\Log;

class TrackingCtrSyncSiteSettingsService
{
    /**
     * Loaded once per `tracker:sync-ctr` run from GET /tracking/site-settings.
     *
     * @return array{
     *     ctr_days: int,
     *     min_clicks: int,
     *     source: 'ecom_api'|'config_fallback',
     * }
     */
    public function fetchForSync(): array
    {
        $fromApi = $this->fetchFromEcomApi();

        if ($fromApi !== null) {
            return [
                'ctr_days' => $fromApi['ctr_days'],
                'min_clicks' => $fromApi['min_clicks'],
                'source' => 'ecom_api',
            ];
        }

        return [
            'ctr_days' => max(1, (int) config('tracker.ctr_rollup_days', 90)),
            'min_clicks' => max(1, (int) config('tracker.ctr_min_clicks_fallback', 1)),
            'source' => 'config_fallback',
        ];
    }

    /**
     * @return array{ctr_days: int, min_clicks: int}|null
     */
    private function fetchFromEcomApi(): ?array
    {
        $baseUrl = rtrim((string) config('enox.base_url'), '/');

        if ($baseUrl === '') {
            Log::warning('CTR sync: ENOX_API_BASE_URL is empty; using config fallback for product sorting.');

            return null;
        }

        try {
            $client = app(EnoxApiClient::class);
            $response = $client->get((string) config('enox.endpoints.tracking_site_settings'));

            if (! $response->successful()) {
                Log::warning('CTR sync: tracking/site-settings request failed', [
                    'status' => $response->status(),
                    'base_url' => $baseUrl,
                ]);

                return null;
            }

            $payload = $response->json();

            if (! ($payload['status'] ?? false)) {
                Log::warning('CTR sync: tracking/site-settings returned status=false');

                return null;
            }

            $extra = $this->extraJsonFromSiteSettingsPayload($payload['data'] ?? null);

            return $this->normalizeFromExtra($extra);
        } catch (\Throwable $exception) {
            Log::warning('CTR sync: tracking/site-settings fetch failed', [
                'error' => $exception->getMessage(),
                'base_url' => $baseUrl,
            ]);

            return null;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function extraJsonFromSiteSettingsPayload(mixed $data): array
    {
        if ($data === null) {
            return [];
        }

        $extra = is_array($data)
            ? ($data['extra_json'] ?? [])
            : (is_object($data) ? ($data->extra_json ?? []) : []);

        if (is_string($extra)) {
            $decoded = json_decode($extra, true);

            return is_array($decoded) ? $decoded : [];
        }

        return is_array($extra) ? $extra : [];
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
