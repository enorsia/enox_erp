<!-- ========== App Menu Start ========== -->
<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{ route('admin.dashboard') }}" class="logo-dark">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-dark.png') }}" class="logo-lg" alt="logo dark">
        </a>

        <a href="{{ route('admin.dashboard') }}" class="logo-light">
            <img src="{{ asset('assets/images/logo-sm.png') }}" class="logo-sm" alt="logo sm">
            <img src="{{ asset('assets/images/logo-light.png') }}" class="logo-lg" alt="logo light">
        </a>
    </div>

    <!-- Menu Toggle Button (sm-hover) -->
    <button type="button" class="button-sm-hover" aria-label="Show Full Sidebar">
        <iconify-icon icon="solar:double-alt-arrow-right-bold-duotone" class="button-sm-hover-icon"></iconify-icon>
    </button>

    <div class="scrollbar" data-simplebar>
        <ul class="navbar-nav" id="navbar-nav">
            @canany(Cache::get('permissions.available', [])['prefix']['authentication_'] ?? [])
                <li class="menu-title">Authentication</li>
                @can('authentication.users.index')
                    <li class="nav-item {{ Request::is('admin/users/*') ? 'active' : '' }}">
                        <a class="nav-link {{ Request::is('admin/users/*') ? 'active' : '' }}"
                            href="{{ route('admin.users.index') }}">
                            <span class="nav-icon">
                                <iconify-icon icon="solar:user-broken"></iconify-icon>
                            </span>
                            <span class="nav-text"> Admins </span>
                        </a>
                    </li>
                @endcan
                @can('authentication.roles.index')
                    <li class="nav-item {{ Request::is('admin/roles/*') ? 'active' : '' }}">
                        <a class="nav-link {{ Request::is('admin/roles/*') ? 'active' : '' }}"
                            href="{{ route('admin.roles.index') }}">
                            <span class="nav-icon">
                                <iconify-icon icon="solar:shield-check-broken"></iconify-icon>
                            </span>
                            <span class="nav-text"> Roles </span>
                        </a>
                    </li>
                @endcan

            @endcanany

            @canany(Cache::get('permissions.available', [])['prefix']['general_'] ?? [])
                <li class="menu-title">General</li>

                @can('general.dashboard.index')
                    <li class="nav-item">
                        <a class="nav-link" href="{{ route('admin.dashboard') }}">
                            <span class="nav-icon">
                                <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                            </span>
                            <span class="nav-text"> Dashboard </span>
                        </a>
                    </li>
                @endcan

                @canany([...array_keys(Cache::get('permissions.available')['grouped']['general_chart']),
                    ...array_keys(Cache::get('permissions.available')['grouped']['general_fabrication']),
                    ...array_keys(Cache::get('permissions.available')['grouped']['general_expense'])])
                    <li class="nav-item">
                        <a class="nav-link menu-arrow {{ Request::is('admin/selling-chart/*') ? 'active' : '' }}"
                            href="#sidebarSellingChart" data-bs-toggle="collapse" role="button" aria-expanded="false"
                            aria-controls="sidebarSellingChart">
                            <span class="nav-icon">
                                <iconify-icon icon="solar:t-shirt-bold-duotone"></iconify-icon>
                            </span>
                            <span class="nav-text">Selling Chart </span>
                        </a>
                        <div class="collapse {{ Request::is('admin/selling-chart/*') ? 'show' : '' }}"
                            id="sidebarSellingChart">
                            <ul class="nav sub-navbar-nav">
                                @can('general.chart.index')
                                    <li class="sub-nav-item {{ Request::is('admin/selling-chart/manage/*') ? 'active' : '' }}">
                                        <a class="sub-nav-link {{ Request::is('admin/selling-chart/manage/*') ? 'active' : '' }}"
                                            href="{{ route('admin.selling_chart.index') }}">
                                            Chart</a>
                                    </li>
                                @endcan
                                @can('general.forecasting.index')
                                    <li
                                        class="sub-nav-item {{ Request::is('admin/selling-chart/forecasting/*') ? 'active' : '' }}">
                                        <a class="sub-nav-link {{ Request::is('admin/selling-chart/forecasting/*') ? 'active' : '' }}"
                                            href="{{ route('admin.selling_chart.forecasting') }}">
                                            Forecasting</a>
                                    </li>
                                @endcan
                                @can('general.fabrication.index')
                                    <li
                                        class="sub-nav-item {{ Request::is('admin/selling-chart/fabrication/*') ? 'active' : '' }}">
                                        <a class="sub-nav-link {{ Request::is('admin/selling-chart/fabrication/*') ? 'active' : '' }}"
                                            href="{{ route('admin.selling_chart.fabrication.index') }}">
                                            Fabrication</a>
                                    </li>
                                @endcan
                                @can('general.expense.index')
                                    <li class="sub-nav-item {{ Request::is('admin/selling-chart/expense/*') ? 'active' : '' }}">
                                        <a class="sub-nav-link {{ Request::is('admin/selling-chart/expense/*') ? 'active' : '' }}"
                                            href="{{ route('admin.selling_chart.expense.index') }}">Expense</a>
                                    </li>
                                @endcan
                            </ul>
                        </div>
                    </li>
                @endcanany

            @endcanany

            @canany(Cache::get('permissions.available', [])['prefix']['settings_'] ?? [])
                <li class="menu-title">Settings</li>

                @can('settings.platforms.index')
                    <li class="nav-item {{ Request::is('admin/platforms/*') ? 'active' : '' }}">
                        <a class="nav-link {{ Request::is('admin/platforms/*') ? 'active' : '' }}"
                            href="{{ route('admin.platforms.index') }}">
                            <span class="nav-icon">
                                <iconify-icon icon="solar:shop-bold-duotone"></iconify-icon>
                            </span>
                            <span class="nav-text">Platforms</span>
                        </a>
                    </li>
                @endcan

            @endcanany
        </ul>
    </div>
</div>
<!-- ========== App Menu End ========== -->
