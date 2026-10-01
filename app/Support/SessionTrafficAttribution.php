<?php

namespace App\Support;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Parse and persist UTM / click-id params from landing URLs.
 */
final class SessionTrafficAttribution
{
    /**
     * @return list<string>
     */
    public static function urlParamKeys(): array
    {
        static $keys = null;

        if ($keys !== null) {
            return $keys;
        }

        $keys = array_values(array_unique(array_merge([
            'utm_source',
            'utm_medium',
            'utm_campaign',
            'utm_id',
            'utm_content',
            'utm_term',
            'media_type',
        ], AttributionRules::trackedPlatformQueryParamNames())));

        return $keys;
    }

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

        foreach (self::urlParamKeys() as $key) {
            $value = $params[$key] ?? null;

            if (! is_scalar($value) || $value === '') {
                continue;
            }

            $parsed[$key] = (string) $value;
        }

        return self::finalizeParsedAttribution(
            $params,
            self::applyGoogleTrafficAliases(
                $params,
                self::applyPlatformQueryAliases($params, self::applyTrafficAliases($params, $parsed)),
            ),
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
    private static function applyPlatformQueryAliases(array $params, array $parsed): array
    {
        foreach (AttributionRules::PLATFORM_QUERY_PARAMS as $param => $definition) {
            $hasParam = isset($parsed[$param])
                || (is_scalar($params[$param] ?? null) && $params[$param] !== '');

            if (! $hasParam) {
                continue;
            }

            foreach (['utm_source', 'utm_medium'] as $field) {
                if (! isset($parsed[$field])) {
                    $parsed[$field] = $definition[$field];
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
        return array_merge([
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
        ], AttributionRules::refererHostAttributionMap());
    }

    /**
     * @param  array<string, string>  $parsed
     * @return array<string, string>
     */
    private static function mergeRefererAttribution(array $parsed, ?string $referer): array
    {
        if (! filled($referer)) {
            return $parsed;
        }

        foreach (self::parseFromUrl((string) $referer) as $key => $value) {
            if (! isset($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        foreach (self::inferFromReferer($referer) as $key => $value) {
            if (! isset($parsed[$key])) {
                $parsed[$key] = $value;
            }
        }

        return $parsed;
    }

    private static function refererFromActions(object $session, ?Collection $actions = null): ?string
    {
        if (method_exists($session, 'relationLoaded')
            && $session->relationLoaded('firstRefererAction')
            && filled($session->firstRefererAction?->referer)) {
            return (string) $session->firstRefererAction->referer;
        }

        $referer = self::firstActionReferer($session, $actions);

        return filled($referer) ? $referer : null;
    }

    /**
     * @param  array<string, string>|null  $parsed
     */
    private static function resolveReferer(object $session, ?Collection $actions = null, ?array $parsed = null): ?string
    {
        $referer = self::refererFromActions($session, $actions);

        if (filled($referer)) {
            return $referer;
        }

        $parsed ??= self::parseFromUrl($session->landing_page ?? null)
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
        object $session,
        array $actionPageUrls = [],
        ?string $referer = null,
    ): array {
        $parsed = self::parseFromUrl($session->landing_page ?? null);

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
        object $session,
        array $parsed = [],
        array $actionPageUrls = [],
        ?string $referer = null,
    ): array {
        $referer = filled($referer) ? $referer : self::refererFromActions($session, null);

        if ($parsed === []) {
            $parsed = self::buildParsedAttribution($session, $actionPageUrls, $referer);
        } else {
            $parsed = self::mergeRefererAttribution($parsed, $referer);
        }

        $sessionSource = filled($session->utm_source ?? null)
            ? self::normalizeSource((string) ($session->utm_source ?? ''))
            : null;
        $parsedSource = isset($parsed['utm_source'])
            ? self::normalizeSource($parsed['utm_source'])
            : null;

        return [
            'source' => $sessionSource ?? $parsedSource,
            'medium' => filled($session->utm_medium ?? null) ? (string) ($session->utm_medium ?? '') : ($parsed['utm_medium'] ?? null),
            'campaign' => filled($session->utm_campaign ?? null) ? (string) ($session->utm_campaign ?? '') : ($parsed['utm_campaign'] ?? null),
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
        object $session,
        array $actionPageUrls = [],
        ?string $referer = null,
    ): array {
        if ($actionPageUrls === [] && $referer === null) {
            foreach (self::sessionAttributionUrls($session, null) as $url) {
                $parsed = self::parseFromUrl($url);

                if (! AttributionRules::isMarketingQualifyingTouch($parsed, $url)) {
                    continue;
                }

                $source = self::normalizeSource((string) ($parsed['utm_source'] ?? '')) ?? ($parsed['utm_source'] ?? null);
                $medium = filled($parsed['utm_medium'] ?? null) ? trim((string) $parsed['utm_medium']) : 'none';

                if (filled($source)) {
                    return [
                        'source' => $source,
                        'medium' => $medium,
                    ];
                }
            }
        }

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

        foreach (self::urlParamKeys() as $key) {
            if ($key === 'utm_source' || $key === 'utm_medium' || $key === 'utm_campaign') {
                $columnValue = $session->{$key} ?? null;

                if (filled($columnValue)) {
                    $merged[$key] = $key === 'utm_source'
                        ? (self::normalizeSource((string) $columnValue) ?? (string) $columnValue)
                        : (string) $columnValue;
                }
            }
        }

        foreach (self::sessionAttributionUrls($session, $actions) as $url) {
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
    public static function conversionDisplayFields(ActivityEcomUser $session): array
    {
        $fields = [];

        if (filled($session->conversion_utm_source)) {
            $fields['Conversion source'] = self::displaySourceLabel($session->conversion_utm_source)
                ?? (string) $session->conversion_utm_source;
        }

        if (filled($session->conversion_utm_medium)) {
            $fields['Conversion medium'] = (string) $session->conversion_utm_medium;
        }

        if (filled($session->conversion_utm_campaign)) {
            $fields['Conversion campaign'] = (string) $session->conversion_utm_campaign;
        }

        if (filled($session->conversion_landing_page)) {
            $fields['Conversion landing page'] = (string) $session->conversion_landing_page;
        }

        if ($session->conversion_touch_captured_at !== null) {
            $fields['Conversion touch at'] = (string) $session->conversion_touch_captured_at;
        }

        return $fields;
    }

    /**
     * Purchase conversion when paid; otherwise 7-day visitor marketing attribution (if not direct).
     *
     * @return array<string, string>
     */
    public static function conversionOrMarketingDisplayFields(ActivityEcomUser $session): array
    {
        $conversion = self::conversionDisplayFields($session);

        if ($conversion !== []) {
            return $conversion;
        }

        $bucket = self::listTrafficDisplayBucket($session);

        $source = $bucket['source'] ?? '(direct)';

        if ($source === '(direct)') {
            return [];
        }

        $fields = [
            'Marketing source' => self::displaySourceLabel($source) ?? $source,
        ];

        $medium = $bucket['medium'] ?? null;

        if (filled($medium) && $medium !== 'none') {
            $fields['Marketing medium'] = (string) $medium;
        }

        $visitorId = trim((string) ($session->visitor_id ?? ''));
        $referenceAt = TrackerTime::toUtc($session->last_active_at ?? $session->created_at);

        if ($visitorId !== '' && $referenceAt !== null) {
            $touch = self::lastMarketingTouchForVisitorBefore($visitorId, $referenceAt);
            $touchSource = filled($touch['utm_source'] ?? null)
                ? (self::normalizeSource((string) $touch['utm_source']) ?? $touch['utm_source'])
                : null;

            if ($touch !== null && $touchSource === $source) {
                if (filled($touch['utm_campaign'] ?? null)) {
                    $fields['Marketing campaign'] = (string) $touch['utm_campaign'];
                }

                $touchLanding = $touch['landing_page'] ?? null;

                if (filled($touchLanding)
                    && AttributionRules::isMarketingQualifyingTouch(
                        self::parseFromUrl((string) $touchLanding),
                        (string) $touchLanding,
                    )) {
                    $fields['Touch landing page'] = (string) $touchLanding;
                }

                if ($touch['captured_at'] !== null) {
                    $fields['Touch at'] = TrackerTime::formatFromStorage($touch['captured_at'], 'd M Y, h:i A');
                }
            }
        }

        return $fields;
    }

    public static function displayFields(ActivityEcomUser $session, ?Collection $actions = null): array
    {
        $attribution = self::forSession($session, $actions);
        $fields = [];

        foreach (self::urlParamKeys() as $key) {
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
        $parsed = self::parseFromUrl($session->landing_page ?? null);
        $utm = self::resolvedUtmFields($session, $parsed);
        $utmParts = array_values(array_filter([$utm['medium'], $utm['campaign']], fn ($value) => filled($value)));
        $source = $utm['source'] ?? null;

        return [
            'source' => self::displaySourceLabel($source),
            'utm' => $utmParts !== [] ? implode(' / ', $utmParts) : null,
            'referer' => ($source !== null && isset(self::canonicalRefererBySource()[$source]))
                ? self::canonicalRefererBySource()[$source]
                : null,
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

        $landing = filled($session->landing_page)
            ? null
            : ($pageUrl ?: ($urls[0] ?? null));

        return self::applyBackfillUpdates($session, $parsed, $landing);
    }

    public static function backfillFromFirstAction(ActivityEcomUser $session): bool
    {
        return self::backfillFromAllActions($session);
    }

    /**
     * Re-parse landing page and every action page_url / referer (earliest wins for empty columns).
     */
    /**
     * @return array{parsed: array<string, string>, first_page_url: ?string}
     */
    /**
     * Last marketing touch for a visitor from stored actions on or before {@see $paymentAt} (7-day window).
     *
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, landing_page: ?string, captured_at: Carbon}|null
     */
    public static function lastMarketingTouchForVisitorBefore(string $visitorId, Carbon $paymentAt): ?array
    {
        if ($visitorId === '') {
            return null;
        }

        $paymentAt = $paymentAt->copy()->utc();
        $windowStart = $paymentAt->copy()->subDays(AttributionRules::attributionWindowDays());

        $rows = DB::table('activity_ecom_user_actions as a')
            ->join('activity_ecom_user as s', 's.session_id', '=', 'a.session_id')
            ->where('s.visitor_id', $visitorId)
            ->where('a.created_at', '>=', $windowStart)
            ->where('a.created_at', '<=', $paymentAt)
            ->orderBy('a.created_at')
            ->orderBy('a.id')
            ->get([
                'a.page_url',
                'a.referer',
                'a.created_at',
                's.landing_page',
            ]);

        $last = null;

        foreach ($rows as $row) {
            $capturedAt = Carbon::parse((string) $row->created_at)->utc();

            $ingest = self::sessionAttributesFromIngest(
                ['landing_page' => $row->landing_page],
                filled($row->page_url) ? (string) $row->page_url : null,
                filled($row->referer) ? (string) $row->referer : null,
            );

            $parsed = [
                'utm_source' => $ingest['utm_source'] ?? null,
                'utm_medium' => $ingest['utm_medium'] ?? null,
                'utm_campaign' => $ingest['utm_campaign'] ?? null,
            ];

            $landing = $ingest['landing_page'] ?? ($row->page_url ?: $row->landing_page);

            if (! AttributionRules::isMarketingQualifyingTouch($parsed, is_string($landing) ? $landing : null)) {
                continue;
            }

            $last = [
                'utm_source' => self::normalizeSource((string) ($parsed['utm_source'] ?? '')) ?? $parsed['utm_source'],
                'utm_medium' => $parsed['utm_medium'],
                'utm_campaign' => $parsed['utm_campaign'],
                'landing_page' => is_string($landing) ? $landing : null,
                'captured_at' => $capturedAt,
            ];
        }

        return $last;
    }

    public static function collectAttributionFromSessionActions(ActivityEcomUser $session): array
    {
        $parsed = self::parseFromUrl($session->landing_page ?? null);
        $firstPageUrl = null;

        $actions = ActivityEcomUserAction::query()
            ->where('session_id', $session->session_id)
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['page_url', 'referer']);

        foreach ($actions as $action) {
            if ($firstPageUrl === null && filled($action->page_url)) {
                $firstPageUrl = (string) $action->page_url;
            }

            if (filled($action->page_url)) {
                $parsed = array_merge($parsed, self::parseFromUrl((string) $action->page_url));
            }

            $parsed = self::mergeRefererAttribution($parsed, filled($action->referer) ? (string) $action->referer : null);
        }

        return [
            'parsed' => $parsed,
            'first_page_url' => $firstPageUrl,
        ];
    }

    public static function backfillFromAllActions(ActivityEcomUser $session): bool
    {
        $collected = self::collectAttributionFromSessionActions($session);
        $parsed = $collected['parsed'];
        $firstPageUrl = $collected['first_page_url'];

        if ($parsed === [] && ! filled($session->landing_page) && $firstPageUrl === null) {
            return false;
        }

        $landing = filled($session->landing_page) ? null : $firstPageUrl;

        return self::applyBackfillUpdates($session, $parsed, $landing);
    }

    /**
     * @param  array<string, string>  $parsed
     */
    private static function applyBackfillUpdates(ActivityEcomUser $session, array $parsed, ?string $preferredLandingPage = null): bool
    {
        $updates = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $column) {
            if ($column === 'utm_source' && ! AttributionRules::sessionUtmSourceIsEmpty($session->utm_source)) {
                continue;
            }

            if ($column !== 'utm_source' && filled($session->{$column})) {
                continue;
            }

            if (isset($parsed[$column])) {
                $updates[$column] = $parsed[$column];
            }
        }

        if (! filled($session->landing_page) && filled($preferredLandingPage)) {
            $updates['landing_page'] = $preferredLandingPage;
        }

        if ($updates === []) {
            return false;
        }

        $session->update($updates);

        return true;
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
     * Landing, first action URLs, and referers (earliest first) for session-level attribution display.
     *
     * @param  Collection<int, ActivityEcomUserAction>|null  $actions
     * @return list<string>
     */
    private static function sessionAttributionUrls(object $session, ?Collection $actions = null): array
    {
        $urls = [];
        $seen = [];

        $push = static function (?string $url) use (&$urls, &$seen): void {
            if (! filled($url)) {
                return;
            }

            $url = (string) $url;

            if (isset($seen[$url])) {
                return;
            }

            $seen[$url] = true;
            $urls[] = $url;
        };

        $push($session->landing_page ?? null);

        if ($actions !== null) {
            $sorted = $actions->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ]);

            foreach ($sorted as $action) {
                $push(filled($action->page_url ?? null) ? (string) $action->page_url : null);
                $push(filled($action->referer ?? null) ? (string) $action->referer : null);
            }
        } elseif (! filled($session->landing_page ?? null)) {
            $push(self::firstActionPageUrl($session));
            $push(self::refererFromActions($session));
        }

        return $urls;
    }

    /**
     * @param  Collection<int, ActivityEcomUserAction>|null  $actions
     */
    private static function firstActionPageUrl(object $session, ?Collection $actions = null): ?string
    {
        if ($actions !== null) {
            $action = $actions
                ->filter(fn (object $row) => filled($row->page_url ?? null))
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
    private static function firstActionReferer(object $session, ?Collection $actions = null): ?string
    {
        if ($actions !== null) {
            $action = $actions
                ->filter(fn (object $row) => filled($row->referer ?? null))
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

    /**
     * @return array{utm_source: string, traffic_type: string, paid_click_id: string}
     */
    public static function exportTrafficFields(ActivityEcomUser $session): array
    {
        $attribution = self::attributionFromSessionRecord($session);
        $sourceKey = filled($session->utm_source ?? null)
            ? (self::normalizeSource((string) $session->utm_source) ?? (string) $session->utm_source)
            : (isset($attribution['utm_source'])
                ? (self::normalizeSource($attribution['utm_source']) ?? $attribution['utm_source'])
                : null);
        $medium = filled($session->utm_medium ?? null)
            ? strtolower(trim((string) $session->utm_medium))
            : strtolower(trim((string) ($attribution['utm_medium'] ?? '')));
        $isPaid = self::isPaidTraffic($medium, $attribution);
        $isOrganic = ! $isPaid && $medium === 'organic';

        $trafficType = $isPaid
            ? 'Paid'
            : ($isOrganic ? 'Organic' : (filled($medium) && $medium !== 'none' ? ucfirst($medium) : '—'));

        return [
            'utm_source' => self::displaySourceLabel($sourceKey) ?? '—',
            'traffic_type' => $trafficType,
            'paid_click_id' => $isPaid ? (self::resolvePaidClickId($attribution, $sourceKey) ?? '—') : '—',
        ];
    }

    /**
     * Resolve attribution from persisted session fields only (no action queries).
     *
     * @return array<string, string>
     */
    private static function attributionFromSessionRecord(ActivityEcomUser $session): array
    {
        $merged = self::parseFromUrl($session->landing_page ?? null);

        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $key) {
            $columnValue = $session->{$key} ?? null;

            if (filled($columnValue)) {
                $merged[$key] = $key === 'utm_source'
                    ? (self::normalizeSource((string) $columnValue) ?? (string) $columnValue)
                    : (string) $columnValue;
            }
        }

        return $merged;
    }

    private static function isPaidTraffic(string $medium, array $attribution): bool
    {
        if (in_array($medium, ['paid', 'cpc'], true)) {
            return true;
        }

        foreach ([
            'gclid',
            'gbraid',
            'wbraid',
            'fbclid',
            'msclkid',
            'ttclid',
            'twclid',
            'li_fat_id',
            'epik',
            'sc_cid',
        ] as $key) {
            if (filled($attribution[$key] ?? null)) {
                return true;
            }
        }

        return isset($attribution['gad_campaignid']) || isset($attribution['gad_source']);
    }

    /**
     * @param  array<string, string>  $attribution
     */
    private static function resolvePaidClickId(array $attribution, ?string $sourceKey): ?string
    {
        $sourceKey = self::normalizeSource($sourceKey ?? '') ?? $sourceKey;

        if ($sourceKey === 'google') {
            return $attribution['gclid']
                ?? $attribution['gad_campaignid']
                ?? $attribution['gbraid']
                ?? $attribution['wbraid']
                ?? null;
        }

        if (in_array($sourceKey, ['facebook', 'instagram'], true)) {
            return $attribution['fbclid']
                ?? $attribution['utm_id']
                ?? null;
        }

        return match ($sourceKey) {
            'bing' => $attribution['msclkid'] ?? null,
            'tiktok' => $attribution['ttclid'] ?? null,
            'twitter' => $attribution['twclid'] ?? null,
            'linkedin' => $attribution['li_fat_id'] ?? null,
            'pinterest' => $attribution['epik'] ?? null,
            'snapchat' => $attribution['sc_cid'] ?? null,
            'awin' => $attribution['awc'] ?? null,
            default => $attribution['utm_id']
                ?? $attribution['gclid']
                ?? $attribution['fbclid']
                ?? $attribution['msclkid']
                ?? $attribution['ttclid']
                ?? $attribution['twclid']
                ?? $attribution['li_fat_id']
                ?? $attribution['epik']
                ?? $attribution['sc_cid']
                ?? $attribution['gad_campaignid']
                ?? null,
        };
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
            'srsltid' => 'Google Shopping / Search listing ID',
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

    /**
     * Activity list columns: stored 7-day bucket when present, else compute.
     *
     * @return array{source: string, medium: string}
     */
    public static function listTrafficDisplayBucket(object $session): array
    {
        if (filled($session->list_traffic_utm_source ?? null)) {
            return [
                'source' => (string) $session->list_traffic_utm_source,
                'medium' => filled($session->list_traffic_utm_medium ?? null)
                    ? (string) $session->list_traffic_utm_medium
                    : 'none',
            ];
        }

        $visitBucket = self::resolvedTrafficBucket($session);

        if ($visitBucket['source'] !== '(direct)' && trim((string) ($session->visitor_id ?? '')) === '') {
            return $visitBucket;
        }

        return self::resolveListTrafficBucket($session);
    }

    /**
     * Dashboard traffic-source table: session-row fields only (no per-visitor touch queries).
     *
     * @return array{source: string, medium: string}
     */
    public static function dashboardTrafficDisplayBucket(object $session): array
    {
        if (filled($session->list_traffic_utm_source ?? null)) {
            return [
                'source' => (string) $session->list_traffic_utm_source,
                'medium' => filled($session->list_traffic_utm_medium ?? null)
                    ? (string) $session->list_traffic_utm_medium
                    : 'none',
            ];
        }

        if (! empty($session->has_payment_success) && filled($session->conversion_utm_source ?? null)) {
            $source = self::normalizeSource((string) $session->conversion_utm_source)
                ?? (string) $session->conversion_utm_source;
            $medium = filled($session->conversion_utm_medium ?? null)
                ? trim((string) $session->conversion_utm_medium)
                : 'none';

            return ['source' => $source, 'medium' => $medium];
        }

        if (filled($session->utm_source ?? null)) {
            $source = self::normalizeSource((string) $session->utm_source)
                ?? (string) $session->utm_source;

            return [
                'source' => $source,
                'medium' => filled($session->utm_medium ?? null)
                    ? trim((string) $session->utm_medium)
                    : 'none',
            ];
        }

        if (filled($session->landing_page ?? null)) {
            return self::resolvedTrafficBucket($session);
        }

        return ['source' => '(direct)', 'medium' => 'none'];
    }

    /**
     * Activity list / filters: conversion on paid orders, else 7-day visitor last touch, else this session.
     *
     * @return array{source: string, medium: string}
     */
    public static function resolveListTrafficBucket(object $session): array
    {
        if ($session->has_payment_success && filled($session->conversion_utm_source)) {
            $source = self::normalizeSource((string) $session->conversion_utm_source)
                ?? (string) $session->conversion_utm_source;
            $medium = filled($session->conversion_utm_medium)
                ? trim((string) $session->conversion_utm_medium)
                : 'none';

            return ['source' => $source, 'medium' => $medium];
        }

        $visitorId = trim((string) ($session->visitor_id ?? ''));
        $referenceAt = TrackerTime::toUtc($session->last_active_at ?? $session->created_at);

        if ($visitorId !== '' && $referenceAt !== null) {
            $touch = self::lastMarketingTouchForVisitorBefore($visitorId, $referenceAt);

            if ($touch !== null && filled($touch['utm_source'] ?? null)) {
                $medium = filled($touch['utm_medium'] ?? null)
                    ? trim((string) $touch['utm_medium'])
                    : 'none';

                return [
                    'source' => self::normalizeSource((string) $touch['utm_source']) ?? (string) $touch['utm_source'],
                    'medium' => $medium,
                ];
            }
        }

        return self::resolvedTrafficBucket($session);
    }

    public static function syncListTrafficAttributionColumns(ActivityEcomUser $session): bool
    {
        $bucket = self::resolveListTrafficBucket($session);

        if ($session->list_traffic_utm_source === $bucket['source']
            && $session->list_traffic_utm_medium === $bucket['medium']) {
            return false;
        }

        $session->forceFill([
            'list_traffic_utm_source' => $bucket['source'],
            'list_traffic_utm_medium' => $bucket['medium'],
        ])->save();

        return true;
    }
}
