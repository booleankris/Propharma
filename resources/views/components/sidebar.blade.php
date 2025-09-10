<div class="main-sidebar sidebar-style-2">
    <aside id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="{{ url('/home') }}">Propharma</a>
        </div>
        <div class="sidebar-brand sidebar-brand-sm">
            <a href="{{ url('/home') }}">Propharma</a>
        </div>
        <ul class="sidebar-menu">

            <li class="menu-header">Dashboard</li>
            <li class="{{ request()->routeIs('home') == true ? 'active' : null }}">
                <a class="nav-link"
                    href="{{ url('/home') }}"><i class="fas fa-home"></i> <span>Dashboard</span></a>
            </li>

            <li class="menu-header">Data</li>
           
            <li class="{{ request()->routeIs('adminitems*') == true ? 'active' : null }}">
                <a class="nav-link" href="{{ route('adminitems.index') }}">
                    <i class="fas fa-box"></i>
                    <span>Produk</span>
                </a>
            </li>
            
    
            <li class="{{ request()->routeIs('report*') == true ? 'active' : null }}">
                <a class="nav-link" href="{{ route('report.index') }}">
                    <i class="fas fa-money-check"></i>
                    <span>Laporan Transaksi</span>
                </a>
            </li>
            


            @if (Auth::user()->hasAnyRole(['administrator', 'Manager']))
            <li 
                class="nav-item dropdown 
                {{ request()->routeIs('users*') == true ? 'active' : null }}
                {{ request()->routeIs('roles*') == true ? 'active' : null }}
                ">                
                <a href="#"
                    class="nav-link has-dropdown"
                    data-toggle="dropdown">
                    <i class="fas fa-columns"></i> 
                    <span>Manajeman Data</span>
                </a>
                <ul class="dropdown-menu">

                    <li class="">
                        <a class="nav-link"
                            href="{{ route('users.index') }}">User</a>
                    </li>

                    <li class="">
                        <a class="nav-link"
                            href="{{ route('roles.index') }}">Level User</a>
                    </li>

                </ul>
            </li>
            @endif

        </ul>
        
        <div class="hide-sidebar-mini mt-4 mb-4 p-3">
            <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="btn btn-danger btn-lg btn-block btn-icon-split">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </div>
    </aside>
</div>
