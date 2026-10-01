<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$queries = [
    'category_code_vs_department' => <<<'SQL'
SELECT category_code, COUNT(DISTINCT department_name) AS department_count
FROM activity_ecom_commerce_line_items
GROUP BY category_code
HAVING department_count > 1
SQL,
    'category_name_vs_department' => <<<'SQL'
SELECT category_name, COUNT(DISTINCT department_name) AS department_count
FROM activity_ecom_commerce_line_items
WHERE category_name <> ''
GROUP BY category_name
HAVING department_count > 1
SQL,
    'empty_identity_fields' => <<<'SQL'
SELECT
  SUM(category_code IS NULL OR category_code = '') AS empty_code,
  SUM(department_name IS NULL OR department_name = '') AS empty_dept,
  SUM(category_name IS NULL OR category_name = '') AS empty_cat,
  SUM(product_code IS NULL OR product_code = '') AS empty_pcode
FROM activity_ecom_commerce_line_items
SQL,
    'product_id_vs_product_code' => <<<'SQL'
SELECT COUNT(*) AS conflicting_product_ids FROM (
  SELECT product_id FROM activity_ecom_commerce_line_items
  WHERE product_id IS NOT NULL
  GROUP BY product_id HAVING COUNT(DISTINCT product_code) > 1
) t
SQL,
];

foreach ($queries as $label => $sql) {
    echo "=== {$label} ===\n";
    $rows = DB::select($sql);
    echo json_encode($rows, JSON_PRETTY_PRINT)."\n\n";
}
