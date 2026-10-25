<?php

namespace App\Services\EcomTrackerDashboard\Sections;

class UniqueReturningSectionService
{
    /**
     * @return array{
     *     newReturning: array{unique: int, returning: int},
     *     chart: array{labels: list<string>, values: list<int>}
     * }
     */
    public function snapshot(int $uniqueVisitors, int $sessions): array
    {
        $returning = max(0, $sessions - $uniqueVisitors);

        return [
            'newReturning' => ['unique' => $uniqueVisitors, 'returning' => $returning],
            'chart' => [
                'labels' => ['Unique', 'Returning'],
                'values' => [$uniqueVisitors, $returning],
            ],
        ];
    }
}