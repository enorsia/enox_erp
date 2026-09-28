<?php

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\EcomDailyMetricsQuery;
use App\Services\EcomStoreDashboardSessionPass;
use App\Support\CommerceFunnelQuery;
use App\Support\EcomAnalyticsRangeSplitter;
use App\Support\EcomDailyRollupSchema;
use App\Support\TrackerTime;
use App\Services\VisitorAnalyticsService;
use Illuminate\Support\Facades\DB;

$svc = app(\App\Services\EcomTrackerDashboardService::class);
$ref = new ReflectionClass($svc);
$m = $ref->getMethod('resolveDateRange');
$m->setAccessible(true);
$range = $m->invoke($svc, ['period' => '30d']);
$from = $range['from'];
$to = $range['to'];
$period = '30d';
$pass = app(EcomStoreDashboardSessionPass::class);
$split = EcomAnalyticsRangeSplitter::split($from, $to, $period);

$mark = fn ($l, $t) => printf("%6.0fms %s\n", (microtime(true) - $t) * 1000, $l);

$t = microtime(true);
$siteRows = DB::table('activity_ecom_daily_site_metrics')->whereIn('metric_date', $split['closed_dates'])->get();
$mark('site metrics', $t);

$t = microtime(true);
$orders = DB::table('activity_ecom_orders')->whereBetween('ordered_at', TrackerTime::storageRange($from, $to))->get();
$mark('orders ('.$orders->count().')', $t);

$t = microtime(true);
$sessions = $pass->loadRows($from, $to, $period);
$mark('loadRows ('.$sessions->count().')', $t);

$t = microtime(true);
$lines = $pass->loadCommerceLineItems($from, $to, $period);
$mark('line items ('.$lines->count().')', $t);

$t = microtime(true);
$derived = $pass->derive($sessions, $orders, ['live_from' => $split['live_from'], 'live_to' => $split['live_to']], app(VisitorAnalyticsService::class));
$mark('derive', $t);

$t = microtime(true);
CommerceFunnelQuery::paymentRows($from, $to, null, $period);
$mark('paymentRows', $t);
