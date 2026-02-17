<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Throwable;

class RoleController extends Controller
{

    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        Gate::authorize('authentication.roles.index');
        $roles = Role::select('id', 'name')
            ->with([
                'permissions:id,name',
            ])
            ->when($request->search, fn ($q, $s) => $q->where('name', 'like', "%{$s}%"))
            ->orderByDesc('id')
            ->paginate($this->perPage);

        return view('roles.index', compact('roles'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        Gate::authorize('authentication.roles.create');
        $permissions = Permission::all();

        $nested = [];

        foreach ($permissions as $perm) {
            $parts = explode('.', $perm->name);

            $moduleKey = isset($parts[0]) && $parts[0] !== '' ? $parts[0] : 'other';
            $modelKey = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $moduleKey;

            $moduleName = ucfirst($moduleKey);
            $modelName = ucfirst($modelKey);

            if (! isset($nested[$moduleName])) {
                $nested[$moduleName] = [];
            }
            if (! isset($nested[$moduleName][$modelName])) {
                $nested[$moduleName][$modelName] = collect();
            }

            $nested[$moduleName][$modelName]->push($perm);
        }

        return view('roles.create', compact('nested'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {

        $formData = $request->validate([
            'name' => 'required|string|min:3|unique:roles,name',
            'permissions' => 'required|array',
        ]);

        try {
            $role = Role::create([
                'name' => $formData['name'],
                'guard_name' => 'web',
            ]);

            if (!empty($formData['permissions'])) {
                $permissions = Permission::whereIn('id', $formData['permissions'])->pluck('name')->toArray();
                $role->syncPermissions($permissions);
            }

            activity()
                ->causedBy(Auth::user())
                ->performedOn($role)
                ->withProperties([
                    'role_name' => $role->name,
                    'permissions_count' => count($permissions ?? [])
                ])
                ->log('Created new role: ' . $role->name . ' with ' . count($permissions ?? []) . ' permission(s)');

            notify()->success('Role added successfully', 'Success');
            return redirect()->route('admin.roles.index');

        } catch (Throwable $e) {
            Log::error('Failed to create role', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);
            notify()->error('Something went wrong', 'Error');
            return redirect()->back();
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        Gate::authorize('authentication.roles.show');
        $role = Role::with('permissions')->findOrFail($id);

        $permissions = $role->permissions;

        $nested = [];

        foreach ($permissions as $perm) {
            $parts = explode('.', $perm->name);

            $moduleKey = $parts[0] ?? 'Other';
            $modelKey = $parts[1] ?? $moduleKey;

            $moduleName = ucfirst($moduleKey);
            $modelName = ucfirst($modelKey);

            if (! isset($nested[$moduleName])) {
                $nested[$moduleName] = [];
            }
            if (! isset($nested[$moduleName][$modelName])) {
                $nested[$moduleName][$modelName] = collect();
            }
            $nested[$moduleName][$modelName]->push($perm);
        }

        return view('roles.show', compact('role', 'nested'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Role $role)
    {
        Gate::authorize('authentication.roles.edit');
        $rolePermissions = $role->permissions->pluck('id')->toArray();

        $permissions = Permission::all();
        $nested = [];

        foreach ($permissions as $perm) {
            $parts = explode('.', $perm->name);

            $moduleKey = isset($parts[0]) && $parts[0] !== '' ? $parts[0] : 'other';
            $modelKey = isset($parts[1]) && $parts[1] !== '' ? $parts[1] : $moduleKey;

            $moduleName = ucfirst($moduleKey);
            $modelName = ucfirst($modelKey);

            if (! isset($nested[$moduleName])) {
                $nested[$moduleName] = [];
            }
            if (! isset($nested[$moduleName][$modelName])) {
                $nested[$moduleName][$modelName] = collect();
            }

            $nested[$moduleName][$modelName]->push($perm);
        }

        return view('roles.edit', compact('role', 'nested', 'rolePermissions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, $id)
    {
        $formData = $request->validate([
            'name' => 'required|string|min:3|unique:roles,name,' . $id,
            'permissions' => 'required|array',
        ]);

        $page = $request->input('page');
        try {
            $role = Role::findOrFail($id);

            // Capture old values
            $oldName = $role->name;
            $oldPermissions = $role->permissions->pluck('name')->toArray();

            $role->name = $formData['name'];
            $role->save();

            $permissions = Permission::whereIn('id', $formData['permissions'] ?? [])->pluck('name')->toArray();
            $role->syncPermissions($permissions);

            // Detect changes
            $changes = [];
            if ($oldName !== $role->name) {
                $changes[] = "name from '{$oldName}' to '{$role->name}'";
            }

            $addedPermissions = array_diff($permissions, $oldPermissions);
            $removedPermissions = array_diff($oldPermissions, $permissions);

            if (count($addedPermissions) > 0) {
                $changes[] = count($addedPermissions) . ' permission(s) added';
            }
            if (count($removedPermissions) > 0) {
                $changes[] = count($removedPermissions) . ' permission(s) removed';
            }

            $description = 'Updated role: ' . $role->name;
            if (count($changes) > 0) {
                $description .= ' (Changed: ' . implode(', ', $changes) . ')';
            }

            if (count($changes) > 0) {
                activity()
                    ->causedBy(Auth::user())
                    ->performedOn($role)
                    ->withProperties([
                        'old' => ['name' => $oldName, 'permissions_count' => count($oldPermissions)],
                        'attributes' => ['name' => $role->name, 'permissions_count' => count($permissions)],
                        'added_permissions' => array_values($addedPermissions),
                        'removed_permissions' => array_values($removedPermissions)
                    ])
                    ->log($description);
            }

            notify()->success('Role updated successfully', 'Success');

            return redirect()->route('admin.roles.index', ['page' => $page]);

        } catch (\Throwable $e) {
            Log::error('Failed to update role', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->all(),
            ]);
            notify()->error('Role updated successfully', 'Success');
            return redirect()->route('admin.roles.index', ['page' => $page]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Role $role)
    {
        Gate::authorize('authentication.roles.delete');
        $page = request('page');
        try {
            $permissionCount = $role->permissions()->count();

            activity()
                ->causedBy(Auth::user())
                ->performedOn($role)
                ->withProperties([
                    'role_name' => $role->name,
                    'permissions_count' => $permissionCount
                ])
                ->log('Deleted role: ' . $role->name . ' (had ' . $permissionCount . ' permission(s))');

            $role->delete();
            notify()->success('Role deleted successfully', 'Success');
            return redirect()->route('admin.roles.index', ['page' => $page]);
        } catch (Throwable $e) {
            Log::error('Failed to delete role.', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            notify()->error('Something went wrong', 'Error');
            return redirect()->route('admin.roles.index', ['page' => $page]);
        }

    }
}
