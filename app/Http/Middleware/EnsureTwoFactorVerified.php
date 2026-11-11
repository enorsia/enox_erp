<?php

namespace App\Http\Middleware;

use App\Services\TwoFactorAuthService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureTwoFactorVerified
{
    public function __construct(
        protected TwoFactorAuthService $twoFactor
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::user();

        if ($user && $this->twoFactor->requiresTwoFactor($user) && ! $this->twoFactor->isSessionVerified()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'Please sign in again and complete two-factor authentication.']);
        }

        return $next($request);
    }
}
