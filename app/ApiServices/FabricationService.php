<?php

namespace App\ApiServices;

use App\Http\Clients\EnoxApiClient;

class FabricationService
{
    public function __construct(
        protected EnoxApiClient $api
    ) {}

    public function get(array $filters = [])
    {
        return $this->api->get(config('enox.endpoints.fabrications'), $filters);
    }

    public function store(array $filters = [])
    {
        return $this->api->post(config('enox.endpoints.fabrications_store'), $filters);
    }
}
