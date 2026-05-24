<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function index(): View
    {
        // Gate::authorize('general.dashboard.index');
        return view('dashboard.index');
    }
}
