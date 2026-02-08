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
            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors([
            'email' => 'Invalid credentials',
        ]);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
