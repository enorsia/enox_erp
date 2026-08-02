<?php

namespace App\Console\Commands;

use App\Support\TrackerCategoryIdentity;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BackfillTrackerCategoryDepartments extends Command
{
    protected $signature = 'tracker:backfill-category-departments
                            {--dry-run : Show updates without writing}
                            {--category= : Only backfill rows whose category_name contains this text}';

    protected $description = 'Backfill missing department_name on tracker category_view rows from page_url (/c/men/..., /c/women/...). Never guesses from category name alone.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $categoryFilter = trim((string) $this->option('category'));

        $query = DB::table('activity_ecom_user_actions')
            ->where('action_type', 'category_view')
            ->where(function ($builder) {
                $builder->whereNull('department_name')
                    ->orWhere('department_name', '');
            })
            ->whereNotNull('page_url')
            ->where('page_url', '!=', '')
            ->orderBy('id');

        if ($categoryFilter !== '') {
            $query->where('category_name', 'like', '%'.$categoryFilter.'%');
        }

        $rows = $query->get(['id', 'event_id', 'category_name', 'department_name', 'page_url']);
        $updated = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $departmentName = TrackerCategoryIdentity::departmentNameFromPageUrl((string) $row->page_url);

            if ($departmentName === '') {
                $skipped++;

                continue;
            }

            $this->line(sprintf(
                '%s %s → %s (%s)',
                $dryRun ? '[dry-run]' : '[update]',
                $row->category_name,
                $departmentName,
                $row->page_url,
            ));

            if (! $dryRun) {
                DB::table('activity_ecom_user_actions')
                    ->where('id', $row->id)
                    ->update(['department_name' => $departmentName]);
            }

            $updated++;
        }

        $this->info(sprintf(
            'Done. %s %d row(s), skipped %d (no department slug in page_url).',
            $dryRun ? 'Would update' : 'Updated',
            $updated,
            $skipped,
        ));

        return self::SUCCESS;
    }
}
