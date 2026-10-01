<?php

namespace App\Services;

use App\Models\AttributionTouchLog;
use App\Models\VisitorLastPaidTouch;
use App\Support\AttributionRules;
use App\Support\EcomTrackerLogger;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class VisitorPaidTouchService
{
    /**
     * @param  array<string, mixed>  $sessionData
     */
    public function recordFromIngestEvent(
        string $visitorId,
        string $sessionId,
        ?string $pageUrl,
        ?string $referer,
        Carbon $capturedAt,
        ?string $eventId = null,
        array $sessionData = [],
    ): void {
        if ($visitorId === '') {
            return;
        }

        $parsed = [];

        foreach ([$sessionData['landing_page'] ?? null, $pageUrl] as $url) {
            if (filled($url)) {
                $parsed = array_merge($parsed, SessionTrafficAttribution::parseFromUrl((string) $url));
            }
        }

        $ingestFields = SessionTrafficAttribution::sessionAttributesFromIngest($sessionData, $pageUrl, $referer);

        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'landing_page'] as $field) {
            if (filled($ingestFields[$field] ?? null)) {
                $parsed[$field] = $ingestFields[$field];
            }
        }

        $qualifiesPaid = AttributionRules::isPaidQualifyingTouch($parsed, $pageUrl);
        $qualifiesMarketing = AttributionRules::isMarketingQualifyingTouch($parsed, $pageUrl);

        AttributionTouchLog::query()->create([
            'visitor_id' => $visitorId,
            'session_id' => $sessionId,
            'ingest_event_id' => $eventId,
            'utm_source' => $ingestFields['utm_source'] ?? $parsed['utm_source'] ?? null,
            'utm_medium' => $ingestFields['utm_medium'] ?? $parsed['utm_medium'] ?? null,
            'utm_campaign' => $ingestFields['utm_campaign'] ?? $parsed['utm_campaign'] ?? null,
            'landing_page' => $ingestFields['landing_page'] ?? $parsed['landing_page'] ?? $pageUrl,
            'qualifies_paid' => $qualifiesPaid,
            'captured_at' => TrackerTime::formatUtc($capturedAt),
        ]);

        if (! $qualifiesMarketing) {
            return;
        }

        $this->upsertLastPaidTouch($visitorId, [
            'utm_source' => $ingestFields['utm_source'] ?? $parsed['utm_source'] ?? null,
            'utm_medium' => $ingestFields['utm_medium'] ?? $parsed['utm_medium'] ?? null,
            'utm_campaign' => $ingestFields['utm_campaign'] ?? $parsed['utm_campaign'] ?? null,
            'landing_page' => $ingestFields['landing_page'] ?? $parsed['landing_page'] ?? $pageUrl,
            'click_param' => $this->firstMarketingClickParam($pageUrl, $parsed),
            'captured_at' => $capturedAt,
            'source_kind' => 'server',
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $clientTouch
     */
    public function mergeClientSnapshot(string $visitorId, ?array $clientTouch): void
    {
        if ($visitorId === '' || ! is_array($clientTouch)) {
            return;
        }

        $capturedAt = $this->parseCapturedAt($clientTouch['captured_at'] ?? null);

        if ($capturedAt === null) {
            return;
        }

        $parsed = array_filter([
            'utm_source' => $clientTouch['utm_source'] ?? null,
            'utm_medium' => $clientTouch['utm_medium'] ?? null,
            'utm_campaign' => $clientTouch['utm_campaign'] ?? null,
        ], fn ($v) => filled($v));

        $landing = isset($clientTouch['landing_page']) ? (string) $clientTouch['landing_page'] : null;

        if (! AttributionRules::isMarketingQualifyingTouch($parsed, $landing)) {
            return;
        }

        $this->upsertLastPaidTouch($visitorId, [
            'utm_source' => SessionTrafficAttribution::normalizeSource((string) ($clientTouch['utm_source'] ?? '')) ?? ($clientTouch['utm_source'] ?? null),
            'utm_medium' => $clientTouch['utm_medium'] ?? null,
            'utm_campaign' => $clientTouch['utm_campaign'] ?? null,
            'landing_page' => $clientTouch['landing_page'] ?? null,
            'click_param' => null,
            'captured_at' => $capturedAt,
            'source_kind' => 'client',
        ]);
    }

    /**
     * @param  array{utm_source?: ?string, utm_medium?: ?string, utm_campaign?: ?string, landing_page?: ?string, click_param?: ?string, captured_at: Carbon, source_kind: string}  $touch
     */
    private function upsertLastPaidTouch(string $visitorId, array $touch): void
    {
        $capturedAt = $touch['captured_at'];
        $formattedAt = TrackerTime::formatUtc($capturedAt);

        DB::transaction(function () use ($visitorId, $touch, $formattedAt, $capturedAt) {
            $existing = VisitorLastPaidTouch::query()->lockForUpdate()->find($visitorId);

            if ($existing !== null && $existing->captured_at !== null && $existing->captured_at->greaterThan($capturedAt)) {
                return;
            }

            VisitorLastPaidTouch::query()->updateOrInsert(
                ['visitor_id' => $visitorId],
                [
                    'utm_source' => $touch['utm_source'] ?? null,
                    'utm_medium' => $touch['utm_medium'] ?? null,
                    'utm_campaign' => $touch['utm_campaign'] ?? null,
                    'landing_page' => $touch['landing_page'] ?? null,
                    'click_param' => $touch['click_param'] ?? null,
                    'captured_at' => $formattedAt,
                    'source_kind' => $touch['source_kind'],
                    'updated_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
                    'created_at' => $existing?->created_at ?? TrackerTime::formatUtc(TrackerTime::nowUtc()),
                ],
            );
        });
    }

    /**
     * @param  array<string, string>  $parsed
     */
    private function firstMarketingClickParam(?string $url, array $parsed = []): ?string
    {
        if ($parsed === [] && filled($url)) {
            $parsed = SessionTrafficAttribution::parseFromUrl($url);
        }

        foreach (AttributionRules::trackedPlatformQueryParamNames() as $param) {
            if (filled($parsed[$param] ?? null)) {
                return $param;
            }
        }

        return null;
    }

    private function parseCapturedAt(mixed $value): ?Carbon
    {
        if (! filled($value)) {
            return null;
        }

        try {
            return Carbon::parse((string) $value)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    public function purgeOldTouchLogs(): int
    {
        $days = max(1, (int) config('tracker.attribution_touch_log_retention_days', 90));
        $cutoff = TrackerTime::formatUtc(TrackerTime::nowUtc()->subDays($days));

        return AttributionTouchLog::query()
            ->where('captured_at', '<', $cutoff)
            ->delete();
    }
}
