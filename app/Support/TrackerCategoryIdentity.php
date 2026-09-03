<?php

namespace App\Support;

class TrackerCategoryIdentity
{
    /**
     * Keep in sync with DeptWiseCategoryImageNew::DISPLAY_NAME_MAP (enorsia_v2).
     *
     * @var array<string, string>
     */
    public const DISPLAY_NAME_MAP = [
        'Co-ords and multipacks' => 'Co-ords',
        'Jumpers and Cardigans' => 'Jumpers',
        'Jumpsuits and Playsuits' => 'Jumpsuits',
    ];

    /**
     * URL department slugs under /c/{slug}/...
     *
     * @var array<string, string>
     */
    public const URL_DEPARTMENT_SLUG_MAP = [
        'men' => 'Men',
        'women' => 'Women',
        'boys' => 'Boys',
        'girls' => 'Girls',
    ];

    /**
     * @var list<string>
     */
    public const DEPARTMENTS = ['Men', 'Women', 'Boys', 'Girls'];

    public static function normalizeDepartmentName(string $departmentName): string
    {
        $departmentName = trim($departmentName);

        if ($departmentName === '') {
            return '';
        }

        foreach (self::URL_DEPARTMENT_SLUG_MAP as $slug => $label) {
            if (strcasecmp($departmentName, $label) === 0 || strcasecmp($departmentName, $slug) === 0) {
                return $label;
            }
        }

        return $departmentName;
    }

    public static function departmentNameFromPageUrl(?string $pageUrl): string
    {
        $pageUrl = trim((string) $pageUrl);

        if ($pageUrl === '') {
            return '';
        }

        $path = parse_url($pageUrl, PHP_URL_PATH);

        if (! is_string($path) || $path === '') {
            return '';
        }

        if (! preg_match('#/c/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            && ! preg_match('#/style/(men|women|boys|girls)(?:/|$)#i', $path, $matches)
            && ! preg_match('#^/(men|women|boys|girls)(?:/|$)#i', $path, $matches)) {
            return '';
        }

        return self::URL_DEPARTMENT_SLUG_MAP[strtolower($matches[1])] ?? '';
    }

    /**
     * @param  array<string, mixed>  $line
     */
    public static function lineHasCategoryIdentity(array $line): bool
    {
        return self::metaFromLine($line) !== null;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array<string, mixed>
     */
    public static function ensureLineCategoryIdentity(array $line): array
    {
        if (self::lineHasCategoryIdentity($line)) {
            return $line;
        }

        $departmentName = trim((string) ($line['department_name'] ?? ''));

        if ($departmentName !== '') {
            $line['category_name'] = $departmentName;
        }

        return $line;
    }

    /**
     * @param  array{department_name?: string, category_name?: string, category_code?: string, category_id?: string, page_url?: string}  $context
     */
    public static function resolveDepartmentName(array $context): string
    {
        $departmentName = trim((string) ($context['department_name'] ?? ''));

        if ($departmentName !== '') {
            return $departmentName;
        }

        return self::departmentNameFromPageUrl((string) ($context['page_url'] ?? ''));
    }

    public static function displayName(?string $categoryName): string
    {
        $name = trim((string) $categoryName);

        if ($name === '') {
            return '';
        }

        return self::DISPLAY_NAME_MAP[$name] ?? $name;
    }

    /**
     * Exact stored category_name values that match a dashboard filter label.
     *
     * @return list<string>
     */
    public static function storedCategoryNamesForFilter(string $category): array
    {
        $category = trim($category);

        if ($category === '') {
            return [];
        }

        $names = [$category];
        $display = self::displayName($category);

        if ($display !== '' && strcasecmp($display, $category) !== 0) {
            $names[] = $display;
        }

        foreach (self::DISPLAY_NAME_MAP as $stored => $mapped) {
            if (strcasecmp($mapped, $category) === 0 || strcasecmp($mapped, $display) === 0) {
                $names[] = $stored;
            }
        }

        return array_values(array_unique($names));
    }

    public static function categoryMatchesFilter(?string $storedName, string $filter): bool
    {
        $storedName = trim((string) $storedName);
        $filter = trim($filter);

        if ($filter === '') {
            return true;
        }

        if ($storedName === '') {
            return false;
        }

        if (strcasecmp(self::displayName($storedName), self::displayName($filter)) === 0) {
            return true;
        }

        foreach (self::storedCategoryNamesForFilter($filter) as $name) {
            if (strcasecmp($storedName, $name) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array{
     *     key: string,
     *     department_name: string,
     *     category_name: string,
     *     category_code: string,
     *     category_id: string,
     *     label: string
     * }
     */
    public static function meta(
        string $departmentName = '',
        string $categoryCode = '',
        string $categoryName = '',
        string $categoryId = '',
    ): array {
        $departmentName = trim($departmentName);
        $categoryCode = trim($categoryCode);
        $categoryName = self::displayName($categoryName);
        $categoryId = trim($categoryId);

        return [
            'key' => self::key($departmentName, $categoryCode, $categoryName, $categoryId),
            'department_name' => $departmentName,
            'category_name' => $categoryName,
            'category_code' => $categoryCode,
            'category_id' => $categoryId,
            'label' => self::label($departmentName, $categoryName),
        ];
    }

    public static function key(
        string $departmentName,
        string $categoryCode,
        string $categoryName,
        string $categoryId = '',
    ): string {
        $identity = $categoryCode !== ''
            ? $categoryCode
            : ($categoryId !== '' ? $categoryId : $categoryName);

        return strtolower(trim($departmentName).'|'.trim($identity));
    }

    public static function label(string $departmentName, string $categoryName): string
    {
        $departmentName = trim($departmentName);
        $categoryName = self::displayName($categoryName);

        if ($departmentName !== '' && $categoryName !== '' && strcasecmp($departmentName, $categoryName) !== 0) {
            return $departmentName.' -> '.$categoryName;
        }

        return $categoryName !== '' ? $categoryName : $departmentName;
    }

    /**
     * @param  array<string, mixed>  $line
     * @param  array<string, mixed>  $row
     */
    public static function lineMatchesRow(array $line, array $row): bool
    {
        $lineDepartment = trim((string) ($line['department_name'] ?? ''));
        $rowDepartment = trim((string) ($row['department_name'] ?? ''));
        $lineCode = trim((string) ($line['category_code'] ?? ''));
        $rowCode = trim((string) ($row['category_code'] ?? ''));
        $lineId = trim((string) ($line['category_id'] ?? ''));
        $rowId = trim((string) ($row['category_id'] ?? ''));
        $lineName = self::displayName((string) ($line['category_name'] ?? $line['category'] ?? ''));
        $rowName = self::displayName((string) ($row['category_name'] ?? ''));

        if ($lineDepartment !== '' && $rowDepartment !== '' && strcasecmp($lineDepartment, $rowDepartment) !== 0) {
            return false;
        }

        if ($rowId !== '' && $lineId !== '' && $rowId === $lineId) {
            return true;
        }

        if ($rowCode !== '' && $lineCode !== '' && strcasecmp($rowCode, $lineCode) === 0) {
            return true;
        }

        if ($rowName !== '' && $lineName !== '' && strcasecmp($rowName, $lineName) === 0) {
            if ($lineDepartment !== '' && $rowDepartment !== '' && strcasecmp($lineDepartment, $rowDepartment) !== 0) {
                return false;
            }

            // Name-only match: do not merge rows when only one side has a department
            // (e.g. Men Jumpers cart line vs legacy Women+Men mixed "Jumpers" row).
            if ($lineDepartment !== '' xor $rowDepartment !== '') {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $line
     * @return array{
     *     key: string,
     *     department_name: string,
     *     category_name: string,
     *     category_code: string,
     *     category_id: string,
     *     label: string
     * }|null
     */
    public static function metaFromLine(array $line): ?array
    {
        $departmentName = trim((string) ($line['department_name'] ?? ''));
        $categoryCode = trim((string) ($line['category_code'] ?? ''));
        $categoryName = trim((string) ($line['category_name'] ?? $line['category'] ?? ''));
        $categoryId = trim((string) ($line['category_id'] ?? ''));

        if ($categoryName === '' && $categoryCode === '' && $categoryId === '') {
            return null;
        }

        return self::meta($departmentName, $categoryCode, $categoryName, $categoryId);
    }

    /**
     * Department/category dropdowns from pairs that actually occurred.
     * Empty storefront departments are omitted.
     *
     * @param  iterable<int, object|array<string, mixed>>  $pairs
     * @return array{
     *     departments: list<string>,
     *     categories_by_department: array<string, list<string>>
     * }
     */
    public static function filterOptionsFromPairs(iterable $pairs): array
    {
        $categoriesByDepartment = array_fill_keys(self::DEPARTMENTS, []);

        foreach ($pairs as $row) {
            $row = is_array($row) ? $row : (array) $row;
            $department = self::normalizeDepartmentName((string) ($row['department_name'] ?? ''));

            if (! in_array($department, self::DEPARTMENTS, true)) {
                continue;
            }

            $category = self::displayName((string) ($row['category_name'] ?? $row['category'] ?? ''));

            if ($category === '' || strcasecmp($category, $department) === 0) {
                continue;
            }

            if (! in_array($category, $categoriesByDepartment[$department], true)) {
                $categoriesByDepartment[$department][] = $category;
            }
        }

        foreach ($categoriesByDepartment as $department => $categories) {
            sort($categories, SORT_NATURAL | SORT_FLAG_CASE);
            $categoriesByDepartment[$department] = array_values($categories);
        }

        $departments = array_values(array_filter(
            self::DEPARTMENTS,
            fn (string $department) => $categoriesByDepartment[$department] !== [],
        ));

        return [
            'departments' => $departments,
            'categories_by_department' => array_intersect_key(
                $categoriesByDepartment,
                array_flip($departments),
            ),
        ];
    }
}
