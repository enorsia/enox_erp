<?php

namespace App\Services;

use App\Models\ActivityEcomUserBotContext;
use Illuminate\Support\Facades\Log;
use Throwable;

class BotContextPersister
{
    /**
     * @param  array{
     *     client_ip?: ?string,
     *     user_agent?: ?string,
     *     ip_country?: ?string,
     *     cf_ray?: ?string,
     *     cf_bot_score?: ?int,
     *     is_bot: bool,
     *     bot_confidence: string,
     *     bot_reason: string
     * }  $resolved
     */
    public function persistIfAbsent(string $sessionId, array $resolved): void
    {
        try {
            $now = now()->toDateTimeString();

            ActivityEcomUserBotContext::query()->insertOrIgnore([
                'session_id' => $sessionId,
                'client_ip' => $resolved['client_ip'] ?? null,
                'user_agent' => $resolved['user_agent'] ?? null,
                'ip_country' => $resolved['ip_country'] ?? null,
                'cf_ray' => $resolved['cf_ray'] ?? null,
                'cf_bot_score' => $resolved['cf_bot_score'] ?? null,
                'is_bot' => $resolved['is_bot'],
                'bot_confidence' => $resolved['bot_confidence'],
                'bot_reason' => $resolved['bot_reason'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        } catch (Throwable $e) {
            Log::warning('[EnoxTracker] Failed to persist bot context', [
                'session_id' => $sessionId,
                'message' => $e->getMessage(),
            ]);
        }
    }
}
