<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Recoverable-sale panel rows for the store dashboard (batch + service).
 */
final class EcomRecoverablePanelFormatter
{
    public const TABLE_DISPLAY_LIMIT = 20;

    /**
     * @param  list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>  $abandonedRows
     * @return array{session_count: int, at_stake: float, rows: array<int, array<string, mixed>>}
     */
    public static function panelFromAbandonedRows(array $abandonedRows, ?int $limit = self::TABLE_DISPLAY_LIMIT): array
    {
        $rows = collect($abandonedRows)
            ->map(fn (array $row) => self::formatRow(
                $row['session_id'],
                (float) $row['value'],
                $row['occurred_at'],
                (int) $row['qty'],
            ))
            ->sortByDesc(fn (array $row) => $row['_sort_at']?->timestamp ?? 0)
            ->values();

        return self::finalize($rows, $limit);
    }

    /**
     * @param  list<array{session_id: string, qty: int, value: float, occurred_at: mixed}>  $paymentRows
     * @return array{session_count: int, at_stake: float, rows: array<int, array<string, mixed>>}
     */
    public static function panelFromPaymentRows(array $paymentRows, ?int $limit = self::TABLE_DISPLAY_LIMIT): array
    {
        $rows = collect($paymentRows)
            ->map(fn (array $row) => self::formatRow(
                $row['session_id'],
                (float) $row['value'],
                $row['occurred_at'],
                (int) $row['qty'],
            ))
            ->sortByDesc(fn (array $row) => $row['_sort_at']?->timestamp ?? 0)
            ->values();

        return self::finalize($rows, $limit);
    }

    /**
     * @return array{session_id: string, session_label: string, value: float, occurred_ago: string, activity_url: string, _sort_at: ?\Carbon\Carbon}
     */
    public static function formatRow(string $sessionId, float $value, mixed $occurredAt, int $qty = 1): array
    {
        return [
            'session_id' => $sessionId,
            'session_label' => substr($sessionId, 0, 8).'…',
            'qty' => max(0, $qty),
            'value' => round($value, 2),
            'occurred_ago' => TrackerTime::diffForHumansFromStorage($occurredAt) ?? '—',
            'activity_url' => EcomTrackerViewData::activityShowUrl($sessionId),
            '_sort_at' => TrackerTime::fromStorage($occurredAt),
        ];
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{session_count: int, at_stake: float, rows: array<int, array<string, mixed>>}
     */
    public static function finalize(Collection $rows, ?int $limit): array
    {
        $totalAtStake = round($rows->sum('value'), 2);
        $limited = $limit !== null ? $rows->take($limit) : $rows;
        $displayRows = $limited
            ->map(function (array $row) {
                unset($row['_sort_at']);

                return $row;
            })
            ->all();

        return [
            'session_count' => $rows->count(),
            'at_stake' => $totalAtStake,
            'rows' => $displayRows,
        ];
    }
}
