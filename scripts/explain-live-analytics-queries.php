<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use App\Support\TrackerTime;
use Carbon\Carbon;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$from = TrackerTime::localNow()->startOfDay()->utc();
$to = Carbon::now('UTC');
$bounds = [$from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s')];

$plans = [
    'sessions_today' => DB::table('activity_ecom_user')->whereBetween('created_at', $bounds)->selectRaw('COUNT(*) as c'),
    'lines_today' => DB::table('activity_ecom_commerce_line_items')->whereBetween('staged_at', $bounds)->selectRaw('COUNT(*) as c'),
    'orders_today' => DB::table('activity_ecom_orders')->whereBetween('ordered_at', $bounds)->selectRaw('COUNT(*) as c'),
    'bot_join' => DB::table('activity_ecom_user as s')
        ->leftJoin('activity_ecom_user_bot_context as bc', 'bc.session_id', '=', 's.session_id')
        ->whereBetween('s.created_at', $bounds)
        ->selectRaw('COUNT(*) as c'),
];

foreach ($plans as $label => $query) {
    $sql = $query->toSql();
    $bindings = $query->getBindings();
    echo "=== {$label} ===\n";
    echo DB::connection()->select('EXPLAIN '.$sql, $bindings);
    echo "\n";
}
