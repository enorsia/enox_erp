# Daily Sales Database System Design

## DAILY SALES BASIC UNDERSTANDING FROM EXCEL

## Enorsia

### Google

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### Meta

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### Klaviyo

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### Influencer

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### SEO

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### Awin

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### Others

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

---
## Debenhams

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities


---

## Amazon

### UK

* date
* spent
* sales
* number_of_orders
* number_of_quantities
* number_of_male_orders
* number_of_female_orders
* number_of_kids_orders
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

### EU

#### Germany

* date
* spent
* sales
* (other fields like above)

#### France

* date
* spent
* sales
* (other fields like above)

#### Italy

* date
* spent
* sales
* (other fields like above)

#### Spain

* date
* spent
* sales
* (other fields like above)

#### Netherlands

* date
* spent
* sales
* (other fields like above)

#### Poland

* date
* spent
* sales
* (other fields like above)

#### Sweden

* date
* spent
* sales
* (other fields like above)

#### Belgium

* date
* spent
* sales
* (other fields like above)

#### Ireland

* date
* spent
* sales
* (other fields like above)


---

## Spartoo

* date
* spent
* sales
* (other fields like above)


---

## Temu

* date
* spent
* sales
* (other fields like above)


---

## Rackhams

* date
* spent
* sales
* (other fields like above)


---

## MORE NEED TO THINK

### Monthly Budget

* Each sale_platform should have monthly budget

---

### Returns System (Per Platform Per Day)

* number_of_returns
* number_of_return_quantities
* number_of_male_return
* number_of_female_return
* number_of_kids_return
* number_of_male_quantities
* number_of_female_quantities
* number_of_kids_quantities

---

---
# DATABASE DESIGN

---

## PLATFORMS TABLE

### SQL
```sql
CREATE TABLE sale_platforms (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(100) NOT NULL,
  slug        VARCHAR(100) NOT NULL,
  parent_id   BIGINT UNSIGNED NULL,
  type        ENUM('channel','sub_channel','marketplace','region') DEFAULT 'channel',
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  is_active   BOOLEAN DEFAULT TRUE,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_slug (slug),
  KEY idx_parent (parent_id),
  CONSTRAINT fk_sale_platform_parent FOREIGN KEY (parent_id) REFERENCES sale_platforms(id)
);
```

### Migration
```php
Schema::create('sale_platforms', function (Blueprint $table) {
    $table->id();
    $table->string('name', 100);
    $table->string('slug', 100)->unique();
    $table->foreignId('parent_id')->nullable()->constrained('sale_platforms')->nullOnDelete();
    $table->enum('type', ['channel', 'sub_channel', 'marketplace', 'region'])->default('channel');
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->index('parent_id');
});
```

### Sample Data
```sql
INSERT INTO sale_platforms (id, name, slug, parent_id, type, sort_order) VALUES
-- Top-level channels
(1,  'Enorsia',     'enorsia',            NULL, 'channel',     1),
(2,  'Debenhams',   'debenhams',          NULL, 'channel',     2),
(3,  'Amazon',      'amazon',             NULL, 'channel',     3),
(4,  'Spartoo',     'spartoo',            NULL, 'channel',     4),
(5,  'Temu',        'temu',               NULL, 'channel',     5),
(6,  'Rackhams',    'rackhams',           NULL, 'channel',     6),

-- Enorsia sub-channels
(10, 'Google',      'enorsia-google',     1,    'sub_channel', 1),
(11, 'Meta',        'enorsia-meta',       1,    'sub_channel', 2),
(12, 'Klaviyo',     'enorsia-klaviyo',    1,    'sub_channel', 3),
(13, 'Influencer',  'enorsia-influencer', 1,    'sub_channel', 4),
(14, 'SEO',         'enorsia-seo',        1,    'sub_channel', 5),
(15, 'Awin',        'enorsia-awin',       1,    'sub_channel', 6),
(16, 'Others',      'enorsia-others',     1,    'sub_channel', 7),

-- Amazon sub-channels
(20, 'Amazon UK',   'amazon-uk',          3,    'sub_channel', 1),
(21, 'Amazon EU',   'amazon-eu',          3,    'sub_channel', 2),

-- Amazon EU countries
(30, 'Germany',     'amazon-eu-de',       21,   'region',      1),
(31, 'France',      'amazon-eu-fr',       21,   'region',      2),
(32, 'Italy',       'amazon-eu-it',       21,   'region',      3),
(33, 'Spain',       'amazon-eu-es',       21,   'region',      4),
(34, 'Netherlands', 'amazon-eu-nl',       21,   'region',      5),
(35, 'Poland',      'amazon-eu-pl',       21,   'region',      6),
(36, 'Sweden',      'amazon-eu-se',       21,   'region',      7),
(37, 'Belgium',     'amazon-eu-be',       21,   'region',      8),
(38, 'Ireland',     'amazon-eu-ie',       21,   'region',      9);
```

---

## DAILY SALES TABLE

### SQL
```sql
CREATE TABLE daily_sales (
  id                          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_platform_id                 BIGINT UNSIGNED NOT NULL,
  date                        DATE NOT NULL,
  spent                       DECIMAL(12,2) NOT NULL DEFAULT 0,
  sales                       DECIMAL(12,2) NOT NULL DEFAULT 0,
  number_of_orders            INT UNSIGNED NOT NULL DEFAULT 0,
  number_of_quantities        INT UNSIGNED NOT NULL DEFAULT 0,
  number_of_male_orders       INT UNSIGNED DEFAULT 0,
  number_of_female_orders     INT UNSIGNED DEFAULT 0,
  number_of_kids_orders       INT UNSIGNED DEFAULT 0,
  number_of_male_quantities   INT UNSIGNED DEFAULT 0,
  number_of_female_quantities INT UNSIGNED DEFAULT 0,
  number_of_kids_quantities   INT UNSIGNED DEFAULT 0,
  created_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_sale_platform_date (sale_platform_id, date),
  KEY idx_date (date),
  CONSTRAINT fk_sale_sale_platform FOREIGN KEY (sale_platform_id) REFERENCES sale_platforms(id)
);
```

### Migration
```php
Schema::create('daily_sales', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_platform_id')->constrained('sale_platforms')->cascadeOnDelete();
    $table->date('date');
    $table->decimal('spent', 12, 2)->default(0);
    $table->decimal('sales', 12, 2)->default(0);
    $table->unsignedInteger('number_of_orders')->default(0);
    $table->unsignedInteger('number_of_quantities')->default(0);
    $table->unsignedInteger('number_of_male_orders')->default(0);
    $table->unsignedInteger('number_of_female_orders')->default(0);
    $table->unsignedInteger('number_of_kids_orders')->default(0);
    $table->unsignedInteger('number_of_male_quantities')->default(0);
    $table->unsignedInteger('number_of_female_quantities')->default(0);
    $table->unsignedInteger('number_of_kids_quantities')->default(0);
    $table->timestamps();

    $table->unique(['sale_platform_id', 'date']);
    $table->index('date');
});
```

### Sample Data
```sql
INSERT INTO daily_sales
  (sale_platform_id, date, spent, sales, number_of_orders, number_of_quantities,
   number_of_male_orders, number_of_female_orders, number_of_kids_orders,
   number_of_male_quantities, number_of_female_quantities, number_of_kids_quantities)
VALUES
-- Enorsia sub-channels (leaf nodes — raw data goes here)
(10, '2025-01-15', 1200.00, 4800.00, 95, 120, 40, 45, 10, 52, 56, 12),  -- Google
(11, '2025-01-15',  850.00, 3200.00, 70,  88, 28, 32, 10, 36, 40, 12),  -- Meta
(12, '2025-01-15',    0.00, 1500.00, 40,  50, 15, 20,  5, 19, 25,  6),  -- Klaviyo
(13, '2025-01-15',  300.00,  900.00, 20,  25,  8, 10,  2, 10, 13,  2),  -- Influencer
(14, '2025-01-15',    0.00,  600.00, 15,  18,  6,  7,  2,  7,  9,  2),  -- SEO
(15, '2025-01-15',    0.00,  400.00, 10,  12,  4,  5,  1,  5,  6,  1),  -- Awin
(16, '2025-01-15',    0.00,  200.00,  5,   6,  2,  2,  1,  2,  3,  1),  -- Others

-- Debenhams (leaf node)
(2,  '2025-01-15',    0.00, 2100.00, 55,  68, 20, 25, 10, 26, 30, 12),

-- Amazon UK (leaf node)
(20, '2025-01-15',  500.00, 3800.00, 90, 110, 35, 42, 13, 44, 52, 14),

-- Amazon EU countries (leaf nodes)
(30, '2025-01-15',  120.00,  980.00, 22, 28,  9, 10,  3, 11, 14,  3),  -- Germany
(31, '2025-01-15',   80.00,  620.00, 14, 18,  5,  7,  2,  6,  9,  3),  -- France
(32, '2025-01-15',   60.00,  480.00, 11, 14,  4,  5,  2,  5,  7,  2),  -- Italy
(33, '2025-01-15',   55.00,  410.00, 10, 13,  4,  4,  2,  5,  6,  2),  -- Spain
(34, '2025-01-15',   40.00,  290.00,  7,  9,  3,  3,  1,  4,  4,  1),  -- Netherlands
(35, '2025-01-15',   30.00,  210.00,  5,  6,  2,  2,  1,  2,  3,  1),  -- Poland
(36, '2025-01-15',   25.00,  180.00,  4,  5,  2,  1,  1,  2,  2,  1),  -- Sweden
(37, '2025-01-15',   20.00,  150.00,  4,  5,  1,  2,  1,  2,  2,  1),  -- Belgium
(38, '2025-01-15',   15.00,  110.00,  3,  4,  1,  1,  1,  1,  2,  1),  -- Ireland

-- Other top-level channels
(4,  '2025-01-15',    0.00,  750.00, 18, 22,  7,  9,  2,  9, 11,  2),  -- Spartoo
(5,  '2025-01-15',    0.00,  320.00,  8, 10,  3,  4,  1,  4,  5,  1),  -- Temu
(6,  '2025-01-15',    0.00,  540.00, 13, 16,  5,  6,  2,  6,  8,  2);  -- Rackhams
```

---

## RETURN REASON TYPES TABLE

### SQL
```sql
CREATE TABLE return_reason_types (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(150) NOT NULL,
  slug        VARCHAR(150) NOT NULL,
  description TEXT NULL,
  is_active   BOOLEAN DEFAULT TRUE,
  sort_order  TINYINT UNSIGNED DEFAULT 0,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_reason_slug (slug)
);
```

### Migration
```php
Schema::create('return_reason_types', function (Blueprint $table) {
    $table->id();
    $table->string('name', 150);
    $table->string('slug', 150)->unique();
    $table->text('description')->nullable();
    $table->boolean('is_active')->default(true);
    $table->unsignedTinyInteger('sort_order')->default(0);
    $table->timestamps();
});
```

### Sample Data
```sql
-- Seed once; rarely changes
INSERT INTO return_reason_types (id, name, slug, description, sort_order) VALUES
(1, 'Wrong size',        'wrong-size',        'Customer ordered wrong size',          1),
(2, 'Defective product', 'defective-product', 'Item arrived damaged or not working',  2),
(3, 'Not as described',  'not-as-described',  'Product differed from listing/photos', 3),
(4, 'Changed mind',      'changed-mind',      'Customer no longer wants the item',    4),
(5, 'Late delivery',     'late-delivery',     'Item arrived too late',                5),
(6, 'Wrong item sent',   'wrong-item-sent',   'Warehouse picked incorrect product',   6),
(7, 'Other',             'other',             'Any reason not covered above',         7);
```

---

## DAILY RETURNS TABLE

> **Note:** The unique key is `(sale_platform_id, date, return_reason_type_id)` — this allows
> multiple rows per sale_platform per day, one row per reason. This means you can record
> e.g. 3 returns for "wrong size" and 2 for "defective" on the same day for the same sale_platform.

### SQL
```sql
CREATE TABLE daily_returns (
  id                               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_platform_id                      BIGINT UNSIGNED NOT NULL,
  return_reason_type_id            BIGINT UNSIGNED NOT NULL,
  date                             DATE NOT NULL,
  number_of_returns                INT UNSIGNED DEFAULT 0,
  number_of_return_quantities      INT UNSIGNED DEFAULT 0,
  number_of_male_returns           INT UNSIGNED DEFAULT 0,
  number_of_female_returns         INT UNSIGNED DEFAULT 0,
  number_of_kids_returns           INT UNSIGNED DEFAULT 0,
  number_of_male_return_quantities INT UNSIGNED DEFAULT 0,
  number_of_female_return_quantities INT UNSIGNED DEFAULT 0,
  number_of_kids_return_quantities INT UNSIGNED DEFAULT 0,
  created_at                       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at                       TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_return_sale_platform_date_reason (sale_platform_id, date, return_reason_type_id),
  KEY idx_return_date (date),
  KEY idx_return_reason (return_reason_type_id),
  CONSTRAINT fk_return_sale_platform    FOREIGN KEY (sale_platform_id)           REFERENCES sale_platforms(id),
  CONSTRAINT fk_return_reason_type FOREIGN KEY (return_reason_type_id) REFERENCES return_reason_types(id)
);
```

### Migration
```php
Schema::create('daily_returns', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_platform_id')->constrained('sale_platforms')->cascadeOnDelete();
    $table->foreignId('return_reason_type_id')->constrained('return_reason_types')->cascadeOnDelete();
    $table->date('date');
    $table->unsignedInteger('number_of_returns')->default(0);
    $table->unsignedInteger('number_of_return_quantities')->default(0);
    $table->unsignedInteger('number_of_male_returns')->default(0);
    $table->unsignedInteger('number_of_female_returns')->default(0);
    $table->unsignedInteger('number_of_kids_returns')->default(0);
    $table->unsignedInteger('number_of_male_return_quantities')->default(0);
    $table->unsignedInteger('number_of_female_return_quantities')->default(0);
    $table->unsignedInteger('number_of_kids_return_quantities')->default(0);
    $table->timestamps();

    $table->unique(['sale_platform_id', 'date', 'return_reason_type_id']);
    $table->index('date');
    $table->index('return_reason_type_id');
});
```

### Sample Data
```sql
-- sale_platform_id 10 = Google, 11 = Meta, 20 = Amazon UK, 30 = Amazon DE
INSERT INTO daily_returns
  (sale_platform_id, return_reason_type_id, date,
   number_of_returns, number_of_return_quantities,
   number_of_male_returns, number_of_female_returns, number_of_kids_returns,
   number_of_male_return_quantities, number_of_female_return_quantities, number_of_kids_return_quantities)
VALUES
-- Google (sale_platform_id=10) — 5 total returns across 3 reasons
(10, 1, '2025-01-15', 2, 2, 1, 1, 0, 1, 1, 0),  -- Wrong size
(10, 2, '2025-01-15', 2, 3, 1, 1, 0, 1, 2, 0),  -- Defective product
(10, 4, '2025-01-15', 1, 1, 0, 0, 1, 0, 0, 1),  -- Changed mind

-- Meta (sale_platform_id=11) — 3 total returns across 2 reasons
(11, 1, '2025-01-15', 1, 1, 0, 1, 0, 0, 1, 0),  -- Wrong size
(11, 3, '2025-01-15', 2, 3, 1, 1, 0, 1, 2, 0),  -- Not as described

-- Amazon UK (sale_platform_id=20) — 8 total returns across 4 reasons
(20, 1, '2025-01-15', 3, 3, 1, 2, 0, 1, 2, 0),  -- Wrong size
(20, 2, '2025-01-15', 2, 2, 1, 1, 0, 1, 1, 0),  -- Defective product
(20, 6, '2025-01-15', 2, 2, 1, 0, 1, 1, 0, 1),  -- Wrong item sent
(20, 7, '2025-01-15', 1, 2, 0, 1, 0, 0, 2, 0),  -- Other

-- Amazon Germany (sale_platform_id=30) — 2 total returns across 2 reasons
(30, 1, '2025-01-15', 1, 1, 1, 0, 0, 1, 0, 0),  -- Wrong size
(30, 2, '2025-01-15', 1, 1, 0, 1, 0, 0, 1, 0);  -- Defective product
```

---

## MONTHLY BUDGETS TABLE

### SQL
```sql
CREATE TABLE monthly_budgets (
  id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_platform_id BIGINT UNSIGNED NOT NULL,
  year        SMALLINT UNSIGNED NOT NULL,
  month       TINYINT UNSIGNED NOT NULL,
  budget      DECIMAL(12,2) NOT NULL DEFAULT 0,
  currency    CHAR(3) NOT NULL DEFAULT 'GBP',
  notes       TEXT NULL,
  created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  UNIQUE KEY uq_budget_sale_platform_month (sale_platform_id, year, month),
  CONSTRAINT fk_budget_sale_platform FOREIGN KEY (sale_platform_id) REFERENCES sale_platforms(id)
);
```

### Migration
```php
Schema::create('monthly_budgets', function (Blueprint $table) {
    $table->id();
    $table->foreignId('sale_platform_id')->constrained('sale_platforms')->cascadeOnDelete();
    $table->unsignedSmallInteger('year');
    $table->unsignedTinyInteger('month');
    $table->decimal('budget', 12, 2)->default(0);
    $table->char('currency', 3)->default('GBP');
    $table->text('notes')->nullable();
    $table->timestamps();

    $table->unique(['sale_platform_id', 'year', 'month']);
});
```

### Sample Data
```sql
INSERT INTO monthly_budgets (sale_platform_id, year, month, budget, currency) VALUES
(1,  2025, 1, 15000.00, 'GBP'),  -- Enorsia total
(10, 2025, 1,  6000.00, 'GBP'),  -- Google
(11, 2025, 1,  4000.00, 'GBP'),  -- Meta
(3,  2025, 1,  2500.00, 'GBP'),  -- Amazon total
(20, 2025, 1,  1500.00, 'GBP'),  -- Amazon UK
(21, 2025, 1,  1000.00, 'GBP');  -- Amazon EU
```

---

## MIGRATION RUN ORDER

Run in this exact sequence to satisfy foreign key dependencies:

```
1. sale_platforms              (self-referencing FK — must be first)
2. daily_sales            (FK → sale_platforms)
3. return_reason_types    (no FKs — can run alongside daily_sales)
4. daily_returns          (FK → sale_platforms, return_reason_types)
5. monthly_budgets        (FK → sale_platforms)
```

---


```
### Platform model
class Platform extends Model
{
    public function parent()    { return $this->belongsTo(Platform::class, 'parent_id'); }
    public function children()  { return $this->hasMany(Platform::class, 'parent_id'); }
    public function dailySales(){ return $this->hasMany(DailySale::class); }
    public function returns()   { return $this->hasMany(DailyReturn::class); }
    public function budgets()   { return $this->hasMany(MonthlyBudget::class); }

    // Get all descendant IDs (recursive)
    public function descendantIds(): array
    {
        $ids = [];
        foreach ($this->children as $child) {
            $ids[] = $child->id;
            array_push($ids, ...$child->descendantIds());
        }
        return $ids;
    }
}

### Get Enorsia summary for a day (sums all children automatically)
$enorsia = Platform::with('children')->where('slug', 'enorsia')->first();
$allIds  = [$enorsia->id, ...$enorsia->descendantIds()];

$summary = DailySale::whereIn('sale_platform_id', $allIds)
    ->where('date', '2025-01-15')
    ->selectRaw('
        SUM(spent)                   as total_spent,
        SUM(sales)                   as total_sales,
        SUM(number_of_orders)        as total_orders,
        SUM(number_of_quantities)    as total_quantities,
        SUM(number_of_male_orders)   as male_orders,
        SUM(number_of_female_orders) as female_orders,
        SUM(number_of_kids_orders)   as kids_orders
    ')
    ->first();

### Get Amazon EU summary
$amazonEu   = Platform::where('slug', 'amazon-eu')->first();
$euIds      = [$amazonEu->id, ...$amazonEu->descendantIds()];
$euSummary  = DailySale::whereIn('sale_platform_id', $euIds)->where('date', '2025-01-15')
    ->selectRaw('SUM(sales) as total_sales, SUM(spent) as total_spent')->first();
```