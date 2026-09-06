<?php

namespace App\Services;

use App\ApiServices\StyleStockService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleStockReportService
{
    public function __construct(
        protected StyleStockService $apiService,
    ) {}

    /**
     * @return array{success: bool, style_stocks: Collection, error: ?string}
     */
    public function getReport(array $filters = []): array
    {
        try {
            $response = $this->apiService->get($filters);
            $styleStocks = $this->parseReportResponse($response);

            return [
                'success' => true,
                'style_stocks' => $styleStocks,
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Style Stock Report fetch failed', [
                'message' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'style_stocks' => collect(),
                'error' => 'Unable to load style stock report. Please try again later.',
            ];
        }
    }

    /**
     * @return array{success: bool, body: ?string, filename: string, content_type: string, error: ?string}
     */
    public function exportReport(array $filters = []): array
    {
        $filters['action'] = 'export_stock_analysis';

        try {
            $response = $this->apiService->export($filters);

            if ($response->failed()) {
                throw new \RuntimeException($this->extractErrorMessage($response));
            }

            if ($this->responseIsJson($response)) {
                $payload = $response->json();
                $message = $payload['message'] ?? 'Export request failed.';

                throw new \RuntimeException($message);
            }

            return [
                'success' => true,
                'body' => $response->body(),
                'filename' => $this->resolveDownloadFilename($response),
                'content_type' => $response->header('Content-Type')
                    ?: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'error' => null,
            ];
        } catch (Throwable $e) {
            Log::error('Style Stock Report export failed', [
                'message' => $e->getMessage(),
                'filters' => $filters,
            ]);

            return [
                'success' => false,
                'body' => null,
                'filename' => 'Style Stock Report.xlsx',
                'content_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'error' => 'Unable to export style stock report. Please try again later.',
            ];
        }
    }

    protected function parseReportResponse(Response $response): Collection
    {
        if ($response->failed()) {
            throw new \RuntimeException($this->extractErrorMessage($response));
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new \RuntimeException('Invalid API response format.');
        }

        if (($payload['status'] ?? false) !== true) {
            throw new \RuntimeException($payload['message'] ?? 'API returned an unsuccessful status.');
        }

        return collect($payload['data']['style_stocks'] ?? []);
    }

    protected function extractErrorMessage(Response $response): string
    {
        $payload = $response->json();

        if (is_array($payload) && ! empty($payload['message'])) {
            return (string) $payload['message'];
        }

        return "API request failed with status {$response->status()}.";
    }

    protected function responseIsJson(Response $response): bool
    {
        $contentType = strtolower((string) $response->header('Content-Type'));

        return str_contains($contentType, 'application/json')
            || str_contains($contentType, '+json');
    }

    protected function resolveDownloadFilename(Response $response): string
    {
        $disposition = (string) $response->header('Content-Disposition');

        if (preg_match('/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', $disposition, $matches)) {
            return trim($matches[1]);
        }

        return 'Style Stock Report.xlsx';
    }
}
