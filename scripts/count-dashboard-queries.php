<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Facades\DB;

$presets = [
    '30d' => ['period' => '30d'],
    '24h' => ['period' => '24h'],
];

foreach ($presets as $name => $filters) {
    DB::flushQueryLog();
    DB::enableQueryLog();
    app(EcomTrackerDashboardService::class)->getDashboardData($filters);
    $log = DB::getQueryLog();
    echo "=== $name: ".count($log)." queries ===\n";
    $sig = [];
    foreach ($log as $q) {
        $sql = preg_replace('/\s+/', ' ', substr($q['query'], 0, 140));
        $sig[$sql] = ($sig[$sql] ?? 0) + 1;
    }
    foreach ($log as $i => $q) {
        $sql = preg_replace('/\s+/', ' ', substr($q['query'], 0, 100));
        echo sprintf("  %2d %s\n", $i + 1, $sql);
    }
}
