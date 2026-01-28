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

            <li class="menu-title">General</li>

            <li class="nav-item">
                <a class="nav-link" href="{{ route('admin.dashboard') }}">
                    <span class="nav-icon">
                        <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                    </span>
                    <span class="nav-text"> Dashboard </span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link menu-arrow {{ request()->routeIs(
                    'admin.selling_chart.index',
                    'admin.selling_chart.create',
                    'admin.selling_chart.edit',
                    'admin.selling_chart.fabrication.index',
                    'admin.selling_chart.fabrication.create',
                    'admin.selling_chart.expense.index',
                    'admin.selling_chart.expense.create',
                    'admin.selling_chart.expense.edit',
                )
                    ? 'active'
                    : '' }}"
                    href="#sidebarSellingChart" data-bs-toggle="collapse" role="button" aria-expanded="false"
                    aria-controls="sidebarSellingChart">
                    <span class="nav-icon">
                        <iconify-icon icon="solar:t-shirt-bold-duotone"></iconify-icon>
                    </span>
                    <span class="nav-text"> Manage Selling Chart </span>
                </a>
                <div class="collapse {{ request()->routeIs(
                    'admin.selling_chart.index',
                    'admin.selling_chart.create',
                    'admin.selling_chart.edit',
                    'admin.selling_chart.fabrication.index',
                    'admin.selling_chart.fabrication.create',
                    'admin.selling_chart.expense.index',
                    'admin.selling_chart.expense.create',
                    'admin.selling_chart.expense.edit',
                )
                    ? 'show'
                    : '' }}"
                    id="sidebarSellingChart">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item {{ request()->routeIs('admin.selling_chart.index', 'admin.selling_chart.create', 'admin.selling_chart.edit') ? 'active' : '' }}">
                            <a class="sub-nav-link {{ request()->routeIs('admin.selling_chart.index', 'admin.selling_chart.create', 'admin.selling_chart.edit') ? 'active' : '' }}" href="{{ route('admin.selling_chart.index') }}">Manage Selling
                                Chart</a>
                        </li>
                        <li class="sub-nav-item">
                            <a class="sub-nav-link" href="{{ route('admin.selling_chart.fabrication.index') }}">Selling
                                Chart Fabrication</a>
                        </li>
                        <li
                            class="sub-nav-item {{ request()->routeIs('admin.selling_chart.expense.index', 'admin.selling_chart.expense.create', 'admin.selling_chart.expense.edit') ? 'active' : '' }}">
                            <a class="sub-nav-link {{ request()->routeIs('admin.selling_chart.expense.index', 'admin.selling_chart.expense.create', 'admin.selling_chart.expense.edit') ? 'active' : '' }}"
                                href="{{ route('admin.selling_chart.expense.index') }}">Selling Chart Expense</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</div>
<!-- ========== App Menu End ========== -->
