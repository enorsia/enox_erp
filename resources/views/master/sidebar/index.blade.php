<!-- ========== App Menu Start ========== -->
<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{route('admin.dashboard')}}" class="logo-dark">
                <img src="{{asset('assets/images/logo-sm.png')}}" class="logo-sm" alt="logo sm">
                <img src="{{asset('assets/images/logo-dark.png')}}" class="logo-lg" alt="logo dark">
        </a>

        <a href="{{route('admin.dashboard')}}" class="logo-light">
                <img src="{{asset('assets/images/logo-sm.png')}}" class="logo-sm" alt="logo sm">
                <img src="{{asset('assets/images/logo-light.png')}}" class="logo-lg" alt="logo light">
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
                <a class="nav-link" href="{{route('admin.dashboard')}}">
                    <span class="nav-icon">
                        <iconify-icon icon="solar:widget-5-bold-duotone"></iconify-icon>
                    </span>
                    <span class="nav-text"> Dashboard </span>
                </a>
            </li>

            <li class="nav-item">
                <a class="nav-link menu-arrow" href="#sidebarProducts" data-bs-toggle="collapse" role="button" aria-expanded="false" aria-controls="sidebarProducts">
                    <span class="nav-icon">
                        <iconify-icon icon="solar:t-shirt-bold-duotone"></iconify-icon>
                    </span>
                    <span class="nav-text"> Products </span>
                </a>
                <div class="collapse" id="sidebarProducts">
                    <ul class="nav sub-navbar-nav">
                        <li class="sub-nav-item">
                                <a class="sub-nav-link" href="#">List</a>
                        </li>
                        <li class="sub-nav-item">
                                <a class="sub-nav-link" href="#">Grid</a>
                        </li>
                        <li class="sub-nav-item">
                                <a class="sub-nav-link" href="#">Details</a>
                        </li>
                        <li class="sub-nav-item">
                                <a class="sub-nav-link" href="#">Edit</a>
                        </li>
                        <li class="sub-nav-item">
                                <a class="sub-nav-link" href="#">Create</a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </div>
</div>
<!-- ========== App Menu End ========== -->
