<?php

namespace App\Exports;

use App\Services\VisitorAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;

class VisitorAnalyticsExport implements WithMultipleSheets
{
    public function __construct(
        private VisitorAnalyticsService $analytics,
        private Carbon $from,
        private ?Carbon $until = null,
    ) {}

    public function sheets(): array
    {
        return [
            new VisitorAnalyticsWindowsSheet($this->analytics),
            new VisitorAnalyticsVisitorsSheet($this->analytics, $this->from, $this->until),
        ];
    }
}

class VisitorAnalyticsWindowsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private VisitorAnalyticsService $analytics,
    ) {}

    public function title(): string
    {
        return 'Rolling windows';
    }

    public function headings(): array
    {
        return ['Window', 'Active visitors', 'New visitors', 'Sessions', 'Avg stay', 'Total stay'];
    }

    public function collection(): Collection
    {
        return collect($this->analytics->buildRollingWindows())->map(fn (array $row) => [
            $row['label'],
            $row['active_visitors'],
            $row['new_visitors'],
            $row['sessions'],
            $row['avg_stay_label'],
            $row['total_stay_label'],
        ]);
    }
}

class VisitorAnalyticsVisitorsSheet implements FromCollection, WithHeadings, WithTitle
{
    public function __construct(
        private VisitorAnalyticsService $analytics,
        private Carbon $from,
        private ?Carbon $until = null,
    ) {}

    public function title(): string
    {
        return 'Visitors';
    }

    public function headings(): array
    {
        return ['Visitor ID', 'Sessions', 'Total stay', 'Avg stay/session', 'First seen', 'Last active', 'Device', 'Browser'];
    }

    public function collection(): Collection
    {
        $paginator = $this->analytics->buildVisitorBreakdown($this->from, 10000, $this->until);

        return collect($paginator->items())->map(fn (array $row) => [
            $row['visitor_id'],
            $row['session_count'],
            $row['total_stay_label'],
            $row['avg_stay_label'],
            $row['first_seen_at'],
            $row['last_active_at'],
            $row['device_type'] ?? '',
            $row['browser'] ?? '',
        ]);
    }
}
