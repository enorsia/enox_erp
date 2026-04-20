@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <div class="max-w-2xl mx-auto px-5 py-6 pb-28">

        <!-- PAGE HEADER -->
        <div class="flex items-center justify-between mb-6 flex-wrap gap-3">
            <div>
                <h1 class="text-xl font-semibold text-slate-800 dark:text-slate-100">Change Password</h1>
                <p class="text-sm text-slate-400 dark:text-slate-500 mt-0.5">Update your account password</p>
            </div>
        </div>

        <form class="validate-form" method="POST" action="{{ route('admin.password.update.post') }}" id="changePasswordForm">
            @csrf

            <div class="section-card">
                <div class="section-title">
                    <svg class="w-4 h-4 text-accent-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                    </svg>
                    Password Settings
                </div>
                <p class="section-desc">Choose a strong password to keep your account secure.</p>

                <div class="space-y-4">
                    <!-- Current Password -->
                    <div>
                        <label for="current_password" class="f-label">Current Password <span class="f-required">*</span></label>
                        <input id="current_password" type="password" name="current_password"
                               class="f-input @error('current_password') border-red-400 @enderror"
                               placeholder="Enter current password" required />
                        @error('current_password')
                            <p class="f-error" style="display:block">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- New Password -->
                    <div>
                        <label for="password" class="f-label">New Password <span class="f-required">*</span></label>
                        <input id="password" type="password" name="password"
                               class="f-input @error('password') border-red-400 @enderror"
                               placeholder="Min 8 characters" required />
                        @error('password')
                            <p class="f-error" style="display:block">{{ $message }}</p>
                        @enderror
                    </div>

                    <!-- Confirm Password -->
                    <div>
                        <label for="confirm_password" class="f-label">Confirm New Password <span class="f-required">*</span></label>
                        <input id="confirm_password" type="password" name="password_confirmation"
                               class="f-input"
                               placeholder="Re-enter new password" required />
                    </div>
                </div>
            </div>

            <!-- ── STICKY FOOTER ── -->
            <div class="sticky-footer mt-5 -mx-5 rounded-none">
                <div class="max-w-2xl mx-auto flex items-center justify-between gap-3 flex-wrap">
                    <div class="flex items-center gap-2 text-xs text-slate-400 dark:text-slate-500">
                        <svg class="w-3.5 h-3.5 text-amber-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                        Fields marked <span class="text-red-400 mx-1">*</span> are required
                    </div>
                    <div class="flex gap-2.5">
                        <a href="{{ route('admin.profile') }}"
                           class="px-4 py-2.5 text-sm border border-slate-200 dark:border-slate-600 rounded-xl bg-white dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors font-medium">
                            Cancel
                        </a>
                        <button type="submit"
                                class="px-5 py-2.5 text-sm rounded-xl bg-accent-400 hover:bg-accent-600 text-white font-semibold transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            Update Password
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
@endsection

