<!-- ═══════ SIDEBAR ═══════ -->
<aside id="sidebar"
    class="w-[230px] min-w-[230px] flex flex-col overflow-y-auto sidebar-scroll z-50 lg:relative lg:translate-x-0 transition-all duration-300">

    <!-- Logo + desktop collapse toggle -->
    <div
        class="sidebar-header px-[18px] py-5 border-b border-[rgba(255,255,255,0.07)] flex items-center justify-between flex-shrink-0">
        <a href="{{ route('admin.dashboard') }}" class="logo-text-group block min-w-0">
            <div class="text-white font-semibold tracking-[2.5px] text-[15px]">{{ config('app.name') }}</div>
            <div class="text-[10px] tracking-[1.2px] text-white/30 mt-0.5">
                Know Your Sales
            </div>
        </a>
        <button type="button" id="sidebarToggle"
            class="hidden lg:inline-flex text-white/40 hover:text-white/90 transition-colors duration-200 shrink-0"
            aria-label="Toggle sidebar width">
            <svg id="menuIcon" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 12h16M4 18h16" />
            </svg>
            <svg id="closeIcon" class="w-6 h-6 hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor"
                stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Nav -->
    <nav class="flex-1 py-2">

        {{-- Access --}}
        @canany(avaiablePermissionsMap()['prefix']['authentication_'] ?? [])
            <div class="sidebar-section" data-section="access">
                <p class="menu-title">Access</p>

                @can('authentication.users.index')
                    <a href="{{ route('admin.users.index') }}"
                        class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/users*') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <circle cx="12" cy="8" r="4" />
                            <path stroke-linecap="round" d="M4 20c0-4 3.6-7 8-7s8 3 8 7" />
                        </svg>
                        Admin Users
                    </a>
                @endcan

                @can('authentication.roles.index')
                    <a href="{{ route('admin.roles.index') }}"
                        class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/roles*') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round"
                                d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" />
                        </svg>
                        Permissions
                    </a>
                @endcan

                @can('authentication.activity_logs.index')
                    <a href="{{ route('admin.activity-logs.index') }}"
                        class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/activity-logs*') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Activities
                    </a>
                @endcan
            </div>
        @endcanany

        {{-- General --}}
        @canany(avaiablePermissionsMap()['prefix']['general_'] ?? [])
            <div class="sidebar-section" data-section="main">
                <p class="menu-title">Main</p>

                @can('general.dashboard.index')
                    <a href="{{ route('admin.dashboard') }}"
                        class="nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::routeIs('admin.dashboard') ? 'nav-active text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">

                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 3v18h18V3H3zm2 2h14v14H5V5z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 9h6v6H9z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 3v6M15 3v6M3 9h6M15 9h6" />
                        </svg>
                        Dashboard
                    </a>
                @endcan

                @canany([
                        ...array_keys(avaiablePermissionsMap()['grouped']['general_chart'] ?? []),
                        ...array_keys(avaiablePermissionsMap()['grouped']['general_fabrication'] ?? []),
                        ...array_keys(avaiablePermissionsMap()['grouped']['general_expense'] ?? []),
                        ...array_keys(avaiablePermissionsMap()['grouped']['general_forecasting'] ?? []),
                        ...array_keys(avaiablePermissionsMap()['grouped']['general_discounts'] ?? []),
                        'settings.platforms.index'
                    ])
                    <!-- Selling Chart Dropdown -->
                    <div
                        x-data="{ open: {{ Request::is('admin/selling-chart/*') || Request::is('admin/platforms*') ? 'true' : 'false' }} }">
                        <button @click="open = !open"
                            class="w-full nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/selling-chart/*') || Request::is('admin/platforms*') ? 'text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                            <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M20 7H4a1 1 0 00-1 1v10a1 1 0 001 1h16a1 1 0 001-1V8a1 1 0 00-1-1zM9 11h6M9 15h4" />
                            </svg>
                            <span class="flex-1 text-left">Selling Chart</span>
                            <svg class="sidebar-chevron w-3 h-3 ml-auto opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <!-- Sub-menu -->
                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <div class="ml-[18px] pl-4 border-l border-white/10 py-1 space-y-0.5">
                                @can('general.chart.index')
                                    <a href="{{ route('admin.selling_chart.index') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/selling-chart/manage*') ? 'is-active' : '' }} transition-colors">
                                        Chart
                                    </a>
                                @endcan

                                @can('general.forecasting.index')
                                    <a href="{{ route('admin.selling_chart.forecasting') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/selling-chart/forecasting*') ? 'is-active' : '' }} transition-colors">
                                        Forecasting
                                    </a>
                                @endcan

                                @can('general.discounts.index')
                                    <a href="{{ route('admin.selling_chart.discounts') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/selling-chart/discounts*') ? 'is-active' : '' }} transition-colors">
                                        Discounts
                                    </a>
                                @endcan

                                @can('general.fabrication.index')
                                    <a href="{{ route('admin.selling_chart.fabrication.index') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/selling-chart/fabrication*') ? 'is-active' : '' }} transition-colors">
                                        Fabrication
                                    </a>
                                @endcan

                                @can('general.expense.index')
                                    <a href="{{ route('admin.selling_chart.expense.index') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/selling-chart/expense*') ? 'is-active' : '' }} transition-colors">
                                        Expense
                                    </a>
                                @endcan

                                @can('settings.platforms.index')
                                    <a href="{{ route('admin.platforms.index') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/platforms*') ? 'is-active' : '' }} transition-colors">
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
        @canany(array_merge(
                avaiablePermissionsMap()['prefix']['general_'] ?? [],
                avaiablePermissionsMap()['prefix']['ecommerce_'] ?? [],
                config('tracker.enabled') ? (avaiablePermissionsMap()['prefix']['ecom_tracker_'] ?? []) : []
            ))
            <div class="sidebar-section" data-section="insights">
                <p class="menu-title">Insights</p>
                <div x-data="{ open: {{ Request::is('admin/sales-spends/*') ? 'true' : 'false' }} }">
                    <button @click="open = !open"
                        class="w-full nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/sales-spends/*') ? 'text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                        <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 6v12m-3-2.818l.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="flex-1 text-left">Sales & Spends</span>
                        <svg class="sidebar-chevron w-3 h-3 ml-auto opacity-40 transition-transform duration-200"
                            :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" d="M19 9l-7 7-7-7" />
                        </svg>
                    </button>

                    <!-- Sub-menu -->
                    <div class="sidebar-submenu" x-show="open" x-collapse>
                        <div class="ml-[18px] pl-4 border-l border-white/10 py-1 space-y-0.5">
                            @can('general.dashboard.index')
                                <a href="{{ route('admin.sales.analytics') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/dashboard*') && !Request::is('admin/sales-spends/analytics-report*') ? 'is-active' : '' }}">
                                    Dashboard
                                </a>
                            @endcan

                            @can('general.daily_sale.index')
                                <a href="{{ route('admin.daily-sales.index') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/daily-sales*', 'admin/sales-spends/sales-report*') ? 'is-active' : '' }}">
                                    Daily Sales
                                </a>
                            @endcan

                            @can('general.daily_return.index')
                                <a href="{{ route('admin.daily-returns.index') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/daily-returns*') ? 'is-active' : '' }}">
                                    Daily Return
                                </a>
                            @endcan

                            @can('general.monthly_budget.index')
                                <a href="{{ route('admin.monthly-budgets.index') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/monthly-budgets*') ? 'is-active' : '' }}">
                                    Monthly Budget
                                </a>
                            @endcan

                            @can('general.sale_tracking.index')
                                <a href="{{ route('admin.ads-performance.index') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/ads-performance*') ? 'is-active' : '' }}">
                                    Ads Performance
                                </a>
                            @endcan

                            @can('general.return_reason_type.index')
                                <a href="{{ route('admin.return-reason.index') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/return-reason*') ? 'is-active' : '' }}">
                                    Return Reasons
                                </a>
                            @endcan

                            @can('general.sale_platform.index')
                                <a href="{{ route('admin.sale-platforms.index') }}"
                                    class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/sales-spends/sale-platforms*') ? 'is-active' : '' }}">
                                    Platforms
                                </a>
                            @endcan

                        </div>
                    </div>
                </div>

                @php
                    $isTrackingRoute = Request::is('admin/ecom-tracker*', 'admin/ecom-activity*');
                @endphp
                @canany(array_merge(
                    avaiablePermissionsMap()['prefix']['ecommerce_'] ?? [],
                    config('tracker.enabled') ? (avaiablePermissionsMap()['prefix']['ecom_tracker_'] ?? []) : []
                ))
                    <div x-data="{ open: {{ Request::is('admin/style/stock*', 'admin/ecommerce/ctr-summary*', 'admin/ecommerce/ranking-products*') || (config('tracker.enabled') && $isTrackingRoute) ? 'true' : 'false' }} }">
                        <button @click="open = !open"
                            class="w-full nav-link-item flex items-center gap-2.5 px-[18px] py-2 text-[13px] {{ Request::is('admin/style/stock*', 'admin/ecommerce/ctr-summary*', 'admin/ecommerce/ranking-products*') || (config('tracker.enabled') && $isTrackingRoute) ? 'text-accent-200 bg-accent-400/20' : 'text-white/55 hover:bg-white/5 hover:text-white/90' }}">
                            <svg class="w-4 h-4 opacity-70 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round"
                                    d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25h9.75M5.106 5.106l-.383-1.437A1.125 1.125 0 003.636 3H2.25m0 0v16.5A2.25 2.25 0 004.5 21h15a2.25 2.25 0 002.25-2.25V3M9 10.5h6M9 14.25h3" />
                            </svg>
                            <span class="flex-1 text-left">Ecommerce</span>
                            <svg class="sidebar-chevron w-3 h-3 ml-auto opacity-40 transition-transform duration-200"
                                :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" stroke-width="2"
                                viewBox="0 0 24 24">
                                <path stroke-linecap="round" d="M19 9l-7 7-7-7" />
                            </svg>
                        </button>

                        <div class="sidebar-submenu" x-show="open" x-collapse>
                            <div class="ml-[18px] pl-4 border-l border-white/10 py-1 space-y-0.5">
                                @can('ecommerce.wh_stock_in_out.index')
                                    <a href="{{ route('admin.style.stock.index') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/style/stock*') ? 'is-active' : '' }}">
                                        Stock Performance
                                    </a>
                                @endcan

                                @can('ecommerce.ranking_products.index')
                                    <a href="{{ route('admin.ecommerce.ranking-products.index') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ Request::is('admin/ecommerce/ranking-products*', 'admin/ecommerce/ctr-summary*') ? 'is-active' : '' }}">
                                        Product Ranking
                                    </a>
                                @endcan

                                @can('ecom_tracker.dashboard.index')
                                    <a href="{{ route('admin.ecom-tracker.dashboard') }}"
                                        class="sidebar-sub-link block px-3 rounded-md transition-colors {{ $isTrackingRoute ? 'is-active' : '' }}">
                                        Enox Tracking
                                    </a>
                                @endcan
                            </div>
                        </div>
                    </div>
                @endcanany
            </div>
        @endcanany

    </nav>
</aside>
