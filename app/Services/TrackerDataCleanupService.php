<?php

namespace App\Services;

use App\Models\ActivityEcomUser;
use App\Models\ActivityEcomUserAction;
use App\Support\TrackerTime;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class TrackerDataCleanupService
{
    public function __construct(
        private TrackIngestService $trackIngestService,
    ) {}

    /**
     * @return array{scanned: int, duplicate_groups: int, deleted_actions: int, kept_actions: int}
     */
    public function dedupePaymentSuccessActions(?Carbon $before = null, bool $dryRun = false): array
    {
        $query = ActivityEcomUserAction::query()
            ->where('action_type', 'payment_success')
            ->orderBy('id');

        if ($before !== null) {
            $query->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        /** @var Collection<int, Collection<int, ActivityEcomUserAction>> $groups */
        $groups = $query
            ->get(['id', 'event_id', 'session_id', 'created_at', 'payment_success'])
            ->groupBy(fn (ActivityEcomUserAction $action) => $this->paymentSuccessOrderId($action));

        $deleted = 0;
        $kept = 0;
        $duplicateGroups = 0;

        foreach ($groups as $orderId => $actions) {
            if ($orderId === '' || $actions->count() <= 1) {
                $kept += $actions->count();

                continue;
            }

            $duplicateGroups++;
            $keeper = $actions->sortBy([
                ['created_at', 'asc'],
                ['id', 'asc'],
            ])->first();

            foreach ($actions as $action) {
                if ($action->id === $keeper->id) {
                    $kept++;

                    continue;
                }

                if (! $dryRun) {
                    $action->delete();

                    if (ActivityEcomUserAction::query()->where('session_id', $action->session_id)->doesntExist()) {
                        ActivityEcomUser::query()->where('session_id', $action->session_id)->delete();
                    }
                }

                $deleted++;
            }
        }

        return [
            'scanned' => $groups->flatten(1)->count(),
            'duplicate_groups' => $duplicateGroups,
            'deleted_actions' => $deleted,
            'kept_actions' => $kept,
        ];
    }

    /**
     * @return array{scanned: int, deleted_sessions: int}
     */
    public function removePaymentOnlySessions(?Carbon $before = null, bool $dryRun = false): array
    {
        $query = ActivityEcomUser::query()
            ->withCount('actions')
            ->with(['actions' => fn ($builder) => $builder->select('id', 'session_id', 'action_type')])
            ->orderBy('id');

        if ($before !== null) {
            $query->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        $deleted = 0;
        $scanned = 0;

        $query->chunkById(200, function (Collection $sessions) use (&$deleted, &$scanned, $dryRun) {
            foreach ($sessions as $session) {
                $scanned++;

                if (! $this->isPaymentOnlySession($session)) {
                    continue;
                }

                if (! $dryRun) {
                    $session->delete();
                }

                $deleted++;
            }
        });

        return [
            'scanned' => $scanned,
            'deleted_sessions' => $deleted,
        ];
    }

    /**
     * @return array{scanned: int, deleted_sessions: int}
     */
    public function removeEmptySessions(?Carbon $before = null, bool $dryRun = false): array
    {
        $query = ActivityEcomUser::query()
            ->withCount('actions')
            ->orderBy('id');

        if ($before !== null) {
            $query->where('created_at', '<=', TrackerTime::formatUtc($before));
        }

        $deleted = 0;
        $scanned = 0;

        $query->chunkById(200, function (Collection $sessions) use (&$deleted, &$scanned, $dryRun) {
            foreach ($sessions as $session) {
                $scanned++;

                if (($session->actions_count ?? 0) > 0) {
                    continue;
                }

                if (! $dryRun) {
                    $session->delete();
                }

                $deleted++;
            }
        });

        return [
            'scanned' => $scanned,
            'deleted_sessions' => $deleted,
        ];
    }

    public function backfillSessionCustomerFields(int $chunkSize = 100): int
    {
        return $this->trackIngestService->backfillSessionCustomerFromCheckoutActions($chunkSize);
    }

    private function isPaymentOnlySession(ActivityEcomUser $session): bool
    {
        if (($session->actions_count ?? 0) !== 1) {
            return false;
        }

        $action = $session->relationLoaded('actions')
            ? $session->actions->first()
            : $session->actions()->first(['id', 'session_id', 'action_type']);

        return $action !== null && $action->action_type === 'payment_success';
    }

    private function paymentSuccessOrderId(ActivityEcomUserAction $action): string
    {
        $payload = is_array($action->payment_success) ? $action->payment_success : [];

        $orderId = trim((string) ($payload['order_id'] ?? ''));

        if ($orderId !== '') {
            return $orderId;
        }

        $checkoutInfo = $payload['checkout_info'] ?? [];

        if (! is_array($checkoutInfo)) {
            return '';
        }

        return trim((string) ($checkoutInfo['order_number'] ?? $checkoutInfo['order_pk'] ?? ''));
    }
}
