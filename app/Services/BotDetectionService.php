<?php

namespace App\Services;

use App\Support\EcomTrackerLogger;
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
                return $this->classified('request', [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'cloudflare bot score',
                ], ['cf_bot_score' => $score]);
            }

            if ($score < $threshold + 20) {
                return $this->classified('request', [
                    'is_bot' => false,
                    'confidence' => 'medium',
                    'reason' => 'cloudflare bot score borderline',
                ], ['cf_bot_score' => $score]);
            }
        }

        $userAgent = $request->userAgent();

        if ($userAgent === null || trim($userAgent) === '') {
            return $this->classified('request', [
                'is_bot' => true,
                'confidence' => 'high',
                'reason' => 'missing UA',
            ]);
        }

        $userAgentLower = strtolower($userAgent);

        foreach (config('bot-detection.known_bot_user_agents', []) as $pattern) {
            if ($pattern !== '' && str_contains($userAgentLower, strtolower($pattern))) {
                return $this->classified('request', [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'known crawler/script UA',
                ], ['ua_pattern' => $pattern]);
            }
        }

        return $this->classified('request', [
            'is_bot' => false,
            'confidence' => 'low',
            'reason' => 'no bot signals detected',
        ]);
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
                return $this->classified('context', [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'cloudflare bot score',
                ], ['cf_bot_score' => $score]);
            }

            if ($score < $threshold + 20) {
                return $this->classified('context', [
                    'is_bot' => false,
                    'confidence' => 'medium',
                    'reason' => 'cloudflare bot score borderline',
                ], ['cf_bot_score' => $score]);
            }
        }

        $userAgent = $context['user_agent'] ?? null;

        if ($userAgent === null || trim((string) $userAgent) === '') {
            return $this->classified('context', [
                'is_bot' => true,
                'confidence' => 'high',
                'reason' => 'missing UA',
            ]);
        }

        $userAgentLower = strtolower((string) $userAgent);

        foreach (config('bot-detection.known_bot_user_agents', []) as $pattern) {
            if ($pattern !== '' && str_contains($userAgentLower, strtolower($pattern))) {
                return $this->classified('context', [
                    'is_bot' => true,
                    'confidence' => 'high',
                    'reason' => 'known crawler/script UA',
                ], ['ua_pattern' => $pattern]);
            }
        }

        return $this->classified('context', [
            'is_bot' => false,
            'confidence' => 'low',
            'reason' => 'no bot signals detected',
        ]);
    }

    /**
     * @param  array{is_bot: bool, confidence: 'high'|'medium'|'low', reason: string}  $result
     * @param  array<string, mixed>  $context
     * @return array{is_bot: bool, confidence: 'high'|'medium'|'low', reason: string}
     */
    private function classified(string $source, array $result, array $context = []): array
    {
        EcomTrackerLogger::frontend()->debug('bot.detect', 'Bot check finished', array_merge([
            'source' => $source,
            'is_bot' => $result['is_bot'],
            'confidence' => $result['confidence'],
            'reason' => $result['reason'],
        ], $context));

        return $result;
    }
}
