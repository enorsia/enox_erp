<?php

namespace App\Services;

use App\ApiServices\StyleAlertStockService;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Log;
use Throwable;

class StyleStockAlertDetailService
{
    public function __construct(
        protected StyleAlertStockService $apiService,
    ) {}

    /**
     * @return array{status: bool, html: ?string, title: ?string, message: string}
     */
    public function panelForStyle(string $style): array
    {
        $style = trim($style);

        if ($style === '') {
            return [
                'status' => false,
                'html' => null,
                'title' => null,
                'message' => 'Style code is missing.',
            ];
        }

        try {
            $response = $this->apiService->get($style);

            if ($response->failed()) {
                throw new \RuntimeException($this->messageFromResponse($response));
            }

            $payload = $response->json();

            if (! is_array($payload) || ($payload['status'] ?? false) !== true) {
                throw new \RuntimeException($payload['message'] ?? 'API returned an unsuccessful status.');
            }

            $detail = $payload['data']['detail'] ?? null;

            if (! is_array($detail)) {
                throw new \RuntimeException('Invalid detail payload from API.');
            }

            $html = view('style_stocks.partials.alert-detail', ['detail' => $detail])->render();
            $title = trim(($detail['item_no'] ?? '') !== '' ? $detail['item_no'] : ($detail['style'] ?? $style));

            return [
                'status' => true,
                'html' => $html,
                'title' => $title,
                'message' => 'OK',
            ];
        } catch (Throwable $e) {
            Log::error('Style alert detail fetch failed', [
                'style' => $style,
                'message' => $e->getMessage(),
            ]);

            return [
                'status' => false,
                'html' => null,
                'title' => null,
                'message' => $e->getMessage() ?: 'Unable to load style details. Please try again later.',
            ];
        }
    }

    protected function messageFromResponse(Response $response): string
    {
        $payload = $response->json();

        if (is_array($payload) && ! empty($payload['message'])) {
            return (string) $payload['message'];
        }

        return 'API request failed with status '.$response->status().'.';
    }
}