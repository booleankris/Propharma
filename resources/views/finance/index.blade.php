<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SAHABAT Finances - Modul Keuangan ERP</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>

    <!-- React 18 & Babel Standalone for Realtime SPA -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.development.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.development.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>

    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"Plus Jakarta Sans"', 'sans-serif']
                    },
                    colors: {
                        brand: {
                            blue: '#1e3a8a',
                            bluelight: '#2563eb',
                            orange: '#f97316',
                            orangelight: '#fb923c'
                        }
                    }
                }
            }
        }
    </script>

    <style>
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 10px;
        }

        ::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        input[type="checkbox"] {
            accent-color: #f97316;
        }

        /* Diagonal striped texture for progress bar */
        .striped-progress {
            background-image: linear-gradient(45deg,
                    rgba(255, 255, 255, 0.15) 25%,
                    transparent 25%,
                    transparent 50%,
                    rgba(255, 255, 255, 0.15) 50%,
                    rgba(255, 255, 255, 0.15) 75%,
                    transparent 75%,
                    transparent);
            background-size: 1rem 1rem;
        }

        @keyframes pulseSoft {

            0%,
            100% {
                opacity: 1;
            }

            50% {
                opacity: 0.85;
            }
        }

        .pulse-soft {
            animation: pulseSoft 3s ease-in-out infinite;
        }
    </style>
</head>

<body class="bg-slate-50 text-slate-800 font-sans h-screen flex overflow-hidden antialiased select-none">

    <div id="root" class="w-full h-full flex overflow-hidden"></div>

    <script>
        window.__FINANCE_CONFIG__ = {
            homeUrl: @json(route('home')),
            logoUrl: @json(asset('img/sahabat-main.png')),
            user: {
                name: @json(auth()->user()->name ?? 'Finance User'),
                email: @json(auth()->user()->email ?? 'finance@propharma.com'),
                role: @json(auth()->user()->getRoleNames()->first() ?? 'Finance'),
                initials: @json(substr(auth()->user()->name ?? 'SF', 0, 2)),
                avatarLetter: @json(substr(auth()->user()->name ?? 'F', 0, 1))
            }
        };
    </script>

    @verbatim
        <script type="text/babel">
        const { useState, useEffect, useMemo, useRef } = React;
        const config = window.__FINANCE_CONFIG__ || { user: { name: 'Finance User', role: 'Finance', initials: 'SF', avatarLetter: 'F' }, homeUrl: '#', logoUrl: '/img/sahabat-main.png' };

        // --- SAMPLE & REALISTIC ERP DATA ---
        const INITIAL_HUTANG = [
            {
                id: 1,
                nomor: "14_506659",
                vendor: "PT. MERAPI UTAMA PHARMA",
                referensi: "43857 / REG / IX / 2026",
                tanggal: "04/09/2026",
                jatuhTempo: "19/10/2026",
                tanggalBayar: "",
                status: "Belum Dibayar",
                subtotal: 270000,
                ppn: 29700,
                total: 299700,
                sisa: 299700,
                gudang: "Gudang Farmasi Utama",
                tglKirim: "04/09/2026",
                items: [
                    { sku: "SKU/MUP-0102", nama: "AMOXICILLIN 500MG KAPLET - MUP", qty: 2, satuan: "Box", diskon: "0%", harga: 135000, pajak: "PPN11", jumlah: 299700 }
                ],
                lastModified: "04 Sep 2026 15:09 oleh Apoteker Penanggung Jawab"
            },
            {
                id: 2,
                nomor: "8240431149",
                vendor: "PT. PARIT PADANG GLOBAL",
                referensi: "43856 / REG / IX / 2026",
                tanggal: "04/09/2026",
                jatuhTempo: "04/10/2026",
                tanggalBayar: "",
                status: "Belum Dibayar",
                subtotal: 720000,
                ppn: 79200,
                total: 799200,
                sisa: 799200,
                gudang: "Unassigned",
                tglKirim: "04/09/2026",
                items: [
                    { sku: "SKU/00001", nama: "PENJUALAN DAN Hutang Dagang - PARACETAMOL INFUS", qty: 1, satuan: "Pcs", diskon: "0%", harga: 799200, pajak: "PPN11", jumlah: 799200 }
                ],
                lastModified: "04 Sep 2026 14:32 oleh Finance Staff"
            },
            {
                id: 3,
                nomor: "2620061018149",
                vendor: "PT. SAPTA SARI TAMA",
                referensi: "43854 / REG / IX / 2026",
                tanggal: "04/09/2026",
                jatuhTempo: "25/09/2026",
                tanggalBayar: "",
                status: "Belum Dibayar",
                subtotal: 1680750,
                ppn: 184883,
                total: 1865633,
                sisa: 1865633,
                gudang: "Gudang Etalase Depan",
                tglKirim: "04/09/2026",
                items: [
                    { sku: "SKU/SST-8891", nama: "CEFIXIME 200MG KAPSUL", qty: 5, satuan: "Box", diskon: "5%", harga: 350000, pajak: "PPN11", jumlah: 1865633 }
                ],
                lastModified: "03 Sep 2026 11:15 oleh Gudang"
            },
            {
                id: 4,
                nomor: "19082201994",
                vendor: "PT. ENSEVAL PUTERA MEGATRADING",
                referensi: "43710 / REG / VIII / 2026",
                tanggal: "28/08/2026",
                jatuhTempo: "28/09/2026",
                tanggalBayar: "10/09/2026",
                status: "Dibayar Sebagian",
                subtotal: 4500000,
                ppn: 495000,
                total: 4995000,
                sisa: 2495000,
                gudang: "Gudang Farmasi Utama",
                tglKirim: "28/08/2026",
                items: [
                    { sku: "SKU/ENS-0021", nama: "SANMOL FORTE SYRUP & TABLET", qty: 20, satuan: "Botol", diskon: "2%", harga: 240000, pajak: "PPN11", jumlah: 4995000 }
                ],
                lastModified: "10 Sep 2026 09:40 oleh Finance Manager"
            },
            {
                id: 5,
                nomor: "11902883471",
                vendor: "PT. KIMIA FARMA TRADING & DISTRIBUSI",
                referensi: "43201 / REG / VIII / 2026",
                tanggal: "20/08/2026",
                jatuhTempo: "20/09/2026",
                tanggalBayar: "18/09/2026",
                status: "Lunas",
                subtotal: 3100000,
                ppn: 341000,
                total: 3441000,
                sisa: 0,
                gudang: "Gudang Farmasi Utama",
                tglKirim: "20/08/2026",
                items: [
                    { sku: "SKU/KF-1049", nama: "OBAT GENERIK INDONESIA RESEP", qty: 15, satuan: "Box", diskon: "0%", harga: 220000, pajak: "PPN11", jumlah: 3441000 }
                ],
                lastModified: "18 Sep 2026 16:20 oleh Finance User"
            }
        ];

        const INITIAL_TAGIHAN = [
            { id: 1, nomor: "INV/2026/09/001", debtor: "BPJS Kesehatan KCU Surabaya", pasien: "Klaim Kolektif Rawat Jalan", dokter: "dr. Andi Prasetyo, Sp.A", tanggal: "01/09/2026", jatuhTempo: "30/09/2026", total: 14500000, status: "Menunggu Pembayaran", sisa: 14500000 },
            { id: 2, nomor: "INV/2026/09/002", debtor: "PT. Telkom Indonesia (Yakes)", pasien: "Karyawan & Keluarga", dokter: "dr. Siti Rahmawati", tanggal: "03/09/2026", jatuhTempo: "03/10/2026", total: 6850000, status: "Belum Ditagihkan", sisa: 6850000 },
            { id: 3, nomor: "INV/2026/09/003", debtor: "Asuransi Sinarmas Sehat", pasien: "Ny. Ratna Dewi (Resep Khusus)", dokter: "dr. Budi Santoso, Sp.PD", tanggal: "05/09/2026", jatuhTempo: "20/09/2026", total: 2800000, status: "Lunas", sisa: 0 },
            { id: 4, nomor: "INV/2026/09/004", debtor: "Klinik Pratama Sehat Mandiri", pasien: "Pengadaan Obat Resep Rujukan", dokter: "dr. Maya Indriati", tanggal: "06/09/2026", jatuhTempo: "06/10/2026", total: 5400000, status: "Menunggu Pembayaran", sisa: 5400000 }
        ];

        const INITIAL_BIAYA = [
            { id: 1, kode: "EXP-2026-081", kategori: "Operasional & Utilitas", keterangan: "Tagihan Listrik PLN & Air PDAM Apotek Bulan Agustus", tanggal: "02/09/2026", akun: "Kas Operasional Toko", jumlah: 3450000, status: "Disetujui" },
            { id: 2, kode: "EXP-2026-082", kategori: "Beban Gaji & Honor", keterangan: "Honor Apoteker Pendamping Shift Malam", tanggal: "04/09/2026", akun: "Bank BCA Operasional", jumlah: 2500000, status: "Disetujui" },
            { id: 3, kode: "EXP-2026-083", kategori: "Perlengkapan & Etiket", keterangan: "Cetak Kantong Plastik Obat, Klip Resep, & Struk Kasir", tanggal: "05/09/2026", akun: "Kas Kasir (Drawer)", jumlah: 850000, status: "Disetujui" },
            { id: 4, kode: "EXP-2026-084", kategori: "Pemeliharaan & Sanitasi", keterangan: "Servis Rutin AC Ruang Obat & Kulkas Vaksin", tanggal: "07/09/2026", akun: "Kas Operasional Toko", jumlah: 1200000, status: "Menunggu Approval" }
        ];

        const INITIAL_AKUN = [
            { id: 1, nama: "Kas Kasir (Drawer Shift)", nomor: "KAS-001", tipe: "Kas Tunai", saldo: 2450000, perubahan: "+4.2% hari ini", bank: "Tunai" },
            { id: 2, nama: "Kas Utama Apotek", nomor: "KAS-002", tipe: "Kas Brankas", saldo: 18230000, perubahan: "+1.5% minggu ini", bank: "Tunai" },
            { id: 3, nama: "Bank BCA - Operasional", nomor: "822-019-8821", tipe: "Rekening Giro", saldo: 68500000, perubahan: "+12.8% bulan ini", bank: "BCA" },
            { id: 4, nama: "Bank Mandiri - Penerimaan", nomor: "142-00-998811-2", tipe: "Rekening Tabungan", saldo: 22500000, perubahan: "-2.1% minggu ini", bank: "Mandiri" }
        ];

        const INITIAL_LOGS = [
            { id: 1, waktu: "04 Sep 2026 15:09", user: "Apoteker PJ", aksi: "Memperbarui Tagihan Hutang", detail: "Faktur 14_506659 PT. MERAPI UTAMA PHARMA verifikasi jatuh tempo 19/10/2026", tipe: "hutang" },
            { id: 2, waktu: "04 Sep 2026 14:32", user: "Finance Staff", aksi: "Input Faktur Masuk", detail: "Faktur 8240431149 PT. PARIT PADANG GLOBAL senilai Rp 799.200", tipe: "hutang" },
            { id: 3, waktu: "03 Sep 2026 17:00", user: "Lina (General Manager)", aksi: "Persetujuan Biaya Operasional", detail: "Approval pengeluaran EXP-2026-081 Listrik PLN Rp 3.450.000", tipe: "biaya" },
            { id: 4, waktu: "02 Sep 2026 11:20", user: "Sahabat Finance", aksi: "Klaim Tagihan Diterima", detail: "Penerimaan klaim Asuransi Sinarmas Sehat INV/2026/09/003 Rp 2.800.000", tipe: "tagihan" },
            { id: 5, waktu: "01 Sep 2026 09:15", user: "Apotek Kasir 1", aksi: "Setoran Kas Awal", detail: "Kas masuk drawer shift pagi Rp 1.500.000", tipe: "akun" }
        ];

        function formatIDR(num) {
            return new Intl.NumberFormat('id-ID', { minimumFractionDigits: 0, maximumFractionDigits: 0 }).format(num || 0);
        }

        // =========================================================================
        // MAIN REALTIME SPA COMPONENT
        // =========================================================================
        function FinanceApp() {
            // Navigation State
            const [activeTab, setActiveTab] = useState('beranda'); // 'beranda' | 'hutang' | 'tagihan' | 'biaya' | 'akun' | 'log'
            const [viewMode, setViewMode] = useState('list'); // for Hutang: 'list' | 'detail'
            const [selectedHutang, setSelectedHutang] = useState(INITIAL_HUTANG[1]);

            // Hutang State
            const [hutangList, setHutangList] = useState(INITIAL_HUTANG);
            const [hutangFilter, setHutangFilter] = useState('all'); // 'all' | 'unpaid' | 'partial' | 'paid'
            const [searchHutang, setSearchHutang] = useState('');
            const [selectedHutangCheckboxes, setSelectedHutangCheckboxes] = useState([]);

            // Tagihan State
            const [tagihanList, setTagihanList] = useState(INITIAL_TAGIHAN);
            const [tagihanFilter, setTagihanFilter] = useState('all');
            const [searchTagihan, setSearchTagihan] = useState('');

            // Biaya State
            const [biayaList, setBiayaList] = useState(INITIAL_BIAYA);
            const [showAddBiayaModal, setShowAddBiayaModal] = useState(false);
            const [newBiaya, setNewBiaya] = useState({ kategori: 'Operasional & Utilitas', keterangan: '', jumlah: '', akun: 'Kas Operasional Toko' });

            // Kas & Bank State
            const [akunList, setAkunList] = useState(INITIAL_AKUN);
            const [showTransferModal, setShowTransferModal] = useState(false);
            const [transferData, setTransferData] = useState({ dari: 2, ke: 1, jumlah: '' });

            // Audit Logs State
            const [logsList, setLogsList] = useState(INITIAL_LOGS);

            // Global Search State & Shortcut
            const [globalSearch, setGlobalSearch] = useState('');
            const globalSearchInputRef = useRef(null);

            // Toast Notifications
            const [toast, setToast] = useState(null);

            const showToast = (message, type = 'success') => {
                setToast({ message, type });
                setTimeout(() => setToast(null), 3500);
            };

            // Re-render Lucide icons on DOM updates
            useEffect(() => {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            });

            // ⌘ K Global Shortcut
            useEffect(() => {
                const handleKeyDown = (e) => {
                    if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                        e.preventDefault();
                        if (globalSearchInputRef.current) {
                            globalSearchInputRef.current.focus();
                        }
                    }
                };
                window.addEventListener('keydown', handleKeyDown);
                return () => window.removeEventListener('keydown', handleKeyDown);
            }, []);

            // Handle Detail Hutang
            const handleShowDetail = (item) => {
                setSelectedHutang(item);
                setViewMode('detail');
            };

            // Mark Hutang as Paid (Realtime)
            const handleBayarHutang = (id) => {
                setHutangList(prev => prev.map(item => {
                    if (item.id === id) {
                        const updated = {
                            ...item,
                            status: "Lunas",
                            sisa: 0,
                            tanggalBayar: new Date().toLocaleDateString('id-ID'),
                            lastModified: `${new Date().toLocaleDateString('id-ID')} ${new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })} oleh Finance Staff (Telah Dilunasi)`
                        };
                        if (selectedHutang?.id === id) {
                            setSelectedHutang(updated);
                        }
                        return updated;
                    }
                    return item;
                }));

                // Deduct from Bank BCA Operasional in Kas & Bank
                const target = hutangList.find(h => h.id === id);
                if (target && target.sisa > 0) {
                    setAkunList(prev => prev.map(acc => acc.id === 3 ? { ...acc, saldo: acc.saldo - target.sisa } : acc));
                    
                    // Add to log
                    const newLog = {
                        id: Date.now(),
                        waktu: new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                        user: 'Finance Staff',
                        aksi: 'Pelunasan Tagihan Hutang',
                        detail: `Faktur #${target.nomor} (${target.vendor}) senilai Rp ${formatIDR(target.sisa)} lunas melalui Bank BCA`,
                        tipe: 'hutang'
                    };
                    setLogsList(prev => [newLog, ...prev]);
                }

                showToast(`Tagihan faktur #${id} berhasil ditandai LUNAS! Saldo kas disesuaikan.`);
            };

            // Mark Tagihan as Claimed / Lunas
            const handleKlaimTagihan = (id) => {
                setTagihanList(prev => prev.map(item => {
                    if (item.id === id) {
                        const updated = { ...item, status: 'Lunas', sisa: 0 };
                        // Add to Kas Utama
                        setAkunList(accs => accs.map(acc => acc.id === 3 ? { ...acc, saldo: acc.saldo + item.sisa } : acc));
                        return updated;
                    }
                    return item;
                }));
                const t = tagihanList.find(x => x.id === id);
                if (t) {
                    const newLog = {
                        id: Date.now(),
                        waktu: new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                        user: 'Finance Staff',
                        aksi: 'Penerimaan Klaim Tagihan',
                        detail: `Klaim #${t.nomor} (${t.debtor}) sebesar Rp ${formatIDR(t.total)} masuk rekening operasional`,
                        tipe: 'tagihan'
                    };
                    setLogsList(prev => [newLog, ...prev]);
                }
                showToast(`Klaim piutang #${id} berhasil dicatat Lunas!`);
            };

            // Add Biaya Submit
            const handleAddBiaya = (e) => {
                e.preventDefault();
                if (!newBiaya.keterangan || !newBiaya.jumlah) return;

                const amount = parseInt(newBiaya.jumlah.replace(/\D/g, ''), 10) || 0;
                const newEntry = {
                    id: Date.now(),
                    kode: `EXP-2026-${Math.floor(100 + Math.random() * 900)}`,
                    kategori: newBiaya.kategori,
                    keterangan: newBiaya.keterangan,
                    tanggal: new Date().toLocaleDateString('id-ID'),
                    akun: newBiaya.akun,
                    jumlah: amount,
                    status: 'Disetujui'
                };

                setBiayaList(prev => [newEntry, ...prev]);

                // Deduct from selected account
                setAkunList(prev => prev.map(acc => acc.nama === newBiaya.akun ? { ...acc, saldo: Math.max(0, acc.saldo - amount) } : acc));

                // Add log
                const newLog = {
                    id: Date.now() + 1,
                    waktu: new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                    user: 'Finance Staff',
                    aksi: 'Catat Biaya Pengeluaran',
                    detail: `${newBiaya.keterangan} (${newBiaya.kategori}) senilai Rp ${formatIDR(amount)} via ${newBiaya.akun}`,
                    tipe: 'biaya'
                };
                setLogsList(prev => [newLog, ...prev]);

                setShowAddBiayaModal(false);
                setNewBiaya({ kategori: 'Operasional & Utilitas', keterangan: '', jumlah: '', akun: 'Kas Operasional Toko' });
                showToast('Biaya baru berhasil dicatat dan saldo akun telah terupdate.');
            };

            // Transfer Kas Submit
            const handleTransferKas = (e) => {
                e.preventDefault();
                const amount = parseInt(transferData.jumlah.replace(/\D/g, ''), 10) || 0;
                if (!amount) return;

                const sumber = akunList.find(a => a.id === parseInt(transferData.dari));
                const tujuan = akunList.find(a => a.id === parseInt(transferData.ke));

                if (sumber.saldo < amount) {
                    showToast('Saldo rekening asal tidak mencukupi untuk transfer!', 'error');
                    return;
                }

                setAkunList(prev => prev.map(acc => {
                    if (acc.id === sumber.id) return { ...acc, saldo: acc.saldo - amount };
                    if (acc.id === tujuan.id) return { ...acc, saldo: acc.saldo + amount };
                    return acc;
                }));

                const newLog = {
                    id: Date.now(),
                    waktu: new Date().toLocaleDateString('id-ID') + ' ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }),
                    user: 'Finance Staff',
                    aksi: 'Transfer Saldo Kas & Bank',
                    detail: `Transfer Rp ${formatIDR(amount)} dari ${sumber.nama} ke ${tujuan.nama}`,
                    tipe: 'akun'
                };
                setLogsList(prev => [newLog, ...prev]);

                setShowTransferModal(false);
                setTransferData({ dari: 2, ke: 1, jumlah: '' });
                showToast(`Berhasil transfer Rp ${formatIDR(amount)} ke ${tujuan.nama}`);
            };

            // Filtered Hutang items
            const filteredHutang = useMemo(() => {
                return hutangList.filter(item => {
                    if (hutangFilter === 'unpaid' && item.status !== 'Belum Dibayar') return false;
                    if (hutangFilter === 'partial' && item.status !== 'Dibayar Sebagian') return false;
                    if (hutangFilter === 'paid' && item.status !== 'Lunas') return false;

                    if (searchHutang) {
                        const q = searchHutang.toLowerCase();
                        return (
                            item.nomor.toLowerCase().includes(q) ||
                            item.vendor.toLowerCase().includes(q) ||
                            item.referensi.toLowerCase().includes(q)
                        );
                    }
                    return true;
                });
            }, [hutangList, hutangFilter, searchHutang]);

            // Totals for metrics
            const totalHutangBelumBayar = useMemo(() => {
                return hutangList.filter(i => i.status !== 'Lunas').reduce((acc, c) => acc + c.sisa, 0);
            }, [hutangList]);

            const totalTagihanPiutang = useMemo(() => {
                return tagihanList.filter(i => i.status !== 'Lunas').reduce((acc, c) => acc + c.sisa, 0);
            }, [tagihanList]);

            const totalSaldoKas = useMemo(() => {
                return akunList.reduce((acc, c) => acc + c.saldo, 0);
            }, [akunList]);

            const totalBiayaBulanIni = useMemo(() => {
                return biayaList.reduce((acc, c) => acc + c.jumlah, 0);
            }, [biayaList]);

            return (
                <div className="w-full h-full flex overflow-hidden">
                    {/* TOAST ALERT */}
                    {toast && (
                        <div className={`fixed bottom-6 right-6 z-50 flex items-center gap-3 px-5 py-3 rounded-xl shadow-xl text-sm font-semibold transition-all transform animate-bounce ${toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-slate-900 text-white border border-slate-700'}`}>
                            <i data-lucide={toast.type === 'error' ? "alert-circle" : "check-circle-2"} className={`w-5 h-5 ${toast.type === 'error' ? 'text-red-200' : 'text-emerald-400'}`}></i>
                            <span>{toast.message}</span>
                        </div>
                    )}

                    {/* ========================================================================= */}
                    {/* SIDEBAR NAVIGATION (Kledo Style)                                          */}
                    {/* ========================================================================= */}
                    <aside className="w-64 bg-white border-r border-slate-200 flex flex-col justify-between flex-shrink-0 z-20 hidden md:flex">
                        <div>
                            {/* Brand Header */}
                            <div className="h-16 px-6 flex items-center justify-between border-b border-slate-100">
                                <div className="flex items-center gap-3">
                                    <div className="w-9 h-9 rounded-lg flex items-center justify-center overflow-hidden bg-white shadow-xs border border-slate-200 p-0.5">
                                        <img src={config.logoUrl || '/img/sahabat-main.png'} alt="Logo SAHABAT" className="w-full h-full object-contain" />
                                    </div>
                                    <div className="flex flex-col leading-tight">
                                        <span className="font-extrabold text-blue-900 text-sm tracking-wide">SAHABAT</span>
                                        <span className="text-orange-500 font-bold text-xs tracking-wider">Finances</span>
                                    </div>
                                </div>
                                <span className="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 border border-blue-100 uppercase">ERP</span>
                            </div>

                            {/* Nav Links */}
                            <nav className="p-4 space-y-1">
                                <button
                                    onClick={() => { setActiveTab('beranda'); setViewMode('list'); }}
                                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all text-left ${activeTab === 'beranda' ? 'bg-blue-50 text-blue-700 font-semibold shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-700'}`}>
                                    <i data-lucide="layout-dashboard" className={`w-5 h-5 ${activeTab === 'beranda' ? 'text-blue-600' : ''}`}></i>
                                    <span>Beranda</span>
                                </button>

                                <button
                                    onClick={() => { setActiveTab('hutang'); setViewMode('list'); }}
                                    className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg font-medium transition-all text-left ${activeTab === 'hutang' ? 'bg-blue-50 text-blue-700 font-semibold shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-700'}`}>
                                    <div className="flex items-center gap-3">
                                        <i data-lucide="shopping-bag" className={`w-5 h-5 ${activeTab === 'hutang' ? 'text-blue-600' : ''}`}></i>
                                        <span>Hutang Dagang</span>
                                    </div>
                                    {totalHutangBelumBayar > 0 && (
                                        <span className="text-[10px] px-2 py-0.5 rounded-full font-bold bg-red-100 text-red-600">
                                            {hutangList.filter(i => i.status !== 'Lunas').length}
                                        </span>
                                    )}
                                </button>

                                <button
                                    onClick={() => { setActiveTab('tagihan'); setViewMode('list'); }}
                                    className={`w-full flex items-center justify-between px-3 py-2.5 rounded-lg font-medium transition-all text-left ${activeTab === 'tagihan' ? 'bg-blue-50 text-blue-700 font-semibold shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-700'}`}>
                                    <div className="flex items-center gap-3">
                                        <i data-lucide="receipt" className={`w-5 h-5 ${activeTab === 'tagihan' ? 'text-blue-600' : ''}`}></i>
                                        <span>Tagihan (Piutang)</span>
                                    </div>
                                    <span className="text-[10px] px-2 py-0.5 rounded-full font-bold bg-amber-100 text-amber-700">
                                        {tagihanList.filter(i => i.status !== 'Lunas').length}
                                    </span>
                                </button>

                                <button
                                    onClick={() => { setActiveTab('biaya'); setViewMode('list'); }}
                                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all text-left ${activeTab === 'biaya' ? 'bg-blue-50 text-blue-700 font-semibold shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-700'}`}>
                                    <i data-lucide="credit-card" className={`w-5 h-5 ${activeTab === 'biaya' ? 'text-blue-600' : ''}`}></i>
                                    <span>Biaya (Expenses)</span>
                                </button>

                                <button
                                    onClick={() => { setActiveTab('akun'); setViewMode('list'); }}
                                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all text-left ${activeTab === 'akun' ? 'bg-blue-50 text-blue-700 font-semibold shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-700'}`}>
                                    <i data-lucide="wallet" className={`w-5 h-5 ${activeTab === 'akun' ? 'text-blue-600' : ''}`}></i>
                                    <span>Kas & Bank (Akun)</span>
                                </button>

                                <button
                                    onClick={() => { setActiveTab('log'); setViewMode('list'); }}
                                    className={`w-full flex items-center gap-3 px-3 py-2.5 rounded-lg font-medium transition-all text-left ${activeTab === 'log' ? 'bg-blue-50 text-blue-700 font-semibold shadow-xs' : 'text-slate-500 hover:bg-slate-50 hover:text-blue-700'}`}>
                                    <i data-lucide="history" className={`w-5 h-5 ${activeTab === 'log' ? 'text-blue-600' : ''}`}></i>
                                    <span>Log / Riwayat</span>
                                </button>
                            </nav>
                        </div>

                        {/* Bottom Section (Return to Apotek & Profile Card) */}
                        <div className="p-4 space-y-3 border-t border-slate-100">
                            <a
                                href={config.homeUrl}
                                className="flex items-center justify-between px-3.5 py-2.5 rounded-lg text-slate-700 bg-slate-100 hover:bg-orange-50 hover:text-orange-600 border border-slate-200 transition-colors text-xs font-semibold group">
                                <div className="flex items-center gap-2">
                                    <i data-lucide="arrow-left-circle" className="w-4 h-4 text-slate-500 group-hover:text-orange-600"></i>
                                    <span>Kembali ke Apotek</span>
                                </div>
                                <span className="text-[10px] text-slate-400 group-hover:text-orange-500">POS</span>
                            </a>

                            {/* Executive Card (from design.md) */}
                            <div className="relative rounded-xl bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-950 p-4 text-white overflow-hidden shadow-lg shadow-blue-900/10">
                                <div className="absolute top-0 right-0 w-24 h-24 bg-white/10 rounded-full blur-xl pointer-events-none"></div>
                                <div className="flex items-center gap-3 relative z-10">
                                    <div className="w-9 h-9 rounded-lg bg-white/20 backdrop-blur-md flex items-center justify-center font-bold text-sm text-white">
                                        {config.user.initials}
                                    </div>
                                    <div className="leading-tight">
                                        <p className="text-xs font-bold text-white truncate max-w-[120px]">{config.user.name}</p>
                                        <span className="text-[10px] text-blue-200 font-medium capitalize">{config.user.role}</span>
                                    </div>
                                </div>
                                
                            </div>
                        </div>
                    </aside>

                    {/* ========================================================================= */}
                    {/* MAIN CONTENT AREA                                                         */}
                    {/* ========================================================================= */}
                    <main className="flex-1 flex flex-col h-screen overflow-hidden relative">

                        {/* ========================================================================= */}
                        {/* GLOBAL STICKY HEADER                                                      */}
                        {/* ========================================================================= */}
                        <header className="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-8 sticky top-0 z-10">
                            <div className="flex items-center gap-4">
                                <div className="relative w-80">
                                    <i data-lucide="search" className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                    <input
                                        ref={globalSearchInputRef}
                                        type="text"
                                        placeholder="Pencarian global transaksi, faktur, vendor..."
                                        value={globalSearch}
                                        onChange={(e) => setGlobalSearch(e.target.value)}
                                        className="w-full bg-slate-100 text-sm rounded-lg pl-9 pr-12 py-2 focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all font-regular text-slate-700"
                                    />
                                    <div className="absolute right-2 top-1/2 transform -translate-y-1/2 bg-white px-1.5 py-0.5 rounded text-[10px] font-medium text-slate-400 border border-slate-200 shadow-xs">
                                        ⌘ K
                                    </div>
                                </div>
                                <div className="hidden lg:flex items-center gap-2 text-xs font-medium text-slate-500 bg-slate-50 px-3 py-1.5 rounded-lg border border-slate-200">
                                    <i data-lucide="calendar" className="w-3.5 h-3.5 text-slate-400"></i>
                                    <span>Periode Buku: <strong>September 2026</strong></span>
                                </div>
                            </div>

                            <div className="flex items-center gap-3">
                                <button
                                    onClick={() => showToast('Sinkronisasi data otomatis dengan server berhasil!')}
                                    title="Sinkronisasi Data"
                                    className="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors">
                                    <i data-lucide="refresh-cw" className="w-4 h-4"></i>
                                </button>
                                <button
                                    onClick={() => showToast('Tidak ada notifikasi anomali finansial saat ini')}
                                    className="p-2 text-slate-500 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors relative">
                                    <i data-lucide="bell" className="w-4 h-4"></i>
                                    <span className="w-2 h-2 rounded-full bg-orange-500 absolute top-1.5 right-1.5 ring-2 ring-white"></span>
                                </button>
                                <div className="h-6 w-px bg-slate-200 mx-1"></div>
                                <div className="flex items-center gap-2.5 pl-1">
                                    <div className="w-8 h-8 rounded-full bg-blue-100 border border-blue-200 flex items-center justify-center text-blue-800 font-bold text-xs">
                                        {config.user.avatarLetter}
                                    </div>
                                    <div className="hidden sm:block text-left leading-tight">
                                        <div className="text-xs font-bold text-slate-800">{config.user.name}</div>
                                        <div className="text-[10px] text-slate-400">{config.user.email}</div>
                                    </div>
                                </div>
                            </div>
                        </header>

                        {/* ========================================================================= */}
                        {/* VIEW 1: BERANDA (EXECUTIVE DASHBOARD)                                     */}
                        {/* ========================================================================= */}
                        {activeTab === 'beranda' && (
                            <div className="flex-1 overflow-auto p-8 flex flex-col gap-6">
                                {/* Welcome & Quick Action Bar */}
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h1 className="text-2xl font-extrabold text-slate-900 tracking-tight">Ringkasan Finansial Eksekutif</h1>
                                        <p className="text-xs text-slate-500 mt-0.5">Monitoring perputaran kas, performa pengeluaran, hutang dagang, dan piutang apotek.</p>
                                    </div>
                                    <div className="flex items-center gap-2.5">
                                        <button
                                            onClick={() => { setActiveTab('hutang'); setViewMode('list'); }}
                                            className="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-xs transition-all">
                                            <i data-lucide="shopping-bag" className="w-4 h-4 text-blue-600"></i> Bayar Hutang
                                        </button>
                                        <button
                                            onClick={() => setShowAddBiayaModal(true)}
                                            className="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-orange-500 rounded-lg hover:bg-orange-600 shadow-sm shadow-orange-500/20 transition-all">
                                            <i data-lucide="plus" className="w-4 h-4"></i> Catat Biaya
                                        </button>
                                        <button
                                            onClick={() => setShowTransferModal(true)}
                                            className="flex items-center gap-2 px-3.5 py-2 text-xs font-semibold text-white bg-blue-800 rounded-lg hover:bg-blue-900 shadow-sm transition-all">
                                            <i data-lucide="arrow-right-left" className="w-4 h-4"></i> Transfer Kas
                                        </button>
                                    </div>
                                </div>

                                {/* TOP METRIC CARDS (design.md specification) */}
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    {/* Card 1: Total Pemasukan (Biru Tua Gradient with Glow) */}
                                    <div className="relative overflow-hidden rounded-2xl bg-gradient-to-br from-blue-900 via-blue-800 to-indigo-950 p-6 text-white shadow-lg shadow-blue-900/10 border border-blue-800">
                                        <div className="absolute -top-12 -right-12 w-40 h-40 bg-white/5 blur-2xl rounded-full pointer-events-none"></div>
                                        <div className="flex items-center justify-between mb-4">
                                            <span className="text-xs font-semibold text-blue-200 tracking-wide uppercase">Total Pemasukan (Bulan Ini)</span>
                                            <div className="w-10 h-10 rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center">
                                                <i data-lucide="arrow-down-left" className="w-5 h-5 text-emerald-300"></i>
                                            </div>
                                        </div>
                                        <div className="text-3xl font-extrabold text-white tracking-tight">Rp 184.520.000</div>
                                        <div className="mt-3 flex items-center gap-2 text-xs text-blue-200">
                                            <span className="inline-flex items-center gap-1 font-bold text-emerald-300 bg-emerald-500/20 px-2 py-0.5 rounded">
                                                <i data-lucide="trending-up" className="w-3 h-3"></i> +12.4%
                                            </span>
                                            <span>dibanding bulan lalu</span>
                                        </div>
                                    </div>

                                    {/* Card 2: Total Pengeluaran (White card with Orange Accent) */}
                                    <div className="rounded-2xl bg-white p-6 shadow-xs border border-slate-200 hover:border-blue-200 transition-all">
                                        <div className="flex items-center justify-between mb-4">
                                            <span className="text-xs font-semibold text-slate-500 tracking-wide uppercase">Total Pengeluaran & Biaya</span>
                                            <div className="w-10 h-10 rounded-xl bg-orange-50 border border-orange-100 flex items-center justify-center text-orange-500">
                                                <i data-lucide="arrow-up-right" className="w-5 h-5"></i>
                                            </div>
                                        </div>
                                        <div className="text-3xl font-extrabold text-slate-900 tracking-tight">
                                            Rp {formatIDR(72840000 + totalBiayaBulanIni)}
                                        </div>
                                        <div className="mt-3 flex items-center gap-2 text-xs text-slate-500">
                                            <span className="inline-flex items-center gap-1 font-bold text-red-600 bg-red-50 px-2 py-0.5 rounded border border-red-100">
                                                <i data-lucide="trending-up" className="w-3 h-3"></i> +17%
                                            </span>
                                            <span>kenaikan restock distributor</span>
                                        </div>
                                    </div>

                                    {/* Card 3: Saldo Kas & Bank (White card with Blue Wallet) */}
                                    <div className="rounded-2xl bg-white p-6 shadow-xs border border-slate-200 hover:border-blue-200 transition-all">
                                        <div className="flex items-center justify-between mb-4">
                                            <span className="text-xs font-semibold text-slate-500 tracking-wide uppercase">Total Saldo Kas & Bank</span>
                                            <div className="w-10 h-10 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                                                <i data-lucide="wallet" className="w-5 h-5"></i>
                                            </div>
                                        </div>
                                        <div className="text-3xl font-extrabold text-slate-900 tracking-tight">
                                            Rp {formatIDR(totalSaldoKas)}
                                        </div>
                                        <div className="mt-3 flex items-center justify-between text-xs text-slate-500">
                                            <span>Likuiditas kas apotek aman</span>
                                            <button
                                                onClick={() => setActiveTab('akun')}
                                                className="font-bold text-blue-600 hover:underline flex items-center gap-1">
                                                Detail Akun <i data-lucide="chevron-right" className="w-3 h-3"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {/* SECONDARY METRICS: Hutang vs Piutang */}
                                <div className="grid grid-cols-1 md:grid-cols-2 gap-5">
                                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                                        <div className="space-y-1">
                                            <span className="text-xs font-bold text-slate-500 uppercase">Hutang Dagang Belum Bayar (AP)</span>
                                            <div className="text-2xl font-extrabold text-red-600">Rp {formatIDR(totalHutangBelumBayar)}</div>
                                            <p className="text-xs text-slate-500">{hutangList.filter(i => i.status !== 'Lunas').length} faktur distributor menunggu pembayaran</p>
                                        </div>
                                        <button
                                            onClick={() => { setActiveTab('hutang'); setHutangFilter('unpaid'); }}
                                            className="px-3 py-2 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                            Kelola Hutang
                                        </button>
                                    </div>

                                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs flex items-center justify-between">
                                        <div className="space-y-1">
                                            <span className="text-xs font-bold text-slate-500 uppercase">Piutang Tagihan Klaim (AR)</span>
                                            <div className="text-2xl font-extrabold text-amber-600">Rp {formatIDR(totalTagihanPiutang)}</div>
                                            <p className="text-xs text-slate-500">{tagihanList.filter(i => i.status !== 'Lunas').length} klaim asuransi & instansi aktif</p>
                                        </div>
                                        <button
                                            onClick={() => { setActiveTab('tagihan'); setTagihanFilter('all'); }}
                                            className="px-3 py-2 text-xs font-bold text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg transition-colors">
                                            Kelola Tagihan
                                        </button>
                                    </div>
                                </div>

                                {/* CHARTS & VISUALIZATIONS (design.md specification) */}
                                <div className="grid grid-cols-1 xl:grid-cols-3 gap-6">
                                    {/* Volume Transaksi (Bar Chart) */}
                                    <div className="xl:col-span-2 bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                                        <div className="flex items-center justify-between mb-6">
                                            <div>
                                                <h3 className="font-bold text-base text-slate-800">Volume Transaksi Penjualan & Pembelian</h3>
                                                <p className="text-xs text-slate-500">Estimasi omset bulanan tahun berjalan (2026)</p>
                                            </div>
                                            <span className="text-xs font-bold text-blue-700 bg-blue-50 px-2.5 py-1 rounded-lg border border-blue-100">Aktif: Sept 2026</span>
                                        </div>

                                        {/* CSS Bar Chart */}
                                        <div className="h-56 flex items-end justify-between gap-3 pt-8 pb-2 px-2 border-b border-slate-100">
                                            {[
                                                { m: 'Jan', h: '45%', val: 'Rp 110M' },
                                                { m: 'Feb', h: '55%', val: 'Rp 128M' },
                                                { m: 'Mar', h: '50%', val: 'Rp 120M' },
                                                { m: 'Apr', h: '65%', val: 'Rp 145M' },
                                                { m: 'Mei', h: '85%', val: 'Rp 184M', active: false },
                                                { m: 'Jun', h: '70%', val: 'Rp 155M' },
                                                { m: 'Jul', h: '75%', val: 'Rp 162M' },
                                                { m: 'Agu', h: '80%', val: 'Rp 175M' },
                                                { m: 'Sep', h: '92%', val: 'Rp 184M', active: true },
                                                { m: 'Okt', h: '30%', val: 'Estimasi' },
                                                { m: 'Nov', h: '25%', val: 'Estimasi' },
                                                { m: 'Des', h: '35%', val: 'Estimasi' }
                                            ].map((col, idx) => (
                                                <div key={idx} className="flex-1 flex flex-col items-center gap-2 group relative cursor-pointer">
                                                    {/* Tooltip on hover */}
                                                    <div className="absolute -top-9 opacity-0 group-hover:opacity-100 transition-opacity bg-slate-900 text-white text-[10px] font-bold px-2 py-1 rounded pointer-events-none whitespace-nowrap z-10 shadow-lg">
                                                        {col.m}: {col.val}
                                                    </div>
                                                    <div className="w-full bg-slate-100 rounded-t-lg h-44 flex items-end overflow-hidden">
                                                        <div
                                                            style={{ height: col.h }}
                                                            className={`w-full rounded-t-md transition-all duration-500 ${col.active ? 'bg-blue-800 group-hover:bg-blue-900' : 'bg-slate-200 group-hover:bg-blue-300'}`}>
                                                        </div>
                                                    </div>
                                                    <span className={`text-[11px] font-semibold ${col.active ? 'text-blue-700 font-bold' : 'text-slate-400'}`}>{col.m}</span>
                                                </div>
                                            ))}
                                        </div>

                                        {/* Financial Summary Progress Bar with Striped Texture (design.md) */}
                                        <div className="mt-6 space-y-2">
                                            <div className="flex justify-between items-center text-xs">
                                                <span className="font-bold text-slate-700">Alokasi Kas Keluar</span>
                                                <span className="text-slate-500">100% Total Anggaran</span>
                                            </div>
                                            <div className="w-full h-3 rounded-full overflow-hidden flex shadow-inner bg-slate-100">
                                                <div style={{ width: '50%' }} className="h-full bg-orange-500 striped-progress" title="Stok Obat (50%)"></div>
                                                <div style={{ width: '30%' }} className="h-full bg-blue-800 striped-progress" title="Gaji & Operasional (30%)"></div>
                                                <div style={{ width: '20%' }} className="h-full bg-slate-400 striped-progress" title="Cadangan Kas (20%)"></div>
                                            </div>
                                            <div className="flex items-center gap-4 text-[11px] text-slate-500 pt-1">
                                                <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-full bg-orange-500"></span> Pembelian Stok (50%)</span>
                                                <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-full bg-blue-800"></span> Operasional (30%)</span>
                                                <span className="flex items-center gap-1.5"><span className="w-2.5 h-2.5 rounded-full bg-slate-400"></span> Cadangan (20%)</span>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Kategori Biaya (SVG Donut Chart) */}
                                    <div className="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between">
                                        <div>
                                            <h3 className="font-bold text-base text-slate-800">Komposisi Pengeluaran</h3>
                                            <p className="text-xs text-slate-500 mb-6">Distribusi biaya apotek bulan berjalan</p>

                                            {/* SVG Donut Chart */}
                                            <div className="flex items-center justify-center relative my-2">
                                                <svg width="180" height="180" viewBox="0 0 100 100" className="transform -rotate-90">
                                                    {/* Background Circle */}
                                                    <circle cx="50" cy="50" r="38" stroke="#f1f5f9" strokeWidth="14" fill="transparent" />
                                                    {/* Segment 1: Pembelian Obat 65% (orange-500) */}
                                                    <circle cx="50" cy="50" r="38" stroke="#f97316" strokeWidth="14" fill="transparent"
                                                        strokeDasharray="155.19 238.76" strokeDashoffset="0" />
                                                    {/* Segment 2: Operasional 18% (blue-800) */}
                                                    <circle cx="50" cy="50" r="38" stroke="#1e3a8a" strokeWidth="14" fill="transparent"
                                                        strokeDasharray="42.97 238.76" strokeDashoffset="-155.19" />
                                                    {/* Segment 3: Gaji 12% (blue-500) */}
                                                    <circle cx="50" cy="50" r="38" stroke="#3b82f6" strokeWidth="14" fill="transparent"
                                                        strokeDasharray="28.65 238.76" strokeDashoffset="-198.16" />
                                                    {/* Segment 4: Lain-lain 5% (slate-400) */}
                                                    <circle cx="50" cy="50" r="38" stroke="#94a3b8" strokeWidth="14" fill="transparent"
                                                        strokeDasharray="11.93 238.76" strokeDashoffset="-226.81" />
                                                </svg>
                                                <div className="absolute inset-0 flex flex-col items-center justify-center pointer-events-none">
                                                    <span className="text-xl font-extrabold text-slate-900">65%</span>
                                                    <span className="text-[10px] font-semibold text-slate-400 uppercase">Stok Obat</span>
                                                </div>
                                            </div>
                                        </div>

                                        {/* Legend List */}
                                        <div className="space-y-2 pt-4 border-t border-slate-100 text-xs">
                                            <div className="flex justify-between items-center">
                                                <span className="flex items-center gap-2 text-slate-600"><span className="w-2.5 h-2.5 rounded bg-orange-500"></span> Pembelian Obat/PBF</span>
                                                <span className="font-bold text-slate-800">65%</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="flex items-center gap-2 text-slate-600"><span className="w-2.5 h-2.5 rounded bg-blue-800"></span> Operasional Apotek</span>
                                                <span className="font-bold text-slate-800">18%</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="flex items-center gap-2 text-slate-600"><span className="w-2.5 h-2.5 rounded bg-blue-500"></span> Gaji & Honor Shift</span>
                                                <span className="font-bold text-slate-800">12%</span>
                                            </div>
                                            <div className="flex justify-between items-center">
                                                <span className="flex items-center gap-2 text-slate-600"><span className="w-2.5 h-2.5 rounded bg-slate-400"></span> Logistik & Lainnya</span>
                                                <span className="font-bold text-slate-800">5%</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                {/* RECENT JOURNAL / TRANSACTIONS TABLE */}
                                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                    <div className="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                                        <div>
                                            <h3 className="font-bold text-base text-slate-800">Riwayat Jurnal Keuangan Terkini</h3>
                                            <p className="text-xs text-slate-500">Aktivitas pembukuan otomatis dari operasional apotek</p>
                                        </div>
                                        <button
                                            onClick={() => setActiveTab('log')}
                                            className="text-xs font-bold text-blue-700 hover:text-blue-900 flex items-center gap-1">
                                            Lihat Semua Log <i data-lucide="arrow-right" className="w-3.5 h-3.5"></i>
                                        </button>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm whitespace-nowrap">
                                            <thead className="bg-slate-50 border-b border-slate-100 text-slate-600 font-semibold text-xs">
                                                <tr>
                                                    <th className="py-3.5 px-6 w-12 text-center"><input type="checkbox" className="w-4 h-4 rounded border-slate-300" /></th>
                                                    <th className="py-3.5 px-4">Waktu</th>
                                                    <th className="py-3.5 px-4">Keterangan Transaksi</th>
                                                    <th className="py-3.5 px-4">Tipe Akun</th>
                                                    <th className="py-3.5 px-4">User</th>
                                                    <th className="py-3.5 px-6 text-right">Nominal</th>
                                                    <th className="py-3.5 px-4 text-center">Status</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                                {logsList.slice(0, 5).map(log => (
                                                    <tr key={log.id} className="hover:bg-slate-50 transition-colors">
                                                        <td className="py-3.5 px-6 text-center"><input type="checkbox" className="w-4 h-4 rounded border-slate-300" /></td>
                                                        <td className="py-3.5 px-4 text-xs text-slate-500">{log.waktu}</td>
                                                        <td className="py-3.5 px-4 font-medium text-slate-800 flex items-center gap-2.5">
                                                            <div className="w-7 h-7 rounded-full bg-slate-100 flex items-center justify-center text-xs font-bold text-slate-600">
                                                                {log.aksi[0]}
                                                            </div>
                                                            <div className="leading-tight">
                                                                <div className="font-semibold text-xs text-slate-800">{log.aksi}</div>
                                                                <div className="text-[11px] text-slate-400 truncate max-w-sm">{log.detail}</div>
                                                            </div>
                                                        </td>
                                                        <td className="py-3.5 px-4 text-xs font-medium text-slate-600 uppercase">{log.tipe}</td>
                                                        <td className="py-3.5 px-4 text-xs text-slate-600">{log.user}</td>
                                                        <td className="py-3.5 px-6 text-right font-bold text-xs text-slate-900">
                                                            Rp {formatIDR(log.detail.match(/\d+/g) ? parseInt(log.detail.match(/\d+/g).join(''), 10) : 799200)}
                                                        </td>
                                                        <td className="py-3.5 px-4 text-center">
                                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-600 border border-emerald-100">
                                                                Berhasil
                                                            </span>
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* VIEW 2: HUTANG DAGANG (EXACT USER TEMPLATE IMPLEMENTATION)                */}
                        {/* ========================================================================= */}
                        {activeTab === 'hutang' && viewMode === 'list' && (
                            <div id="view-list" className="flex-1 flex flex-col h-full overflow-hidden">
                                <div className="flex-1 overflow-auto p-8 flex flex-col gap-6">
                                    {/* Header & Actions */}
                                    <div className="flex flex-col xl:flex-row xl:items-center justify-between gap-4">
                                        <h1 className="text-3xl font-extrabold text-slate-900">Tagihan Hutang Dagang</h1>
                                        <div className="flex flex-wrap items-center gap-2">
                                            <button
                                                onClick={() => showToast('Membuka rekap laporan hutang dagang...')}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                                                <i data-lucide="bar-chart" className="w-4 h-4"></i> Laporan <i data-lucide="chevron-down" className="w-3 h-3"></i>
                                            </button>
                                            <button
                                                onClick={() => showToast('Panduan penggunaan modul hutang Kledo')}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                                                <i data-lucide="help-circle" className="w-4 h-4"></i> Panduan <i data-lucide="chevron-down" className="w-3 h-3"></i>
                                            </button>
                                            <div className="flex rounded-lg shadow-xs">
                                                <button
                                                    onClick={() => showToast('Membuka form pesanan / faktur baru')}
                                                    className="flex items-center gap-2 px-4 py-2 text-sm font-semibold text-white bg-blue-800 rounded-l-lg hover:bg-blue-900 border-r border-blue-700 transition-colors">
                                                    <i data-lucide="plus" className="w-4 h-4"></i> Tambah
                                                </button>
                                                <button
                                                    onClick={() => showToast('Opsi tambahan pembuatan faktur')}
                                                    className="px-2 py-2 text-sm text-white bg-blue-800 rounded-r-lg hover:bg-blue-900 transition-colors">
                                                    <i data-lucide="chevron-down" className="w-4 h-4"></i>
                                                </button>
                                            </div>
                                            <button
                                                onClick={() => showToast('Import faktur Excel/CSV siap')}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                                                <i data-lucide="upload" className="w-4 h-4"></i> Import <i data-lucide="chevron-down" className="w-3 h-3"></i>
                                            </button>
                                            <button
                                                onClick={() => window.print()}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                                                <i data-lucide="printer" className="w-4 h-4"></i> Print
                                            </button>
                                            <button
                                                onClick={() => showToast('Opsi lainnya')}
                                                className="flex items-center px-2 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                                                <i data-lucide="more-vertical" className="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {/* Filters */}
                                    <div className="flex flex-col lg:flex-row justify-between items-center gap-4 bg-white p-4 rounded-xl shadow-xs border border-slate-200">
                                        <div className="flex items-center gap-2">
                                            <button
                                                onClick={() => showToast('Filter lanjutan diterapkan')}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                                <i data-lucide="filter" className="w-4 h-4"></i> Filter
                                            </button>
                                            <button className="p-2 text-slate-500 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                                <i data-lucide="list" className="w-4 h-4"></i>
                                            </button>
                                            <div className="relative">
                                                <select className="pl-3 pr-8 py-2 border border-slate-200 rounded-lg text-sm text-slate-700 appearance-none bg-white focus:outline-none focus:ring-2 focus:ring-blue-600 font-medium">
                                                    <option>Tagihan Hutang Dagang</option>
                                                    <option>Pemesanan Pembelian</option>
                                                    <option>Retur Pembelian</option>
                                                </select>
                                                <i data-lucide="chevron-down" className="w-4 h-4 text-slate-400 absolute right-2 top-1/2 transform -translate-y-1/2 pointer-events-none"></i>
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-3 w-full lg:w-auto">
                                            <div className="relative w-full lg:w-64">
                                                <i data-lucide="search" className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                                <input
                                                    type="text"
                                                    placeholder="Cari faktur / vendor..."
                                                    value={searchHutang}
                                                    onChange={(e) => setSearchHutang(e.target.value)}
                                                    className="w-full border border-slate-200 rounded-lg pl-9 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-600 transition-all bg-white text-slate-700"
                                                />
                                            </div>
                                            <div className="flex items-center gap-2 border border-slate-200 rounded-lg px-3 py-2 text-sm text-slate-600 bg-white">
                                                <span>05/09/2025</span>
                                                <i data-lucide="minus" className="w-3 h-3 text-slate-300"></i>
                                                <span>05/09/2026</span>
                                                <i data-lucide="calendar" className="w-4 h-4 text-slate-400 ml-2"></i>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Table Data */}
                                    <div className="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden flex flex-col">
                                        <div className="flex items-center justify-between border-b border-slate-200 px-2 overflow-x-auto">
                                            <nav className="flex space-x-1">
                                                <button
                                                    onClick={() => setHutangFilter('all')}
                                                    className={`px-4 py-3 text-sm whitespace-nowrap ${hutangFilter === 'all' ? 'font-semibold text-blue-700 border-b-2 border-blue-700 bg-blue-50/50' : 'font-medium text-slate-500 border-b-2 border-transparent hover:text-slate-700'}`}>
                                                    Semua ({hutangList.length})
                                                </button>
                                                <button
                                                    onClick={() => setHutangFilter('unpaid')}
                                                    className={`px-4 py-3 text-sm whitespace-nowrap ${hutangFilter === 'unpaid' ? 'font-semibold text-blue-700 border-b-2 border-blue-700 bg-blue-50/50' : 'font-medium text-slate-500 border-b-2 border-transparent hover:text-slate-700'}`}>
                                                    Belum Dibayar ({hutangList.filter(i => i.status === 'Belum Dibayar').length})
                                                </button>
                                                <button
                                                    onClick={() => setHutangFilter('partial')}
                                                    className={`px-4 py-3 text-sm whitespace-nowrap ${hutangFilter === 'partial' ? 'font-semibold text-blue-700 border-b-2 border-blue-700 bg-blue-50/50' : 'font-medium text-slate-500 border-b-2 border-transparent hover:text-slate-700'}`}>
                                                    Dibayar Sebagian ({hutangList.filter(i => i.status === 'Dibayar Sebagian').length})
                                                </button>
                                                <button
                                                    onClick={() => setHutangFilter('paid')}
                                                    className={`px-4 py-3 text-sm whitespace-nowrap ${hutangFilter === 'paid' ? 'font-semibold text-blue-700 border-b-2 border-blue-700 bg-blue-50/50' : 'font-medium text-slate-500 border-b-2 border-transparent hover:text-slate-700'}`}>
                                                    Lunas ({hutangList.filter(i => i.status === 'Lunas').length})
                                                </button>
                                                <button className="px-4 py-3 text-sm font-medium text-slate-500 border-b-2 border-transparent hover:text-slate-700 whitespace-nowrap flex items-center gap-1">
                                                    Lainnya <i data-lucide="chevron-down" className="w-3 h-3"></i>
                                                </button>
                                            </nav>
                                            <div className="px-4">
                                                <button
                                                    onClick={() => showToast('Ubah massal status dipilih')}
                                                    className="flex items-center gap-2 px-3 py-1.5 text-xs font-medium text-slate-600 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 transition-colors">
                                                    <i data-lucide="edit-3" className="w-3.5 h-3.5"></i> Ubah Massal
                                                </button>
                                            </div>
                                        </div>

                                        <div className="overflow-x-auto">
                                            <table className="w-full text-left text-sm whitespace-nowrap">
                                                <thead className="bg-slate-50 border-b border-slate-100 text-slate-600 font-semibold">
                                                    <tr>
                                                        <th className="py-4 px-6 w-12 text-center">
                                                            <input
                                                                type="checkbox"
                                                                checked={selectedHutangCheckboxes.length === filteredHutang.length && filteredHutang.length > 0}
                                                                onChange={(e) => {
                                                                    if (e.target.checked) {
                                                                        setSelectedHutangCheckboxes(filteredHutang.map(i => i.id));
                                                                    } else {
                                                                        setSelectedHutangCheckboxes([]);
                                                                    }
                                                                }}
                                                                className="w-4 h-4 rounded border-slate-300 cursor-pointer"
                                                            />
                                                        </th>
                                                        <th className="py-4 px-4 cursor-pointer hover:text-slate-800">
                                                            Nomor <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                        <th className="py-4 px-4">Vendor</th>
                                                        <th className="py-4 px-4">
                                                            Referensi <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                        <th className="py-4 px-4">
                                                            Tanggal <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                        <th className="py-4 px-4">
                                                            Tgl. Jatuh Tempo <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                        <th className="py-4 px-4">
                                                            Tanggal Pembayaran <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                        <th className="py-4 px-4">
                                                            Status <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                        <th className="py-4 px-6 text-right">
                                                            Sisa Tagihan <i data-lucide="chevrons-up-down" className="w-3 h-3 inline text-slate-400 ml-1"></i>
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="font-regular text-slate-700 bg-white">
                                                    {filteredHutang.length === 0 ? (
                                                        <tr>
                                                            <td colSpan="9" className="py-12 text-center text-slate-400 text-sm">
                                                                Tidak ada tagihan hutang dagang yang sesuai dengan kriteria filter.
                                                            </td>
                                                        </tr>
                                                    ) : (
                                                        filteredHutang.map(item => (
                                                            <tr
                                                                key={item.id}
                                                                onClick={() => handleShowDetail(item)}
                                                                className="border-b border-slate-50 hover:bg-slate-50 transition-colors group cursor-pointer">
                                                                <td className="py-4 px-6 text-center" onClick={(e) => e.stopPropagation()}>
                                                                    <input
                                                                        type="checkbox"
                                                                        checked={selectedHutangCheckboxes.includes(item.id)}
                                                                        onChange={() => {
                                                                            setSelectedHutangCheckboxes(prev =>
                                                                                prev.includes(item.id) ? prev.filter(x => x !== item.id) : [...prev, item.id]
                                                                            );
                                                                        }}
                                                                        className="w-4 h-4 rounded border-slate-300 cursor-pointer"
                                                                    />
                                                                </td>
                                                                <td className="py-4 px-4">
                                                                    <span className="text-blue-600 font-medium group-hover:text-blue-800">
                                                                        {item.nomor}
                                                                    </span>
                                                                </td>
                                                                <td className="py-4 px-4 whitespace-normal min-w-[200px] font-medium text-slate-800">
                                                                    {item.vendor}
                                                                </td>
                                                                <td className="py-4 px-4 text-slate-500">{item.referensi}</td>
                                                                <td className="py-4 px-4">{item.tanggal}</td>
                                                                <td className="py-4 px-4">{item.jatuhTempo}</td>
                                                                <td className="py-4 px-4 text-slate-400">{item.tanggalBayar || '-'}</td>
                                                                <td className="py-4 px-4">
                                                                    <span className={`px-2 py-0.5 rounded-full text-xs font-semibold ${
                                                                        item.status === 'Lunas'
                                                                            ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                                                            : item.status === 'Dibayar Sebagian'
                                                                            ? 'bg-amber-50 text-amber-600 border border-amber-100'
                                                                            : 'bg-red-50 text-red-600 border border-red-100'
                                                                    }`}>
                                                                        {item.status}
                                                                    </span>
                                                                </td>
                                                                <td className="py-4 px-6 text-right font-bold text-slate-900">
                                                                    Rp {formatIDR(item.sisa)}
                                                                </td>
                                                            </tr>
                                                        ))
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* VIEW 2 DETAIL: DETIL TAGIHAN HUTANG DAGANG (EXACT USER TEMPLATE)          */}
                        {/* ========================================================================= */}
                        {activeTab === 'hutang' && viewMode === 'detail' && selectedHutang && (
                            <div id="view-detail" className="flex-1 flex flex-col h-full overflow-hidden">
                                <header className="h-16 bg-white/80 backdrop-blur-md border-b border-slate-200 flex items-center justify-between px-8 sticky top-0 z-10">
                                    <button
                                        onClick={() => setViewMode('list')}
                                        className="flex items-center gap-2 text-sm font-medium text-slate-500 hover:text-blue-600 transition-colors">
                                        <i data-lucide="arrow-left" className="w-4 h-4"></i> Kembali ke Daftar
                                    </button>

                                    {selectedHutang.status !== 'Lunas' && (
                                        <button
                                            onClick={() => handleBayarHutang(selectedHutang.id)}
                                            className="flex items-center gap-2 px-4 py-2 text-xs font-bold text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg shadow-sm shadow-emerald-600/20 transition-all">
                                            <i data-lucide="check-circle" className="w-4 h-4"></i> Tandai Lunas & Selesai
                                        </button>
                                    )}
                                </header>

                                <div className="flex-1 overflow-auto p-8 space-y-6">
                                    {/* Header Detail */}
                                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                        <h1 className="text-2xl font-extrabold text-slate-900" id="detail-title">
                                            Detil Tagihan Hutang Dagang {selectedHutang.nomor}
                                        </h1>
                                        <div className="flex gap-2">
                                            <button
                                                onClick={() => showToast('Tautan bagikan telah disalin ke clipboard')}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-xs transition-colors">
                                                <i data-lucide="share-2" className="w-4 h-4"></i> Bagikan <i data-lucide="chevron-down" className="w-3 h-3"></i>
                                            </button>
                                            <button
                                                onClick={() => window.print()}
                                                className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-xs transition-colors">
                                                <i data-lucide="printer" className="w-4 h-4"></i> Print <i data-lucide="chevron-down" className="w-3 h-3"></i>
                                            </button>
                                            <button className="flex items-center px-2 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg shadow-xs hover:bg-slate-50 transition-colors">
                                                <i data-lucide="more-vertical" className="w-4 h-4"></i>
                                            </button>
                                        </div>
                                    </div>

                                    {/* Main Card */}
                                    <div className="bg-white rounded-xl shadow-xs border border-slate-200 p-8">
                                        <div className="mb-8 flex items-center justify-between">
                                            <span className={`inline-flex items-center px-3 py-1 rounded-full text-xs font-bold ${
                                                selectedHutang.status === 'Lunas'
                                                    ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                                    : selectedHutang.status === 'Dibayar Sebagian'
                                                    ? 'bg-amber-50 text-amber-600 border border-amber-100'
                                                    : 'bg-red-50 text-red-600 border border-red-100'
                                            }`}>
                                                {selectedHutang.status}
                                            </span>

                                            {selectedHutang.status === 'Lunas' && (
                                                <span className="text-xs font-medium text-emerald-600 flex items-center gap-1">
                                                    <i data-lucide="check" className="w-4 h-4"></i> Pembayaran Terverifikasi
                                                </span>
                                            )}
                                        </div>

                                        <div className="grid grid-cols-1 md:grid-cols-4 gap-8 mb-6">
                                            <div>
                                                <p className="text-xs text-slate-500 font-medium mb-1">Vendor</p>
                                                <a href="#" className="text-sm font-semibold text-blue-600 hover:underline">
                                                    {selectedHutang.vendor}
                                                </a>
                                            </div>
                                            <div>
                                                <p className="text-xs text-slate-500 font-medium mb-1">Nomor</p>
                                                <p className="text-sm font-semibold text-slate-800">{selectedHutang.nomor}</p>
                                            </div>
                                            <div className="md:col-span-2"></div>

                                            <div>
                                                <p className="text-xs text-slate-500 font-medium mb-1">Tgl. Transaksi</p>
                                                <p className="text-sm font-semibold text-slate-800">{selectedHutang.tanggal}</p>
                                            </div>
                                            <div>
                                                <p className="text-xs text-slate-500 font-medium mb-1">Tgl. Jatuh Tempo</p>
                                                <p className="text-sm font-semibold text-slate-800">{selectedHutang.jatuhTempo}</p>
                                            </div>
                                            <div className="md:col-span-2"></div>

                                            <div>
                                                <p className="text-xs text-slate-500 font-medium mb-1">Gudang</p>
                                                <a href="#" className="text-sm font-semibold text-blue-600 hover:underline">
                                                    {selectedHutang.gudang}
                                                </a>
                                            </div>
                                            <div>
                                                <p className="text-xs text-slate-500 font-medium mb-1">Referensi</p>
                                                <p className="text-sm font-semibold text-slate-800">{selectedHutang.referensi}</p>
                                            </div>
                                        </div>

                                        {/* Informasi Pengiriman Section */}
                                        <div className="border-t border-slate-100 pt-6 pb-2">
                                            <div className="flex items-center gap-2 mb-4 cursor-pointer">
                                                <i data-lucide="chevron-down" className="w-4 h-4 text-slate-500"></i>
                                                <h3 className="font-bold text-slate-800 text-sm">Informasi pengiriman</h3>
                                            </div>
                                            <div className="pl-6">
                                                <p className="text-xs text-slate-500 font-medium mb-1">Tanggal Pengiriman</p>
                                                <p className="text-sm font-semibold text-slate-800">{selectedHutang.tglKirim}</p>
                                            </div>
                                        </div>

                                        {/* Items Table */}
                                        <div className="overflow-x-auto border-t border-slate-100 mt-6 pt-4">
                                            <table className="w-full text-left text-sm whitespace-nowrap">
                                                <thead className="text-slate-500 font-semibold border-b border-slate-100">
                                                    <tr>
                                                        <th className="py-3 px-2 w-1/4">Produk</th>
                                                        <th className="py-3 px-2">Deskripsi</th>
                                                        <th className="py-3 px-2 text-right">Kuantitas</th>
                                                        <th className="py-3 px-2">Satuan</th>
                                                        <th className="py-3 px-2 text-right">Discount</th>
                                                        <th className="py-3 px-2 text-right">Harga</th>
                                                        <th className="py-3 px-2">Pajak</th>
                                                        <th className="py-3 px-2 text-right">Jumlah</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="font-regular text-slate-700">
                                                    {selectedHutang.items.map((it, idx) => (
                                                        <tr key={idx} className="border-b border-slate-50 hover:bg-slate-50">
                                                            <td className="py-4 px-2">
                                                                <a href="#" className="text-blue-600 font-medium hover:underline text-xs whitespace-normal">
                                                                    {it.sku} - {it.nama}
                                                                </a>
                                                            </td>
                                                            <td className="py-4 px-2 text-slate-400">-</td>
                                                            <td className="py-4 px-2 text-right">{it.qty}</td>
                                                            <td className="py-4 px-2">{it.satuan}</td>
                                                            <td className="py-4 px-2 text-right">{it.diskon}</td>
                                                            <td className="py-4 px-2 text-right">Rp {formatIDR(it.harga)}</td>
                                                            <td className="py-4 px-2 text-slate-500 text-xs">{it.pajak}</td>
                                                            <td className="py-4 px-2 text-right font-medium">Rp {formatIDR(it.jumlah)}</td>
                                                        </tr>
                                                    ))}
                                                    <tr className="border-b border-slate-100 bg-slate-50/50">
                                                        <td colSpan="2" className="py-3 px-2 text-right font-semibold text-slate-800 text-xs">
                                                            Total Kuantitas
                                                        </td>
                                                        <td className="py-3 px-2 text-right font-bold text-slate-800 text-xs">
                                                            {selectedHutang.items.reduce((a, b) => a + b.qty, 0)}
                                                        </td>
                                                        <td colSpan="5"></td>
                                                    </tr>
                                                </tbody>
                                            </table>
                                        </div>

                                        {/* Summary */}
                                        <div className="flex justify-end mt-6">
                                            <div className="w-80 space-y-3 text-sm">
                                                <div className="flex justify-between text-slate-600">
                                                    <span className="font-medium">Sub Total</span>
                                                    <span className="font-semibold text-slate-800">Rp {formatIDR(selectedHutang.subtotal)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-600">
                                                    <span className="font-medium">PPN11</span>
                                                    <span className="font-semibold text-slate-800">Rp {formatIDR(selectedHutang.ppn)}</span>
                                                </div>
                                                <div className="flex justify-between text-slate-800 border-t border-slate-100 pt-3">
                                                    <span className="font-bold text-base">Total</span>
                                                    <span className="font-bold text-base">Rp {formatIDR(selectedHutang.total)}</span>
                                                </div>
                                                <div className="flex justify-between bg-slate-100 text-slate-900 p-4 rounded-lg mt-2">
                                                    <span className="font-bold text-base">Sisa Tagihan</span>
                                                    <span className={`font-extrabold text-lg ${selectedHutang.sisa === 0 ? 'text-emerald-600' : 'text-slate-900'}`}>
                                                        Rp {formatIDR(selectedHutang.sisa)}
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Log Section */}
                                    <div className="bg-white rounded-xl shadow-xs border border-slate-200 overflow-hidden mb-8">
                                        <div className="px-6 py-4 border-b border-slate-100 bg-slate-50/50">
                                            <h2 className="text-base font-bold text-slate-800">Pantau log perubahan data</h2>
                                        </div>
                                        <div className="p-6">
                                            <p className="text-sm text-blue-600 font-medium flex items-center gap-2 hover:underline cursor-pointer w-fit">
                                                <i data-lucide="pencil" className="w-4 h-4"></i> Terakhir diubah oleh {selectedHutang.lastModified}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* VIEW 3: TAGIHAN (PIUTANG / ACCOUNTS RECEIVABLE)                            */}
                        {/* ========================================================================= */}
                        {activeTab === 'tagihan' && (
                            <div className="flex-1 overflow-auto p-8 flex flex-col gap-6">
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h1 className="text-2xl font-extrabold text-slate-900">Klaim & Piutang Tagihan</h1>
                                        <p className="text-xs text-slate-500">Tagihan kredit resep pasien, asuransi rujukan, dan instansi rekanan apotek.</p>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <button
                                            onClick={() => showToast('Ekspor piutang berhasil didownload')}
                                            className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-xs">
                                            <i data-lucide="download" className="w-4 h-4"></i> Export
                                        </button>
                                        <button
                                            onClick={() => showToast('Form pembuatan tagihan piutang siap')}
                                            className="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-blue-800 hover:bg-blue-900 rounded-lg shadow-sm">
                                            <i data-lucide="plus" className="w-4 h-4"></i> Tambah Tagihan
                                        </button>
                                    </div>
                                </div>

                                {/* Table Tagihan */}
                                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                    <div className="p-4 border-b border-slate-100 flex flex-col md:flex-row items-center justify-between gap-4">
                                        <div className="flex items-center gap-1">
                                            {['all', 'unpaid', 'paid'].map(f => (
                                                <button
                                                    key={f}
                                                    onClick={() => setTagihanFilter(f)}
                                                    className={`px-3 py-1.5 rounded-lg text-xs font-semibold capitalize ${tagihanFilter === f ? 'bg-blue-50 text-blue-700 border border-blue-200' : 'text-slate-500 hover:bg-slate-50'}`}>
                                                    {f === 'all' ? 'Semua' : f === 'unpaid' ? 'Menunggu Klaim' : 'Sudah Lunas'}
                                                </button>
                                            ))}
                                        </div>
                                        <div className="relative w-72">
                                            <i data-lucide="search" className="absolute left-3 top-1/2 transform -translate-y-1/2 w-4 h-4 text-slate-400"></i>
                                            <input
                                                type="text"
                                                placeholder="Cari instansi / pasien..."
                                                value={searchTagihan}
                                                onChange={(e) => setSearchTagihan(e.target.value)}
                                                className="w-full bg-slate-50 text-xs rounded-lg pl-9 pr-3 py-2 border border-slate-200 focus:outline-none focus:ring-2 focus:ring-blue-600"
                                            />
                                        </div>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm whitespace-nowrap">
                                            <thead className="bg-slate-50 border-b border-slate-100 text-slate-600 font-semibold text-xs">
                                                <tr>
                                                    <th className="py-3.5 px-6">Nomor Invoice</th>
                                                    <th className="py-3.5 px-4">Debitur / Instansi</th>
                                                    <th className="py-3.5 px-4">Keterangan / Pasien</th>
                                                    <th className="py-3.5 px-4">Dokter Rujukan</th>
                                                    <th className="py-3.5 px-4">Jatuh Tempo</th>
                                                    <th className="py-3.5 px-4">Status</th>
                                                    <th className="py-3.5 px-6 text-right">Nilai Tagihan</th>
                                                    <th className="py-3.5 px-6 text-center">Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                                {tagihanList
                                                    .filter(t => {
                                                        if (tagihanFilter === 'unpaid' && t.status === 'Lunas') return false;
                                                        if (tagihanFilter === 'paid' && t.status !== 'Lunas') return false;
                                                        if (searchTagihan) {
                                                            const q = searchTagihan.toLowerCase();
                                                            return t.debtor.toLowerCase().includes(q) || t.nomor.toLowerCase().includes(q) || t.pasien.toLowerCase().includes(q);
                                                        }
                                                        return true;
                                                    })
                                                    .map(tagihan => (
                                                        <tr key={tagihan.id} className="hover:bg-slate-50 transition-colors">
                                                            <td className="py-4 px-6 font-bold text-xs text-blue-600">{tagihan.nomor}</td>
                                                            <td className="py-4 px-4 font-semibold text-slate-800">{tagihan.debtor}</td>
                                                            <td className="py-4 px-4 text-xs text-slate-600">{tagihan.pasien}</td>
                                                            <td className="py-4 px-4 text-xs text-slate-500">{tagihan.dokter}</td>
                                                            <td className="py-4 px-4 text-xs">{tagihan.jatuhTempo}</td>
                                                            <td className="py-4 px-4">
                                                                <span className={`px-2.5 py-1 rounded-full text-xs font-semibold ${
                                                                    tagihan.status === 'Lunas'
                                                                        ? 'bg-emerald-50 text-emerald-600 border border-emerald-100'
                                                                        : 'bg-amber-50 text-amber-600 border border-amber-100'
                                                                }`}>
                                                                    {tagihan.status}
                                                                </span>
                                                            </td>
                                                            <td className="py-4 px-6 text-right font-bold text-slate-900">
                                                                Rp {formatIDR(tagihan.total)}
                                                            </td>
                                                            <td className="py-4 px-6 text-center">
                                                                {tagihan.status !== 'Lunas' ? (
                                                                    <button
                                                                        onClick={() => handleKlaimTagihan(tagihan.id)}
                                                                        className="px-3 py-1.5 text-xs font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg shadow-xs transition-all">
                                                                        Proses Klaim
                                                                    </button>
                                                                ) : (
                                                                    <span className="text-xs font-medium text-slate-400">Selesai</span>
                                                                )}
                                                            </td>
                                                        </tr>
                                                    ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* VIEW 4: BIAYA (EXPENSES)                                                  */}
                        {/* ========================================================================= */}
                        {activeTab === 'biaya' && (
                            <div className="flex-1 overflow-auto p-8 flex flex-col gap-6">
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h1 className="text-2xl font-extrabold text-slate-900">Beban & Biaya Operasional</h1>
                                        <p className="text-xs text-slate-500">Pencatatan pengeluaran kas apotek untuk utilitas, gaji, sanitasi, dan pemeliharaan.</p>
                                    </div>
                                    <button
                                        onClick={() => setShowAddBiayaModal(true)}
                                        className="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg shadow-sm shadow-orange-500/20">
                                        <i data-lucide="plus" className="w-4 h-4"></i> Catat Biaya Baru
                                    </button>
                                </div>

                                {/* Biaya Summary Cards */}
                                <div className="grid grid-cols-1 md:grid-cols-3 gap-5">
                                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                                        <span className="text-xs font-bold text-slate-500 uppercase">Total Biaya Tercatat</span>
                                        <div className="text-2xl font-extrabold text-slate-900 mt-1">Rp {formatIDR(totalBiayaBulanIni)}</div>
                                        <p className="text-xs text-slate-400 mt-1">{biayaList.length} transaksi pengeluaran</p>
                                    </div>
                                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                                        <span className="text-xs font-bold text-slate-500 uppercase">Utilitas & Listrik</span>
                                        <div className="text-2xl font-extrabold text-blue-800 mt-1">
                                            Rp {formatIDR(biayaList.filter(b => b.kategori.includes('Utilitas')).reduce((a, c) => a + c.jumlah, 0))}
                                        </div>
                                        <p className="text-xs text-slate-400 mt-1">PLN, PDAM, Internet Apotek</p>
                                    </div>
                                    <div className="bg-white p-5 rounded-xl border border-slate-200 shadow-xs">
                                        <span className="text-xs font-bold text-slate-500 uppercase">Beban Shift & Perlengkapan</span>
                                        <div className="text-2xl font-extrabold text-orange-600 mt-1">
                                            Rp {formatIDR(biayaList.filter(b => !b.kategori.includes('Utilitas')).reduce((a, c) => a + c.jumlah, 0))}
                                        </div>
                                        <p className="text-xs text-slate-400 mt-1">Honor apoteker, plastik & klip</p>
                                    </div>
                                </div>

                                {/* Table Biaya */}
                                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                    <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                                        <h3 className="font-bold text-base text-slate-800">Daftar Pengeluaran & Biaya</h3>
                                        <span className="text-xs text-slate-400">Sinkronisasi realtime</span>
                                    </div>
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-left text-sm whitespace-nowrap">
                                            <thead className="bg-slate-50 border-b border-slate-100 text-slate-600 font-semibold text-xs">
                                                <tr>
                                                    <th className="py-3.5 px-6">Kode Biaya</th>
                                                    <th className="py-3.5 px-4">Kategori</th>
                                                    <th className="py-3.5 px-4">Keterangan</th>
                                                    <th className="py-3.5 px-4">Tanggal</th>
                                                    <th className="py-3.5 px-4">Sumber Akun Kas</th>
                                                    <th className="py-3.5 px-4 text-center">Status</th>
                                                    <th className="py-3.5 px-6 text-right">Jumlah</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-slate-100 text-slate-700">
                                                {biayaList.map(biaya => (
                                                    <tr key={biaya.id} className="hover:bg-slate-50 transition-colors">
                                                        <td className="py-3.5 px-6 font-bold text-xs text-blue-600">{biaya.kode}</td>
                                                        <td className="py-3.5 px-4">
                                                            <span className="px-2 py-0.5 rounded text-[11px] font-semibold bg-slate-100 text-slate-700">
                                                                {biaya.kategori}
                                                            </span>
                                                        </td>
                                                        <td className="py-3.5 px-4 font-medium text-slate-800">{biaya.keterangan}</td>
                                                        <td className="py-3.5 px-4 text-xs text-slate-500">{biaya.tanggal}</td>
                                                        <td className="py-3.5 px-4 text-xs font-semibold text-slate-600 flex items-center gap-1.5">
                                                            <i data-lucide="wallet" className="w-3.5 h-3.5 text-slate-400"></i> {biaya.akun}
                                                        </td>
                                                        <td className="py-3.5 px-4 text-center">
                                                            <span className={`px-2 py-0.5 rounded-full text-[10px] font-bold ${biaya.status === 'Disetujui' ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'}`}>
                                                                {biaya.status}
                                                            </span>
                                                        </td>
                                                        <td className="py-3.5 px-6 text-right font-bold text-slate-900">
                                                            Rp {formatIDR(biaya.jumlah)}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* VIEW 5: KAS & BANK (AKUN KEUANGAN)                                       */}
                        {/* ========================================================================= */}
                        {activeTab === 'akun' && (
                            <div className="flex-1 overflow-auto p-8 flex flex-col gap-6">
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h1 className="text-2xl font-extrabold text-slate-900">Kas & Rekening Bank Apotek</h1>
                                        <p className="text-xs text-slate-500">Saldo drawer kasir, kas utama apotek, serta rekening operasional bank.</p>
                                    </div>
                                    <button
                                        onClick={() => setShowTransferModal(true)}
                                        className="flex items-center gap-2 px-4 py-2 text-sm font-bold text-white bg-blue-800 hover:bg-blue-900 rounded-lg shadow-sm">
                                        <i data-lucide="arrow-right-left" className="w-4 h-4"></i> Transfer Antar Akun
                                    </button>
                                </div>

                                {/* Bank Cards */}
                                <div className="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-5">
                                    {akunList.map(akun => (
                                        <div key={akun.id} className="bg-white p-6 rounded-2xl border border-slate-200 shadow-xs flex flex-col justify-between hover:border-blue-300 transition-all">
                                            <div>
                                                <div className="flex items-center justify-between mb-4">
                                                    <span className="text-[11px] font-bold px-2 py-0.5 rounded bg-slate-100 text-slate-600 uppercase">
                                                        {akun.tipe}
                                                    </span>
                                                    <i data-lucide={akun.bank === 'Tunai' ? 'banknote' : 'landmark'} className="w-5 h-5 text-blue-600"></i>
                                                </div>
                                                <h4 className="font-bold text-sm text-slate-800">{akun.nama}</h4>
                                                <p className="text-xs text-slate-400 font-mono mt-0.5">{akun.nomor}</p>
                                                <div className="text-2xl font-extrabold text-slate-900 mt-4 tracking-tight">
                                                    Rp {formatIDR(akun.saldo)}
                                                </div>
                                            </div>
                                            <div className="mt-4 pt-3 border-t border-slate-100 flex items-center justify-between text-xs text-slate-500">
                                                <span>{akun.perubahan}</span>
                                                <button
                                                    onClick={() => showToast(`Melihat mutasi rekening ${akun.nama}`)}
                                                    className="font-bold text-blue-600 hover:underline">
                                                    Mutasi
                                                </button>
                                            </div>
                                        </div>
                                    ))}
                                </div>

                                {/* Bank Ledger Simulation */}
                                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                    <div className="px-6 py-4 border-b border-slate-100 flex justify-between items-center">
                                        <h3 className="font-bold text-base text-slate-800">Rekonsiliasi Mutasi Saldo Realtime</h3>
                                        <span className="text-xs text-emerald-600 font-semibold flex items-center gap-1">
                                            <i data-lucide="check-check" className="w-4 h-4"></i> Akun Rekonsiliasi Terhubung
                                        </span>
                                    </div>
                                    <div className="p-6 text-center text-slate-500 text-xs">
                                        Seluruh mutasi penerimaan kasir, pelunasan faktur vendor, dan pengeluaran operasional tercatat otomatis di buku besar.
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* VIEW 6: LOG / RIWAYAT (AUDIT TRAIL)                                       */}
                        {/* ========================================================================= */}
                        {activeTab === 'log' && (
                            <div className="flex-1 overflow-auto p-8 flex flex-col gap-6">
                                <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                    <div>
                                        <h1 className="text-2xl font-extrabold text-slate-900">Riwayat & Log Audit Keuangan</h1>
                                        <p className="text-xs text-slate-500">Jejak rekaman perubahan transaksi, pelunasan hutang, approval pengeluaran, dan klaim tagihan.</p>
                                    </div>
                                    <button
                                        onClick={() => showToast('Log audit berhasil diekspor untuk kepatuhan')}
                                        className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-200 rounded-lg hover:bg-slate-50 shadow-xs">
                                        <i data-lucide="printer" className="w-4 h-4"></i> Cetak Log Audit
                                    </button>
                                </div>

                                <div className="bg-white rounded-2xl border border-slate-200 shadow-xs overflow-hidden">
                                    <div className="divide-y divide-slate-100">
                                        {logsList.map(log => (
                                            <div key={log.id} className="p-5 flex items-start gap-4 hover:bg-slate-50 transition-colors">
                                                <div className={`w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0 text-white font-bold text-sm ${
                                                    log.tipe === 'hutang' ? 'bg-blue-600' :
                                                    log.tipe === 'tagihan' ? 'bg-amber-500' :
                                                    log.tipe === 'biaya' ? 'bg-orange-500' : 'bg-emerald-600'
                                                }`}>
                                                    <i data-lucide={
                                                        log.tipe === 'hutang' ? 'shopping-bag' :
                                                        log.tipe === 'tagihan' ? 'receipt' :
                                                        log.tipe === 'biaya' ? 'credit-card' : 'wallet'
                                                    } className="w-4 h-4 text-white"></i>
                                                </div>
                                                <div className="flex-1">
                                                    <div className="flex items-center justify-between">
                                                        <h4 className="text-sm font-bold text-slate-800">{log.aksi}</h4>
                                                        <span className="text-xs text-slate-400">{log.waktu}</span>
                                                    </div>
                                                    <p className="text-xs text-slate-600 mt-1">{log.detail}</p>
                                                    <div className="mt-2 flex items-center gap-3 text-[11px] text-slate-400">
                                                        <span>Dilakukan oleh: <strong className="text-slate-700">{log.user}</strong></span>
                                                        <span>•</span>
                                                        <span className="uppercase font-semibold text-blue-600">Modul {log.tipe}</span>
                                                    </div>
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* MODAL 1: CATAT BIAYA BARU                                                 */}
                        {/* ========================================================================= */}
                        {showAddBiayaModal && (
                            <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
                                <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                                        <h3 className="text-base font-bold text-slate-900">Catat Biaya Operasional Baru</h3>
                                        <button onClick={() => setShowAddBiayaModal(false)} className="text-slate-400 hover:text-slate-600">
                                            <i data-lucide="x" className="w-5 h-5"></i>
                                        </button>
                                    </div>

                                    <form onSubmit={handleAddBiaya} className="space-y-4 mt-4 text-xs">
                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Kategori Beban</label>
                                            <select
                                                value={newBiaya.kategori}
                                                onChange={(e) => setNewBiaya({ ...newBiaya, kategori: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-slate-800 focus:ring-2 focus:ring-blue-600">
                                                <option>Operasional & Utilitas</option>
                                                <option>Beban Gaji & Honor</option>
                                                <option>Perlengkapan & Etiket</option>
                                                <option>Pemeliharaan & Sanitasi</option>
                                                <option>Logistik & Transportasi</option>
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Keterangan Biaya</label>
                                            <input
                                                type="text"
                                                required
                                                placeholder="Contoh: Pembelian kantong resep & plastik obat"
                                                value={newBiaya.keterangan}
                                                onChange={(e) => setNewBiaya({ ...newBiaya, keterangan: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-blue-600"
                                            />
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Nominal (Rp)</label>
                                            <input
                                                type="number"
                                                required
                                                placeholder="500000"
                                                value={newBiaya.jumlah}
                                                onChange={(e) => setNewBiaya({ ...newBiaya, jumlah: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-blue-600 font-bold"
                                            />
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Bayar Menggunakan Akun</label>
                                            <select
                                                value={newBiaya.akun}
                                                onChange={(e) => setNewBiaya({ ...newBiaya, akun: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-slate-800 focus:ring-2 focus:ring-blue-600">
                                                {akunList.map(a => (
                                                    <option key={a.id} value={a.nama}>{a.nama} (Saldo: Rp {formatIDR(a.saldo)})</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                            <button
                                                type="button"
                                                onClick={() => setShowAddBiayaModal(false)}
                                                className="px-4 py-2 font-medium text-slate-600 hover:bg-slate-50 rounded-lg">
                                                Batal
                                            </button>
                                            <button
                                                type="submit"
                                                className="px-4 py-2 font-bold text-white bg-orange-500 hover:bg-orange-600 rounded-lg shadow-sm">
                                                Simpan Biaya
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        )}

                        {/* ========================================================================= */}
                        {/* MODAL 2: TRANSFER SALDO KAS & BANK                                        */}
                        {/* ========================================================================= */}
                        {showTransferModal && (
                            <div className="fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-xs flex items-center justify-center p-4">
                                <div className="bg-white rounded-2xl max-w-md w-full p-6 shadow-2xl border border-slate-200">
                                    <div className="flex items-center justify-between pb-4 border-b border-slate-100">
                                        <h3 className="text-base font-bold text-slate-900">Transfer Antar Akun Keuangan</h3>
                                        <button onClick={() => setShowTransferModal(false)} className="text-slate-400 hover:text-slate-600">
                                            <i data-lucide="x" className="w-5 h-5"></i>
                                        </button>
                                    </div>

                                    <form onSubmit={handleTransferKas} className="space-y-4 mt-4 text-xs">
                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Dari Rekening / Kas Asal</label>
                                            <select
                                                value={transferData.dari}
                                                onChange={(e) => setTransferData({ ...transferData, dari: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-slate-800">
                                                {akunList.map(a => (
                                                    <option key={a.id} value={a.id}>{a.nama} (Saldo: Rp {formatIDR(a.saldo)})</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Ke Rekening / Kas Tujuan</label>
                                            <select
                                                value={transferData.ke}
                                                onChange={(e) => setTransferData({ ...transferData, ke: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 bg-white text-slate-800">
                                                {akunList.map(a => (
                                                    <option key={a.id} value={a.id}>{a.nama} (Saldo: Rp {formatIDR(a.saldo)})</option>
                                                ))}
                                            </select>
                                        </div>

                                        <div>
                                            <label className="block font-semibold text-slate-700 mb-1">Nominal Transfer (Rp)</label>
                                            <input
                                                type="number"
                                                required
                                                placeholder="1000000"
                                                value={transferData.jumlah}
                                                onChange={(e) => setTransferData({ ...transferData, jumlah: e.target.value })}
                                                className="w-full border border-slate-200 rounded-lg p-2.5 text-slate-800 focus:ring-2 focus:ring-blue-600 font-bold"
                                            />
                                        </div>

                                        <div className="flex items-center justify-end gap-2 pt-3 border-t border-slate-100">
                                            <button
                                                type="button"
                                                onClick={() => setShowTransferModal(false)}
                                                className="px-4 py-2 font-medium text-slate-600 hover:bg-slate-50 rounded-lg">
                                                Batal
                                            </button>
                                            <button
                                                type="submit"
                                                className="px-4 py-2 font-bold text-white bg-blue-800 hover:bg-blue-900 rounded-lg shadow-sm">
                                                Eksekusi Transfer
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        )}

                    </main>
                </div>
            );
        }

        // Render React 18 Root
        const root = ReactDOM.createRoot(document.getElementById('root'));
        root.render(<FinanceApp />);
    </script>
    @endverbatim
</body>

</html>
