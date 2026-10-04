<?php

namespace App\Support;

use Carbon\Carbon;

final class EcomTrackerPeriodRange
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array{from: Carbon, to: Carbon, label: string, days: int, period: string}
     */
    public static function resolve(array $filters): array
    {
        $period = $filters['period'] ?? '24h';
        if ($period === '' || $period === null) {
            $period = '24h';
        }

        if ($period === 'custom' && ! empty($filters['date_from']) && ! empty($filters['date_to'])) {
            $fromLocal = Carbon::parse($filters['date_from'], TrackerTime::timezone())->startOfDay();
            $toLocal = Carbon::parse($filters['date_to'], TrackerTime::timezone())->endOfDay();

            $from = $fromLocal->copy()->utc();
            $to = $toLocal->copy()->utc();

            return [
                'from' => $from,
                'to' => $to,
                'label' => TrackerTime::formatLocalDateRangeLabel($fromLocal, $toLocal),
                'days' => (int) ($fromLocal?->diffInDays($toLocal) ?? 0) + 1,
                'period' => 'custom',
            ];
        }

        if ($period === '24h') {
            $today = TrackerTime::todayRangeUtc();

            return [
                'from' => $today['from'],
                'to' => $today['to'],
                'label' => TrackerTime::todayPresetLabel(),
                'days' => 1,
                'period' => '24h',
            ];
        }

        if ($period === 'yesterday') {
            $yesterday = TrackerTime::yesterdayRangeUtc();

            return [
                'from' => $yesterday['from'],
                'to' => $yesterday['to'],
                'label' => TrackerTime::yesterdayPresetLabel(),
                'days' => 1,
                'period' => 'yesterday',
            ];
        }

        $days = match ($period) {
            '7d' => 7,
            '90d' => 30,
            default => 30,
        };

        $toLocal = TrackerTime::localNow()->endOfDay();
        $fromLocal = TrackerTime::localNow()->subDays($days - 1)->startOfDay();

        return [
            'from' => $fromLocal->copy()->utc(),
            'to' => $toLocal->copy()->utc(),
            'label' => "Last {$days} days",
            'days' => $days,
            'period' => $period === '7d' ? '7d' : '30d',
        ];
    }
}
