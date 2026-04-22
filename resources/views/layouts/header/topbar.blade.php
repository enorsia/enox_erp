<!-- ── TOPBAR ── -->
<header class="h-14 bg-white dark:bg-slate-800 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between px-5 flex-shrink-0 gap-3">

    <!-- Left: hamburger + greeting -->
    <div class="flex items-center gap-3 min-w-0">
        <!-- Mobile hamburger -->
        <button onclick="toggleSidebar()" class="lg:hidden p-1.5 rounded-lg text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors" aria-label="Toggle sidebar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M4 6h16M4 12h16M4 18h16"/>
            </svg>
        </button>
        <h4 class="text-sm font-semibold text-slate-700 dark:text-slate-200 uppercase truncate">Hi, {{ Auth::user()->name }}</h4>
    </div>

    <!-- Right: dark mode toggle + user dropdown -->
    <div class="flex items-center gap-2 flex-shrink-0">

        <!-- Dark mode toggle -->
        <button onclick="toggleDark()"
                class="p-2 rounded-lg text-slate-500 dark:text-slate-400 hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
                title="Toggle dark mode"
                aria-label="Toggle dark mode">
            {{-- Sun icon (shown in dark mode) --}}
            <svg id="iconSun" class="w-4 h-4 hidden" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <circle cx="12" cy="12" r="5"/>
                <path stroke-linecap="round" d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
            </svg>
            {{-- Moon icon (shown in light mode) --}}
            <svg id="iconMoon" class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                <path stroke-linecap="round" d="M21 12.79A9 9 0 1111.21 3 7 7 0 0021 12.79z"/>
            </svg>
        </button>

        <!-- User Dropdown -->
        <div class="relative" x-data="{ open: false }">
            <button @click="open = !open" @click.outside="open = false"
                    class="flex items-center gap-2 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors focus:outline-none"
                    id="user-dropdown-btn">
                <img class="w-8 h-8 rounded-full object-cover flex-shrink-0"
                     src="{{ auth()->user()->avatar ? cloudflareImage(auth()->user()->avatar, 200) : cloudflareImage('eca4fbfc-baba-4ac2-0966-e8a13d097700', 200) }}"
                     alt="avatar">
                <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                </svg>
            </button>

            <!-- Dropdown Menu -->
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-1"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-100"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-1"
                 class="absolute right-0 mt-2 w-52 bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl shadow-lg shadow-slate-200/50 dark:shadow-slate-900/50 py-1.5 z-50"
                 style="display: none;">

                <!-- Header -->
                <div class="px-4 py-2 border-b border-slate-100 dark:border-slate-700">
                    <p class="text-xs font-semibold text-slate-800 dark:text-slate-200">Welcome {{ Str::limit(Auth::user()->name, 10) }}!</p>
                    <p class="text-[11px] text-slate-400 dark:text-slate-500 truncate">{{ Auth::user()->email }}</p>
                </div>

                <!-- Profile -->
                <a href="{{ route('admin.profile') }}"
                   class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <circle cx="12" cy="8" r="4"/><path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                    </svg>
                    Profile
                </a>

                <!-- Password Change -->
                <a href="{{ route('admin.change.password') }}"
                   class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-slate-600 dark:text-slate-300 hover:bg-slate-50 dark:hover:bg-slate-700 transition-colors">
                    <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/>
                    </svg>
                    Password Change
                </a>

                <!-- Divider -->
                <div class="my-1.5 border-t border-slate-100 dark:border-slate-700"></div>

                <!-- Logout -->
                <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                   class="flex items-center gap-2.5 px-4 py-2 text-[13px] text-red-500 hover:bg-red-50 dark:hover:bg-red-900/20 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                        <path stroke-linecap="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3-3l3-3m0 0l-3-3m3 3H9"/>
                    </svg>
                    Logout
                </a>

                <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="hidden">
                    @csrf
                </form>
            </div>
        </div>
    </div>
</header>

