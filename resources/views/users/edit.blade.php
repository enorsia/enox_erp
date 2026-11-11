@extends('layouts.app')

@section('title', 'Edit Admin User')

@section('content')
    <div id="user-page-content"></div>
    <div class="max-w-5xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Edit Admin User</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update details for {{ $user->name }}</p>
            </div>
        </div>

        <form method="POST" action="{{ route('admin.users.update', $user->id) }}" enctype="multipart/form-data" id="EditValidateForm">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 lg:grid-cols-[1fr_300px] gap-5">

                <!-- LEFT COLUMN -->
                <div class="space-y-5">

                    <!-- ── User Information ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/></svg>
                            User Information
                        </div>
                        <p class="section-desc">Update the admin user account details.</p>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <!-- Name -->
                            <div class="sm:col-span-2">
                                <label class="f-label">Name <span class="f-required">*</span></label>
                                <input type="text" name="name" class="f-input @error('name') border-red-400 @enderror" value="{{ $user->name ?? old('name') }}" required />
                                @error('name') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Email -->
                            <div>
                                <label class="f-label">Email <span class="f-required">*</span></label>
                                <input type="email" name="email" class="f-input @error('email') border-red-400 @enderror" value="{{ $user->email ?? old('email') }}" required />
                                @error('email') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Designation -->
                            <div>
                                <label class="f-label">Designation</label>
                                <input type="text" name="designation" class="f-input" value="{{ $user->designation ?? old('designation') }}" />
                                @error('designation') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Password -->
                            <div>
                                <label class="f-label">Password</label>
                                <input type="password" name="password" id="password" class="f-input @error('password') border-red-400 @enderror" placeholder="Leave blank to keep current" />
                                <p class="f-hint">Only fill if you want to change the password.</p>
                                @error('password') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Confirm Password -->
                            <div>
                                <label class="f-label">Confirm Password</label>
                                <input type="password" name="password_confirmation" class="f-input" placeholder="Re-enter new password" />
                            </div>
                        </div>
                    </div>

                    <!-- ── Avatar ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                            Avatar
                        </div>
                        <p class="section-desc">Update profile picture.</p>

                        <input type="file" name="avatar" class="f-input image-input" accept="image/*" />
                        <img class="image-preview mt-3 w-[120px] rounded-lg"
                             @if($user->avatar) style="display: block;" @else style="display:none" @endif
                             src="{{ $user->avatar ? cloudflareImage($user->avatar, 150) : '' }}" alt="Preview">
                        @error('avatar') <p class="f-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- RIGHT COLUMN -->
                <div class="space-y-5">

                    <!-- ── Role & Status ── -->
                    <div class="section-card">
                        <div class="section-title">
                            <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/></svg>
                            Role & Settings
                        </div>

                        <div class="space-y-4">
                            <!-- Role -->
                            <div>
                                <label class="f-label">Role <span class="f-required">*</span></label>
                                <select name="role" class="tom-select f-input @error('role') border-red-400 @enderror" data-placeholder="Select Role" required>
                                    <option value="">Select Role</option>
                                    @foreach ($roles as $role)
                                        <option value="{{ $role->name }}" {{ $user?->roles?->first()?->name == $role->name ? 'selected' : '' }}>
                                            {{ $role->name }}
                                        </option>
                                    @endforeach
                                </select>
                                @error('role') <p class="f-error">{{ $message }}</p> @enderror
                            </div>

                            <!-- Status -->
                            <div>
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ $user->status ? 'on' : '' }}" id="statusToggle" onclick="toggleSwitch('statusToggle')">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium" onclick="toggleSwitch('statusToggle')">Active status</span>
                                    <input type="hidden" name="status" id="statusValue" value="{{ $user->status ? '1' : '0' }}">
                                </label>
                            </div>

                            <!-- Two-factor authentication -->
                            <div class="pt-3 border-t border-slate-200 dark:border-slate-700">
                                <label class="flex items-center gap-3 cursor-pointer">
                                    <div class="toggle-track {{ $user->two_factor_enabled ? 'on' : '' }}" id="twoFactorToggle" onclick="toggleSwitch('twoFactorToggle')">
                                        <div class="toggle-thumb"></div>
                                    </div>
                                    <span class="text-sm text-slate-600 dark:text-slate-300 font-medium" onclick="toggleSwitch('twoFactorToggle')">Require Google Authenticator (2FA)</span>
                                    <input type="hidden" name="two_factor_enabled" id="twoFactorValue" value="{{ $user->two_factor_enabled ? '1' : '0' }}">
                                </label>
                                <p class="f-hint mt-2">When enabled, this user must enter a 6-digit code at login unless this browser is trusted.</p>

                                @if ($user->two_factor_enabled)
                                    <ul class="mt-2 space-y-1 text-xs text-slate-500 dark:text-slate-400">
                                        @if ($user->two_factor_confirmed_at)
                                            <li>Authenticator: <span class="text-emerald-600 dark:text-emerald-400 font-medium">Active</span> (since {{ $user->two_factor_confirmed_at->format('M j, Y') }})</li>
                                        @else
                                            <li>Authenticator: <span class="text-amber-600 dark:text-amber-400 font-medium">Pending</span> — user must confirm on first login</li>
                                        @endif
                                        <li>Trusted browsers: <span class="font-medium text-slate-700 dark:text-slate-200">{{ $user->trusted_devices_count }}</span></li>
                                    </ul>
                                @endif

                                @if ($user->two_factor_enabled && $user->two_factor_secret)
                                    @php
                                        $twoFactor = app(\App\Services\TwoFactorAuthService::class);
                                        $otpUrl = $twoFactor->provisioningUri($user);
                                    @endphp
                                    <div class="mt-3 p-3 rounded-lg border border-slate-200 dark:border-slate-600 bg-slate-50 dark:bg-slate-900/40">
                                        <p class="text-xs font-semibold text-slate-600 dark:text-slate-300 mb-2">Setup QR (Google Authenticator)</p>
                                        <img src="{{ $twoFactor->qrCodeDataUri($otpUrl) }}" width="180" height="180" alt="2FA QR code" class="rounded-md border border-slate-200 dark:border-slate-600">
                                        <p class="f-hint mt-2">Share this QR if the user has not finished setup yet.</p>
                                    </div>
                                @endif

                                @if ($user->two_factor_enabled)
                                    <button type="submit"
                                        form="resetTrustedDevicesForm"
                                        class="mt-3 w-full px-3 py-2 text-xs font-semibold rounded-lg border border-amber-300 text-amber-800 bg-amber-50 hover:bg-amber-100 dark:border-amber-700 dark:text-amber-200 dark:bg-amber-900/20"
                                        onclick="return confirm('Clear all trusted devices? This user must enter 2FA on every browser again.');">
                                        Force 2FA on next login (reset trusted devices)
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── STICKY FOOTER ── -->
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-5xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.users.index') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit" class="submit-btn px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M5 13l4 4L19 7"/></svg>
                            Update User
                        </button>
                    </div>
                </div>
            </div>
        </form>

        @if ($user->two_factor_enabled)
            <form id="resetTrustedDevicesForm" method="POST" action="{{ route('admin.users.reset-two-factor-devices', $user) }}" class="hidden">
                @csrf
            </form>
        @endif
    </div>
@endsection