<?php

namespace App\Debugbar;

use Fruitcake\LaravelDebugbar\DataCollector\QueryCollector;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Str;

/**
 * Omits infrastructure SQL (Spatie permission cache, etc.) from Debugbar counts.
 * Does not change what runs on the server — only what is shown and counted.
 */
class FilteredQueryCollector extends QueryCollector
{
    /** @var list<string> */
    private array $excludeSqlPatterns;

    public function __construct()
    {
        $patterns = config('debugbar.options.db.exclude_sql_patterns', []);
        $this->excludeSqlPatterns = is_array($patterns) ? $patterns : [];
    }

    public function addQuery(QueryExecuted $query): void
    {
        if ($this->shouldExcludeSql($query->sql)) {
            return;
        }

        parent::addQuery($query);
    }

    private function shouldExcludeSql(string $sql): bool
    {
        if ($this->excludeSqlPatterns === []) {
            return false;
        }

        $normalized = Str::lower(preg_replace('/\s+/', ' ', trim($sql)) ?? $sql);

        foreach ($this->excludeSqlPatterns as $pattern) {
            if (@preg_match($pattern, $normalized) === 1) {
                return true;
            }
        }

        return false;
    }
}
