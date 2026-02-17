<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Helpers\Helper;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('auth.login');

    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        $rememberMe = $request->has('remember_me') ? true : false;
        if (Auth::attempt($request->only('email', 'password'), $rememberMe)) {
            $request->session()->regenerate();
            avaiablePermissions();

            activity()
                ->causedBy(Auth::user())
                ->withProperties([
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'email' => Auth::user()->email
                ])
                ->log(Auth::user()->name . ' logged in successfully from IP: ' . $request->ip());

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials',
        ]);
    }

    public function logout(Request $request)
    {
        $userName = Auth::user()->name;

        activity()
            ->causedBy(Auth::user())
            ->withProperties(['ip' => $request->ip()])
            ->log($userName . ' logged out from IP: ' . $request->ip());

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
