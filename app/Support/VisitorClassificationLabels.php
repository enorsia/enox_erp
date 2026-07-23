<?php

namespace App\Support;

class VisitorClassificationLabels
{
    /**
     * @return array{headline: string, help: string}
     */
    public static function reason(?string $botReason, bool $isBot): array
    {
        return match ($botReason) {
            'cloudflare bot score' => [
                'headline' => 'Automated traffic',
                'help' => 'Cloudflare trust score was below our threshold',
            ],
            'cloudflare bot score borderline' => [
                'headline' => 'Likely real visitor',
                'help' => 'Trust score is borderline — worth a quick review',
            ],
            'missing UA' => [
                'headline' => 'Automated traffic',
                'help' => 'No browser identifier was sent',
            ],
            'known crawler/script UA' => [
                'headline' => 'Crawler or bot',
                'help' => 'Matched a known search engine or script',
            ],
            'no bot signals detected' => [
                'headline' => 'Real visitor',
                'help' => 'Looks like a normal person browsing your site',
            ],
            default => $isBot
                ? ['headline' => 'Automated traffic', 'help' => 'Classified as non-human traffic']
                : ['headline' => 'Real visitor', 'help' => 'Looks like a normal person browsing your site'],
        };
    }

    public static function typeLabel(string $classification): string
    {
        return match ($classification) {
            'bot' => 'Automated traffic',
            'human' => 'Real visitor',
            default => 'Not classified',
        };
    }

    public static function typeLabelPlural(string $classification): string
    {
        return match ($classification) {
            'human' => 'Real visitors',
            'bot' => 'Automated traffic',
            default => 'Not classified',
        };
    }

    /**
     * @return array{real_shoppers: string, automated_traffic: string, not_classified: string, uk_shoppers: string}
     */
    public static function summaryMetricLabels(): array
    {
        return [
            'real_shoppers' => 'Real visitors',
            'automated_traffic' => 'Automated traffic',
            'not_classified' => 'Not classified',
            'uk_shoppers' => 'UK visitors',
        ];
    }

    public static function filterTypeLabels(): array
    {
        return [
            'human' => 'Real visitors',
            'bot' => 'Automated traffic',
            'unclassified' => 'Not classified',
        ];
    }

    /**
     * Marketer-facing label for country breakdown rows.
     */
    public static function countryBreakdownLabel(string $countryCode): string
    {
        $code = strtoupper(trim($countryCode));

        return match ($code) {
            'GB' => 'United Kingdom (GB)',
            'US' => 'United States (US)',
            default => $code,
        };
    }

    public static function typeBadgeClass(string $classification): string
    {
        return match ($classification) {
            'human' => 'bg-blue-50 dark:bg-blue-900/30 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800/50',
            'bot' => 'bg-amber-50 dark:bg-amber-900/30 text-amber-700 dark:text-amber-300 border-amber-200 dark:border-amber-800/50',
            default => 'bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400 border-slate-200 dark:border-slate-600',
        };
    }

    public static function confidenceLabel(?string $confidence): ?string
    {
        return match ($confidence) {
            'high' => 'High confidence',
            'medium' => 'Review recommended',
            'low' => 'Low confidence',
            default => null,
        };
    }

    public static function countryLabel(?string $ipCountry): ?string
    {
        if (! filled($ipCountry)) {
            return null;
        }

        $code = strtoupper((string) $ipCountry);

        if ($code === 'GB') {
            return 'UK visitor';
        }

        return 'International ('.$code.')';
    }

    public static function trustScoreLabel(?int $cfBotScore): string
    {
        if ($cfBotScore === null) {
            return 'Not available';
        }

        $threshold = (int) config('bot-detection.cloudflare_bot_score_threshold', 30);

        if ($cfBotScore < $threshold) {
            return sprintf('Trust score %d/99 — likely automated', $cfBotScore);
        }

        if ($cfBotScore < $threshold + 20) {
            return sprintf('Trust score %d/99 — borderline', $cfBotScore);
        }

        return sprintf('Trust score %d/99 — likely a real visitor', $cfBotScore);
    }

    public static function userAgentHint(?string $userAgent): ?string
    {
        if (! filled($userAgent)) {
            return null;
        }

        $ua = strtolower((string) $userAgent);

        $patterns = [
            'googlebot' => 'Google crawler',
            'bingbot' => 'Bing crawler',
            'facebookexternalhit' => 'Facebook crawler',
            'twitterbot' => 'Twitter crawler',
            'linkedinbot' => 'LinkedIn crawler',
            'applebot' => 'Apple crawler',
            'gptbot' => 'GPT crawler',
            'claudebot' => 'Claude crawler',
            'headlesschrome' => 'Headless browser',
            'python-requests' => 'Script (Python)',
            'curl/' => 'Script (curl)',
        ];

        foreach ($patterns as $needle => $label) {
            if (str_contains($ua, $needle)) {
                return $label;
            }
        }

        return null;
    }

    public static function unclassifiedHelp(): string
    {
        return 'Bot check was not captured for this session';
    }

    /**
     * Map internal bot_reason to marketer-facing breakdown label.
     */
    public static function breakdownLabel(?string $botReason, bool $isBot): string
    {
        $reason = self::reason($botReason, $isBot);

        return $reason['headline'];
    }
}
