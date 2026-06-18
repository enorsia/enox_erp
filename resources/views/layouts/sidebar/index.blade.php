<!-- ═══════ SIDEBAR ═══════ -->
<aside id="sidebar"
       class="w-[230px] min-w-[230px] bg-[#0c1521] flex flex-col overflow-y-auto sidebar-scroll z-50 lg:relative lg:translate-x-0">

    <!-- Logo -->
    <div class="px-[18px] py-5 border-b border-[rgba(255,255,255,0.07)] flex-shrink-0">
        <a href="{{ route('admin.dashboard') }}" class="block">
            <div class="text-white font-semibold tracking-[2.5px] text-[15px]">{{ config('app.name') }}</div>
            <div class="text-[10px] tracking-[1.2px] text-white/30 mt-0.5">
                Know Your Sales
            </div>
        </a>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-2">

        {{-- Access --}}
        @canany(Cache::get('permissions.available', [])['prefix']['authentication_'] ?? [])
            <div class="pt-4 pb-1">
                <p class="text-[9px] tracking-[1.8px] uppercase text-white/30 font-semibold px-[18px] pb-2">Access</p>

                @can('authentication.users.index')
                    <a href="{{ route('admin.users.index') }}"
                       class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/users*') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <circle cx="12" cy="8" r="4"/>
                            <path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7"/>
                        </svg>
                        Admin Users
                    </a>
                @endcan

                @can('authentication.roles.index')
                    <a href="{{ route('admin.roles.index') }}"
                       class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/roles*') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z"/>
                        </svg>
                        Permissions
                    </a>
                @endcan

                @can('authentication.activity_logs.index')
                    <a href="{{ route('admin.activity-logs.index') }}"
                       class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/activity-logs*') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        Activities
                    </a>
                @endcan
            </div>
        @endcanany

        {{-- General --}}
        @canany(Cache::get('permissions.available', [])['prefix']['general_'] ?? [])
            <div class="pt-4">
                <p class="text-[9px] tracking-[1.8px] uppercase text-white/30 font-semibold px-[18px] pb-2">Main</p>

                @can('general.dashboard.index')
                    <a href="{{ route('admin.dashboard') }}"
                       class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::routeIs('admin.dashboard') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">

                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18V3H3zm2 2h14v14H5V5z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9h6v6H9z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v6M15 3v6M3 9h6M15 9h6"/>
                        </svg>
                        Dashboard
                    </a>
                @endcan

                @canany([
                    ...array_keys(Cache::get('permissions.available', [])['grouped']['general_chart'] ?? []),
                    ...array_keys(Cache::get('permissions.available', [])['grouped']['general_fabrication'] ?? []),
                    ...array_keys(Cache::get('permissions.available', [])['grouped']['general_expense'] ?? []),
                    ...array_keys(Cache::get('permissions.available', [])['grouped']['general_forecasting'] ?? []),
                    ...array_keys(Cache::get('permissions.available', [])['grouped']['general_discounts'] ?? []),
                    'settings.platforms.index'
                ])
                    <!-- Selling Chart Dropdown -->
                    <div x-data="{ open: {{ Request::is('admin/selling-chart/*') || Request::is('admin/platforms*') ? 'true' : 'false' }} }">
                        <button @click="open = !open"
                                class="w-full nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/selling-chart/*') || Request::is('admin/platforms*') ? 'text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                            <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor"
                                 stroke-width="1.5" viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                      d="M20 7H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1zM9 11h6M9 15h4"/>
                            </svg>
                            <span class="flex-1 text-left">Selling Chart</span>
                            <svg class="w-3 h-3 ml-auto opacity-40 transition-transform duration-200"
                                 :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"
                                 viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </button>

                        <!-- Sub-menu -->
                        <div x-show="open" x-collapse>
                            <div class="ml-[18px] pl-4 border-l border-white/10 py-1 space-y-0.5">
                                @can('general.chart.index')
                                    <a href="{{ route('admin.selling_chart.index') }}"
                                       class="block py-1.5 px-3 text-[12px] rounded-md {{ Request::is('admin/selling-chart/manage*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }} transition-colors">
                                        Chart
                                    </a>
                                @endcan

                                @can('general.forecasting.index')
                                    <a href="{{ route('admin.selling_chart.forecasting') }}"
                                       class="block py-1.5 px-3 text-[12px] rounded-md {{ Request::is('admin/selling-chart/forecasting*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }} transition-colors">
                                        Forecasting
                                    </a>
                                @endcan

                                @can('general.discounts.index')
                                    <a href="{{ route('admin.selling_chart.discounts') }}"
                                       class="block py-1.5 px-3 text-[12px] rounded-md {{ Request::is('admin/selling-chart/discounts*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }} transition-colors">
                                        Discounts
                                    </a>
                                @endcan

                                @can('general.fabrication.index')
                                    <a href="{{ route('admin.selling_chart.fabrication.index') }}"
                                       class="block py-1.5 px-3 text-[12px] rounded-md {{ Request::is('admin/selling-chart/fabrication*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }} transition-colors">
                                        Fabrication
                                    </a>
                                @endcan

                                @can('general.expense.index')
                                    <a href="{{ route('admin.selling_chart.expense.index') }}"
                                       class="block py-1.5 px-3 text-[12px] rounded-md {{ Request::is('admin/selling-chart/expense*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }} transition-colors">
                                        Expense
                                    </a>
                                @endcan

                                @can('settings.platforms.index')
                                    <a href="{{ route('admin.platforms.index') }}"
                                       class="block py-1.5 px-3 text-[12px] rounded-md {{ Request::is('admin/platforms*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }} transition-colors">
                                        Platforms
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany
            </div>
        @endcanany

        {{-- Reports --}}
        @canany(Cache::get('permissions.available', [])['prefix']['general_'] ?? [])
            <div class="pb-1">
                <div x-data="{ open: {{ Request::is('admin/sales-spends/*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                            class="w-full nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/sales-spends/*') ? 'text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor"
                             stroke-width="1.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                  d="M20 7H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1zM9 11h6M9 15h4"/>
                        </svg>
                        <span class="flex-1 text-left">Sale & Spend</span>
                        <svg class="w-3 h-3 ml-auto opacity-40 transition-transform duration-200"
                             :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"
                             viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <!-- Sub-menu -->
                    <div x-show="open" x-collapse>
                        <div class="ml-[18px] pl-4 border-l border-white/10 py-1 space-y-0.5">
                            @can('general.dashboard.index')
                                <a href="{{ route('admin.sales.analytics') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/dashboard*') && !Request::is('admin/sales-spends/analytics-report*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Dashboard
                                </a>
                            @endcan

                            @can('general.daily_sale.index')
                                <a href="{{ route('admin.daily-sales.index') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/daily-sales*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Daily Sales
                                </a>
                            @endcan

                            @can('general.daily_return.index')
                                <a href="{{ route('admin.daily-returns.index') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/daily-returns*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Daily Return
                                </a>
                            @endcan

                            @can('general.monthly_budget.index')
                                <a href="{{ route('admin.monthly-budgets.index') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/monthly-budgets*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Monthly Budget
                                </a>
                            @endcan

                            @can('general.dashboard.index')
                                <a href="{{ route('admin.sales.analytics.report') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/sales-report*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Sales Report
                                </a>
                            @endcan

                            @can('general.sale_tracking.index')
                                <a href="{{ route('admin.ads-performance.index') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/ads-performance*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Ads Performance
                                </a>
                            @endcan

                            @can('general.return_reason_type.index')
                                <a href="{{ route('admin.return-reason.index') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/return-reason*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Return Reasons
                                </a>
                            @endcan

                            @can('general.sale_platform.index')
                                <a href="{{ route('admin.sale-platforms.index') }}"
                                   class="block py-1.5 px-3 text-[12px] rounded-md transition-colors {{ Request::is('admin/sales-spends/sale-platforms*') ? 'text-accent-200 bg-accent-400/15' : 'text-white/45 hover:text-white/80 hover:bg-white/5' }}">
                                    Platforms
                                </a>
                            @endcan

                        </div>
                    </div>
                </div>
            </div>
        @endcanany

    </nav>
</aside>
