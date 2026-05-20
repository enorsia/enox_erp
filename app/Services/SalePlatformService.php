<?php
namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use App\Models\SalePlatform;


class SalePlatformService
{
    /**
     * Build a DFS-ordered flat list with depth metadata.
     * Each node gets: depth, is_root, has_children, children_count,
     * ancestor_names, is_last_child
    */
    public function buildFlatTree(array $nodes, int $depth = 0, array $ancestorNames = []): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $node->depth          = $depth;
            $node->is_root        = $depth === 0;
            $node->has_children   = $node->children->isNotEmpty();
            $node->children_count = $node->children->count();
            $node->ancestor_names = $ancestorNames;
            $node->is_last_child  = false;

            $result[] = $node;

            if ($node->has_children) {
                $childAncestors = array_merge($ancestorNames, [$node->name]);
                $childNodes     = $this->buildFlatTree(
                    $node->children->sortBy('sort_order')->all(),
                    $depth + 1,
                    $childAncestors
                );
                array_push($result, ...$childNodes);
            }
        }

        $byParent = [];
        foreach ($result as $item) {
            $byParent[$item->parent_id ?? 'root'][] = $item;
        }
        foreach ($byParent as $siblings) {
            if (!empty($siblings)) {
                $siblings[array_key_last($siblings)]->is_last_child = true;
            }
        }

        return $result;
    }

    /**
     * Recursively attach children. $childrenMap must stay a Collection of Collections
     * (do NOT call ->toArray() on it before passing in).
    */
    public function attachChildren(array $roots, Collection $childrenMap): array
    {
        foreach ($roots as $node) {
            $children = $childrenMap->get($node->id) ?? collect();
            $node->setRelation('children', $children);

            if ($children->isNotEmpty()) {
                $this->attachChildren($children->all(), $childrenMap);
            }
        }

        return $roots;
    }

    /**
     * Return a DFS-ordered flat array of all platforms suitable for <select> dropdowns.
     * Each item has: id, name, depth, label (indented display name).
     *
     * Pass $excludeId to hide a platform and all its descendants (prevents
     * a platform from being set as its own parent/ancestor).
    */
    public function getParentOptions(?int $excludeId = null): array
    {
        $all         = SalePlatform::orderBy('sort_order')->orderBy('id')->get();
        $childrenMap = $all->groupBy('parent_id');
        $roots       = $all->whereNull('parent_id')->values();

        $this->attachChildren($roots->all(), $childrenMap);

        $flatTree = $this->buildFlatTree($roots->sortBy('sort_order')->all());

        // Collect the IDs of the excluded node + all its descendants
        $excludeIds = [];
        if ($excludeId) {
            $excludeIds   = $this->collectDescendantIds($flatTree, $excludeId);
            $excludeIds[] = $excludeId;
        }

        $options = [];
        foreach ($flatTree as $node) {
            if (in_array($node->id, $excludeIds)) {
                continue;
            }

            // Full-width spaces give visual indent inside <option> on most browsers
            $pad       = str_repeat("\xE3\x80\x80", $node->depth); // U+3000 ideographic space
            $arrow     = $node->depth > 0 ? '└ ' : '';
            $options[] = [
                'id'                    => $node->id,
                'parent_id'             => $node->parent_id,
                'name'                  => $node->name,
                'depth'                 => $node->depth,
                'label'                 => $pad . $arrow . $node->name,
                'is_spent'              => (bool) $node->is_spent,
                'is_sales'              => (bool) $node->is_sales,
                'allows_direct_entry'   => (bool) $node->allows_direct_entry,
                'show_in_analytics'     => (bool) $node->show_in_analytics,
                'show_in_sale_tracking' => (bool) $node->show_in_sale_tracking,
            ];
        }
        return $options;
    }

    /**
     * Return parent options filtered to platforms with show_in_analytics = true.
     */
    public function getAnalyticsPlatformOptions(?int $excludeId = null): array
    {
        $options = $this->getParentOptions($excludeId);
        return array_values(array_filter($options, fn($o) => $o['show_in_analytics']));
    }

    /**
     * Return parent options filtered to platforms with show_in_sale_tracking = true.
     */
    public function getSaleTrackingPlatformOptions(?int $excludeId = null): array
    {
        $options = $this->getParentOptions($excludeId);
        return array_values(array_filter($options, fn($o) => $o['show_in_sale_tracking']));
    }

    /**
     * Walk the flat tree and collect all descendant IDs of $targetId.
    */
    public function collectDescendantIds(array $flatTree, int $targetId): array
    {
        $targetDepth = null;
        $collecting  = false;
        $ids         = [];

        foreach ($flatTree as $node) {
            if ($node->id === $targetId) {
                $targetDepth = $node->depth;
                $collecting  = true;
                continue;
            }

            if ($collecting) {
                if ($node->depth > $targetDepth) {
                    $ids[] = $node->id;
                } else {
                    break;
                }
            }
        }

        return $ids;
    }

    /**
     * Build and return the complete DFS-ordered flat tree for the index listing.
     */
    public function getFullTreeList(): array
    {
        $all         = SalePlatform::orderBy('sort_order')->orderBy('id')->get();
        $childrenMap = $all->groupBy('parent_id');
        $roots       = $all->whereNull('parent_id')->values();

        $this->attachChildren($roots->all(), $childrenMap);

        return $this->buildFlatTree($roots->sortBy('sort_order')->all());
    }

    /**
     * Return a paginated, filtered flat list with basic tree-metadata stamped on each item.
     */
    public function getFilteredList(array $filters)
    {
        $platforms = SalePlatform::with('parent')
            ->filter($filters)
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        foreach ($platforms as $p) {
            $p->depth          = 0;
            $p->is_root        = true;
            $p->has_children   = false;
            $p->children_count = 0;
            $p->ancestor_names = [];
            $p->is_last_child  = false;
        }

        return $platforms;
    }

    /**
     * Return an un-paginated query for export (respects same filters as getFilteredList).
     */
    public function getExportQuery(array $filters): Builder
    {
        return SalePlatform::with('parent.parent')
            ->filter($filters)
            ->orderBy('sort_order')
            ->latest('id');
    }

    /**
     * Return aggregate stats for the dashboard cards on the index page.
     */
    public function getStats(): array
    {
        return [
            'total'                 => SalePlatform::count(),
            'active'                => SalePlatform::where('is_active', true)->count(),
            'inactive'              => SalePlatform::where('is_active', false)->count(),
            'with_spent'            => SalePlatform::where('is_spent', true)->count(),
            'with_sales'            => SalePlatform::where('is_sales', true)->count(),
            'allow_direct_entry'    => SalePlatform::where('allows_direct_entry', true)->count(),
            'show_in_analytics'     => SalePlatform::where('show_in_analytics', true)->count(),
            'show_in_sale_tracking' => SalePlatform::where('show_in_sale_tracking', true)->count(),
            'types'                 => SalePlatform::selectRaw('type, count(*) as count')
                ->groupBy('type')
                ->pluck('count', 'type'),
        ];
    }
}
