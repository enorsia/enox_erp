<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class TwoFactorAuthController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactor
    ) {}

    public function showChallenge(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get(TwoFactorAuthService::SESSION_USER_KEY);
        if (! $userId) {
            return redirect()->route('admin.login');
        }

        $user = User::query()->find($userId);
        if (! $user || ! $this->twoFactor->requiresTwoFactor($user)) {
            $request->session()->forget([
                TwoFactorAuthService::SESSION_USER_KEY,
                TwoFactorAuthService::SESSION_REMEMBER_KEY,
            ]);

            return redirect()->route('admin.login');
        }

        if (! $this->twoFactor->isEnrolled($user)) {
            return redirect()->route('admin.two-factor.setup');
        }

        return view('auth.two-factor-challenge', ['email' => $user->email]);
    }

    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'remember_device' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->session()->get(TwoFactorAuthService::SESSION_USER_KEY);
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user) {
            return redirect()->route('admin.login');
        }

        $key = 'two-factor:'.$user->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Please try again shortly.']);
        }

        if (! $this->twoFactor->verifyCode($user, $request->input('code'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        RateLimiter::clear($key);

        return $this->completeLogin($request, $user, $request->boolean('remember_device'));
    }

    public function showSetup(Request $request): View|RedirectResponse
    {
        $userId = $request->session()->get(TwoFactorAuthService::SESSION_USER_KEY);
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user || ! $this->twoFactor->requiresTwoFactor($user)) {
            return redirect()->route('admin.login');
        }

        if ($this->twoFactor->isEnrolled($user)) {
            return redirect()->route('admin.two-factor.challenge');
        }

        if (! $user->two_factor_secret) {
            $this->twoFactor->assignSecret($user, $this->twoFactor->generateSecret());
            $user->refresh();
        }

        $secret = decrypt($user->two_factor_secret);
        $otpUrl = $this->twoFactor->provisioningUri($user, $secret);

        return view('auth.two-factor-setup', [
            'email' => $user->email,
            'secret' => $secret,
            'otpUrl' => $otpUrl,
        ]);
    }

    public function confirmSetup(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
            'remember_device' => ['sometimes', 'boolean'],
        ]);

        $userId = $request->session()->get(TwoFactorAuthService::SESSION_USER_KEY);
        $user = $userId ? User::query()->find($userId) : null;

        if (! $user) {
            return redirect()->route('admin.login');
        }

        $key = 'two-factor-setup:'.$user->id.':'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['code' => 'Too many attempts. Please try again shortly.']);
        }

        if (! $this->twoFactor->verifyCode($user, $request->input('code'))) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['code' => 'Invalid authentication code.']);
        }

        RateLimiter::clear($key);
        $this->twoFactor->markConfirmed($user);

        return $this->completeLogin($request, $user, $request->boolean('remember_device'));
    }

    protected function completeLogin(Request $request, User $user, bool $rememberDevice): RedirectResponse
    {
        $remember = (bool) $request->session()->get(TwoFactorAuthService::SESSION_REMEMBER_KEY, false);

        $request->session()->forget([
            TwoFactorAuthService::SESSION_USER_KEY,
            TwoFactorAuthService::SESSION_REMEMBER_KEY,
        ]);

        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->twoFactor->markSessionVerified();

        if ($rememberDevice) {
            $this->twoFactor->rememberDevice($user, $request);
        }

        avaiablePermissions();

        activity()
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'email' => $user->email,
                'two_factor' => true,
            ])
            ->log($user->name.' logged in successfully with 2FA from IP: '.$request->ip());

        return redirect()->intended(route('admin.dashboard'));
    }
}
