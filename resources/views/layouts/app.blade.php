<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <link href="/src/style.css" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
    <!-- PWA Settings -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#1d7ed8">
    <link rel="apple-touch-icon" href="{{ asset('img/sahabat-mascot.png') }}">

    @yield('style')

</head>

<body class="dashboard-body">
    @include('components.header')
    @include('components.sidebar-client')

    <main class="my-[10px] pb-[80px]">
        @yield('content')
    </main>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.3.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/axios/dist/axios.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- PWA Service Worker Registration -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(registration => console.log('ServiceWorker registered:', registration))
                    .catch(error => console.log('ServiceWorker registration failed:', error));
            });
        }
    </script>

    <script>
        $(document).ready(function() {
            initFactorySelect();
            initDoctorSelect();
        });
        // Sales Report Onpage load (Starting point)
        document.addEventListener("DOMContentLoaded", function() {

            const activeBtn = document.querySelector('.report-btn[data-active="true"]');

            if (!activeBtn) return;

            const label = activeBtn.innerText.trim();

            if (label === "LIPH") {
                document.getElementById('type_filter').style.display = 'none';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';
                if (document.getElementById('btn-online')) {
                    document.getElementById('btn-online').style.display = 'flex';
                }
            } else {
                document.getElementById('type_filter').style.display = 'block';
                if (document.getElementById('btn-online')) {
                    document.getElementById('btn-online').style.display = 'none';
                }
            }


        });
        $('#factory').on('change', function() {
            console.log('Selected factory:', $(this).val());
        });
        $('#doctor').on('change', function() {
            console.log('Selected doctor:', $(this).val());
        });
        var selectedReport = "LIPH";
        var selectedShift = "";
        var selectedType = 'recap';
        var selectedShiftType = "shift";

        // ─── Sidebar (mobile) ─────────────────────────────────────────────────────────
        function initFactorySelect() {
            const el = $('#factory');

            if (el.hasClass("select2-hidden-accessible")) {
                el.select2('destroy');
            }

            el.select2({
                placeholder: 'Pilih pabrik...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#reportModal')
            });
        }

        function initDoctorSelect() {
            const el = $('#doctor');

            if (el.hasClass("select2-hidden-accessible")) {
                el.select2('destroy');
            }

            el.select2({
                placeholder: 'Pilih dokter...',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#reportModal')
            });
        }

        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('open');
            document.getElementById('sidebarOverlay').classList.toggle('open');
        }

        function closeSidebar() {
            document.getElementById('sidebar').classList.remove('open');
            document.getElementById('sidebarOverlay').classList.remove('open');
        }

        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', () => {
                if (window.innerWidth <= 768) closeSidebar();
            });
        });
        const backdrop = document.getElementById('modalBackdrop');
        window.openModal = function(id) {
            const modal = document.getElementById(id);
            if (!modal) return;
            modal.classList.remove('modal-hide');
            modal.classList.add('modal-show');
            backdrop.classList.remove('hidden');

            if (id === 'reportModal') {
                setTimeout(() => initFactorySelect(), 100);
            }
            // ← add this
            if (id === 'orderReportModal') {
                setTimeout(() => initOrderSupplierSelect(), 100);
            }
        };
        window.closeModals = function() {
            document.querySelectorAll('.modal-show').forEach(modal => {
                modal.classList.remove('modal-show');
                modal.classList.add('modal-hide');
            });
            backdrop.classList.add('hidden');
        };

        document.querySelectorAll('.closeModal').forEach(btn => {
            btn.addEventListener('click', window.closeModals);
        });

        backdrop.addEventListener('click', window.closeModals);

        function toggleModal(id) {
            const target = document.getElementById(id);
            if (!target) return;
            document.querySelectorAll('#notif-modal, #expiry-modal').forEach(m => {
                if (m.id !== id) m.classList.add('hidden');
            });
            target.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            ['notif-modal', 'expiry-modal'].forEach(id => {
                const modal = document.getElementById(id);
                if (!modal) return;
                if (!e.target.closest(`#${id}`) && !e.target.closest(`[onclick*="${id}"]`)) {
                    modal.classList.add('hidden');
                }
            });
        });

        function selectReport(el) {
            document.querySelectorAll('.report-btn').forEach(btn => {
                btn.classList.remove('border-violet-200', 'bg-violet-50');
                btn.classList.add('border-slate-100', 'bg-slate-50');
                const label = btn.querySelector('span.text-sm');
                if (label) {
                    label.classList.remove('text-violet-700');
                    label.classList.add('text-slate-600');
                }
            });
            el.classList.remove('border-slate-100', 'bg-slate-50');
            el.classList.add('border-violet-200', 'bg-violet-50');
            const label = el.querySelector('span.text-sm');
            if (label) {
                label.classList.remove('text-slate-600');
                label.classList.add('text-violet-700');
            }
            selectedReport = label ? label.textContent.trim() : null;
            if (selectedReport == "LIPH") {
                document.getElementById('date_filter').style.display = 'block';
                document.getElementById('shift-select').style.display = 'block';
                document.getElementById('type_filter').style.display = 'none';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';

                if (document.getElementById('btn-online')) {
                    document.getElementById('btn-online').style.display = 'flex';
                }

            } else if (selectedReport == "Obat") {
                document.getElementById('date_filter').style.display = 'block';
                document.getElementById('shift-select').style.display = 'block';
                document.getElementById('type_filter').style.display = 'block';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';


            } else if (selectedReport == "Golongan") {
                document.getElementById('date_filter').style.display = 'none';
                document.getElementById('shift-select').style.display = 'none';
                document.getElementById('type_filter').style.display = 'none';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';


            } else if (selectedReport == "Pabrik") {
                document.getElementById('date_filter').style.display = 'none';
                document.getElementById('shift-select').style.display = 'none';
                document.getElementById('type_filter').style.display = 'block';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';


                setTimeout(() => {
                    initFactorySelect();
                }, 100);
            } else if (selectedReport == "Dokter") {
                document.getElementById('date_filter').style.display = 'none';
                document.getElementById('shift-select').style.display = 'none';
                document.getElementById('type_filter').style.display = 'block';
                document.getElementById('doctor-select').style.display = 'none';

                setTimeout(() => {
                    initDoctorSelect();
                }, 100);
            } else if (selectedReport == "Daftar Resep") {
                document.getElementById('date_filter').style.display = 'none';
                document.getElementById('shift-select').style.display = 'block';
                document.getElementById('type_filter').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';

                setTimeout(() => {
                    initDoctorSelect();
                }, 100);
            } else if (selectedReport == "Retur Jual") {
                document.getElementById('date_filter').style.display = 'none';
                document.getElementById('shift-select').style.display = 'none';
                document.getElementById('type_filter').style.display = 'none';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';


            } else {
                document.getElementById('type_filter').style.display = 'none';
            }

            if (selectedReport !== "LIPH" && document.getElementById('btn-online')) {
                document.getElementById('btn-online').style.display = 'none';
                if (selectedShiftType === 'online') {
                    // reset to semua if online was selected
                    setFilter('semua', document.getElementById('btn-semua'));
                }
            }
            console.log('Selected report:', selectedReport);
        }

        function setFilter(type, el) {
            const btnSemua = document.getElementById('btn-semua');
            const btnShift = document.getElementById('btn-shift');
            const btnOnline = document.getElementById('btn-online');
            const shiftSel = document.getElementById('shift-select');

            [btnSemua, btnShift, btnOnline].forEach(btn => {
                if (btn) {
                    btn.classList.remove('border-slate-800', 'bg-slate-800', 'text-white');
                    btn.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-600');
                }
            });

            el.classList.remove('border-slate-200', 'bg-slate-50', 'text-slate-600');
            el.classList.add('border-slate-800', 'bg-slate-800', 'text-white');

            shiftSel.style.display = type === 'shift' ? 'block' : 'none';

            selectedShiftType = type;
        }

        function getReport(mode = 'download') {
            const start_date = document.getElementById('start_date').value;
            const end_date = document.getElementById('end_date').value;
            const shift = document.getElementById('shift').value;
            const factory = document.getElementById('factory').value;
            const doctor = document.getElementById('doctor').value;

            // Start Loading
            const overlay = document.getElementById('loading-overlay');
            overlay.style.display = 'flex';

            axios.post('/reports', {
                start_date: start_date,
                end_date: end_date,
                selectedReport: selectedReport,
                shiftType: selectedShiftType,
                shift: shift,
                selectedType: selectedType,
                factory: factory,
                doctor: doctor,
                mode: mode
            }, {
                responseType: mode === 'download' ? 'blob' : 'text'
            }).then((result) => {
                if (mode === 'download') {
                    const url = window.URL.createObjectURL(new Blob([result.data]));
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Laporan_Jual_${selectedReport}_${start_date}_${end_date}.xlsx`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    // Preview mode
                    const previewContent = document.getElementById('previewModalContent');
                    previewContent.innerHTML = result.data;
                    document.getElementById('previewModal').classList.remove('modal-hide');
                    document.getElementById('previewModal').classList.add('modal-show');

                    // Update download button inside modal to trigger the sales report download instead
                    const modalDownloadBtn = document.querySelector(
                        '#previewModal button[onclick="getOrderReport(\\\'download\\\')"]');
                    if (modalDownloadBtn) {
                        // Change it to use getReport for sales reports
                        modalDownloadBtn.setAttribute('onclick', "getReport('download')");
                    }
                }
            }).catch((err) => {
                if (err.response && err.response.data && err.response.data.message) {
                    iziToast.error({
                        title: 'Error',
                        message: err.response.data.message,
                        position: 'topRight'
                    });
                } else {
                    console.log(err);
                }
            }).finally(() => {
                // Hide loading
                overlay.style.display = 'none';
            });
        }

        const active = {
            card: ['border-blue-400', 'bg-blue-50'],
            icon: ['bg-blue-400'],
            svg: ['text-white'],
            title: ['text-blue-900'],
            desc: ['text-blue-500'],
        };
        const inactive = {
            card: ['border-gray-200', 'bg-white'],
            icon: ['bg-gray-100'],
            svg: ['text-gray-400'],
            title: ['text-gray-800'],
            desc: ['text-gray-400'],
        };

        function selectOption(el) {
            document.querySelectorAll('.opt').forEach(o => {
                o.classList.remove(...active.card);
                o.classList.add(...inactive.card);
                o.querySelector('.icon-box').classList.remove(...active.icon);
                o.querySelector('.icon-box').classList.add(...inactive.icon);
                o.querySelector('.icon-svg').classList.remove(...active.svg);
                o.querySelector('.icon-svg').classList.add(...inactive.svg);
                o.querySelector('.opt-title').classList.remove(...active.title);
                o.querySelector('.opt-title').classList.add(...inactive.title);
                o.querySelector('.opt-desc').classList.remove(...active.desc);
                o.querySelector('.opt-desc').classList.add(...inactive.desc);
            });

            el.classList.remove(...inactive.card);
            el.classList.add(...active.card);
            el.querySelector('.icon-box').classList.remove(...inactive.icon);
            el.querySelector('.icon-box').classList.add(...active.icon);
            el.querySelector('.icon-svg').classList.remove(...inactive.svg);
            el.querySelector('.icon-svg').classList.add(...active.svg);
            el.querySelector('.opt-title').classList.remove(...inactive.title);
            el.querySelector('.opt-title').classList.add(...active.title);
            el.querySelector('.opt-desc').classList.remove(...inactive.desc);
            el.querySelector('.opt-desc').classList.add(...active.desc);

            selectedType = el.dataset.value;
            if (selectedType == "detail" && selectedReport == "Pabrik") {
                document.getElementById('factory-select').style.display = 'block';
            } else if (selectedType == "detail" && selectedReport == "Dokter") {
                document.getElementById('doctor-select').style.display = 'block';
            } else {
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';
            }
            console.log(selectedType);
        }



        // ─── Orders Report ────────────────────────────────────────────────────────────

        var selectedOrderReport = "Laporan Pembelian";
        var selectedOrderType = "rekap";

        // Visibility map: which filters each report type shows
        // Keys match the button label text exactly
        const orderReportFilters = {
            "Laporan Pembelian": {
                type_filter: false,
                supplier_select: false
            },
            "Faktur Pembelian": {
                type_filter: true,
                supplier_select: false
            },
            "Per Supplier": {
                type_filter: true,
                supplier_select: false
            }, // supplier shown only on detail
            "Daftar PO": {
                type_filter: false,
                supplier_select: false
            },
            "Retur Beli": {
                type_filter: false,
                supplier_select: false
            },
            "Hutang": {
                type_filter: false,
                supplier_select: false
            },
        };
        // Order Initial Setup
        document.addEventListener("DOMContentLoaded", function() {
            initOrderSupplierSelect();

            // Set initial state based on data-active button
            const activeBtn = document.querySelector('.order-report-btn[data-active="true"]');
            const label = activeBtn.innerText.trim();

            if (label === "Faktur Pembelian") {
                document.getElementById('order_type_filter').style.display = 'block';
                document.getElementById('factory-select').style.display = 'none';
                document.getElementById('doctor-select').style.display = 'none';

            }
            if (activeBtn) applyOrderFilters(activeBtn.querySelector('span.text-sm')?.textContent.trim());
        });

        function initOrderSupplierSelect() {
            const el = $('#order_supplier');
            if (el.hasClass("select2-hidden-accessible")) el.select2('destroy');
            el.select2({
                placeholder: 'Pilih supplier...',
                allowClear: true,
                width: '100%',

            });
        }

        function applyOrderFilters(reportLabel) {
            const config = orderReportFilters[reportLabel] ?? {
                type_filter: false,
                supplier_select: false
            };

            document.getElementById('order_type_filter').style.display = config.type_filter ? 'block' : 'none';
            document.getElementById('order_supplier_select').style.display = config.supplier_select ? 'block' : 'none';

            // Reset type to rekap whenever report changes
            selectedOrderType = 'rekap';
            const rekapOpt = document.querySelector('.order-opt[data-value="rekap"]');
            if (rekapOpt) selectOrderOption(rekapOpt);
        }

        function selectOrderReport(el) {
            // Reset all buttons to inactive style
            document.querySelectorAll('.order-report-btn').forEach(btn => {
                btn.classList.remove('border-violet-200', 'bg-violet-50');
                btn.classList.add('border-slate-100', 'bg-slate-50');
                const lbl = btn.querySelector('span.text-sm');
                if (lbl) {
                    lbl.classList.remove('text-violet-700');
                    lbl.classList.add('text-slate-600');
                }
            });

            // Activate clicked button
            el.classList.remove('border-slate-100', 'bg-slate-50');
            el.classList.add('border-violet-200', 'bg-violet-50');
            const lbl = el.querySelector('span.text-sm');
            if (lbl) {
                lbl.classList.remove('text-slate-600');
                lbl.classList.add('text-violet-700');
            }

            selectedOrderReport = lbl ? lbl.textContent.trim() : null;
            applyOrderFilters(selectedOrderReport);

            if (selectedOrderReport === "Per Supplier") {
                setTimeout(() => initOrderSupplierSelect(), 100);
            }

            console.log('Selected order report:', selectedOrderReport);
        }

        const orderActive = {
            card: ['border-blue-400', 'bg-blue-50'],
            icon: ['bg-blue-400'],
            svg: ['text-white'],
            title: ['text-blue-900'],
            desc: ['text-blue-500'],
        };
        const orderInactive = {
            card: ['border-gray-200', 'bg-white'],
            icon: ['bg-gray-100'],
            svg: ['text-gray-400'],
            title: ['text-gray-800'],
            desc: ['text-gray-400'],
        };

        function selectOrderOption(el) {
            document.querySelectorAll('.order-opt').forEach(o => {
                o.classList.remove(...orderActive.card);
                o.classList.add(...orderInactive.card);
                o.querySelector('.order-icon-box').classList.remove(...orderActive.icon);
                o.querySelector('.order-icon-box').classList.add(...orderInactive.icon);
                o.querySelector('.order-icon-svg').classList.remove(...orderActive.svg);
                o.querySelector('.order-icon-svg').classList.add(...orderInactive.svg);
                o.querySelector('.order-opt-title').classList.remove(...orderActive.title);
                o.querySelector('.order-opt-title').classList.add(...orderInactive.title);
                o.querySelector('.order-opt-desc').classList.remove(...orderActive.desc);
                o.querySelector('.order-opt-desc').classList.add(...orderInactive.desc);
            });

            el.classList.remove(...orderInactive.card);
            el.classList.add(...orderActive.card);
            el.querySelector('.order-icon-box').classList.remove(...orderInactive.icon);
            el.querySelector('.order-icon-box').classList.add(...orderActive.icon);
            el.querySelector('.order-icon-svg').classList.remove(...orderInactive.svg);
            el.querySelector('.order-icon-svg').classList.add(...orderActive.svg);
            el.querySelector('.order-opt-title').classList.remove(...orderInactive.title);
            el.querySelector('.order-opt-title').classList.add(...orderActive.title);
            el.querySelector('.order-opt-desc').classList.remove(...orderInactive.desc);
            el.querySelector('.order-opt-desc').classList.add(...orderActive.desc);

            selectedOrderType = el.dataset.value;

            // Show supplier select only when Per Supplier + Detail
            // const showSupplier = selectedOrderType === 'detail' && selectedOrderReport === 'Faktur Pembelian';
            // document.getElementById('order_supplier_select').style.display = showSupplier ? 'block' : 'none';

            console.log('Order type:', selectedOrderType);
        }

        function getOrderReport(mode = 'download') {
            const start_date = document.getElementById('order_start_date').value;
            const end_date = document.getElementById('order_end_date').value;
            const supplier = document.getElementById('order_supplier').value;

            const overlay = document.getElementById('loading-overlay');
            overlay.style.display = 'flex';

            axios.post('/reports', {
                start_date: start_date,
                end_date: end_date,
                selectedReport: selectedOrderReport,
                selectedType: selectedOrderType,
                supplier: supplier,
                mode: mode
            }, {
                responseType: mode === 'download' ? 'blob' : 'text'
            }).then((result) => {
                if (mode === 'download') {
                    const url = window.URL.createObjectURL(new Blob([result.data]));
                    const a = document.createElement('a');
                    a.href = url;
                    a.download = `Laporan_Beli_${selectedOrderReport}_${start_date}_${end_date}.xlsx`;
                    a.click();
                    window.URL.revokeObjectURL(url);
                } else {
                    // Preview mode
                    const previewContent = document.getElementById('previewModalContent');
                    previewContent.innerHTML = result.data;
                    document.getElementById('previewModal').classList.remove('modal-hide');
                    document.getElementById('previewModal').classList.add('modal-show');
                }
            }).catch((err) => {
                if (err.response && err.response.data && err.response.data.message) {
                    iziToast.error({
                        title: 'Error',
                        message: err.response.data.message,
                        position: 'topRight'
                    });
                } else {
                    console.error(err);
                }
            }).finally(() => {
                overlay.style.display = 'none';
            });
        }

        function switchSheetTab(activeIdx) {
            document.querySelectorAll('.sheet-tab-btn').forEach(function(btn) {
                var idx = parseInt(btn.dataset.tabIndex);
                btn.classList.remove('border-slate-800', 'bg-slate-800', 'text-white',
                    'border-slate-200', 'bg-slate-50', 'text-slate-600');
                if (idx === activeIdx) {
                    btn.classList.add('border-slate-800', 'bg-slate-800', 'text-white');
                } else {
                    btn.classList.add('border-slate-200', 'bg-slate-50', 'text-slate-600');
                }
            });
            document.querySelectorAll('.sheet-panel').forEach(function(panel) {
                var idx = parseInt(panel.dataset.panelIndex);
                panel.classList.toggle('hidden', idx !== activeIdx);
            });
        }

        function closePreviewModal() {
            document.getElementById('previewModal').classList.remove('modal-show');
            document.getElementById('previewModal').classList.add('modal-hide');
        }
    </script>

    {{-- Preview Report Modal --}}
    <div id="previewModal"
        class="modal-hide modal-transition fixed top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[90%] bg-white rounded-3xl shadow-2xl z-[9999] overflow-hidden flex flex-col max-h-[90vh]">
        <div class="bg-slate-800 px-6 py-5 flex items-center justify-between flex-shrink-0">
            <div>
                <p class="text-slate-400 text-xs font-semibold tracking-widest uppercase mb-1">Preview</p>
                <h2 class="text-white text-xl font-semibold">Laporan</h2>
            </div>
            <button onclick="closePreviewModal()"
                class="w-10 h-10 bg-white/10 hover:bg-white/20 rounded-2xl flex items-center justify-center transition-colors">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2"
                    stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
        </div>
        <div id="previewModalContent" class="p-6 overflow-y-auto flex-1 bg-slate-50">
            {{-- Table preview goes here --}}
        </div>
        <div class="p-5 border-t border-slate-100 flex justify-end gap-3 bg-white flex-shrink-0">
            <button onclick="closePreviewModal()"
                class="px-5 py-2.5 rounded-xl font-semibold text-slate-500 bg-slate-100 hover:bg-slate-200 transition-colors">Tutup</button>
            <button onclick="getOrderReport('download')"
                class="px-5 py-2.5 rounded-xl font-semibold text-white bg-[linear-gradient(45deg,_#41a8f4,_#7cd086)] hover:opacity-90 transition-all">Download
                Excel</button>
        </div>
    </div>
    {{-- =========== --}}
    <script>
        let quantitySubmitting = false;

        function showLoading(event) {
            if (quantitySubmitting) {
                event.preventDefault();
                return false;
            }

            quantitySubmitting = true;
            const form = event.target;

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
                btn.disabled = true;
            });

            Swal.fire({
                title: 'Mohon Tunggu...',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            return true;
        }
        let cartSubmitting = false;

        function showLoadingCart(event) {
            if (cartSubmitting) {
                event.preventDefault();
                return false;
            }

            cartSubmitting = true;
            const form = event.target;

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(btn => {
                btn.disabled = true;
            });

            Swal.fire({
                title: 'Mohon Tunggu...',
                didOpen: () => {
                    Swal.showLoading();
                },
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false
            });

            return true;
        }

        function filterItems() {
            const input = document.getElementById('searchInput');
            const filter = input.value.toLowerCase();
            const cards = document.querySelectorAll('#items-container .item-card');

            cards.forEach(card => {
                const itemName = card.querySelector('.item-name')?.textContent.toLowerCase();
                if (!itemName) return; // skip if no name found (safety)
                card.style.display = itemName.includes(filter) || filter === '' ? '' : 'none';
            });
        }

        // Attach once DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('searchInput');
            if (searchInput) {
                searchInput.addEventListener('keyup', filterItems);
            }
        });
    </script>

    @if (session()->has('success'))
        <script>
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: @json(session('success')),
                confirmButtonText: 'OK',
                buttonsStyling: false, // disable SweetAlert default button styling
                customClass: {
                    confirmButton: 'bg-[#3085d6] text-white px-4 py-2 rounded hover:bg-[#2563eb]',
                    title: 'swal-title',
                    popup: 'swal-popup',
                    content: 'swal-text',
                }
            });
        </script>
    @endif
    @if (session()->has('failed'))
        <script>
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: @json(session('failed')),
                confirmButtonText: 'OK',
                buttonsStyling: false, // disable SweetAlert default button styling
                customClass: {
                    confirmButton: 'bg-[#3085d6] text-white px-4 py-2 rounded hover:bg-[#2563eb]',
                    title: 'swal-title',
                    popup: 'swal-popup',
                    content: 'swal-text',
                }
            });
        </script>
    @endif
    @if (session()->has('loading'))
        <script>
            Swal.fire({
                title: 'Mohon tunggu...',
                allowOutsideClick: false,
                didOpen: () => {
                    Swal.showLoading();
                }
            });
        </script>
    @endif

    <script>
        $(document).ready(function() {

            // JS
            new Swiper('.productSwiper', {
                slidesPerView: 2,
                spaceBetween: 8,
                grabCursor: true,
                loop: true,
                navigation: {
                    nextEl: '.swiper-button-next',
                    prevEl: '.swiper-button-prev',
                },
                breakpoints: {
                    640: {
                        slidesPerView: 2,
                    },
                    768: {
                        slidesPerView: 2,
                    },
                    1024: {
                        slidesPerView: 3,
                    },
                },
            });

        });
    </script>
    <script>
        // const MAX_IMAGE_SIZE = 5 * 1024 * 1024; // 5MB

        // const imageInput = document.getElementById('imageInput');
        // const previewContainer = document.getElementById('previewContainer');
        // const previewImage = document.getElementById('previewImage');
        // const removeButton = document.getElementById('removeImage');

        // Handle image preview
        // imageInput.addEventListener('change', function(event) {
        //     const file = event.target.files[0];
        //     if (file) {
        //         if (file.size > MAX_IMAGE_SIZE) {
        //             alert("Gambar Tidak Boleh Lebih Dari 5MB.");
        //             imageInput.value = ""; // Clear file input
        //             previewImage.src = "";
        //             previewContainer.classList.add('hidden');
        //             return;
        //         }

        //         const reader = new FileReader();
        //         reader.onload = function(e) {
        //             previewImage.src = e.target.result;
        //             previewContainer.classList.remove('hidden');
        //         };
        //         reader.readAsDataURL(file);
        //     }
        // });

        // // Handle remove button
        // removeButton.addEventListener('click', function() {
        //     previewImage.src = "";
        //     previewContainer.classList.add('hidden');
        //     imageInput.value = ""; // Clear file input
        // });
    </script>
    @yield('scripts')

    @include('components.footer')

</html>
