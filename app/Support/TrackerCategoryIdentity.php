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
            && ! preg_match('#/style/(men|women|boys|girls)(?:/|$)#i', $path, $matches)) {
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
}
