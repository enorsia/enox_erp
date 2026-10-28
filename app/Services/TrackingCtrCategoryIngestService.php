<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\TrackingCtrCategory;
use App\Models\TrackingCtrCategoryImpression;
use App\Support\EcomTrackerLogger;
use App\Support\TrackerDatabase;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class TrackingCtrCategoryIngestService
{
    public function __construct(
        private VisitorSessionResolver $visitorSessionResolver,
        private TrackerDashboardSyncService $dashboardSyncService,
    ) {}

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    public function ingest(Request $request, array $payload): array
    {
        $sessionData = $payload['session'] ?? [];
        $categoryData = $payload['category'] ?? [];
        $events = $payload['events'] ?? [];

        if ($events === []) {
            return [];
        }

        $visitorId = trim((string) ($sessionData['visitor_id'] ?? ''));
        $sessionId = trim((string) ($sessionData['session_id'] ?? ''));

        if ($visitorId === '') {
            throw ValidationException::withMessages([
                'session.visitor_id' => ['Visitor ID is required.'],
            ]);
        }

        if ($sessionId === '') {
            throw ValidationException::withMessages([
                'session.session_id' => ['Session ID is required.'],
            ]);
        }

        $resolved = $this->visitorSessionResolver->resolveForIngest(
            $visitorId,
            $sessionId,
            [],
        );

        $sessionId = (string) $resolved['session_id'];

        $this->ensureActivitySessionExists($sessionId, $visitorId, $request);

        $trackingSessionId = $this->resolveTrackingSessionId($sessionId);

        if ($trackingSessionId === null) {
            EcomTrackerLogger::frontend()->warning('ctr_category.ingest.no_tracking_session', 'CTR category events skipped (no tracking_session row)', [
                'session_id' => $sessionId,
                'event_count' => count($events),
            ]);

            return [];
        }

        $categoryRow = $this->resolveCategoryRow($categoryData);
        $acceptedIds = [];

        foreach ($events as $event) {
            $eventId = (string) ($event['id'] ?? '');

            if ($eventId === '') {
                continue;
            }

            $productCode = trim((string) ($event['product_code'] ?? ''));
            $type = $this->eventTypeToStorageType((string) ($event['event_type'] ?? ''));

            if ($productCode === '' || $type === null) {
                continue;
            }

            try {
                $inserted = $this->storeImpressionRow(
                    $trackingSessionId,
                    $categoryRow,
                    $type,
                    $productCode,
                );

                if ($inserted) {
                    $acceptedIds[] = $eventId;
                }
            } catch (Throwable $exception) {
                EcomTrackerLogger::frontend()->error('ctr_category.ingest.event_failed', 'Could not store CTR category event', [
                    'session_id' => $sessionId,
                    'event_id' => $eventId,
                    'message' => $exception->getMessage(),
                ]);
            }
        }

        return $acceptedIds;
    }

    private function ensureActivitySessionExists(string $sessionId, string $visitorId, Request $request): void
    {
        $exists = ActivityEcomUser::query()->where('session_id', $sessionId)->exists();

        if ($exists) {
            return;
        }

        ActivityEcomUser::query()->create([
            'session_id' => $sessionId,
            'visitor_id' => $visitorId,
            'device_type' => 'unknown',
            'browser' => 'unknown',
            'os' => 'unknown',
            'session_duration_seconds' => 0,
        ]);
    }

    private function resolveTrackingSessionId(string $sessionUuid): ?int
    {
        $existing = TrackerDatabase::table('tracking_session')
            ->where('session_id', $sessionUuid)
            ->value('id');

        if ($existing) {
            return (int) $existing;
        }

        $this->dashboardSyncService->syncPendingBySessionId($sessionUuid);

        $existing = TrackerDatabase::table('tracking_session')
            ->where('session_id', $sessionUuid)
            ->value('id');

        return $existing ? (int) $existing : null;
    }

    /**
     * @param  array<string, mixed>  $categoryData
     */
    private function resolveCategoryRow(array $categoryData): TrackingCtrCategory
    {
        $categoryId = (int) $categoryData['category_id'];
        $categorySlug = trim((string) ($categoryData['category_slug'] ?? ''));
        $type = trim((string) ($categoryData['type'] ?? TrackingCtrCategory::TYPE_CATEGORY));
        $departmentId = $categoryData['department_id'];
        $departmentId = $departmentId === null || $departmentId === ''
            ? null
            : (int) $departmentId;
        $departmentSlug = trim((string) ($categoryData['department_slug'] ?? ''));
        $departmentSlug = $departmentSlug !== '' ? $departmentSlug : null;
        $microCategoryId = $categoryData['micro_category_id'] ?? null;
        $microCategoryId = $microCategoryId === null || $microCategoryId === ''
            ? null
            : (int) $microCategoryId;
        $microCategorySlug = trim((string) ($categoryData['micro_category_slug'] ?? ''));
        $microCategorySlug = $microCategorySlug !== '' ? $microCategorySlug : null;

        return TrackingCtrCategory::query()->firstOrCreate(
            [
                'category_id' => $categoryId,
                'category_slug' => $categorySlug,
                'micro_category_id' => $microCategoryId,
                'type' => $type,
                'department_id' => $departmentId,
            ],
            [
                'department_slug' => $departmentSlug,
                'micro_category_slug' => $microCategorySlug,
            ],
        );
    }

    private function eventTypeToStorageType(string $eventType): ?int
    {
        return match ($eventType) {
            'impression' => TrackingCtrCategoryImpression::TYPE_IMPRESSION,
            'grid-product-click' => TrackingCtrCategoryImpression::TYPE_CLICK,
            default => null,
        };
    }

    private function storeImpressionRow(
        int $trackingSessionId,
        TrackingCtrCategory $categoryRow,
        int $type,
        string $productCode,
    ): bool {
        TrackingCtrCategoryImpression::query()->create([
            'tracking_session_id' => $trackingSessionId,
            'tracking_ctr_category_id' => (int) $categoryRow->id,
            'micro_category_id' => $categoryRow->micro_category_id,
            'micro_category_slug' => $categoryRow->micro_category_slug,
            'type' => $type,
            'product_code' => $productCode,
        ]);

        return true;
    }
}
