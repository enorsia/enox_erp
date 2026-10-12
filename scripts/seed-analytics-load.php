<?php

declare(strict_types=1);

/**
 * Seeds ~10k tracker_products rows and sample commerce lines for load testing.
 * Run: php scripts/seed-analytics-load.php
 */

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

if (! Schema::hasTable('tracker_products')) {
    fwrite(STDERR, "Run migrations first (tracker catalog).\n");
    exit(1);
}

$targetProducts = 10000;
$existing = (int) DB::table('tracker_products')->where('product_code', 'not like', '__%')->count();
$toCreate = max(0, $targetProducts - $existing);

echo "Creating {$toCreate} products (existing {$existing})...\n";

$now = now();
$batch = [];
for ($i = 0; $i < $toCreate; $i++) {
    $code = 'LOAD-'.str_pad((string) ($existing + $i + 1), 6, '0', STR_PAD_LEFT);
    $batch[] = [
        'product_code' => $code,
        'store_product_id' => 100000 + $i,
        'product_name' => 'Load test product '.$code,
        'tracker_department_id' => 1,
        'tracker_category_id' => 2,
        'created_at' => $now,
        'updated_at' => $now,
    ];

    if (count($batch) >= 500) {
        DB::table('tracker_products')->insertOrIgnore($batch);
        $batch = [];
    }
}

if ($batch !== []) {
    DB::table('tracker_products')->insertOrIgnore($batch);
}

echo "Done. Use scripts/profile-dashboard-periods.php to measure.\n";
