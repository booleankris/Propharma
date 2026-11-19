<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionCatalog
{
    /**
     * Katalog resmi permission per modul beserta mapping baseline 1:1
     * terhadap rule di routes/web.php, PharmacyHelper.php, sidebar-client.blade.php, & Controllers.
     */
    public static function modules(): array
    {
        // Semua role yang bisa melakukan penjualan (tidak termasuk Gudang PMI murni)
        $salesRoles = [
            'General Manager',
            'Kasir',
            'HO',
            'administrator',
            'manager',
            'Manager',
            'Koordinator',
            'koordinator',
            'Online',
            'Online Grab',
            'Online Shopee',
            'Digital',
            'UMKM',
            'operator',
            'Operator',
            'Finance',
        ];

        // Semua role non-online (yang bisa akses Persediaan, Mutasi, Pusat Export)
        $nonOnlineRoles = [
            'General Manager',
            'Kasir',
            'Gudang PMI',
            'HO',
            'administrator',
            'manager',
            'Manager',
            'Koordinator',
            'koordinator',
            'UMKM',
            'operator',
            'Operator',
            'Finance',
        ];

        // Role yang bisa mengakses Pembelian / SP / Penerimaan (canAccessPurchasing)
        $purchasingRoles = [
            'General Manager',
            'Kasir', // Kasir Cabang (non-PMI retail)
            'Gudang PMI',
            'HO',
            'administrator',
            'manager',
            'Manager',
            'Koordinator',
            'koordinator',
            'UMKM',
            'operator',
            'Operator',
            'Finance',
        ];

        // Role Master Data & Pasien
        $masterAndPatientRoles = [
            'HO',
            'operator',
            'Operator',
            'administrator',
            'Koordinator',
            'koordinator',
            'General Manager',
        ];

        // Role Manager Access
        $managerAccessRoles = [
            'General Manager',
            'manager',
            'Manager',
            'administrator',
        ];

        return [
            'sales' => [
                'label' => '1. Penjualan & Kasir',
                'permissions' => [
                    'sales.pos' => [
                        'label' => 'Transaksi Kasir (POS & Online)',
                        'description' => 'Buka kasir penjualan (/transaction/upds), tambah keranjang, checkout, dan cetak struk/kwitansi. (Nonaktif untuk Gudang PMI)',
                        'roles' => $salesRoles,
                    ],
                    'sales.data' => [
                        'label' => 'Data Penjualan & Kunjungan Pending',
                        'description' => 'Lihat riwayat transaksi penjualan (/data/sales), transaksi pending (/data/pending), dan export data penjualan.',
                        'roles' => $salesRoles,
                    ],
                    'sales.update_payment' => [
                        'label' => 'Ubah Metode Pembayaran Transaksi',
                        'description' => 'Mengubah metode bayar (Cash/QRIS/Transfer/Debit/Kredit) pada transaksi penjualan yang sudah selesai.',
                        'roles' => ['General Manager', 'administrator'],
                    ],
                    'sales.retur' => [
                        'label' => 'Retur Penjualan & Data Retur',
                        'description' => 'Proses retur penjualan dari pelanggan (/retur) dan lihat daftar retur penjualan (/data/retur).',
                        'roles' => $salesRoles,
                    ],
                    'sales.reject' => [
                        'label' => 'Penolakan Transaksi & Tindak Lanjut',
                        'description' => 'Catat penolakan obat (/reject) serta tindak lanjut penolakan (/reject/follow-up).',
                        'roles' => $salesRoles,
                    ],
                    'sales.reject_export' => [
                        'label' => 'Export & Monitoring Penolakan Lintas Kasir',
                        'description' => 'Export Excel penolakan dan lihat seluruh data penolakan tanpa dibatasi kasir pribadi.',
                        'roles' => ['administrator', 'General Manager', 'Manager', 'manager', 'Koordinator', 'koordinator', 'HO', 'operator', 'Operator'],
                    ],
                ],
            ],
            'purchasing' => [
                'label' => '2. Pembelian & Penerimaan Barang',
                'permissions' => [
                    'orders.manage' => [
                        'label' => 'Surat Pesanan (SP) & Smart Order',
                        'description' => 'Buat pesanan obat (/createorder), smart order, dan cetak SPB. (Nonaktif untuk Role Online & Kasir Retail PMI)',
                        'roles' => $purchasingRoles,
                    ],
                    'receiving.manage' => [
                        'label' => 'Penerimaan Barang (BPBA), Rincian & Revisi Faktur',
                        'description' => 'Terima barang masuk (/receiving), rincian penerimaan, revisi faktur, dan riwayat pembelian.',
                        'roles' => $purchasingRoles,
                    ],
                    'receiving.update_master_price' => [
                        'label' => 'Update Harga Master dari Riwayat Harga Beli',
                        'description' => 'Memperbarui harga dasar (HNA/Raw Price) master obat langsung dari halaman Riwayat Harga Beli.',
                        'roles' => ['General Manager', 'HO', 'operator', 'Operator', 'administrator'],
                    ],
                    'receiving.cancel_consolidation' => [
                        'label' => 'Konsolidasi & Batalkan Pemindahan Item BPBA',
                        'description' => 'Menggabungkan atau membatalkan pemindahan item antar BPBA pada faktur gabungan.',
                        'roles' => ['HO', 'administrator', 'General Manager', 'manager', 'Manager', 'Koordinator', 'koordinator', 'operator', 'Operator', 'Gudang PMI'],
                    ],
                    'orders.retur' => [
                        'label' => 'Retur Pembelian (Order Lama)',
                        'description' => 'Proses retur barang pembelian ke PBF/Supplier (/returorder).',
                        'roles' => $purchasingRoles,
                    ],
                ],
            ],
            'inventory' => [
                'label' => '3. Persediaan & Mutasi Stok',
                'permissions' => [
                    'supplies.view' => [
                        'label' => 'Kartu Stok, Stok Pelayanan & Data Stok',
                        'description' => 'Lihat Kartu Stok (/supplies), Stok Pelayanan (/stock-detail), dan Data Stok (/stock-data). (Nonaktif untuk Role Online)',
                        'roles' => $nonOnlineRoles,
                    ],
                    'supplies.warehouse_stock' => [
                        'label' => 'Stok Gudang Pusat',
                        'description' => 'Akses menu Stok Gudang (/stock-storage) khusus Gudang PMI, PMI, dan manajemen pusat.',
                        'roles' => ['Gudang PMI', 'HO', 'administrator', 'General Manager', 'Manager', 'manager', 'Koordinator', 'koordinator'],
                    ],
                    'supplies.opname' => [
                        'label' => 'Stock Opname & Reset Batch',
                        'description' => 'Input/import Excel Stock Opname (/stockopname) dan penyesuaian stok fisik.',
                        'roles' => $nonOnlineRoles,
                    ],
                    'supplies.revision' => [
                        'label' => 'Revisi Stok Terfokus (Masuk Kartu Stok)',
                        'description' => 'Revisi & penyelarasan stok batch terhadap saldo Kartu Stok (/stock-revision) tanpa mengubah Saldo Awal.',
                        'roles' => ['General Manager', 'Koordinator', 'koordinator', 'administrator'],
                    ],
                    'transfers.manage' => [
                        'label' => 'Mutasi Stok Antar Cabang & Etalase',
                        'description' => 'Buat mutasi keluar, request mutasi, terima/tolak mutasi masuk (wajib PIN), dan kelola etalase cabang.',
                        'roles' => $nonOnlineRoles,
                    ],
                ],
            ],
            'reports' => [
                'label' => '4. Pusat Laporan & Statistik',
                'permissions' => [
                    'reports.export_center' => [
                        'label' => 'Pusat Export & Laporan Penjualan/Pembelian',
                        'description' => 'Akses modal Pusat Export, Laporan Penjualan (LIPH, Dokter, Pasien), dan Laporan Pembelian.',
                        'roles' => $nonOnlineRoles,
                    ],
                    'reports.export_expiry' => [
                        'label' => 'Export Monitoring Kadaluarsa (ED)',
                        'description' => 'Unduh laporan monitoring tanggal kadaluarsa (ED) seluruh batch obat di Pusat Export.',
                        'roles' => ['General Manager', 'HO', 'Koordinator', 'koordinator', 'manager', 'Manager', 'Gudang PMI', 'administrator'],
                    ],
                    'reports.special_medicines' => [
                        'label' => 'Pelaporan Obat Khusus (SIPNAP)',
                        'description' => 'Kelola master obat khusus (/reported-medicines) dan export laporan Narkotika/Psikotropika/Prekursor.',
                        'roles' => ['General Manager', 'Koordinator', 'koordinator', 'manager', 'Manager', 'administrator'],
                    ],
                    'reports.pareto' => [
                        'label' => 'Analisis Pareto',
                        'description' => 'Akses halaman dan export Analisis Pareto penjualan & pembelian (/pareto).',
                        'roles' => ['Finance', 'General Manager', 'administrator', 'HO', 'Koordinator', 'koordinator'],
                    ],
                    'reports.staff_stats' => [
                        'label' => 'Statistik Kinerja Kasir',
                        'description' => 'Monitoring performa jumlah struk dan nilai transaksi per kasir (/staff-stats).',
                        'roles' => ['General Manager', 'administrator'],
                    ],
                ],
            ],
            'finance' => [
                'label' => '5. Head Office (HO) & Keuangan',
                'permissions' => [
                    'ho.analytics' => [
                        'label' => 'Executive Analytics HO',
                        'description' => 'Akses dashboard Executive Analytics HO (/ho/analytics) dan export stok seluruh cabang.',
                        'roles' => ['HO', 'administrator'],
                    ],
                    'branch.switch_and_filter' => [
                        'label' => 'Pindah Konteks Cabang & Filter Semua Cabang',
                        'description' => 'Mengganti cabang aktif (/pharmacy/switch) dan memfilter lintas cabang di Pusat Export, Notifikasi Stok, & Finance.',
                        'roles' => ['HO', 'General Manager', 'Koordinator', 'koordinator', 'administrator'],
                    ],
                    'finance.manage' => [
                        'label' => 'Sahabat Finance ERP',
                        'description' => 'Akses penuh modul Finance (/finance): Hutang, Piutang, Cashflow, Kas & Bank, Biaya, Diskon, Cashbond, dan Akun.',
                        'roles' => ['Finance', 'General Manager', 'administrator', 'HO'],
                    ],
                    'finance.return_policy' => [
                        'label' => 'Ketentuan Retur & Monitoring Input Retur Beli',
                        'description' => 'Kelola Master Ketentuan Retur (/finance/return-policies) dan Monitoring Retur Pembelian (/finance/purchase-returns).',
                        'roles' => ['administrator', 'General Manager', 'Gudang PMI', 'Koordinator', 'koordinator'],
                    ],
                ],
            ],
            'master' => [
                'label' => '6. Master Data & Pasien',
                'permissions' => [
                    'master.data' => [
                        'label' => 'Kelola Master Data Sistem',
                        'description' => 'Kelola Master Obat, Pabrik, Kategori, Komposisi, Kreditur, Debitur, Dokter, Jasa, Lokasi, Parameter, & Etalase.',
                        'roles' => $masterAndPatientRoles,
                    ],
                    'patients.manage' => [
                        'label' => 'Data Pasien & Kunjungan Pasien',
                        'description' => 'Kelola daftar pasien (/patients), riwayat kunjungan pasien (/master/patient-transactions), dan kartu obat pasien (/master/patient-medicine-cards).',
                        'roles' => $masterAndPatientRoles,
                    ],
                ],
            ],
            'admin' => [
                'label' => '7. Manager Access & Administrasi',
                'permissions' => [
                    'manager.access' => [
                        'label' => 'Akses Menu Manager Access',
                        'description' => 'Membuka menu dan halaman pusat kendali Manager Access.',
                        'roles' => $managerAccessRoles,
                    ],
                    'users.manage' => [
                        'label' => 'Manajemen User & Atur Role Users',
                        'description' => 'Tambah, edit, hapus pengguna, atur Secret PIN, dan delegasikan role ke akun user.',
                        'roles' => $managerAccessRoles,
                    ],
                    'roles.manage' => [
                        'label' => 'Manajemen Role & Permission',
                        'description' => 'Buat role baru, ubah nama role, dan atur checklist permission per role.',
                        'roles' => $managerAccessRoles,
                    ],
                    'pharmacies.manage' => [
                        'label' => 'Manajemen Cabang Apotek',
                        'description' => 'Tambah, edit detail izin SIA/SIPA, ubah status aktif/nonaktif, atau hapus cabang apotek.',
                        'roles' => $managerAccessRoles,
                    ],
                ],
            ],
        ];
    }

    /**
     * Daftar flat semua definisi permission.
     */
    public static function allDefinitions(): array
    {
        $flat = [];
        foreach (self::modules() as $moduleKey => $module) {
            foreach ($module['permissions'] as $permName => $meta) {
                $flat[$permName] = array_merge($meta, [
                    'name' => $permName,
                    'module' => $moduleKey,
                    'module_label' => $module['label'],
                ]);
            }
        }
        return $flat;
    }

    /**
     * Pastikan seluruh permission di katalog sudah ada di tabel `permissions`
     * dan ter-assign secara additive pada saat pertama kali diinisialisasi.
     */
    public static function ensureSynced(): void
    {
        $definitions = self::allDefinitions();
        $existingNames = Permission::where('guard_name', 'web')
            ->whereIn('name', array_keys($definitions))
            ->pluck('name')
            ->all();

        if (count($existingNames) === count($definitions)) {
            return;
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $existingRoles = Role::where('guard_name', 'web')->get();

        foreach ($definitions as $permName => $meta) {
            $wasMissing = !in_array($permName, $existingNames, true);
            $permission = Permission::firstOrCreate([
                'name' => $permName,
                'guard_name' => 'web',
            ]);

            if ($wasMissing) {
                $targetRolesLower = array_map('strtolower', $meta['roles']);
                foreach ($existingRoles as $role) {
                    if (in_array(strtolower($role->name), $targetRolesLower, true)) {
                        if (!$role->hasPermissionTo($permission)) {
                            $role->givePermissionTo($permission);
                        }
                    }
                }
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * Mengelompokkan collection Spatie Permission dari DB untuk tampilan UI Manajemen Role.
     */
    public static function groupedForView(Collection $dbPermissions): array
    {
        $byName = $dbPermissions->keyBy('name');
        $usedIds = [];
        $groups = [];

        foreach (self::modules() as $moduleKey => $module) {
            $items = [];
            foreach ($module['permissions'] as $permName => $meta) {
                if ($byName->has($permName)) {
                    $perm = $byName->get($permName);
                    $usedIds[$perm->id] = true;
                    $items[] = [
                        'id' => $perm->id,
                        'name' => $perm->name,
                        'label' => $meta['label'],
                        'description' => $meta['description'],
                    ];
                }
            }

            if (count($items) > 0) {
                $groups[$moduleKey] = [
                    'label' => $module['label'],
                    'items' => $items,
                ];
            }
        }

        $legacyItems = [];
        foreach ($dbPermissions as $perm) {
            if (!isset($usedIds[$perm->id])) {
                $legacyItems[] = [
                    'id' => $perm->id,
                    'name' => $perm->name,
                    'label' => $perm->name,
                    'description' => 'Permission bawaan / custom sistem.',
                ];
            }
        }

        if (count($legacyItems) > 0) {
            $groups['legacy'] = [
                'label' => '8. Permission Bawaan / Lainnya',
                'items' => $legacyItems,
            ];
        }

        return $groups;
    }
}
