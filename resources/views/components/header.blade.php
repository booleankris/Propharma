{{-- <div class="navbar-bg"></div>
<nav class="navbar navbar-expand-lg main-navbar">
    <form class="form-inline mr-auto">
        <ul class="navbar-nav mr-3">
            <li><a href="#"
                    data-toggle="sidebar"
                    class="nav-link nav-link-lg"><i class="fas fa-bars"></i></a></li>
            <li><a href="#"
                    data-toggle="search"
                    class="nav-link nav-link-lg d-none"><i class="fas fa-search"></i></a></li>
        </ul>
    </form>
    <ul class="navbar-nav navbar-right">
        <li class="dropdown"><a href="#"
                data-toggle="dropdown"
                class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                <div class="d-sm-none d-lg-inline-block">Hi, {{ Auth::user()->name }}</div>
            </a>
            <div class="dropdown-menu dropdown-menu-right">
                <a href="{{ route('account.profile') }}"
                    class="dropdown-item has-icon">
                    <i class="far fa-user"></i> Profile
                </a>
                <div class="dropdown-divider"></div>
                <a href="{{ route('logout') }}" onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                    class="dropdown-item has-icon text-danger">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>

                <form id="logout-form" action="{{ route('logout') }}" method="POST" class="d-none">
                    @csrf
                </form>
            </div>
        </li>
    </ul>
</nav> --}}
<nav class="fixed shadow-lg z-[9999] bg-white w-full top-0">
    <div class="flex justify-between items-center px-5 py-3">
        <!-- LEFT: Greeting -->
        <div class="flex flex-col">
            <div class="text-[13px] font-light">Halo,</div>
            <div class="font-montserrat font-bold text-[20px]">
                {{ Auth::user()->name }}
            </div>
        </div>

        <!-- RIGHT: Dropdown Menus -->
        <div class="flex gap-4 items-center">

            <!-- PROFILE DROPDOWN -->
            <div class="relative">
                <button id="dropdownProfileBtn" class="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-md">
                    <svg class="w-5 h-5 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg"
                        width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-width="2"
                            d="M20 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6h-2m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4m16 6H10m0 0a2 2 0 1 0-4 0m4 0a2 2 0 1 1-4 0m0 0H4" />
                    </svg>

                    <span class="text-[12px] font-montserrat">Menu</span>
                    <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div id="dropdownProfileMenu"
                    class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg">
                    <a href="{{ url('/home') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Menu Utama</a>
                    <a href="{{ url('transaction/upds') }}"
                        class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Penjualan</a>
                    <a href="{{ route('receiving.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Pembelian</a>

                    <a href="{{ route('logout') }}"
                        onclick="event.preventDefault(); document.getElementById('logout-form').submit();"
                        class="block px-4 py-2 text-sm text-red-600 hover:bg-red-50">Logout</a>
                </div>
            </div>

            <!-- MASTER DROPDOWN -->
            <div class="relative">
                <button id="dropdownMasterBtn" class="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-md">
                    <svg class="w-5 h-5 text-gray-600" aria-hidden="true"
                        xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none"
                        viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M10 3v4a1 1 0 0 1-1 1H5m4 10v-2m3 2v-6m3 6v-3m4-11v16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V7.914a1 1 0 0 1 .293-.707l3.914-3.914A1 1 0 0 1 9.914 3H18a1 1 0 0 1 1 1Z" />
                    </svg>

                    <span class="text-[12px] font-montserrat">Master</span>
                    <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div id="dropdownMasterMenu"
                    class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg">
                  
                    <a href="{{ route('medicines.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Obat</a>
                    <a href="{{ route('debtors.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Debitur</a>
                    <a href="{{ route('categories.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Kategori Obat</a>
                    <a href="{{ route('creditors.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Kreditur</a>
                    <a href="{{ route('compositions.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Komposisi</a>
                    <a href="{{ route('doctors.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Dokter</a>
                    <a href="{{ route('patients.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Pasien</a>
                    <a href="{{ route('factories.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Master
                        Pabrik</a>
                </div>
            </div>

            {{-- <div class="relative">
                <button id="dropdownDataBtn" class="flex items-center gap-2 p-2 hover:bg-gray-100 rounded-md">
                    <svg class="w-5 h-5 text-gray-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 4h3a1 1 0 0 1 1 1v15a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h3m0 3h6m-3 5h3m-6 0h.01M12 16h3m-6 0h.01M10 3v4h4V3h-4Z"/>
                      </svg>
                      
                    <span class="text-[12px] font-montserrat">Laporan</span>
                    <svg class="w-4 h-4 text-gray-500" xmlns="http://www.w3.org/2000/svg" fill="none"
                        viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                    </svg>
                </button>

                <div id="dropdownDataMenu"
                    class="hidden absolute right-0 mt-2 w-40 bg-white border border-gray-200 rounded-lg shadow-lg">
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Data Penjualan</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Data Pembelian</a>
                    <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">Data Retur</a>
                </div>
            </div> --}}
        </div>

        <!-- Logout Form -->
        <form class="hidden" id="logout-form" action="{{ route('logout') }}" method="POST">
            @csrf
        </form>
    </div>
</nav>

<script>
    function setupDropdown(buttonId, menuId) {
        const button = document.getElementById(buttonId);
        const menu = document.getElementById(menuId);

        button.addEventListener('click', () => {
            // Hide all open dropdowns first
            document.querySelectorAll('[id$="Menu"]').forEach(el => el.classList.add('hidden'));
            // Toggle current
            menu.classList.toggle('hidden');
        });

        // Close when clicking outside
        window.addEventListener('click', (e) => {
            if (!button.contains(e.target) && !menu.contains(e.target)) {
                menu.classList.add('hidden');
            }
        });
    }

    // Setup all dropdowns
    setupDropdown('dropdownProfileBtn', 'dropdownProfileMenu');
    setupDropdown('dropdownMasterBtn', 'dropdownMasterMenu');
    setupDropdown('dropdownDataBtn', 'dropdownDataMenu');
</script>
