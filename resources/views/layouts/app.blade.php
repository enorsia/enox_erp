<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }} | @yield('title', 'Admin')</title>

    {{-- Instant dark-mode: runs synchronously before any paint to prevent flash --}}
    <script>
        (function () {
            var saved = localStorage.getItem('enorsia-dark');
            var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            var isDark = saved !== null ? saved === 'true' : prefersDark;
            if (isDark) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    <link rel="shortcut icon" href="{{ asset('assets/images/favicon.ico') }}">

    <!-- Vite CSS and JS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('css')
</head>

<body class="bg-slate-100 dark:bg-slate-900 text-slate-800 dark:text-slate-200 transition-colors duration-200">
<div class="flex h-screen overflow-hidden">

    @include('layouts.sidebar.index')

    <!-- ═══════ MAIN AREA ═══════ -->
    <div class="flex-1 flex flex-col overflow-hidden min-w-0">

        @include('layouts.header.topbar')

        <!-- ── PAGE CONTENT (scrollable) ── -->
        <main class="flex-1 overflow-y-auto">
            @yield('content')
        </main>
    </div>
</div>

<!-- Mobile sidebar backdrop -->
<div id="sidebarBackdrop" onclick="closeSidebar()" class="hidden fixed inset-0 bg-black/40 z-[200] lg:hidden"></div>

@include('layouts.footer.index')
@stack('js')
@include('master.lara-izitoast')
</body>
</html>