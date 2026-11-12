<?php

namespace App\ApiServices;

use App\Http\Clients\EnoxApiClient;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StyleProfitService
{
    public function __construct(
        protected EnoxApiClient $api
    ) {}

    public function get(array $filters = []): Response
    {
        return $this->api->get(config('enox.endpoints.style_orders'), $filters);
    }

    public function ecomOrdersNormalize(array $data = [])
    {
        $normalizeData = [];
        $orders = $data['data']['ecom_orders'] ?? [];
        $platforms = $data['data']['platforms'] ?? [];
        $deliveryPartners = $data['data']['deliveryPartners'] ?? [];

        foreach ($orders as $order) {
            foreach ($order['order_products'] as $item) {
                $deliveryPartner = $deliveryPartners[1][$order['shipment']['delivery_partner'] ?? null] ?? null;
                $normalizeData[] = [
                    'order_id' => $order['order_number'] ?? null,
                    'order_date' => $order['created_at'] ?? null,
                    'product_code' => $item['product']['sku'] ?? null,
                    'style_no' => $item['product']['style']['name'] ?? null,
                    'marketplace' => $platforms[1]['name'] ?? null,
                    'courier' => $deliveryPartner,
                    'price' => $item['price'] ?? 0.0,
                    'quantity' => $item['quantity'] ?? 0,
                    'total_price' => ($item['price'] ?? 0.0) * ($item['quantity'] ?? 0),
                ];
            }
        }

        return $normalizeData;
    }
}
