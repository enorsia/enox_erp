<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use Illuminate\Support\Collection;

/**
 * Parse and persist UTM / click-id params from landing URLs.
 */
final class SessionTrafficAttribution
{
    /** @var list<string> */
    public const URL_PARAMS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_id',
        'utm_content',
        'utm_term',
        'media_type',
        'fbclid',
        'gclid',
        'gbraid',
        'wbraid',
        'gad_source',
        'gad_campaignid',
        'msclkid',
        'awc',
        'ttclid',
        'twclid',
        'li_fat_id',
        'epik',
        'sc_cid',
    ];

    /** @var list<string> */
    private const SESSION_COLUMNS = [
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'landing_page',
    ];

    /**
     * @return array<string, string>
     */
    public static function parseFromUrl(?string $url): array
    {
        if (! filled($url)) {
            return [];
        }

        $query = parse_url($url, PHP_URL_QUERY);

        if (! is_string($query) || $query === '') {
            return [];
        }

        parse_str($query, $params);

        if (! is_array($params)) {
            return [];
        }

        $parsed = [];

        foreach (self::URL_PARAMS as $key) {
            $value = $params[$key] ?? null;

            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $parsed[$key] = (string) $value;
        }

        return self::finalizeParsedAttribution(
            $params,
            self::applyGoogleTrafficAliases($params, self::applyClickIdAliases($params, self::applyTrafficAliases($params, $parsed))),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private static function finalizeParsedAttribution(array $params, array $parsed): array
    {
        if (isset($parsed['utm_source'])) {
            $parsed['utm_source'] = self::normalizeSource($parsed['utm_source']) ?? $parsed['utm_source'];
        }

        return $parsed;
    }

    public static function normalizeSource(?string $source): ?string
    {
        if (! filled($source)) {
            return null;
        }

        $source = strtolower(trim($source));
        $aliases = config('tracker.utm_source_aliases', []);

        if (isset($aliases[$source])) {
            return $aliases[$source];
        }

        if (array_key_exists($source, config('tracker.utm_sources', []))) {
            return $source;
        }

        return $source;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private static function applyClickIdAliases(array $params, array $parsed): array
    {
        $clickIds = [
            'fbclid' => ['utm_source' => 'facebook', 'utm_medium' => 'paid'],
            'msclkid' => ['utm_source' => 'bing', 'utm_medium' => 'cpc'],
            'ttclid' => ['utm_source' => 'tiktok', 'utm_medium' => 'paid'],
            'twclid' => ['utm_source' => 'twitter', 'utm_medium' => 'paid'],
            'li_fat_id' => ['utm_source' => 'linkedin', 'utm_medium' => 'paid'],
            'epik' => ['utm_source' => 'pinterest', 'utm_medium' => 'paid'],
            'sc_cid' => ['utm_source' => 'snapchat', 'utm_medium' => 'paid'],
        ];

        foreach ($clickIds as $param => $attribution) {
            $hasClickId = isset($parsed[$param])
                || (is_scalar($params[$param] ?? null) && $params[$param] !== '');

            if (! $hasClickId) {
                continue;
            }

            foreach ($attribution as $field => $value) {
                if (! isset($parsed[$field])) {
                    $parsed[$field] = $value;
                }
            }
        }

        if (! isset($parsed['utm_source']) && isset($parsed['awc'])) {
            $parsed['utm_source'] = 'awin';
        }

        if (! isset($parsed['utm_medium']) && isset($parsed['awc'])) {
            $parsed['utm_medium'] = 'affiliate';
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private static function applyGoogleTrafficAliases(array $params, array $parsed): array
    {
        $gadCampaignId = $parsed['gad_campaignid']
            ?? (is_scalar($params['gad_campaignid'] ?? null) && $params['gad_campaignid'] !== ''
                ? (string) $params['gad_campaignid']
                : null);

        if ($gadCampaignId !== null) {
            $parsed['gad_campaignid'] = $gadCampaignId;

            if (! isset($parsed['utm_campaign'])) {
                $parsed['utm_campaign'] = $gadCampaignId;
            }
        }

        $hasGooglePaidClick = isset($parsed['gclid'])
            || isset($parsed['gbraid'])
            || isset($parsed['wbraid'])
            || $gadCampaignId !== null
            || (isset($parsed['gad_source']) && $parsed['gad_source'] !== '');

        if (! isset($parsed['utm_source']) && $hasGooglePaidClick) {
            $parsed['utm_source'] = 'google';
        }

        if (! isset($parsed['utm_medium']) && $hasGooglePaidClick) {
            $parsed['utm_medium'] = 'paid';
        }

        return $parsed;
    }

    /**
     * @return array<string, string>
     */
    public static function inferFromReferer(?string $referer): array
    {
        if (! filled($referer)) {
            return [];
        }

        $host = parse_url($referer, PHP_URL_HOST);

        if (! is_string($host) || $host === '') {
            return [];
        }

        $host = strtolower($host);

        foreach (self::refererHostAttribution() as $pattern => $attribution) {
            if (str_contains($host, $pattern)) {
                return $attribution;
            }
        }

        return [];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function refererHostAttribution(): array
    {
        return [
            'google.' => [
                'utm_source' => 'google',
                'utm_medium' => 'organic',
            ],
            'facebook.' => [
                'utm_source' => 'facebook',
                'utm_medium' => 'social',
            ],
            'fb.com' => [
                'utm_source' => 'facebook',
                'utm_medium' => 'social',
            ],
            'instagram.' => [
                'utm_source' => 'instagram',
                'utm_medium' => 'social',
            ],
            'tiktok.' => [
                'utm_source' => 'tiktok',
                'utm_medium' => 'social',
            ],
            'youtube.' => [
                'utm_source' => 'youtube',
                'utm_medium' => 'social',
            ],
            'youtu.be' => [
                'utm_source' => 'youtube',
                'utm_medium' => 'social',
            ],
            'bing.' => [
                'utm_source' => 'bing',
                'utm_medium' => 'organic',
            ],
            'pinterest.' => [
                'utm_source' => 'pinterest',
                'utm_medium' => 'social',
            ],
            'linkedin.' => [
                'utm_source' => 'linkedin',
                'utm_medium' => 'social',
            ],
            'twitter.' => [
                'utm_source' => 'twitter',
                'utm_medium' => 'social',
            ],
            'x.com' => [
                'utm_source' => 'twitter',
                'utm_medium' => 'social',
            ],
            't.co' => [
                'utm_source' => 'twitter',
                'utm_medium' => 'social',
            ],
            'snapchat.' => [
                'utm_source' => 'snapchat',
                'utm_medium' => 'social',
            ],
        ];
    }

    /**
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private static function mergeRefererAttribution(array $parsed, ?string $referer): array
    {
        foreach (self::inferFromReferer($referer) as $key => $value) {
            if (! isset($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    private static function refererFromActions(ActivityEcomUser $session, ?Collection $actions = null): ?string
    {
        if ($session->relationLoaded('firstRefererAction') && filled($session->firstRefererAction?->referer)) {
            return (string) $session->firstRefererAction->referer;
        }

        $referer = self::firstActionReferer($session, $actions);

        return filled($referer) ? $referer : null;
    }

    /**
     * @param  array<string, string>|null  $parsed
     */
    private static function resolveReferer(ActivityEcomUser $session, ?Collection $actions = null, ?array $parsed = null): ?string
    {
        $referer = self::refererFromActions($session, $actions);

        if (filled($referer)) {
            return $referer;
        }

        $parsed ??= self::parseFromUrl($session->landing_page)
            + self::parseFromUrl(self::firstActionPageUrl($session, $actions));

        $source = self::normalizeSource($parsed['utm_source'] ?? null);

        if ($source !== null && isset(self::canonicalRefererBySource()[$source])) {
            return self::canonicalRefererBySource()[$source];
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    private static function canonicalRefererBySource(): array
    {
        return [
            'google' => 'https://www.google.com/',
            'facebook' => 'https://www.facebook.com/',
            'instagram' => 'https://www.instagram.com/',
            'tiktok' => 'https://www.tiktok.com/',
            'youtube' => 'https://www.youtube.com/',
            'bing' => 'https://www.bing.com/',
            'pinterest' => 'https://www.pinterest.com/',
            'linkedin' => 'https://www.linkedin.com/',
            'twitter' => 'https://twitter.com/',
            'snapchat' => 'https://www.snapchat.com/',
        ];
    }

    /**
     * @param  array<string, mixed>  $params
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private static function applyTrafficAliases(array $params, array $parsed): array
    {
        if (! isset($parsed['utm_source'])) {
            $source = $params['source'] ?? null;

            if (is_scalar($source) && $source !== '') {
                $parsed['utm_source'] = self::normalizeSource((string) $source) ?? (string) $source;
            }
        }

        if (! isset($parsed['utm_medium']) && isset($params['sv1']) && is_scalar($params['sv1']) && $params['sv1'] !== '') {
            $parsed['utm_medium'] = (string) $params['sv1'];
        }

        if (! isset($parsed['utm_campaign']) && isset($params['sv_campaign_id']) && is_scalar($params['sv_campaign_id']) && $params['sv_campaign_id'] !== '') {
            $parsed['utm_campaign'] = (string) $params['sv_campaign_id'];
        }

        return $parsed;
    }

    /**
     * @param  list<string>  $actionPageUrls
     * @return array<string, string>
     */
    public static function buildParsedAttribution(
        ActivityEcomUser $session,
        array $actionPageUrls = [],
        ?string $referer = null,
    ): array {
        $parsed = self::parseFromUrl($session->landing_page);

        if ($actionPageUrls === []) {
            $firstPageUrl = self::firstActionPageUrl($session);

            if (filled($firstPageUrl)) {
                $actionPageUrls = [$firstPageUrl];
            }
        }

        foreach ($actionPageUrls as $url) {
            if (filled($url)) {
                $parsed = $parsed + self::parseFromUrl((string) $url);
            }
        }

        if ($referer === null) {
            $referer = self::refererFromActions($session);
        }

        return self::mergeRefererAttribution($parsed, $referer);
    }

    /**
     * @param  array<string, string>  $parsed
     * @param  list<string>  $actionPageUrls
     * @return array{source: ?string, medium: ?string, campaign: ?string}
     */
    public static function resolvedUtmFields(
        ActivityEcomUser $session,
        array $parsed = [],
        array $actionPageUrls = [],
        ?string $referer = null,
    ): array {
        if ($parsed === []) {
            $parsed = self::buildParsedAttribution($session, $actionPageUrls, $referer);
        } else {
            $parsed = self::mergeRefererAttribution($parsed, $referer ?? self::refererFromActions($session));
        }

        $sessionSource = filled($session->utm_source)
            ? self::normalizeSource((string) $session->utm_source)
            : null;
        $parsedSource = isset($parsed['utm_source'])
            ? self::normalizeSource($parsed['utm_source'])
            : null;

        return [
            'source' => $sessionSource ?? $parsedSource,
            'medium' => filled($session->utm_medium) ? (string) $session->utm_medium : ($parsed['utm_medium'] ?? null),
            'campaign' => filled($session->utm_campaign) ? (string) $session->utm_campaign : ($parsed['utm_campaign'] ?? null),
        ];
    }

    public static function displaySourceLabel(?string $source): ?string
    {
        if (! filled($source)) {
            return null;
        }

        $labels = config('tracker.utm_sources', []);
        $source = self::normalizeSource($source) ?? $source;

        return $labels[$source] ?? ucfirst((string) $source);
    }

    /**
     * Canonical source/medium keys for dashboard traffic-source grouping.
     *
     * @param  list<string>  $actionPageUrls
     * @return array{source: string, medium: string}
     */
    public static function resolvedTrafficBucket(
        ActivityEcomUser $session,
        array $actionPageUrls = [],
        ?string $referer = null,
    ): array {
        $utm = self::resolvedUtmFields($session, [], $actionPageUrls, $referer);

        $source = filled($utm['source'] ?? null)
            ? (self::normalizeSource((string) $utm['source']) ?? (string) $utm['source'])
            : '(direct)';

        $medium = filled($utm['medium'] ?? null)
            ? trim((string) $utm['medium'])
            : 'none';

        return [
            'source' => $source,
            'medium' => $medium,
        ];
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>|null  $actions
     * @return array<string, string>
     */
    public static function forSession(ActivityEcomUser $session, ?Collection $actions = null): array
    {
        $merged = [];

        foreach (self::URL_PARAMS as $key) {
            if ($key === 'utm_source' || $key === 'utm_medium' || $key === 'utm_campaign') {
                $columnValue = $session->{$key} ?? null;

                if (filled($columnValue)) {
                    $merged[$key] = $key === 'utm_source'
                        ? (self::normalizeSource((string) $columnValue) ?? (string) $columnValue)
                        : (string) $columnValue;
                }
            }
        }

        foreach ([$session->landing_page, self::firstActionPageUrl($session, $actions)] as $url) {
            foreach (self::parseFromUrl($url) as $key => $value) {
                if (! isset($merged[$key])) {
                    $merged[$key] = $value;
                }
            }
        }

        return self::mergeRefererAttribution($merged, self::refererFromActions($session, $actions));
    }

    /**
     * @return array<string, string>
     */
    public static function displayFields(ActivityEcomUser $session, ?Collection $actions = null): array
    {
        $attribution = self::forSession($session, $actions);
        $fields = [];

        foreach (self::URL_PARAMS as $key) {
            if (! isset($attribution[$key])) {
                continue;
            }

            $fields[self::label($key)] = $attribution[$key];
        }

        $referer = self::resolveReferer($session, $actions);

        if (filled($referer)) {
            $fields['Referer'] = $referer;
        }

        return $fields;
    }

    /**
     * @return array{source: ?string, utm: ?string, referer: ?string}
     */
    public static function listRowSummary(ActivityEcomUser $session): array
    {
        $parsed = self::buildParsedAttribution($session);
        $utm = self::resolvedUtmFields($session, $parsed);
        $utmParts = array_values(array_filter([$utm['medium'], $utm['campaign']], fn ($value) => filled($value)));

        return [
            'source' => self::displaySourceLabel($utm['source']),
            'utm' => $utmParts !== [] ? implode(' / ', $utmParts) : null,
            'referer' => self::resolveReferer($session, null, $parsed),
        ];
    }

    /**
     * Resolve session column values to persist during ingest.
     *
     * @param  array<string, mixed>  $sessionData
     * @return array<string, string>
     */
    public static function sessionAttributesFromIngest(
        array $sessionData,
        ?string $pageUrl = null,
        ?string $referer = null,
    ): array {
        $parsed = [];

        foreach ([$sessionData['landing_page'] ?? null, $pageUrl] as $url) {
            if (! filled($url)) {
                continue;
            }

            $parsed = array_merge($parsed, self::parseFromUrl($url));
        }

        $parsed = self::mergeRefererAttribution($parsed, $referer);

        $attributes = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $field) {
            if (filled($sessionData[$field] ?? null)) {
                $value = (string) $sessionData[$field];
                $attributes[$field] = $field === 'utm_source'
                    ? (self::normalizeSource($value) ?? $value)
                    : $value;
            } elseif (isset($parsed[$field])) {
                $attributes[$field] = $parsed[$field];
            }
        }

        $landing = filled($pageUrl)
            ? $pageUrl
            : ($sessionData['landing_page'] ?? null);

        if (filled($landing)) {
            $attributes['landing_page'] = (string) $landing;
        }

        return $attributes;
    }

    public static function backfillSession(ActivityEcomUser $session, ?string $pageUrl = null, ?string $referer = null): bool
    {
        $urls = array_filter([
            $pageUrl,
            $session->landing_page,
            self::firstActionPageUrl($session),
        ]);

        $parsed = [];

        foreach ($urls as $url) {
            $parsed = array_merge($parsed, self::parseFromUrl($url));
        }

        $parsed = self::mergeRefererAttribution(
            $parsed,
            $referer ?? self::refererFromActions($session),
        );

        if ($parsed === [] && $urls === []) {
            return false;
        }

        $updates = [];

        foreach (self::SESSION_COLUMNS as $column) {
            if (filled($session->{$column})) {
                continue;
            }

            if ($column === 'landing_page') {
                $landing = $pageUrl ?: ($urls[0] ?? null);

                if (filled($landing)) {
                    $updates[$column] = $landing;
                }

                continue;
            }

            if (isset($parsed[$column])) {
                $updates[$column] = $parsed[$column];
            }
        }

        if ($updates === []) {
            return false;
        }

        $session->update($updates);

        return true;
    }

    public static function backfillFromFirstAction(ActivityEcomUser $session): bool
    {
        $action = ActivityEcomUserAction::query()
            ->where('session_id', $session->session_id)
            ->whereNotNull('page_url')
            ->where('page_url', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->first();

        return self::backfillSession($session, $action?->page_url);
    }

    public static function backfillAllMissing(int $chunkSize = 100): int
    {
        $updated = 0;

        ActivityEcomUser::query()
            ->where(function ($query) {
                $query->whereNull('utm_source')->orWhere('utm_source', '')
                    ->orWhereNull('utm_medium')->orWhere('utm_medium', '')
                    ->orWhereNull('utm_campaign')->orWhere('utm_campaign', '');
            })
            ->orderBy('id')
            ->chunkById($chunkSize, function ($sessions) use (&$updated) {
                foreach ($sessions as $session) {
                    if (self::backfillFromFirstAction($session)) {
                        $updated++;
                    }
                }
            });

        return $updated;
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>|null  $actions
     */
    private static function firstActionPageUrl(ActivityEcomUser $session, ?Collection $actions = null): ?string
    {
        if ($actions !== null) {
            $action = $actions
                ->filter(fn (ActivityEcomUserAction $row) => filled($row->page_url))
                ->sortBy([
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();

            return $action?->page_url;
        }

        return ActivityEcomUserAction::query()
            ->where('session_id', $session->session_id)
            ->whereNotNull('page_url')
            ->where('page_url', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('page_url');
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>|null  $actions
     */
    private static function firstActionReferer(ActivityEcomUser $session, ?Collection $actions = null): ?string
    {
        if ($actions !== null) {
            $action = $actions
                ->filter(fn (ActivityEcomUserAction $row) => filled($row->referer))
                ->sortBy([
                    ['created_at', 'asc'],
                    ['id', 'asc'],
                ])
                ->first();

            return $action?->referer;
        }

        return ActivityEcomUserAction::query()
            ->where('session_id', $session->session_id)
            ->whereNotNull('referer')
            ->where('referer', '!=', '')
            ->orderBy('created_at')
            ->orderBy('id')
            ->value('referer');
    }

    private static function label(string $key): string
    {
        return match ($key) {
            'utm_source' => 'UTM source',
            'utm_medium' => 'UTM medium',
            'utm_campaign' => 'UTM campaign',
            'utm_id' => 'UTM ID',
            'utm_content' => 'UTM content',
            'utm_term' => 'UTM term',
            'media_type' => 'Media type',
            'fbclid' => 'Facebook click ID',
            'gclid' => 'Google click ID',
            'gbraid' => 'Google iOS click ID',
            'wbraid' => 'Google web click ID',
            'gad_source' => 'Google ad source',
            'gad_campaignid' => 'Google ad campaign ID',
            'msclkid' => 'Microsoft click ID',
            'awc' => 'Awin click ID',
            'ttclid' => 'TikTok click ID',
            'twclid' => 'Twitter / X click ID',
            'li_fat_id' => 'LinkedIn click ID',
            'epik' => 'Pinterest click ID',
            'sc_cid' => 'Snapchat click ID',
            default => str_replace('_', ' ', ucfirst($key)),
        };
    }
}
