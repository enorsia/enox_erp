<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TwoFactorAuthService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(
        protected TwoFactorAuthService $twoFactor
    ) {}

    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $throttleKey = strtolower($request->input('email')).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ]);
        }

        $rememberMe = $request->boolean('remember_me');

        if (! Auth::validate($request->only('email', 'password'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withInput($request->only('email', 'remember_me'))
                ->withErrors([
                    'email' => 'Invalid credentials',
                ]);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = User::query()->where('email', $request->input('email'))->firstOrFail();

        if (! $user->status) {
            return back()
                ->withInput($request->only('email', 'remember_me'))
                ->withErrors([
                    'email' => 'This account is inactive. Contact an administrator.',
                ]);
        }

        if ($this->twoFactor->requiresTwoFactor($user)) {
            $trusted = $this->twoFactor->trustedDeviceTokenFromRequest($request);
            if ($this->twoFactor->hasValidTrustedDevice($user, $trusted)) {
                return $this->finishLogin($request, $user, $rememberMe, false);
            }

            $request->session()->put(TwoFactorAuthService::SESSION_USER_KEY, $user->id);
            $request->session()->put(TwoFactorAuthService::SESSION_REMEMBER_KEY, $rememberMe);

            if (! $this->twoFactor->isEnrolled($user)) {
                if (! $user->two_factor_secret) {
                    $this->twoFactor->assignSecret($user, $this->twoFactor->generateSecret());
                }

                return redirect()->route('admin.two-factor.setup');
            }

            return redirect()->route('admin.two-factor.challenge');
        }

        return $this->finishLogin($request, $user, $rememberMe, false);
    }

    public function logout(Request $request): RedirectResponse
    {
        $userName = Auth::user()?->name;

        if (Auth::check()) {
            activity()
                ->causedBy(Auth::user())
                ->withProperties(['ip' => $request->ip()])
                ->log($userName.' logged out from IP: '.$request->ip());
        }

        Auth::logout();
        $this->twoFactor->clearSessionVerified();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }

    protected function finishLogin(Request $request, User $user, bool $remember, bool $fromTwoFactor): RedirectResponse
    {
        Auth::login($user, $remember);
        $request->session()->regenerate();
        $this->twoFactor->markSessionVerified();

        avaiablePermissions();

        activity()
            ->causedBy($user)
            ->withProperties([
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'email' => $user->email,
                'two_factor' => $fromTwoFactor || $this->twoFactor->requiresTwoFactor($user),
            ])
            ->log($user->name.' logged in successfully from IP: '.$request->ip());

        return redirect()->intended(route('admin.dashboard'));
    }
}
