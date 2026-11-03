<?php

namespace App\Services;

use App\Models\TrackingCtrCategory;
use App\Support\TrackerDatabase;
use Illuminate\Support\Str;

class EcommerceRankingListingPathService
{
    private const PATH_SEPARATOR = ' -> ';

    /**
     * @param  list<array{key: string, label: string, listing_scope: string, listing_scope_id: int}>  $contexts
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    public function resolve(string $contextKey, string $listingScope, int $listingScopeId, array $contexts): array
    {
        $apiLabel = $this->contextLabel($contextKey, $contexts);
        if ($apiLabel !== null && str_contains($apiLabel, self::PATH_SEPARATOR)) {
            return $this->fromPathString($apiLabel);
        }

        $fromScope = $this->fromListingScope($listingScope, $listingScopeId);
        if ($fromScope['path'] !== '—') {
            return $fromScope;
        }

        if ($apiLabel !== null && $apiLabel !== '') {
            return [
                'path' => $apiLabel,
                'department' => $apiLabel,
                'category' => '—',
                'micro_category' => '—',
            ];
        }

        return $fromScope;
    }

    /**
     * @param  list<array{key: string, label: string, listing_scope: string, listing_scope_id: int}>  $contexts
     */
    private function contextLabel(string $contextKey, array $contexts): ?string
    {
        $contextKey = trim($contextKey);
        if ($contextKey === '') {
            return null;
        }

        foreach ($contexts as $context) {
            if (($context['key'] ?? '') === $contextKey) {
                $label = trim((string) ($context['label'] ?? ''));

                return $label !== '' ? $label : null;
            }
        }

        return null;
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function fromPathString(string $path): array
    {
        $segments = array_values(array_filter(
            array_map('trim', preg_split('/\s*->\s*/', trim($path)) ?: []),
            fn (string $segment): bool => $segment !== '',
        ));

        if ($segments === []) {
            return $this->emptyListing();
        }

        $department = $segments[0];
        $category = $segments[1] ?? '—';
        $microCategory = $segments[2] ?? '—';

        if (count($segments) > 3) {
            $microCategory = implode(self::PATH_SEPARATOR, array_slice($segments, 2));
        }

        return [
            'path' => implode(self::PATH_SEPARATOR, $segments),
            'department' => $department,
            'category' => $category,
            'micro_category' => $microCategory,
        ];
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function fromListingScope(string $listingScope, int $listingScopeId): array
    {
        return match ($listingScope) {
            'global' => [
                'path' => 'Shop All',
                'department' => '—',
                'category' => 'Shop All',
                'micro_category' => '—',
            ],
            'department' => $this->fromStaticDepartmentListing($listingScopeId, 'shop-all', 'Shop All'),
            'new-in' => $this->fromStaticDepartmentListing($listingScopeId, 'new-in', 'New In'),
            'category' => $this->fromCategoryListing($listingScopeId),
            'micro-category' => $this->fromMicroCategoryListing($listingScopeId),
            'special-department' => $this->fromSpecialDepartmentListing($listingScopeId),
            default => $this->emptyListing(),
        };
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function fromStaticDepartmentListing(int $departmentId, string $categorySlug, string $categoryLabel): array
    {
        $row = TrackerDatabase::table('tracking_ctr_categories')
            ->where('type', TrackingCtrCategory::TYPE_STATIC)
            ->where('department_id', $departmentId)
            ->where('category_slug', $categorySlug)
            ->orderBy('id')
            ->first(['department_slug']);

        if ($row === null) {
            return $this->emptyListing();
        }

        $department = $this->formatSlug($row->department_slug, $departmentId);

        return [
            'path' => $department.self::PATH_SEPARATOR.$categoryLabel,
            'department' => $department,
            'category' => $categoryLabel,
            'micro_category' => '—',
        ];
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function fromCategoryListing(int $categoryId): array
    {
        $row = TrackerDatabase::table('tracking_ctr_categories')
            ->where('type', TrackingCtrCategory::TYPE_CATEGORY)
            ->where('category_id', $categoryId)
            ->orderBy('id')
            ->first(['department_slug', 'category_slug', 'department_id']);

        if ($row === null) {
            return $this->emptyListing();
        }

        $department = $this->formatSlug($row->department_slug, $row->department_id);
        $category = $this->formatSlug($row->category_slug, $categoryId);

        return [
            'path' => $department.self::PATH_SEPARATOR.$category,
            'department' => $department,
            'category' => $category,
            'micro_category' => '—',
        ];
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function fromMicroCategoryListing(int $microCategoryId): array
    {
        $row = TrackerDatabase::table('tracking_ctr_categories')
            ->where('type', TrackingCtrCategory::TYPE_MICRO_CATEGORY)
            ->where('micro_category_id', $microCategoryId)
            ->orderBy('id')
            ->first(['department_slug', 'category_slug', 'micro_category_slug', 'department_id', 'micro_category_id']);

        if ($row === null) {
            return $this->emptyListing();
        }

        $department = $this->formatSlug($row->department_slug, $row->department_id);
        $category = $this->formatSlug($row->category_slug, null);
        $microCategory = $this->formatSlug($row->micro_category_slug, $row->micro_category_id);

        return [
            'path' => $department.self::PATH_SEPARATOR.$category.self::PATH_SEPARATOR.$microCategory,
            'department' => $department,
            'category' => $category,
            'micro_category' => $microCategory,
        ];
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function fromSpecialDepartmentListing(int $categoryId): array
    {
        $row = TrackerDatabase::table('tracking_ctr_categories')
            ->where('type', TrackingCtrCategory::TYPE_DYNAMIC_CUSTOM_LINK)
            ->where('category_id', $categoryId)
            ->orderBy('id')
            ->first(['category_slug']);

        if ($row === null) {
            return $this->emptyListing();
        }

        $category = $this->formatSlug($row->category_slug, $categoryId);

        return [
            'path' => $category,
            'department' => '—',
            'category' => $category,
            'micro_category' => '—',
        ];
    }

    /**
     * @return array{path: string, department: string, category: string, micro_category: string}
     */
    private function emptyListing(): array
    {
        return [
            'path' => '—',
            'department' => '—',
            'category' => '—',
            'micro_category' => '—',
        ];
    }

    private function formatSlug(?string $slug, mixed $id): string
    {
        $slug = trim((string) ($slug ?? ''));
        if ($slug !== '') {
            return Str::headline(str_replace(['-', '_'], ' ', $slug));
        }

        if ($id !== null && $id !== '' && (int) $id > 0) {
            return (string) $id;
        }

        return '—';
    }
}
