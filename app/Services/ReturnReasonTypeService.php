<?php

namespace App\Services;

use App\Models\ReturnReasonType;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ReturnReasonTypeService
{
    /**
     * Return a paginated, filtered list of return reason types.
     */
    public function getList(array $filters): LengthAwarePaginator
    {
        return ReturnReasonType::filter($filters)
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(30)
            ->withQueryString();
    }

    /**
     * Return an un-paginated query for export (respects same filters as getList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return ReturnReasonType::filter($filters)
            ->orderBy('sort_order')
            ->latest('id');
    }

    /**
     * Create a new return reason type from validated data.
     */
    public function create(array $validated, bool $isActive): ReturnReasonType
    {
        return ReturnReasonType::create([
            'name'        => $validated['name'],
            'slug'        => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active'   => $isActive,
            'sort_order'  => $validated['sort_order'] ?? 0,
        ]);
    }

    /**
     * Update an existing return reason type.
     */
    public function update(ReturnReasonType $reasonType, array $validated, bool $isActive): ReturnReasonType
    {
        $reasonType->update([
            'name'        => $validated['name'],
            'slug'        => $validated['slug'] ?? Str::slug($validated['name']),
            'description' => $validated['description'] ?? null,
            'is_active'   => $isActive,
            'sort_order'  => $validated['sort_order'] ?? 0,
        ]);

        return $reasonType;
    }

    /**
     * Delete a return reason type.
     */
    public function delete(ReturnReasonType $reasonType): void
    {
        $reasonType->delete();
    }
}

