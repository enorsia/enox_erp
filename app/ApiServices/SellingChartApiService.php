<?php

namespace App\ApiServices;

use App\Http\Clients\EnoxApiClient;
use App\Models\SellingChartType;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SellingChartApiService
{
    public function __construct(
        protected EnoxApiClient $api
    ) {}

    public function get($url, array $filters = [])
    {
        return $this->api->get($url, $filters);
    }


    // for get response

    public function getEcomProducts(array $filters = [])
    {
        $ecommerceProducts = collect();
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->get(config('enox.endpoints.selling_chart_ecom_products'), $filters);
            if ($response->failed()) {
                throw new Exception('API request failed');
            }

            $ecommerceProducts = collect($response->json('data.ecommerceProducts', []));
        } catch (Exception $e) {
            Log::error('Selling Chart products API Error', [
                'message' => $e->getMessage(),
            ]);
        }

        return $ecommerceProducts;
    }

    public function getLookupResponse($typeIds = [], $names = [])
    {
        $lookupData = collect();

        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->get(config('enox.endpoints.selling_chart_lookup_names'), [
                'typeIds' => $typeIds,
                'names' => $names,
            ]);
            if ($response->failed()) {
                throw new Exception('API request failed');
            }

            if ($response->successful()) {
                $lookupData = $response->json('data.lookupNames', collect());
            }
        } catch (Exception $e) {
            Log::error('Selling_chart lookup_names API Error', [
                'message' => $e->getMessage(),
            ]);
        }
        return $lookupData;
    }

    public function getPoHistoryResponse($styleIds = [])
    {
        $data = collect();
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->get(config('enox.endpoints.selling_chart_po_histories'), [
                'styleIds' => $styleIds,
            ]);
            if ($response->failed()) {
                throw new Exception('API request failed');
            }

            if ($response->successful()) {
                $data = $response->json('data.poHistories', collect());
            }
        } catch (Exception $e) {
            Log::error('Selling_chart po histories API Error', [
                'message' => $e->getMessage(),
            ]);
        }
        return $data;
    }

    public function getCategoryResponse()
    {
        $categoriesData = collect();
        try {
            /** @var \Illuminate\Http\Client\Response $response */
            $response = $this->get(config('enox.endpoints.selling_chart_product_categories'));

            if ($response->failed()) {
                throw new Exception('API request failed');
            }

            $categoriesData = collect($response->json('data.categories', collect()))->map(fn($item) => (object) $item);
        } catch (Exception $e) {
            Log::error('Selling_chart Categories API Error', [
                'message' => $e->getMessage(),
            ]);
        }

        return $categoriesData;
    }

    public function getCommonData(): array
    {
        // Cache::forget('common_data_v1');
        return Cache::remember('common_data_v1', now()->addHours(2), function () {
            $data = [];
            try {
                $lookupData = $this->getLookupResponse([1, 5, 8, 10, 11]);
                // Split by type_id
                $data['departments'] = collect($lookupData)->where('type_id', 1)->map(fn($item) => (object) $item);
                $data['fabrics'] = collect($lookupData)->where('type_id', 5)->map(fn($item) => (object) $item);
                $data['initialRepeats'] = collect($lookupData)->where('type_id', 8)->map(fn($item) => (object) $item);
                $data['seasons'] = collect($lookupData)->where('type_id', 10)->map(fn($item) => (object) $item);
                $data['seasons_phases'] = collect($lookupData)->where('type_id', 11)->map(fn($item) => (object) $item);

                // 2️⃣ Product Categories
                $getCategoryData = $this->getCategoryResponse();
                $data['selling_chart_cats'] = $getCategoryData->map(fn($item) => (object) $item);

                $data['selling_chart_types'] = SellingChartType::get();
            } catch (Exception $e) {
                Log::error('getCommonData API call failed', [
                    'message' => $e->getMessage()
                ]);
                // fallback empty arrays
                $data['fabrics'] = [];
                $data['initialRepeats'] = [];
                $data['seasons'] = [];
                $data['seasons_phases'] = [];
                $data['selling_chart_cats'] = [];
                $data['selling_chart_types'] = [];
                $data['departments'] = [];
            }

            return $data;
        });
    }
}
