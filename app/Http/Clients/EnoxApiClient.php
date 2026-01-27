<?php

namespace App\Http\Clients;

use Illuminate\Support\Facades\Http;
use Illuminate\Http\Client\Response;

class EnoxApiClient
{
    protected string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('enox.base_url'), '/') . '/';

        if (empty($this->baseUrl)) {
            throw new \RuntimeException('Enox API base URL not configured');
        }
    }

    protected function client()
    {
        return Http::withHeaders(config('enox.headers'))
            ->timeout(config('enox.timeout'))
            ->retry(config('enox.retry'), 200);
    }

    public function get(string $uri, array $params = [])
    {
        return $this->client()->get($this->baseUrl . $uri, $params);
    }

    public function post(string $uri, array $data = [])
    {
        return $this->client()->post($this->baseUrl . $uri, $data);
    }
}
