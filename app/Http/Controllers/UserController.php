<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\CloudflareFileDeleteJob;
use App\Jobs\CloudflareFileUploadJob;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request)
    {
        Gate::authorize('authentication.users.index');
        $data['users'] = User::filter($request->all())
            ->with(['roles:id,name'])
            ->latest()
            ->paginate(30);

        $data['roles'] = Role::select('id', 'name')->orderBy('name')->get();

        $data['start'] = ($data['users']->currentPage() - 1) * $data['users']->perPage() + 1;

        return view('users.index', $data);
    }

    public function create()
    {
        Gate::authorize('authentication.users.create');
        $roles = Role::all();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        $request->validate(
            [
                'name' => 'required|string|max:255',
                'email'  => 'required|string|email|max:255|unique:users',
                'password' => 'required|confirmed|string|min:8',
                'avatar' => 'nullable|image',
                'role' => 'required',
            ],
        );

        $filename = null;
        $fileUrl = null;

        if ($request->hasFile('avatar')) {
            $file = $request->file('avatar');
            $filename = 'IMG_' . date('YmdHi') . '.' . $file->getClientOriginalExtension();
            $file->move(public_path('upload/user_images'), $filename);
            $fileUrl = 'upload/user_images/' . $filename;

            if (app()->environment('production')) {
                CloudflareFileUploadJob::dispatch($fileUrl);
            }
        }

        try {
            $user  = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'designation' => $request->designation,
                'avatar' => $filename,
                'password' => Hash::make($request->password),
                'status' => $request->filled('status'),
            ]);

            $user->assignRole($request->role);

            activity()
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->withProperties(['attributes' => $user->toArray()])
                ->log('Created new user account for ' . $user->name . ' (' . $user->email . ')');

            notify()->success("User created successfully.", "Success");
            return redirect()->route('admin.users.index');
        } catch (\Exception $e) {
            Log::error('User created failed: ' . $e->getMessage());
            notify()->error('Failed to created user', 'Error');
            return redirect()->route('admin.users.index');
        }
    }

    public function show($id)
    {
        Gate::authorize('authentication.users.show');
        $user = User::with([
            'roles:id,name'
        ])
            ->findOrFail($id);

        // dd($user);
        $roleName = $user->getRoleNames()->first();

        return view('users.show', compact('user', 'roleName'));
    }

    public function edit(User $user)
    {
        Gate::authorize('authentication.users.edit');
        $roles = Role::all();

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email'  => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|confirmed|string|min:8',
            'avatar' => 'nullable|image',
            'role' => 'required'
        ]);

        try {
            // Capture old values before update
            $oldValues = [
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
                'status' => $user->status,
            ];

            $filePath = public_path('upload/user_images/' . $user->avatar);
            $fileUrl = null;
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'designation' => $request->designation,
                'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
                'status' => $request->filled('status'),
            ]);
            $user->syncRoles([$request->role]);

            if ($request->hasFile('avatar')) {
                if ($user->avatar) {
                    if (file_exists($filePath)) {
                        unlink($filePath);
                    }

                    if (app()->environment('production')) {
                        $fileUrl = 'upload/user_images/' . $user->avatar;
                        CloudflareFileDeleteJob::dispatch(basename($fileUrl));
                    }
                }

                $file = $request->file('avatar');
                $filename = 'IMG_' . date('YmdHi') . '.' . $file->getClientOriginalExtension();
                $file->move(public_path('upload/user_images'), $filename);
                $fileUrl = 'upload/user_images/' . $filename;

                if (app()->environment('production')) {
                    CloudflareFileUploadJob::dispatch($fileUrl);
                }

                $user->update([
                    'avatar' => $filename ?? null
                ]);
            }

            // Capture new values after update
            $user->refresh();
            $newValues = [
                'name' => $user->name,
                'email' => $user->email,
                'designation' => $user->designation,
                'status' => $user->status,
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

            // Build readable description
            $changedFields = array_keys($changes);
            $description = 'Updated user profile for ' . $user->name;
            if (count($changedFields) > 0) {
                $description .= ' (Changed: ' . implode(', ', array_map(fn($f) => ucfirst($f), $changedFields)) . ')';
            }

            // Log only if there are actual changes
            if (count($changes) > 0 || $request->filled('password') || $request->hasFile('avatar')) {
                $properties = ['old' => $oldValues, 'attributes' => $newValues];
                if ($request->filled('password')) {
                    $description .= ' (Password updated)';
                }
                if ($request->hasFile('avatar')) {
                    $description .= ' (Avatar updated)';
                }

                activity()
                    ->causedBy(auth()->user())
                    ->performedOn($user)
                    ->withProperties($properties)
                    ->log($description);
            }


            notify()->success("User updated successfully.", "Success");
            return redirect()->route('admin.users.index');
        } catch (\Exception $e) {
            notify()->error('Failed to updated user', 'Error');
            Log::error('User updated failed: ' . $e->getMessage());
            return redirect()->route('admin.users.index');
        }
    }

    public function destroy(User $user)
    {
         Gate::authorize('authentication.users.delete');

        try {
            if (isset($user) && $user->id == 1) {
                notify()->error('User can not delete.', 'Error');
                return redirect()->route('admin.users.index');
            }

            if ($user->avatar) {
                $filePath = public_path('upload/user_images/' . $user->avatar);

                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                if (app()->environment('production')) {
                    $fileUrl = 'upload/user_images/' . $user->avatar;
                    CloudflareFileDeleteJob::dispatch(basename($fileUrl));
                }
            }

            activity()
                ->causedBy(auth()->user())
                ->performedOn($user)
                ->withProperties(['deleted_user' => $user->name, 'email' => $user->email])
                ->log('Deleted user account: ' . $user->name . ' (' . $user->email . ')');

            $user->delete();

            notify()->success("User deleted successfully.", "Deleted");
            return redirect()->back();
        } catch (\Exception $e) {
            Log::error('User delete failed: ' . $e->getMessage());
            notify()->error('Failed to deleting user', 'Error');
            return redirect()->back();
        }
    }
}
