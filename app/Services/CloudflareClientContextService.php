<?php

namespace App\Services;

use Illuminate\Http\Request;

class CloudflareClientContextService
{
    public function resolve(Request $request): array
    {
        $payload = $request->input('client_context');

        if (is_array($payload) && $payload !== []) {
            return $this->resolveFromContext($payload, $request);
        }

        $clientIp = $request->header('CF-Connecting-IP')
            ?: $request->header('X-Forwarded-For')
            ?: $request->ip();

        if (is_string($clientIp) && str_contains($clientIp, ',')) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }

        return [
            'client_ip' => $clientIp,
            'user_agent' => $request->userAgent(),
            'ip_country' => $this->normalizeCountry($request->header('CF-IPCountry')),
            'cf_ray' => $request->header('CF-Ray'),
            'cf_bot_score' => $this->parseBotScore($request->header('CF-Bot-Score')),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{client_ip: ?string, user_agent: ?string, ip_country: ?string, cf_ray: ?string, cf_bot_score: ?int}
     */
    public function resolveFromContext(array $context, ?Request $request = null): array
    {
        $clientIp = $context['client_ip'] ?? null;

        if (($clientIp === null || $clientIp === '') && $request !== null) {
            $clientIp = $request->header('CF-Connecting-IP')
                ?: $request->header('X-Forwarded-For')
                ?: $request->ip();
        }

        if (is_string($clientIp) && str_contains($clientIp, ',')) {
            $clientIp = trim(explode(',', $clientIp)[0]);
        }

        $userAgent = $context['user_agent'] ?? $request?->userAgent();

        return [
            'client_ip' => $clientIp,
            'user_agent' => $userAgent,
            'ip_country' => $this->normalizeCountry($context['ip_country'] ?? null),
            'cf_ray' => $context['cf_ray'] ?? null,
            'cf_bot_score' => array_key_exists('cf_bot_score', $context)
                ? $this->normalizeBotScore($context['cf_bot_score'])
                : null,
        ];
    }

    protected function normalizeCountry(mixed $country): ?string
    {
        if (! is_string($country) || $country === '' || strtoupper($country) === 'XX') {
            return null;
        }

        return strtoupper($country);
    }

    protected function parseBotScore(?string $botScore): ?int
    {
        if ($botScore === null || $botScore === '') {
            return null;
        }

        return $this->normalizeBotScore((int) $botScore);
    }

    protected function normalizeBotScore(mixed $score): ?int
    {
        if ($score === null || $score === '') {
            return null;
        }

        $score = (int) $score;

        return $score >= 1 && $score <= 99 ? $score : null;
    }
}
