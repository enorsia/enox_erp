<div class="main-nav">
    <!-- Sidebar Logo -->
    <div class="logo-box">
        <a href="{{ route('admin.dashboard') }}" class="logo-dark">
            <img src="{{ cloudflareImage('261784ae-520b-42cc-80d3-9a65216f0400', 0, true) }}" class="logo-sm" alt="logo sm">
            <img src="{{ cloudflareImage('261784ae-520b-42cc-80d3-9a65216f0400', 0, true) }}" class="logo-lg" alt="logo dark">
        </a>

        <a href="{{ route('admin.dashboard') }}" class="logo-light">
            <img src="{{ cloudflareImage('261784ae-520b-42cc-80d3-9a65216f0400', 0, true) }}" class="logo-sm" alt="logo sm">
            <img src="{{ cloudflareImage('261784ae-520b-42cc-80d3-9a65216f0400', 0, true) }}" class="logo-lg" alt="logo light">
        </a>
    </div>

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

            @canany(Cache::get('permissions.available')['prefix']['inventory_'])
                <li class="menu-title mt-2">Inventory</li>
            @endcanany

            @canany(array_keys(Cache::get('permissions.available')['grouped']['inventory_attribute']))
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.attributes.*') ? 'active' : '' }}"
                    href="#sidebarAttributes" data-bs-toggle="collapse" role="button"
                    aria-expanded="{{ request()->routeIs('admin.attributes.*') ? 'true' : 'false' }}"
                    aria-controls="sidebarAttributes">
                        <span class="nav-icon">
                            <iconify-icon icon="mdi:tag-multiple-outline" class="collapse-icon"></iconify-icon>
                        </span>
                        <span class="nav-text"> Attributes </span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.attributes.*') ? 'show' : '' }}"
                        id="sidebarAttributes">
                        <ul class="nav sub-navbar-nav">
                            @can('inventory.attribute.index')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('admin.attributes.index', 'admin.attributes.show', 'admin.attributes.edit') ? 'active' : '' }}"
                                    href="{{ route('admin.attributes.index') }}">List</a>
                                </li>
                            @endcan
                            @can('inventory.attribute.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('admin.attributes.create') ? 'active' : '' }}"
                                    href="{{ route('admin.attributes.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany(array_keys(Cache::get('permissions.available')['grouped']['inventory_category']))
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}"
                    href="#sidebarCategories" data-bs-toggle="collapse" role="button"
                    aria-expanded="{{ request()->routeIs('admin.categories.*') ? 'true' : 'false' }}"
                    aria-controls="sidebarCategories">
                        <span class="nav-icon">
                            <iconify-icon icon="mdi:cart-outline" class="collapse-icon"></iconify-icon>
                        </span>
                        <span class="nav-text"> Categories </span>
                    </a>

                    <div class="collapse {{ request()->routeIs('admin.categories.*') ? 'show' : '' }}"
                        id="sidebarCategories">
                        <ul class="nav sub-navbar-nav">
                            @can('inventory.category.index')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('admin.categories.index', 'admin.categories.show', 'admin.categories.edit') ? 'active' : '' }}"
                                    href="{{ route('admin.categories.index') }}">List</a>
                                </li>
                            @endcan
                            @can('inventory.category.create')
                                <li class="sub-nav-item">
                                    <a class="sub-nav-link {{ request()->routeIs('admin.categories.create') ? 'active' : '' }}"
                                    href="{{ route('admin.categories.create') }}">Create</a>
                                </li>
                            @endcan
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany([...array_keys(Cache::get('permissions.available')['grouped']['inventory_product']), ...array_keys(Cache::get('permissions.available')['grouped']['inventory_inventory'])])
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.products.*', 'admin.inventories.*') ? 'active' : '' }}"
                        href="#sidebarProducts" data-bs-toggle="collapse" role="button"
                        aria-expanded="{{ request()->routeIs('admin.products.*', 'admin.inventories.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarProducts">
                        <span class="nav-icon">
                            <iconify-icon icon="mdi:shopping-outline" class="collapse-icon"></iconify-icon>

                        </span>
                        <span class="nav-text"> Products </span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.products.*', 'admin.inventories.*') ? 'show' : '' }}"
                        id="sidebarProducts">
                        <ul class="nav sub-navbar-nav">
                            <ul class="nav sub-navbar-nav">
                                @can('inventory.product.index')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.products.index', 'admin.products.show', 'admin.products.edit') ? 'active' : '' }}"
                                            href="{{ route('admin.products.index') }}">List</a>
                                    </li>
                                @endcan

                                @can('inventory.product.create')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.products.create') ? 'active' : '' }}"
                                            href="{{ route('admin.products.create') }}">Create</a>
                                    </li>
                                @endcan
                                @can('inventory.inventory.index')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.inventories.*') ? 'active' : '' }}"
                                            href="{{ route('admin.inventories.index') }}">Inventory</a>
                                    </li>
                                @endcan
                            </ul>
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany(Cache::get('permissions.available')['prefix']['users_'])
                <li class="menu-title mt-2">Users</li>
            @endcanany

            @canany(array_keys(Cache::get('permissions.available')['grouped']['users_customers']))
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.customers.*') ? 'active' : '' }}"
                    href="#sidebarCustomers" data-bs-toggle="collapse" role="button"
                    aria-expanded="{{ request()->routeIs('admin.customers.*') ? 'true' : 'false' }}"
                    aria-controls="sidebarCustomers">
                        <span class="nav-icon">

                            <iconify-icon icon="ph:users-bold"></iconify-icon>
                        </span>
                        <span class="nav-text"> Customers </span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.customers.*') ? 'show' : '' }}"
                        id="sidebarCustomers">
                        <ul class="nav sub-navbar-nav">
                            <ul class="nav sub-navbar-nav">
                                @can('users.customers.index')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.customers.index', 'admin.customers.show') ? 'active' : '' }}"
                                        href="{{ route('admin.customers.index') }}">List</a>
                                    </li>
                                @endcan
                            </ul>
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany(Cache::get('permissions.available')['prefix']['authentication_'])
                <li class="menu-title mt-2">Authorization</li>
            @endcanany
            @canany(array_keys(Cache::get('permissions.available')['grouped']['authentication_role']))
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                    href="#sidebarRoles" data-bs-toggle="collapse" role="button"
                    aria-expanded="{{ request()->routeIs('admin.roles.*') ? 'true' : 'false' }}"
                    aria-controls="sidebarRoles">
                        <span class="nav-icon">
                            <iconify-icon icon="solar:user-speak-rounded-bold-duotone"></iconify-icon>
                        </span>
                        <span class="nav-text"> Roles </span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.roles.*') ? 'show' : '' }}" id="sidebarRoles">
                        <ul class="nav sub-navbar-nav">
                            <ul class="nav sub-navbar-nav">
                                @can('authentication.role.index')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.roles.index', 'admin.roles.show', 'admin.roles.edit') ? 'active' : '' }}"
                                        href="{{ route('admin.roles.index') }}">List</a>
                                    </li>
                                @endcan
                                @can('authentication.role.create')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link" href="{{ route('admin.roles.create') }}">Create</a>
                                    </li>
                                @endcan
                            </ul>
                        </ul>
                    </div>
                </li>
            @endcanany
            @canany(array_keys(Cache::get('permissions.available')['grouped']['authentication_admin']))
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.users.*') ? 'active' : '' }}"
                    href="#sidebarAdmins" data-bs-toggle="collapse" role="button"
                    aria-expanded="{{ request()->routeIs('admin.users.*') ? 'true' : 'false' }}"
                    aria-controls="sidebarAdmins">
                        <span class="nav-icon">
                            <iconify-icon icon="ph:user-gear-bold"></iconify-icon>

                            {{-- <iconify-icon icon="ph:users-bold"></iconify-icon> --}}
                        </span>
                        <span class="nav-text"> Admins </span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.users.*') ? 'show' : '' }}" id="sidebarAdmins">
                        <ul class="nav sub-navbar-nav">
                            <ul class="nav sub-navbar-nav">
                                @can('authentication.admin.index')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.users.index', 'admin.users.show', 'admin.users.edit') ? 'active' : '' }}"
                                        href="{{ route('admin.users.index') }}">List</a>
                                    </li>
                                @endcan
                                @can('authentication.admin.create')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.users.create') ? 'active' : '' }}"
                                        href="{{ route('admin.users.create') }}">Create</a>
                                    </li>
                                @endcan
                            </ul>
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany(Cache::get('permissions.available')['prefix']['order_'])
                <li class="menu-title mt-2">Orders</li>
            @endcanany

            @canany(array_keys(Cache::get('permissions.available')['grouped']['order_order']))
                <li class="nav-item">
                    <a class="nav-link menu-arrow {{ request()->routeIs('admin.orders.*') ? 'active' : '' }}"
                        href="#sidebarOrders" data-bs-toggle="collapse" role="button"
                        aria-expanded="{{ request()->routeIs('admin.orders.*') ? 'true' : 'false' }}"
                        aria-controls="sidebarRoles">
                        <span class="nav-icon">
                            <iconify-icon icon="fa-solid:clipboard-list"></iconify-icon>

                        </span>
                        <span class="nav-text"> Orders </span>
                    </a>
                    <div class="collapse {{ request()->routeIs('admin.orders.*') ? 'show' : '' }}" id="sidebarOrders">
                        <ul class="nav sub-navbar-nav">
                            <ul class="nav sub-navbar-nav">
                                @can('order.order.index')
                                    <li class="sub-nav-item">
                                        <a class="sub-nav-link {{ request()->routeIs('admin.orders.index', 'admin.orders.show', 'admin.orders.edit') ? 'active' : '' }}"
                                            href="{{ route('admin.orders.index') }}">List</a>
                                    </li>
                                @endcan
                            </ul>
                        </ul>
                    </div>
                </li>
            @endcanany

            @canany(Cache::get('permissions.available')['prefix']['support_'])
                <li class="menu-title mt-2">Support</li>
            @endcanany
            @can('support.notification.index')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.notifications.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="mdi:bell-ring-outline"></iconify-icon>
                        </span>
                        <span class="nav-text"> Notifications </span>
                    </a>
                </li>
            @endcan
            @canany('support.messages.index')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.chats.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="mdi:message-outline"></iconify-icon>
                        </span>
                        <span class="nav-text"> Messages </span>
                        <span class="badge bg-danger rounded-pill ms-auto" id="regularMessagesCount" style="display: none;">0</span>
                    </a>
                </li>
            @endcan

            @canany('support.order_messages.index')
                <li class="nav-item">
                    <a class="nav-link" href="{{ route('admin.order.chats.index') }}">
                        <span class="nav-icon">
                            <iconify-icon icon="mdi:email-outline"></iconify-icon>
                        </span>
                        <span class="nav-text"> Order Messages </span>
                        <span class="badge bg-danger rounded-pill ms-auto" id="orderMessagesCount" style="display: none;">0</span>
                    </a>
                </li>
            @endcan

        </ul>
    </div>
</div>
