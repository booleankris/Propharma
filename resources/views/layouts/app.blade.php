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
    <style>
        .select2-container--open {
            z-index: 9999999 !important;
        }
        .select2-dropdown {
            z-index: 9999999 !important;
        }
        #orderReportModal .select2-container,
        #reportModal .select2-container {
            width: 100% !important;
        }
        #orderReportModal .select2-container .select2-selection--single,
        #reportModal .select2-container .select2-selection--single {
            height: 42px !important;
            border-radius: 0.75rem !important;
            border: 1px solid #e2e8f0 !important;
            background-color: #f8fafc !important;
            display: flex !important;
            align-items: center !important;
        }
        #orderReportModal .select2-container .select2-selection--single .select2-selection__rendered,
        #reportModal .select2-container .select2-selection--single .select2-selection__rendered {
            color: #334155 !important;
            font-size: 0.875rem !important;
            padding-left: 0.75rem !important;
            line-height: 40px !important;
        }
        #orderReportModal .select2-container .select2-selection--single .select2-selection__arrow,
        #reportModal .select2-container .select2-selection--single .select2-selection__arrow {
            height: 40px !important;
            right: 8px !important;
        }
    </style>
    <!-- Favicon & PWA Settings -->
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('img/favicon-32x32.png') }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('img/favicon-16x16.png') }}">
    <link rel="apple-touch-icon" sizes="180x180" href="{{ asset('img/apple-touch-icon.png') }}">
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#1d7ed8">

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

    <!-- Global Session Expired Handler (419 / 401) -->
    <script>
        (function() {
            let isRedirecting = false;
            function handleExpiredSession() {
                if (isRedirecting) return;
                isRedirecting = true;
                if (window.Swal) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Sesi Telah Berakhir',
                        text: 'Sesi Anda telah habis atau Anda telah logout di tab lain. Mengarahkan ke login...',
                        showConfirmButton: false,
                        timer: 1800
                    }).then(() => {
                        window.location.href = "{{ route('login') }}";
                    });
                } else {
                    window.location.href = "{{ route('login') }}";
                }
            }

            if (window.axios) {
                window.axios.interceptors.response.use(
                    response => response,
                    error => {
                        if (error.response && (error.response.status === 419 || error.response.status === 401)) {
                            handleExpiredSession();
                        }
                        return Promise.reject(error);
                    }
                );
            }

            if (window.jQuery) {
                $(document).ajaxError(function(event, jqXHR) {
                    if (jqXHR.status === 419 || jqXHR.status === 401) {
                        handleExpiredSession();
                    }
                });
            }
        })();
    </script>

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
        // Sales Report Filter Configuration
        const salesReportFilters = {
            "LIPH": {
                date_filter: true,
                type_filter: false,
                online_btn: true,
                has_factory: false,
                has_doctor: false
            },
            "Obat": {
                date_filter: true,
                type_filter: true,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            },
            "Golongan": {
                date_filter: false,
                type_filter: false,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            },
            "Pabrik": {
                date_filter: false,
                type_filter: true,
                online_btn: false,
                has_factory: true,
                has_doctor: false
            },
            "Dokter": {
                date_filter: false,
                type_filter: true,
                online_btn: false,
                has_factory: false,
                has_doctor: true
            },
            "Daftar Resep": {
                date_filter: true,
                type_filter: false,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            },
            "Retur Jual": {
                date_filter: false,
                type_filter: false,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            },
            "Bank": {
                date_filter: true,
                type_filter: false,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            },
            "Penjualan Bank": {
                date_filter: true,
                type_filter: false,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            },
        };

        var selectedReport = "LIPH";
        var selectedShift = "";
        var selectedType = 'rekap';
        var selectedShiftType = "shift";

        document.addEventListener("DOMContentLoaded", function() {
            initFactorySelect();
            initDoctorSelect();

            const activeBtn = document.querySelector('.report-btn[data-active="true"]');
            if (activeBtn) {
                const label = activeBtn.querySelector('span.text-sm')?.textContent.trim() || activeBtn.innerText.trim();
                selectedReport = label;
                applySalesFilters(label);
            }
        });

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
            if (id === 'orderReportModal') {
                setTimeout(() => {
                    const activeBtn = document.querySelector('.order-report-btn[data-active="true"]');
                    if (activeBtn) {
                        applyOrderFilters(activeBtn.querySelector('span.text-sm')?.textContent.trim());
                    } else {
                        initOrderSupplierSelect();
                    }
                }, 150);
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
            if (id === 'notif-modal' && !target.classList.contains('hidden')) {
                if (typeof loadStockNotifications === 'function') {
                    loadStockNotifications();
                }
            }
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

        function applySalesFilters(reportLabel) {
            const config = salesReportFilters[reportLabel] ?? {
                date_filter: false,
                type_filter: false,
                online_btn: false,
                has_factory: false,
                has_doctor: false
            };

            const titleEl = document.getElementById('reporttitle');
            if (titleEl) {
                titleEl.textContent = `Laporan Penjualan - ${reportLabel}`;
            }

            const dateFilterEl = document.getElementById('date_filter');
            const shiftSelectEl = document.getElementById('shift-select');
            const typeFilterEl = document.getElementById('type_filter');
            const factorySelectEl = document.getElementById('factory-select');
            const doctorSelectEl = document.getElementById('doctor-select');
            const btnOnline = document.getElementById('btn-online');

            if (dateFilterEl) {
                dateFilterEl.style.display = config.date_filter ? 'block' : 'none';
            }

            if (btnOnline) {
                btnOnline.style.display = config.online_btn ? 'flex' : 'none';
                if (!config.online_btn && selectedShiftType === 'online') {
                    setFilter('semua', document.getElementById('btn-semua'));
                }
            }

            if (shiftSelectEl) {
                shiftSelectEl.style.display = (config.date_filter && (selectedShiftType === 'shift' || selectedShiftType === 'online')) ? 'block' : 'none';
            }

            if (typeFilterEl) {
                typeFilterEl.style.display = config.type_filter ? 'block' : 'none';
            }

            // Always reset type to 'rekap' when switching report
            resetReportTypeOptions();

            // Factory & Doctor only shown on detail
            if (factorySelectEl) factorySelectEl.style.display = 'none';
            if (doctorSelectEl) doctorSelectEl.style.display = 'none';

            if (reportLabel === "Pabrik") {
                setTimeout(() => initFactorySelect(), 100);
            } else if (reportLabel === "Dokter") {
                setTimeout(() => initDoctorSelect(), 100);
            }
        }

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

            selectedReport = label ? label.textContent.trim() : "LIPH";
            applySalesFilters(selectedReport);
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

            if (el) {
                el.classList.remove('border-slate-200', 'bg-slate-50', 'text-slate-600');
                el.classList.add('border-slate-800', 'bg-slate-800', 'text-white');
            }

            selectedShiftType = type;

            const config = salesReportFilters[selectedReport] ?? { date_filter: false };
            if (shiftSel) {
                shiftSel.style.display = (config.date_filter && (type === 'shift' || type === 'online')) ? 'block' : 'none';
            }
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

        function resetReportTypeOptions() {
            selectedType = 'rekap';
            const rekapOpt = document.querySelector('.opt[data-value="rekap"]');
            if (!rekapOpt) return;

            document.querySelectorAll('.opt').forEach(o => {
                o.classList.remove(...active.card);
                o.classList.add(...inactive.card);
                o.querySelector('.icon-box')?.classList.remove(...active.icon);
                o.querySelector('.icon-box')?.classList.add(...inactive.icon);
                o.querySelector('.icon-svg')?.classList.remove(...active.svg);
                o.querySelector('.icon-svg')?.classList.add(...inactive.svg);
                o.querySelector('.opt-title')?.classList.remove(...active.title);
                o.querySelector('.opt-title')?.classList.add(...inactive.title);
                o.querySelector('.opt-desc')?.classList.remove(...active.desc);
                o.querySelector('.opt-desc')?.classList.add(...inactive.desc);
            });

            rekapOpt.classList.remove(...inactive.card);
            rekapOpt.classList.add(...active.card);
            rekapOpt.querySelector('.icon-box')?.classList.remove(...inactive.icon);
            rekapOpt.querySelector('.icon-box')?.classList.add(...active.icon);
            rekapOpt.querySelector('.icon-svg')?.classList.remove(...inactive.svg);
            rekapOpt.querySelector('.icon-svg')?.classList.add(...active.svg);
            rekapOpt.querySelector('.opt-title')?.classList.remove(...inactive.title);
            rekapOpt.querySelector('.opt-title')?.classList.add(...active.title);
            rekapOpt.querySelector('.opt-desc')?.classList.remove(...inactive.desc);
            rekapOpt.querySelector('.opt-desc')?.classList.add(...active.desc);
        }

        function selectOption(el) {
            document.querySelectorAll('.opt').forEach(o => {
                o.classList.remove(...active.card);
                o.classList.add(...inactive.card);
                o.querySelector('.icon-box')?.classList.remove(...active.icon);
                o.querySelector('.icon-box')?.classList.add(...inactive.icon);
                o.querySelector('.icon-svg')?.classList.remove(...active.svg);
                o.querySelector('.icon-svg')?.classList.add(...inactive.svg);
                o.querySelector('.opt-title')?.classList.remove(...active.title);
                o.querySelector('.opt-title')?.classList.add(...inactive.title);
                o.querySelector('.opt-desc')?.classList.remove(...active.desc);
                o.querySelector('.opt-desc')?.classList.add(...inactive.desc);
            });

            el.classList.remove(...inactive.card);
            el.classList.add(...active.card);
            el.querySelector('.icon-box')?.classList.remove(...inactive.icon);
            el.querySelector('.icon-box')?.classList.add(...active.icon);
            el.querySelector('.icon-svg')?.classList.remove(...inactive.svg);
            el.querySelector('.icon-svg')?.classList.add(...active.svg);
            el.querySelector('.opt-title')?.classList.remove(...inactive.title);
            el.querySelector('.opt-title')?.classList.add(...active.title);
            el.querySelector('.opt-desc')?.classList.remove(...inactive.desc);
            el.querySelector('.opt-desc')?.classList.add(...active.desc);

            selectedType = el.dataset.value;

            const factorySelectEl = document.getElementById('factory-select');
            const doctorSelectEl = document.getElementById('doctor-select');

            if (selectedReport === "Pabrik") {
                if (factorySelectEl) {
                    factorySelectEl.style.display = selectedType === "detail" ? 'block' : 'none';
                    if (selectedType === "detail") setTimeout(() => initFactorySelect(), 50);
                }
            } else if (factorySelectEl) {
                factorySelectEl.style.display = 'none';
            }

            if (selectedReport === "Dokter") {
                if (doctorSelectEl) {
                    doctorSelectEl.style.display = selectedType === "detail" ? 'block' : 'none';
                    if (selectedType === "detail") setTimeout(() => initDoctorSelect(), 50);
                }
            } else if (doctorSelectEl) {
                doctorSelectEl.style.display = 'none';
            }

            console.log('Selected type:', selectedType);
        }



        // ─── Orders Report ────────────────────────────────────────────────────────────

        var selectedOrderReport = "Pembelian";
        var selectedOrderType = "rekap";

        // Visibility map: which filters each report type shows
        // Keys match the button label text exactly
        const orderReportFilters = {
            "Pembelian": {
                type_filter: false,
                supplier_select: false
            },
            "Pembelian Faktur": {
                type_filter: true,
                supplier_select: true
            },
            "Konsinyasi": {
                type_filter: false,
                supplier_select: true
            },
            "Tunai": {
                type_filter: false,
                supplier_select: true
            },
            "Jatuh Tempo": {
                type_filter: false,
                supplier_select: true
            },
            // Legacy fallbacks
            "Laporan Pembelian": {
                type_filter: false,
                supplier_select: false
            },
            "Faktur Pembelian": {
                type_filter: true,
                supplier_select: true
            },
        };
        // Order Initial Setup
        document.addEventListener("DOMContentLoaded", function() {
            initOrderSupplierSelect();

            // Set initial state based on data-active button
            const activeBtn = document.querySelector('.order-report-btn[data-active="true"]');
            if (activeBtn) {
                applyOrderFilters(activeBtn.querySelector('span.text-sm')?.textContent.trim());
            }
        });

        function initOrderSupplierSelect() {
            const el = $('#order_supplier');
            if (!el.length) return;
            if (el.hasClass("select2-hidden-accessible")) {
                el.select2('destroy');
            }
            el.select2({
                placeholder: 'Semua PBF / Kreditur',
                allowClear: true,
                width: '100%',
                dropdownParent: $('#orderReportModal')
            });
        }

        function applyOrderFilters(reportLabel) {
            const config = orderReportFilters[reportLabel] ?? {
                type_filter: false,
                supplier_select: false
            };

            const typeFilterEl = document.getElementById('order_type_filter');
            const supplierSelectEl = document.getElementById('order_supplier_select');

            if (typeFilterEl) typeFilterEl.style.display = config.type_filter ? 'block' : 'none';
            if (supplierSelectEl) supplierSelectEl.style.display = config.supplier_select ? 'block' : 'none';

            if (config.supplier_select) {
                initOrderSupplierSelect();
                setTimeout(() => initOrderSupplierSelect(), 100);
            }

            // Reset type to rekap whenever report changes
            selectedOrderType = 'rekap';
            const rekapOpt = document.querySelector('.order-opt[data-value="rekap"]');
            if (rekapOpt) selectOrderOption(rekapOpt);
        }

        function selectOrderReport(el) {
            // Reset all buttons to inactive style
            document.querySelectorAll('.order-report-btn').forEach(btn => {
                btn.removeAttribute('data-active');
                btn.classList.remove('border-violet-200', 'bg-violet-50');
                btn.classList.add('border-slate-100', 'bg-slate-50');
                const lbl = btn.querySelector('span.text-sm');
                if (lbl) {
                    lbl.classList.remove('text-violet-700');
                    lbl.classList.add('text-slate-600');
                }
            });

            // Activate clicked button
            el.setAttribute('data-active', 'true');
            el.classList.remove('border-slate-100', 'bg-slate-50');
            el.classList.add('border-violet-200', 'bg-violet-50');
            const lbl = el.querySelector('span.text-sm');
            if (lbl) {
                lbl.classList.remove('text-slate-600');
                lbl.classList.add('text-violet-700');
            }

            selectedOrderReport = lbl ? lbl.textContent.trim() : null;
            applyOrderFilters(selectedOrderReport);

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
