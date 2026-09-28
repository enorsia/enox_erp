<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EcomDailyMetricsQuery;
use App\Services\EcomStoreDashboardBatchRead;
use App\Services\EcomTrackerDashboardService;
use Illuminate\Support\Facades\DB;

$svc = app(EcomTrackerDashboardService::class);
$ref = new ReflectionClass($svc);
$resolve = $ref->getMethod('resolveDateRange');
$resolve->setAccessible(true);
$range = $resolve->invoke($svc, ['period' => '30d']);
$from = $range['from'];
$to = $range['to'];
$period = $range['period'] ?? null;

$mark = function (string $label, float $since) {
    echo sprintf("%6.0fms  %s\n", (microtime(true) - $since) * 1000, $label);
};

$t0 = microtime(true);
$batch = app(EcomStoreDashboardBatchRead::class);
$snap = $batch->tryLoad($from, $to, $period, ['period' => '30d'], app(EcomDailyMetricsQuery::class));
$mark('batch tryLoad', $t0);

if ($snap) {
    echo 'sessions loaded: '.$snap->sessions->count()."\n";
    echo 'line items: '.$snap->commerceLineItems->count()."\n";
    echo 'orders: '.$snap->orders->count()."\n";
}

$t1 = microtime(true);
DB::enableQueryLog();
app(EcomTrackerDashboardService::class)->getDashboardData(['period' => '30d']);
$log = DB::getQueryLog();
$mark('full getDashboardData ('.count($log).' queries)', $t1);
