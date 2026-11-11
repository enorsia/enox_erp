<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\CloudflareFileDeleteJob;
use App\Jobs\CloudflareFileUploadJob;
use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactor
    ) {}

    public function index(Request $request): View
    {
        Gate::authorize('authentication.users.index');
        $data['users'] = User::visibleToAdmins()
            ->filter($request->all())
            ->with(['roles:id,name'])
            ->latest()
            ->paginate(30);

        $data['roles'] = Role::select('id', 'name')->orderBy('name')->get();

        $data['start'] = ($data['users']->currentPage() - 1) * $data['users']->perPage() + 1;

        return view('users.index', $data);
    }

    public function create(): View
    {
        Gate::authorize('authentication.users.create');
        $roles = Role::all();

        return view('users.create', compact('roles'));
    }

    public function store(Request $request): RedirectResponse
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
            $enableTwoFactor = $request->boolean('two_factor_enabled');

            $this->logUserTwoFactor('create_started', null, $request, [
                'email' => $request->input('email'),
                'enable_requested' => $enableTwoFactor,
            ]);

            $user  = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'designation' => $request->designation,
                'avatar' => $filename,
                'password' => Hash::make($request->password),
                'status' => $request->boolean('status'),
                'two_factor_enabled' => $enableTwoFactor,
            ]);

            if ($enableTwoFactor) {
                $this->twoFactor->assignSecret($user, $this->twoFactor->generateSecret());
            }

            $this->logUserTwoFactor('create_completed', $user->fresh(), $request, [
                'secret_assigned' => $enableTwoFactor,
                'has_two_factor_secret' => $user->two_factor_secret !== null,
            ]);

            $user->assignRole($request->role);

            activity()
                ->causedBy(Auth::user())
                ->performedOn($user)
                ->withProperties(['attributes' => $user->toArray()])
                ->log('Created new user account for ' . $user->name . ' (' . $user->email . ')');

            notify()->success("User created successfully.", "Success");

            if ($enableTwoFactor) {
                return redirect()->route('admin.users.edit', $user);
            }

            return redirect()->route('admin.users.index');
        } catch (\Exception $e) {
            $this->logUserTwoFactor('create_failed', null, $request, [
                'email' => $request->input('email'),
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
            Log::error('User created failed: ' . $e->getMessage());
            notify()->error('Failed to created user', 'Error');
            return redirect()->route('admin.users.index');
        }
    }

    public function show(int $id): View
    {
        Gate::authorize('authentication.users.show');
        $user = User::visibleToAdmins()
            ->with([
            'roles:id,name'
        ])
            ->findOrFail($id);

        $roleName = $user->getRoleNames()->first();

        return view('users.show', compact('user', 'roleName'));
    }

    public function edit(User $user): View
    {
        Gate::authorize('authentication.users.edit');
        $roles = Role::all();
        $user->loadCount('trustedDevices');

        $this->logUserTwoFactor('edit_page_view', $user);

        return view('users.edit', compact('user', 'roles'));
    }

    public function update(Request $request, User $user): RedirectResponse
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
            $wasTwoFactorEnabled = (bool) $user->two_factor_enabled;
            $enableTwoFactor = $request->boolean('two_factor_enabled');

            $this->logUserTwoFactor('update_started', $user, $request, [
                'db_two_factor_enabled_before' => $wasTwoFactorEnabled,
                'enable_requested' => $enableTwoFactor,
            ]);

            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'designation' => $request->designation,
                'password' => $request->filled('password') ? Hash::make($request->password) : $user->password,
                'status' => $request->boolean('status'),
                'two_factor_enabled' => $enableTwoFactor,
            ]);

            $secretAssigned = false;
            $twoFactorCleared = false;

            if ($enableTwoFactor) {
                $user->refresh();
                if (! $user->two_factor_secret) {
                    $this->twoFactor->assignSecret($user, $this->twoFactor->generateSecret());
                    $secretAssigned = true;
                }
            }

            if (! $enableTwoFactor && $wasTwoFactorEnabled) {
                $user->forceFill([
                    'two_factor_secret' => null,
                    'two_factor_confirmed_at' => null,
                ])->save();
                $user->trustedDevices()->delete();
                $twoFactorCleared = true;
            }

            $this->logUserTwoFactor('update_2fa_processed', $user->fresh(), $request, [
                'enable_requested' => $enableTwoFactor,
                'db_two_factor_enabled_before' => $wasTwoFactorEnabled,
                'secret_assigned' => $secretAssigned,
                'two_factor_cleared' => $twoFactorCleared,
                'status_parsed' => $request->boolean('status'),
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
                    ->causedBy(Auth::user())
                    ->performedOn($user)
                    ->withProperties($properties)
                    ->log($description);
            }


            $user->refresh();

            $this->logUserTwoFactor('update_completed', $user, $request, [
                'show_qr_on_edit' => (bool) $user->two_factor_enabled && $user->two_factor_secret !== null,
            ]);

            notify()->success("User updated successfully.", "Success");

            return redirect()->route('admin.users.edit', $user);
        } catch (\Exception $e) {
            notify()->error('Failed to updated user', 'Error');
            $this->logUserTwoFactor('update_failed', $user, $request, [
                'error' => $e->getMessage(),
                'exception_class' => $e::class,
            ]);
            Log::error('[user.2fa] update_exception', [
                'user_id' => $user->id,
                'message' => $e->getMessage(),
                'exception' => $e,
            ]);

            return redirect()
                ->route('admin.users.edit', $user)
                ->withInput($request->except(['password', 'password_confirmation', 'avatar']));
        }
    }

    public function resetTwoFactorDevices(User $user): RedirectResponse
    {
        Gate::authorize('authentication.users.edit');

        $this->logUserTwoFactor('reset_trusted_devices_requested', $user);

        $this->twoFactor->revokeAllTrustedDevices($user);

        $this->logUserTwoFactor('reset_trusted_devices_done', $user->fresh());

        activity()
            ->causedBy(Auth::user())
            ->performedOn($user)
            ->log('Reset trusted devices / 2FA remember-for-device for '.$user->name);

        notify()->success('Trusted devices cleared. User must complete 2FA again on every device.', 'Success');

        return redirect()->route('admin.users.edit', $user);
    }

    public function destroy(User $user): RedirectResponse
    {
         Gate::authorize('authentication.users.delete');

        try {
            if ($user->isSystem() || $user->id == 1) {
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
                ->causedBy(Auth::user())
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

    /**
     * Structured diagnostic logging for admin user 2FA (grep server logs for "[user.2fa]").
     */
    protected function logUserTwoFactor(string $event, ?User $user, ?Request $request = null, array $extra = []): void
    {
        $context = [
            'event' => $event,
            'admin_user_id' => Auth::id(),
        ];

        if ($user !== null) {
            $trustedCount = $user->trusted_devices_count ?? null;
            if ($trustedCount === null && $user->relationLoaded('trustedDevices')) {
                $trustedCount = $user->trustedDevices->count();
            }

            $context = array_merge($context, [
                'target_user_id' => $user->id,
                'target_email' => $user->email,
                'two_factor_enabled' => (bool) $user->two_factor_enabled,
                'has_two_factor_secret' => $user->two_factor_secret !== null,
                'two_factor_confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
                'two_factor_enrolled' => $this->twoFactor->isEnrolled($user),
                'trusted_devices_count' => $trustedCount,
            ]);
        }

        $context = array_merge($context, $extra);

        if ($request !== null) {
            $rawTwoFactor = $request->input('two_factor_enabled');
            $context['request_method'] = $request->method();
            $context['request_path'] = $request->path();
            $context['request_has_two_factor_field'] = $request->has('two_factor_enabled');
            $context['request_two_factor_value'] = $rawTwoFactor;
            $context['request_two_factor_raw_type'] = is_array($rawTwoFactor) ? 'array' : get_debug_type($rawTwoFactor);
            $context['request_two_factor_parsed'] = $request->boolean('two_factor_enabled');
            $context['request_has_status_field'] = $request->has('status');
            $context['request_status_value'] = $request->input('status');
            $context['request_status_parsed'] = $request->boolean('status');
            $context['request_form_keys'] = array_keys($request->except([
                'password', 'password_confirmation', '_token', '_method', 'avatar',
            ]));
        }

        Log::info('[user.2fa] '.$event, $context);
    }
}
