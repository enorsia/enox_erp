<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class TrackerClientContextResolver
{
    public function __construct(
        private CloudflareClientContextService $clientContext,
        private BotDetectionService $botDetection,
    ) {}

    /**
     * @return array{
     *     client_ip: ?string,
     *     user_agent: ?string,
     *     ip_country: ?string,
     *     cf_ray: ?string,
     *     cf_bot_score: ?int,
     *     is_bot: bool,
     *     bot_confidence: 'high'|'medium'|'low',
     *     bot_reason: string
     * }|null
     */
    public function resolve(Request $request): ?array
    {
        $sanitized = $this->sanitizeClientContext($request->input('client_context'));

        if ($sanitized === null) {
            return null;
        }

        $request->merge(['client_context' => $sanitized]);

        $client = $this->clientContext->resolveFromContext($sanitized, $request);
        $bot = $this->botDetection->isLikelyBotFromContext($client);

        return array_merge($client, [
            'is_bot' => $bot['is_bot'],
            'bot_confidence' => $bot['confidence'],
            'bot_reason' => $bot['reason'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function sanitizeClientContext(mixed $clientContext): ?array
    {
        if (! is_array($clientContext) || $clientContext === []) {
            return null;
        }

        $validator = Validator::make($clientContext, [
            'client_ip' => ['nullable', 'string', 'max:45'],
            'user_agent' => ['nullable', 'string'],
            'ip_country' => ['nullable', 'string', 'max:8'],
            'cf_ray' => ['nullable', 'string', 'max:64'],
            'cf_bot_score' => ['nullable', 'integer', 'min:1', 'max:99'],
        ]);

        if ($validator->fails()) {
            Log::warning('[EnoxTracker] Invalid client_context discarded', [
                'errors' => $validator->errors()->toArray(),
            ]);

            return null;
        }

        return $validator->validated();
    }

    /**
     * @return array{is_bot: bool, confidence: 'high'|'medium'|'low', reason: string}
     */
    public function classifyFromRequest(Request $request): array
    {
        $sanitized = $this->sanitizeClientContext($request->input('client_context'));

        if ($sanitized === null) {
            return $this->botDetection->isLikelyBotFromContext([
                'user_agent' => $request->userAgent(),
            ]);
        }

        $client = $this->clientContext->resolveFromContext($sanitized, $request);

        return $this->botDetection->isLikelyBotFromContext($client);
    }
}
