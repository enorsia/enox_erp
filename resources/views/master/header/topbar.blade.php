<!-- ========== Topbar Start ========== -->
<header class="topbar">
    <div class="container-fluid">
        <div class="navbar-header">
            <div class="d-flex align-items-center">
                <!-- Menu Toggle Button -->
                <div class="topbar-item">
                    <button type="button" class="button-toggle-menu me-2">
                        <iconify-icon icon="solar:hamburger-menu-broken" class="fs-24 align-middle"></iconify-icon>
                    </button>
                </div>

                <!-- Menu Toggle Button -->
                <div class="topbar-item">
                    <h4 class="fw-bold topbar-button pe-none text-uppercase mb-0">Welcome</h4>
                </div>
            </div>

            <div class="d-flex align-items-center gap-1">
                <!-- Theme Color (Light/Dark) -->
                <div class="topbar-item">
                    <button type="button" class="topbar-button" id="light-dark-mode">
                        <iconify-icon icon="solar:moon-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                    </button>
                </div>

                @include('master.header.notification')

                <!-- Theme Setting -->
                <div class="topbar-item d-none d-md-flex">
                    <button type="button" class="topbar-button" id="theme-settings-btn" data-bs-toggle="offcanvas" data-bs-target="#theme-settings-offcanvas" aria-controls="theme-settings-offcanvas">
                        <iconify-icon icon="solar:settings-bold-duotone" class="fs-24 align-middle"></iconify-icon>
                    </button>
                </div>
                <!-- User -->
                <div class="dropdown topbar-item">
                    <a type="button" class="topbar-button" id="page-header-user-dropdown" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <span class="d-flex align-items-center">
                                <img class="rounded-circle" width="32" src="{{asset('assets/images/users/avatar-1.jpg')}}" alt="avatar-3">
                        </span>
                    </a>
                    <div class="dropdown-menu dropdown-menu-end">
                        <!-- item-->
                        <h6 class="dropdown-header">Welcome {{Str::limit(Auth::user()->name, 10)}}!</h6>
                        <a class="dropdown-item" href="pages-profile.html">
                                <i class="bx bx-user-circle text-muted fs-18 align-middle me-1"></i><span class="align-middle">Profile</span>
                        </a>
                        <a class="dropdown-item" href="apps-chat.html">
                                <i class="bx bx-message-dots text-muted fs-18 align-middle me-1"></i><span class="align-middle">Messages</span>
                        </a>

                        <a class="dropdown-item" href="pages-pricing.html">
                                <i class="bx bx-wallet text-muted fs-18 align-middle me-1"></i><span class="align-middle">Pricing</span>
                        </a>
                        <a class="dropdown-item" href="pages-faqs.html">
                                <i class="bx bx-help-circle text-muted fs-18 align-middle me-1"></i><span class="align-middle">Help</span>
                        </a>
                        <a class="dropdown-item" href="auth-lock-screen.html">
                                <i class="bx bx-lock text-muted fs-18 align-middle me-1"></i><span class="align-middle">Lock screen</span>
                        </a>

                        <div class="dropdown-divider my-1"></div>

                        <a class="dropdown-item text-danger" href="#"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="bx bx-log-out fs-18 align-middle me-1"></i>
                            <span class="align-middle">Logout</span>
                        </a>

                        <form id="logout-form" action="{{ route('admin.logout') }}" method="POST" class="d-none">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
@include('master.header.theme_settings')
