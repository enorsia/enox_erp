<?php

namespace App\ApiServices;

use App\Http\Clients\EnoxApiClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class StyleStockService
{
    public function __construct(
        protected EnoxApiClient $api
    ) {}

    public function get(array $filters = []): Response
    {
        return $this->api->get(config('enox.endpoints.style_stock_report'), $filters);
    }

    public function export(array $filters = []): Response
    {
        $baseUrl = rtrim(config('enox.base_url'), '/') . '/';
        $uri = config('enox.endpoints.style_stock_report');

        return Http::withHeaders(array_merge(config('enox.headers'), [
            'Accept' => '*/*',
        ]))
            ->timeout(config('enox.timeout'))
            ->retry(config('enox.retry'), 200)
            ->get($baseUrl . $uri, $filters);
    }
}
