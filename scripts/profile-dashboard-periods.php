<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Facades\DB;

$svc = app(EcomTrackerDashboardService::class);

$cases = [
    ['period' => 'yesterday'],
    ['period' => '7d'],
    ['period' => '30d'],
    [
        'period' => 'custom',
        'date_from' => '2026-07-01',
        'date_to' => '2026-09-29',
    ],
];

foreach ($cases as $filters) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    $t0 = microtime(true);
    $svc->getDashboardData($filters);
    $ms = (microtime(true) - $t0) * 1000;
    $count = count(DB::getQueryLog());
    $label = $filters['period'].(isset($filters['date_from']) ? " {$filters['date_from']}..{$filters['date_to']}" : '');
    printf("%6.0fms  %3d queries  %s\n", $ms, $count, $label);
}
