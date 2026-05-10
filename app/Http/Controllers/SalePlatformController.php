<?php

namespace App\Http\Controllers;

use App\Models\SalePlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalePlatformController extends Controller
{
    // ─────────────────────────────────────────────────────────────
    // Private helpers
    // ─────────────────────────────────────────────────────────────

    /**
     * Build a DFS-ordered flat list with depth metadata.
     * Each node gets: depth, is_root, has_children, children_count,
     *                 ancestor_names, is_last_child
     */
    private function buildFlatTree(array $nodes, int $depth = 0, array $ancestorNames = []): array
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

        // Mark the last sibling at each parent level for connector lines
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
    private function attachChildren(array $roots, Collection $childrenMap): array
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
    private function getParentOptions(int $excludeId = null): array
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
                'id'    => $node->id,
                'parent_id' => $node->parent_id,
                'name'  => $node->name,
                'depth' => $node->depth,
                'label' => $pad . $arrow . $node->name,
            ];
        }
        return $options;
    }

    /**
     * Walk the flat tree and collect all descendant IDs of $targetId.
     */
    private function collectDescendantIds(array $flatTree, int $targetId): array
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
                    break; // back to same or higher level — done
                }
            }
        }

        return $ids;
    }

    // ─────────────────────────────────────────────────────────────
    // Resource methods
    // ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        Gate::authorize('general.sale_platform.index');

        $search   = $request->input('search');
        $type     = $request->input('type');
        $isActive = $request->input('is_active');

        $hasFilter = $search || $type || ($isActive !== null && $isActive !== '');

        if ($hasFilter) {
            $platforms = SalePlatform::with('parent')
                ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('slug', 'like', "%{$search}%");
                }))
                ->when($type, fn($q) => $q->where('type', $type))
                ->when($isActive !== null && $isActive !== '', fn($q) => $q->where('is_active', $isActive))
                ->orderBy('sort_order')
                ->latest('id')
                ->paginate(30)
                ->withQueryString();

            foreach ($platforms as $p) {
                $p->depth          = 0;
                $p->is_root        = true;
                $p->has_children   = false;
                $p->children_count = 0;
                $p->ancestor_names = [];
                $p->is_last_child  = false;
            }

            $data['platforms']   = $platforms;
            $data['flat_list']   = $platforms->items();
            $data['is_filtered'] = true;
            $data['start']       = ($platforms->currentPage() - 1) * $platforms->perPage() + 1;
        } else {
            $all         = SalePlatform::orderBy('sort_order')->orderBy('id')->get();
            $childrenMap = $all->groupBy('parent_id');
            $roots       = $all->whereNull('parent_id')->values();

            $this->attachChildren($roots->all(), $childrenMap);

            $flatTree = $this->buildFlatTree($roots->sortBy('sort_order')->all());

            $data['platforms']   = null;
            $data['flat_list']   = $flatTree;
            $data['is_filtered'] = false;
            $data['start']       = 1;
        }

        $data['stats'] = [
            'total'    => SalePlatform::count(),
            'active'   => SalePlatform::where('is_active', true)->count(),
            'inactive' => SalePlatform::where('is_active', false)->count(),
            'types'    => SalePlatform::selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
        ];

        return view('sale_platforms.index', $data);
    }

    public function create()
    {
        Gate::authorize('general.sale_platform.create');

        $data['parentOptions'] = $this->getParentOptions();
        $data['types']         = ['channel', 'sub_channel', 'marketplace', 'region'];

        return view('sale_platforms.create', $data);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:sale_platforms,name',
            'slug'       => 'nullable|string|max:100|unique:sale_platforms,slug',
            'parent_id'  => 'nullable|exists:sale_platforms,id',
            'type'       => 'required|in:channel,sub_channel,marketplace,region',
            'is_active'  => 'nullable|in:on,off',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $platform = SalePlatform::create([
                'name'       => $validated['name'],
                'slug'       => $validated['slug'] ?? Str::slug($validated['name']),
                'parent_id'  => $validated['parent_id'] ?? null,
                'type'       => $validated['type'],
                'is_active'  => $request->has('is_active'),
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($platform)
                ->withProperties(['attributes' => $platform->toArray()])
                ->log('Created new sale platform: ' . $platform->name);

            notify()->success("Sale platform created successfully.", "Success");
            return redirect()->route('admin.sale-platforms.index');
        } catch (\Exception $e) {
            Log::error('Sale platform creation failed: ' . $e->getMessage());
            notify()->error('Failed to create sale platform', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function show(SalePlatform $salePlatform)
    {
        Gate::authorize('general.sale_platform.show');

        $salePlatform->load(['parent', 'children' => fn($q) => $q->orderBy('sort_order')]);

        $breadcrumbs = [];
        $current     = $salePlatform->parent;
        while ($current) {
            array_unshift($breadcrumbs, $current);
            $current = $current->parent ?? null;
            if ($current) {
                $current->load('parent');
            }
        }

        $siblingsCount = SalePlatform::where('parent_id', $salePlatform->parent_id)
            ->where('id', '!=', $salePlatform->id)
            ->count();

        return view('sale_platforms.show', [
            'salePlatform'  => $salePlatform,
            'breadcrumbs'   => $breadcrumbs,
            'siblingsCount' => $siblingsCount,
        ]);
    }

    public function edit(SalePlatform $salePlatform)
    {
        Gate::authorize('general.sale_platform.edit');

        $data['salePlatform']  = $salePlatform;
        $data['parentOptions'] = $this->getParentOptions($salePlatform->id);
        $data['types']         = ['channel', 'sub_channel', 'marketplace', 'region'];

        return view('sale_platforms.edit', $data);
    }

    public function update(Request $request, SalePlatform $salePlatform)
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:100|unique:sale_platforms,name,' . $salePlatform->id,
            'slug'       => 'nullable|string|max:100|unique:sale_platforms,slug,' . $salePlatform->id,
            'parent_id'  => 'nullable|exists:sale_platforms,id',
            'type'       => 'required|in:channel,sub_channel,marketplace,region',
            'is_active'  => 'nullable|in:on,off',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $oldValues = $salePlatform->only(['name', 'slug', 'parent_id', 'type', 'is_active', 'sort_order']);

            $salePlatform->update([
                'name'       => $validated['name'],
                'slug'       => $validated['slug'] ?? Str::slug($validated['name']),
                'parent_id'  => $validated['parent_id'] ?? null,
                'type'       => $validated['type'],
                'is_active'  => $request->has('is_active'),
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            $newValues = $salePlatform->only(['name', 'slug', 'parent_id', 'type', 'is_active', 'sort_order']);
            $changes   = array_filter($newValues, fn($v, $k) => $v != $oldValues[$k], ARRAY_FILTER_USE_BOTH);

            if (!empty($changes)) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($salePlatform)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log('Updated sale platform: ' . $salePlatform->name . ' (Changed: ' . implode(', ', array_keys($changes)) . ')');
            }

            notify()->success("Sale platform updated successfully.", "Success");
            return redirect()->route('admin.sale-platforms.index');
        } catch (\Exception $e) {
            Log::error('Sale platform update failed: ' . $e->getMessage());
            notify()->error('Failed to update sale platform', 'Error');
            return redirect()->back()->withInput();
        }
    }

    public function destroy(SalePlatform $salePlatform)
    {
        Gate::authorize('general.sale_platform.delete');

        try {
            activity()
                ->causedBy(Auth::user())
                ->performedOn($salePlatform)
                ->withProperties(['deleted_platform' => $salePlatform->name])
                ->log('Deleted sale platform: ' . $salePlatform->name);

            $salePlatform->delete();

            notify()->success("Sale platform deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('Sale platform deletion failed: ' . $e->getMessage());
            notify()->error('Failed to delete sale platform', 'Error');
            return redirect()->back();
        }
    }
}