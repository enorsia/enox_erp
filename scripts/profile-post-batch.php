<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EcomStoreDashboardBatchRead;
use App\Services\EcomDailyMetricsQuery;
use App\Services\EcomTrackerDashboardService;

$svc = app(EcomTrackerDashboardService::class);
$ref = new ReflectionClass($svc);
$resolve = $ref->getMethod('resolveDateRange');
$resolve->setAccessible(true);
$range = $resolve->invoke($svc, ['period' => '30d']);
$from = $range['from'];
$to = $range['to'];
$period = '30d';

$snap = app(EcomStoreDashboardBatchRead::class)->tryLoad($from, $to, $period, ['period' => '30d'], app(EcomDailyMetricsQuery::class));

$recover = $ref->getMethod('buildRecoverableAbandonmentFromLoaded');
$recover->setAccessible(true);

$t = microtime(true);
for ($i = 0; $i < 4; $i++) {
    $recover->invoke($svc, $snap->sessions, $snap->commerceLineItems, 'add_to_cart', 'begin_checkout', 20);
}
printf("%6.0fms 4x recoverable from loaded\n", (microtime(true) - $t) * 1000);

$t = microtime(true);
$svc->getDashboardData(['period' => '30d']);
printf("%6.0fms getDashboardData full\n", (microtime(true) - $t) * 1000);
