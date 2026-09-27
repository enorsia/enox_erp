<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Models\VisitorLastPaidTouch;
use App\Support\AttributionRules;
use App\Support\EcomTrackerLogger;
use App\Support\SessionTrafficAttribution;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ConversionAttributionService
{
    /**
     * @param  array<string, mixed>  $sessionData
     * @return array<string, mixed>
     */
    public function applyForPaymentSuccess(
        ActivityEcomUserAction $action,
        array $sessionData = [],
        ?Carbon $paymentAt = null,
    ): array {
        $session = ActivityEcomUser::query()->where('session_id', $action->session_id)->first();

        if ($session === null) {
            return ['status' => 'skipped', 'reason' => 'missing_session'];
        }

        $visitorId = trim((string) ($session->visitor_id ?? $sessionData['visitor_id'] ?? ''));

        if ($visitorId === '') {
            EcomTrackerLogger::frontend()->info('conversion.missing_visitor_id', 'Payment without visitor_id; conversion not applied', [
                'session_id' => $action->session_id,
                'event_id' => $action->event_id,
            ]);

            return ['status' => 'skipped', 'reason' => 'missing_visitor_id'];
        }

        $paymentAt ??= $action->created_at ?? TrackerTime::nowUtc();
        $touch = $this->resolveTouch($visitorId, $sessionData, $paymentAt);

        if ($touch === null) {
            return ['status' => 'skipped', 'reason' => 'no_qualifying_touch'];
        }

        $payload = [
            'conversion_utm_source' => $touch['utm_source'],
            'conversion_utm_medium' => $touch['utm_medium'],
            'conversion_utm_campaign' => $touch['utm_campaign'],
            'conversion_landing_page' => $touch['landing_page'],
            'conversion_touch_captured_at' => TrackerTime::formatUtc($touch['captured_at']),
        ];

        $session->update($payload);

        $orderId = trim((string) ($action->order_id ?? ''));

        if ($orderId !== '') {
            DB::table('activity_ecom_orders')
                ->where('order_id', $orderId)
                ->update(array_merge($payload, [
                    'updated_at' => TrackerTime::formatUtc(TrackerTime::nowUtc()),
                ]));
        }

        return ['status' => 'ok', 'conversion_utm_source' => $touch['utm_source']];
    }

    /**
     * @param  array<string, mixed>  $sessionData
     * @return array{utm_source: ?string, utm_medium: ?string, utm_campaign: ?string, landing_page: ?string, captured_at: Carbon}|null
     */
    private function resolveTouch(string $visitorId, array $sessionData, Carbon $paymentAt): ?array
    {
        $windowStart = $paymentAt->copy()->subDays(AttributionRules::attributionWindowDays());

        $candidates = [];

        $serverTouch = VisitorLastPaidTouch::query()->find($visitorId);

        if ($serverTouch !== null && $serverTouch->captured_at !== null && $serverTouch->captured_at->greaterThanOrEqualTo($windowStart)) {
            $candidates[] = [
                'utm_source' => SessionTrafficAttribution::normalizeSource($serverTouch->utm_source) ?? $serverTouch->utm_source,
                'utm_medium' => $serverTouch->utm_medium,
                'utm_campaign' => $serverTouch->utm_campaign,
                'landing_page' => $serverTouch->landing_page,
                'captured_at' => $serverTouch->captured_at,
            ];
        }

        $clientTouch = $sessionData['last_paid_touch'] ?? null;

        if (is_array($clientTouch)) {
            try {
                $capturedAt = Carbon::parse((string) ($clientTouch['captured_at'] ?? ''))->utc();
            } catch (\Throwable) {
                $capturedAt = null;
            }

            if ($capturedAt !== null && $capturedAt->greaterThanOrEqualTo($windowStart)) {
                $parsed = [
                    'utm_source' => $clientTouch['utm_source'] ?? null,
                    'utm_medium' => $clientTouch['utm_medium'] ?? null,
                    'utm_campaign' => $clientTouch['utm_campaign'] ?? null,
                ];

                if (AttributionRules::isMarketingQualifyingTouch($parsed, $clientTouch['landing_page'] ?? null)) {
                    $candidates[] = [
                        'utm_source' => SessionTrafficAttribution::normalizeSource((string) ($clientTouch['utm_source'] ?? '')) ?? ($clientTouch['utm_source'] ?? null),
                        'utm_medium' => $clientTouch['utm_medium'] ?? null,
                        'utm_campaign' => $clientTouch['utm_campaign'] ?? null,
                        'landing_page' => $clientTouch['landing_page'] ?? null,
                        'captured_at' => $capturedAt,
                    ];
                }
            }
        }

        if ($candidates === []) {
            return null;
        }

        usort($candidates, fn (array $left, array $right) => $right['captured_at']->getTimestamp() <=> $left['captured_at']->getTimestamp());

        return $candidates[0];
    }
}
