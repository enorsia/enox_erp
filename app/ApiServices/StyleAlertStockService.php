<?php

namespace App\ApiServices;

use App\Http\Clients\EnoxApiClient;
use Illuminate\Http\Client\Response;

class StyleAlertStockService
{
    public function __construct(
        protected EnoxApiClient $api
    ) {}

    public function get(string $style): Response
    {
        return $this->api->get(config('enox.endpoints.style_alert_detail'), [
            'style' => $style,
        ]);
    }
}