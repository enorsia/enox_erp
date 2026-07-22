<?php

namespace App\Services;

use Illuminate\Http\Request;

class BotDetectionService
{
    /**
     * @return array{is_bot: bool, confidence: 'high'|'medium'|'low', reason: string}
     */
    public function isLikelyBot(Request $request): array
    {
        $botScore = $request->header('CF-Bot-Score');

        if ($botScore !== null && $botScore !== '') {
            $score = (int) $botScore;
            $threshold = (int) config('bot-detection.cloudflare_bot_score_threshold', 30);

            if ($score < $threshold) {
                return [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'cloudflare bot score',
                ];
            }

            if ($score < $threshold + 20) {
                return [
                    'is_bot' => false,
                    'confidence' => 'medium',
                    'reason' => 'cloudflare bot score borderline',
                ];
            }
        }

        $userAgent = $request->userAgent();

        if ($userAgent === null || trim($userAgent) === '') {
            return [
                'is_bot' => true,
                'confidence' => 'high',
                'reason' => 'missing UA',
            ];
        }

        $userAgentLower = strtolower($userAgent);

        foreach (config('bot-detection.known_bot_user_agents', []) as $pattern) {
            if ($pattern !== '' && str_contains($userAgentLower, strtolower($pattern))) {
                return [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'known crawler/script UA',
                ];
            }
        }

        return [
            'is_bot' => false,
            'confidence' => 'low',
            'reason' => 'no bot signals detected',
        ];
    }

    /**
     * Classify using resolved client_context (tracker ingest path).
     *
     * @param  array{cf_bot_score?: ?int, user_agent?: ?string}  $context
     * @return array{is_bot: bool, confidence: 'high'|'medium'|'low', reason: string}
     */
    public function isLikelyBotFromContext(array $context): array
    {
        $botScore = $context['cf_bot_score'] ?? null;

        if ($botScore !== null) {
            $score = (int) $botScore;
            $threshold = (int) config('bot-detection.cloudflare_bot_score_threshold', 30);

            if ($score < $threshold) {
                return [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'cloudflare bot score',
                ];
            }

            if ($score < $threshold + 20) {
                return [
                    'is_bot' => false,
                    'confidence' => 'medium',
                    'reason' => 'cloudflare bot score borderline',
                ];
            }
        }

        $userAgent = $context['user_agent'] ?? null;

        if ($userAgent === null || trim((string) $userAgent) === '') {
            return [
                'is_bot' => true,
                'confidence' => 'high',
                'reason' => 'missing UA',
            ];
        }

        $userAgentLower = strtolower((string) $userAgent);

        foreach (config('bot-detection.known_bot_user_agents', []) as $pattern) {
            if ($pattern !== '' && str_contains($userAgentLower, strtolower($pattern))) {
                return [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'known crawler/script UA',
                ];
            }
        }

        return [
            'is_bot' => false,
            'confidence' => 'low',
            'reason' => 'no bot signals detected',
        ];
    }
}
