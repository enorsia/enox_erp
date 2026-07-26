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
        'msclkid',
        'awc',
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

        return self::applyTrafficAliases($params, $parsed);
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

            if ($source === 'aw') {
                $parsed['utm_source'] = 'awin';
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
     * @param  array<string, string>  $parsed
     * @return array{source: ?string, medium: ?string, campaign: ?string}
     */
    public static function resolvedUtmFields(ActivityEcomUser $session, array $parsed = []): array
    {
        if ($parsed === []) {
            $firstAction = $session->relationLoaded('firstAction') ? $session->firstAction : null;
            $parsed = self::parseFromUrl($session->landing_page)
                + self::parseFromUrl($firstAction?->page_url);
        }

        return [
            'source' => filled($session->utm_source) ? (string) $session->utm_source : ($parsed['utm_source'] ?? null),
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

        return $labels[$source] ?? ucfirst((string) $source);
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
                    $merged[$key] = (string) $columnValue;
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

        return $merged;
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

        $referer = self::firstActionReferer($session, $actions);

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
        $firstAction = $session->relationLoaded('firstAction') ? $session->firstAction : null;
        $parsed = self::parseFromUrl($session->landing_page)
            + self::parseFromUrl($firstAction?->page_url);
        $utm = self::resolvedUtmFields($session, $parsed);
        $utmParts = array_values(array_filter([$utm['medium'], $utm['campaign']], fn ($value) => filled($value)));

        return [
            'source' => self::displaySourceLabel($utm['source']),
            'utm' => $utmParts !== [] ? implode(' / ', $utmParts) : null,
            'referer' => filled($session->firstRefererAction?->referer)
                ? (string) $session->firstRefererAction->referer
                : null,
        ];
    }

    public static function backfillSession(ActivityEcomUser $session, ?string $pageUrl = null): bool
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
            'msclkid' => 'Microsoft click ID',
            'awc' => 'Awin click ID',
            default => str_replace('_', ' ', ucfirst($key)),
        };
    }
}
