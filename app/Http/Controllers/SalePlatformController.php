<?php

namespace App\Http\Controllers;

use App\Models\SalePlatform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SalePlatformController extends Controller
{
    private function buildFlatTree(array $nodes, int $depth = 0, array $ancestorNames = []): array
    {
        $result = [];

        foreach ($nodes as $node) {
            $node->depth          = $depth;
            $node->is_root        = $depth === 0;
            $node->has_children   = $node->children->isNotEmpty();
            $node->children_count = $node->children->count();
            $node->ancestor_names = $ancestorNames;
            $node->is_last_child  = false; // will be fixed below

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

        // Mark last children so the blade can draw connectors correctly
        if (!empty($result)) {
            // Group siblings and mark the last one at each parent
            $byParent = [];
            foreach ($result as $item) {
                $byParent[$item->parent_id ?? 'root'][] = $item;
            }
            foreach ($byParent as $siblings) {
                if (!empty($siblings)) {
                    $siblings[array_key_last($siblings)]->is_last_child = true;
                }
            }
        }

        return $result;
    }

    /**
     * Recursively attach children from a keyed map.
     */
    private function attachChildren(array $roots, \Illuminate\Support\Collection $childrenMap): array
    {
        foreach ($roots as $node) {
            // groupBy() keys on the cast value; parent_id may be int or string key
            $children = $childrenMap->get($node->id) ?? collect();
            $node->setRelation('children', $children);

            if ($children->isNotEmpty()) {
                $this->attachChildren($children->all(), $childrenMap);
            }
        }

        return $roots;
    }

    public function index(Request $request)
    {
        Gate::authorize('general.sale_platform.index');

        $search   = $request->input('search');
        $type     = $request->input('type');
        $isActive = $request->input('is_active');

        $hasFilter = $search || $type || ($isActive !== null && $isActive !== '');

        if ($hasFilter) {
            // Filtered: flat paginated list (hierarchy doesn't apply cleanly to filtered sets)
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

            // Annotate depth = 0 for flat filtered results
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
            // No filter: load ALL platforms and build nested tree
            $all = SalePlatform::orderBy('sort_order')->orderBy('id')->get();

            // Keep groupBy result as a Collection of Collections — do NOT call toArray()
            $childrenMap = $all->groupBy('parent_id');
            $roots       = $all->whereNull('parent_id')->values();

            // Attach children recursively (passes the Collection, not an array)
            $this->attachChildren($roots->all(), $childrenMap);

            // Build DFS flat list with depth metadata
            $flatTree = $this->buildFlatTree($roots->sortBy('sort_order')->all());

            $data['platforms']   = null; // no paginator needed in tree mode
            $data['flat_list']   = $flatTree;
            $data['is_filtered'] = false;
            $data['start']       = 1;
        }

        // Summary stats
        $data['stats'] = [
            'total'    => SalePlatform::count(),
            'active'   => SalePlatform::where('is_active', true)->count(),
            'inactive' => SalePlatform::where('is_active', false)->count(),
            'types'    => SalePlatform::selectRaw('type, count(*) as count')->groupBy('type')->pluck('count', 'type'),
        ];

        return view('sale_platforms.index', $data);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('general.sale_platform.create');

        $data['parentPlatforms'] = SalePlatform::where('parent_id', null)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $data['types'] = ['channel', 'sub_channel', 'marketplace', 'region'];

        return view('sale_platforms.create', $data);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:sale_platforms,name',
            'slug' => 'nullable|string|max:100|unique:sale_platforms,slug',
            'parent_id' => 'nullable|exists:sale_platforms,id',
            'type' => 'required|in:channel,sub_channel,marketplace,region',
            'is_active' => 'nullable|boolean',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        try {
            $slug = $validated['slug'] ?? Str::slug($request->name);

            $platform = SalePlatform::create([
                'name' => $validated['name'],
                'slug' => $slug,
                'parent_id' => $validated['parent_id'] ?? null,
                'type' => $validated['type'],
                'is_active' => $request->has('is_active') ? true : false,
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
            return redirect()->route('admin.sale-platforms.index');
        }
    }

    public function show(SalePlatform $salePlatform)
    {
        Gate::authorize('general.sale_platform.show');

        $salePlatform->load(['parent', 'children' => fn($q) => $q->orderBy('sort_order')]);

        // Build full ancestor breadcrumb chain
        $breadcrumbs = [];
        $current     = $salePlatform->parent;
        while ($current) {
            array_unshift($breadcrumbs, $current);
            $current = $current->parent ?? null;
            if ($current) {
                $current->load('parent');
            }
        }

        // Sibling count (same parent, same type)
        $siblingsCount = SalePlatform::where('parent_id', $salePlatform->parent_id)
            ->where('id', '!=', $salePlatform->id)
            ->count();

        return view('sale_platforms.show', [
            'salePlatform'  => $salePlatform,
            'breadcrumbs'   => $breadcrumbs,
            'siblingsCount' => $siblingsCount,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(SalePlatform $salePlatform)
    {
        Gate::authorize('general.sale_platform.edit');

        $data['salePlatform'] = $salePlatform;
        $data['parentPlatforms'] = SalePlatform::where('parent_id', null)
            ->where('id', '!=', $salePlatform->id)
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        $data['types'] = ['channel', 'sub_channel', 'marketplace', 'region'];

        return view('sale_platforms.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, SalePlatform $salePlatform)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100|unique:sale_platforms,name,' . $salePlatform->id,
            'slug' => 'nullable|string|max:100|unique:sale_platforms,slug,' . $salePlatform->id,
            'parent_id' => 'nullable|exists:sale_platforms,id',
            'type' => 'required|in:channel,sub_channel,marketplace,region',
            'is_active' => 'nullable|in:on,off',
            'sort_order' => 'nullable|integer|min:0|max:255',
        ]);

        $validated['is_active'] = $request->boolean('is_active');

        try {
            // Capture old values before update
            $oldValues = [
                'name' => $salePlatform->name,
                'slug' => $salePlatform->slug,
                'parent_id' => $salePlatform->parent_id,
                'type' => $salePlatform->type,
                'is_active' => $salePlatform->is_active,
                'sort_order' => $salePlatform->sort_order,
            ];

            $slug = $validated['slug'] ?? Str::slug($request->name);

            $salePlatform->update([
                'name' => $validated['name'],
                'slug' => $slug,
                'parent_id' => $validated['parent_id'] ?? null,
                'type' => $validated['type'],
                'is_active' => $request->has('is_active') ? true : false,
                'sort_order' => $validated['sort_order'] ?? 0,
            ]);

            // Capture new values after update
            $newValues = [
                'name' => $salePlatform->name,
                'slug' => $salePlatform->slug,
                'parent_id' => $salePlatform->parent_id,
                'type' => $salePlatform->type,
                'is_active' => $salePlatform->is_active,
                'sort_order' => $salePlatform->sort_order,
            ];

            // Detect actual changes
            $changes = [];
            foreach ($oldValues as $key => $oldValue) {
                if ($oldValue != $newValues[$key]) {
                    $changes[$key] = [
                        'old' => $oldValue,
                        'new' => $newValues[$key]
                    ];
                }
            }

            // Log only if there are actual changes
            if (count($changes) > 0) {
                $changedFields = array_keys($changes);
                $description = 'Updated sale platform: ' . $salePlatform->name;
                $description .= ' (Changed: ' . implode(', ', array_map(fn($f) => ucfirst($f), $changedFields)) . ')';

                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($salePlatform)
                    ->withProperties(['old' => $oldValues, 'attributes' => $newValues])
                    ->log($description);
            }

            notify()->success("Sale platform updated successfully.", "Success");
            return redirect()->route('admin.sale-platforms.index');
        } catch (\Exception $e) {
            Log::error('Sale platform update failed: ' . $e->getMessage());
            notify()->error('Failed to update sale platform', 'Error');
            return redirect()->route('admin.sale-platforms.index');
        }
    }

    /**
     * Remove the specified resource from storage.
     */
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

