<?php

namespace App\Support;

use App\Models\ActivityEcomUser;

/**
 * Single source of truth for paid-touch qualification, conversion-copy eligibility,
 * and platform query-param → utm_source mapping (email/SMS/affiliate/paid ads).
 */
final class AttributionRules
{
    /**
     * Query params beyond utm_* / media_type: maps to session attribution.
     * qualifies_paid_touch = updates visitor_last_paid_touch (conversion credit only).
     *
     * @var array<string, array{utm_source: string, utm_medium: string, qualifies_paid_touch: bool}>
     */
    public const PLATFORM_QUERY_PARAMS = [
        // Email / CRM / SMS (session source only in v1)
        '_kx' => ['utm_source' => 'klaviyo', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'kxcid' => ['utm_source' => 'klaviyo', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'mc_cid' => ['utm_source' => 'mailchimp', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'mc_eid' => ['utm_source' => 'mailchimp', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'omnisendContactId' => ['utm_source' => 'omnisend', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'omnisendAttributionToken' => ['utm_source' => 'omnisend', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        '_hsenc' => ['utm_source' => 'hubspot', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'hsCtaTracking' => ['utm_source' => 'hubspot', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'hsctatracking' => ['utm_source' => 'hubspot', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        '__hsfp' => ['utm_source' => 'hubspot', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'sib_id' => ['utm_source' => 'brevo', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'iterableCampaignId' => ['utm_source' => 'iterable', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'iterable_campaign_id' => ['utm_source' => 'iterable', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'cio_link_id' => ['utm_source' => 'customerio', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'attntv' => ['utm_source' => 'attentive', 'utm_medium' => 'sms', 'qualifies_paid_touch' => false],
        'attentive_id' => ['utm_source' => 'attentive', 'utm_medium' => 'sms', 'qualifies_paid_touch' => false],
        'ps_id' => ['utm_source' => 'postscript', 'utm_medium' => 'sms', 'qualifies_paid_touch' => false],
        'postscript_id' => ['utm_source' => 'postscript', 'utm_medium' => 'sms', 'qualifies_paid_touch' => false],
        'dotdigital_campaign_id' => ['utm_source' => 'dotdigital', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'ac_contact_id' => ['utm_source' => 'activecampaign', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        'sfmc_id' => ['utm_source' => 'salesforce', 'utm_medium' => 'email', 'qualifies_paid_touch' => false],
        // Paid ads (click IDs)
        'gclid' => ['utm_source' => 'google', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'gbraid' => ['utm_source' => 'google', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'wbraid' => ['utm_source' => 'google', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'gad_source' => ['utm_source' => 'google', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'gad_campaignid' => ['utm_source' => 'google', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        // Google Search / Shopping product listings (not paid click IDs)
        'srsltid' => ['utm_source' => 'google', 'utm_medium' => 'organic', 'qualifies_paid_touch' => false],
        'fbclid' => ['utm_source' => 'facebook', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'msclkid' => ['utm_source' => 'bing', 'utm_medium' => 'cpc', 'qualifies_paid_touch' => true],
        'ttclid' => ['utm_source' => 'tiktok', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'twclid' => ['utm_source' => 'twitter', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'li_fat_id' => ['utm_source' => 'linkedin', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'epik' => ['utm_source' => 'pinterest', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'sc_cid' => ['utm_source' => 'snapchat', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'rdt_cid' => ['utm_source' => 'reddit', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        'yclid' => ['utm_source' => 'yahoo', 'utm_medium' => 'paid', 'qualifies_paid_touch' => true],
        // Affiliate / partner (paid touch)
        'awc' => ['utm_source' => 'awin', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
        'irclickid' => ['utm_source' => 'impact', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
        'clickref' => ['utm_source' => 'cj', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
        'afftrack' => ['utm_source' => 'shareasale', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
        'ranMID' => ['utm_source' => 'rakuten', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
        'ranEAID' => ['utm_source' => 'rakuten', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
        'pk_campaign' => ['utm_source' => 'partnerize', 'utm_medium' => 'affiliate', 'qualifies_paid_touch' => true],
    ];

    /** @var list<string> */
    public const PAID_MEDIUMS = [
        'paid',
        'cpc',
        'affiliate',
    ];

    /** @var list<string> */
    public const EMAIL_CRM_SOURCES = [
        'klaviyo',
        'mailchimp',
        'omnisend',
        'hubspot',
        'brevo',
        'iterable',
        'customerio',
        'attentive',
        'postscript',
        'dotdigital',
        'activecampaign',
        'salesforce',
        'email',
    ];

    /**
     * @return list<string>
     */
    public static function trackedPlatformQueryParamNames(): array
    {
        return array_keys(self::PLATFORM_QUERY_PARAMS);
    }

    /**
     * @return list<string>
     */
    public static function paidClickQueryParamNames(): array
    {
        $names = [];

        foreach (self::PLATFORM_QUERY_PARAMS as $param => $definition) {
            if ($definition['qualifies_paid_touch']) {
                $names[] = $param;
            }
        }

        return $names;
    }

    /**
     * @return list<string>
     */
    public static function crmClickQueryParamNames(): array
    {
        $names = [];

        foreach (self::PLATFORM_QUERY_PARAMS as $param => $definition) {
            if (! $definition['qualifies_paid_touch']) {
                $names[] = $param;
            }
        }

        return $names;
    }

    public static function conversionWindowDays(): int
    {
        return max(1, (int) config('tracker.conversion_window_days', 7));
    }

    /** 7-day last-touch window for all marketing platforms (paid, email, affiliate, etc.). */
    public static function attributionWindowDays(): int
    {
        return self::conversionWindowDays();
    }

    /**
     * @param  array<string, string>  $parsed
     */
    public static function isMarketingQualifyingTouch(array $parsed, ?string $url = null): bool
    {
        if ($url !== null) {
            $parsed = array_merge($parsed, SessionTrafficAttribution::parseFromUrl($url));
        }

        $source = SessionTrafficAttribution::normalizeSource($parsed['utm_source'] ?? null);

        return filled($source);
    }

    /**
     * @return array{utm_source: string, utm_medium: string}|array{}
     */
    public static function platformAttributionForQueryParam(string $param): array
    {
        $definition = self::PLATFORM_QUERY_PARAMS[$param] ?? null;

        if ($definition === null) {
            return [];
        }

        return [
            'utm_source' => $definition['utm_source'],
            'utm_medium' => $definition['utm_medium'],
        ];
    }

    /**
     * @param  array<string, string>  $parsed
     */
    public static function isPaidQualifyingTouch(array $parsed, ?string $url = null): bool
    {
        if ($url !== null) {
            $parsed = array_merge($parsed, SessionTrafficAttribution::parseFromUrl($url));
        }

        foreach (self::paidClickQueryParamNames() as $param) {
            if (filled($parsed[$param] ?? null)) {
                return true;
            }
        }

        $medium = strtolower(trim((string) ($parsed['utm_medium'] ?? '')));

        if ($medium !== '' && in_array($medium, self::PAID_MEDIUMS, true)) {
            return true;
        }

        $source = SessionTrafficAttribution::normalizeSource($parsed['utm_source'] ?? null);

        if (in_array($source, ['awin', 'impact', 'cj', 'shareasale', 'rakuten', 'partnerize'], true)) {
            return true;
        }

        return false;
    }

    public static function isPaidQualifyingSession(ActivityEcomUser $session): bool
    {
        $parsed = [];

        foreach (['utm_source', 'utm_medium', 'utm_campaign'] as $field) {
            if (filled($session->{$field})) {
                $value = (string) $session->{$field};
                $parsed[$field] = $field === 'utm_source'
                    ? (SessionTrafficAttribution::normalizeSource($value) ?? $value)
                    : $value;
            }
        }

        if (filled($session->landing_page)) {
            $parsed = array_merge($parsed, SessionTrafficAttribution::parseFromUrl((string) $session->landing_page));
        }

        return self::isPaidQualifyingTouch($parsed, $session->landing_page);
    }

    public static function sessionAllowsPaidConversionCopy(ActivityEcomUser $session): bool
    {
        return ! self::isPaidQualifyingSession($session);
    }

    public static function isEmailOrCrmSessionSource(?string $source): bool
    {
        $normalized = SessionTrafficAttribution::normalizeSource($source);

        if ($normalized === null) {
            return false;
        }

        return in_array($normalized, self::EMAIL_CRM_SOURCES, true);
    }

    public static function sessionUtmSourceIsEmpty(?string $utmSource): bool
    {
        if (! filled($utmSource)) {
            return true;
        }

        return trim((string) $utmSource) === '(direct)';
    }

    /**
     * @return array<string, string>
     */
    public static function crmClickAliases(string $param): array
    {
        return self::platformAttributionForQueryParam($param);
    }

    /**
     * Referer host fragment → session attribution when no UTMs on URL.
     *
     * @return array<string, array{utm_source: string, utm_medium: string}>
     */
    public static function refererHostAttributionMap(): array
    {
        return [
            'klaviyo' => ['utm_source' => 'klaviyo', 'utm_medium' => 'email'],
            'klaviyomail' => ['utm_source' => 'klaviyo', 'utm_medium' => 'email'],
            'mailchimp' => ['utm_source' => 'mailchimp', 'utm_medium' => 'email'],
            'list-manage.com' => ['utm_source' => 'mailchimp', 'utm_medium' => 'email'],
            'omnisend' => ['utm_source' => 'omnisend', 'utm_medium' => 'email'],
            'hubspot' => ['utm_source' => 'hubspot', 'utm_medium' => 'email'],
            'hs-sites.com' => ['utm_source' => 'hubspot', 'utm_medium' => 'email'],
            'brevo' => ['utm_source' => 'brevo', 'utm_medium' => 'email'],
            'sendinblue' => ['utm_source' => 'brevo', 'utm_medium' => 'email'],
            'attentive' => ['utm_source' => 'attentive', 'utm_medium' => 'sms'],
            'postscript' => ['utm_source' => 'postscript', 'utm_medium' => 'sms'],
            'dotdigital' => ['utm_source' => 'dotdigital', 'utm_medium' => 'email'],
            'activecampaign' => ['utm_source' => 'activecampaign', 'utm_medium' => 'email'],
            'customer.io' => ['utm_source' => 'customerio', 'utm_medium' => 'email'],
            'iterable' => ['utm_source' => 'iterable', 'utm_medium' => 'email'],
            'reddit.' => ['utm_source' => 'reddit', 'utm_medium' => 'social'],
        ];
    }
}
