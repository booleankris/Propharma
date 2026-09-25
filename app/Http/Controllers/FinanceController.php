<?php

namespace App\Http\Controllers;

use App\Models\Creditor;
use App\Models\Debtors;
use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class FinanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || (!$user->hasRole('Finance') && !$user->hasRole('General Manager') && !$user->hasRole('administrator') && !$user->hasRole('HO'))) {
                abort(403, 'Akses ditolak: Modul SAHABAT Finances hanya dapat diakses oleh role Finance, General Manager, dan Administrator.');
            }
            return $next($request);
        });
    }

    /**
     * Tampilkan halaman Ringkasan (Dashboard) Keuangan Eksekutif.
     */
    public function index(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        // Dukung backward-compatibility jika ada query ?tab=
        if ($request->has('tab')) {
            $tab = $request->query('tab');
            if ($tab === 'hutang') return redirect()->route('finance.hutang');
            if ($tab === 'cash') return redirect()->route('finance.cash');
            if ($tab === 'piutang') return redirect()->route('finance.piutang');
            if ($tab === 'kas-bank' || $tab === 'bank') return redirect()->route('finance.kasBank');
        }

        $hutang = $this->getHutangData();
        $cash = $this->getCashData();
        $piutang = $this->getPiutangData();
        $stats = $this->getStats($hutang, $cash, $piutang);
        $recentMutasi = $this->getMutasiData(10);
        $accounts = FinanceAccount::where('category', 'Kas & Bank')->where('is_active', true)->orderBy('code')->get();

        // 5 Faktur Hutang yang mendekati atau melewati jatuh tempo
        $upcomingHutang = [];
        foreach ($hutang as $h) {
            if ($h['sisa'] > 0) {
                $dueDateStr = $h['raw_jatuh_tempo'] ?? null;
                $diffDays = 0;
                $isOverdue = false;
                if ($dueDateStr) {
                    $due = \Carbon\Carbon::parse($dueDateStr)->startOfDay();
                    $now = \Carbon\Carbon::now()->startOfDay();
                    $diffDays = $now->diffInDays($due, false);
                    $isOverdue = $diffDays < 0;
                }
                $upcomingHutang[] = [
                    'id' => $h['id'],
                    'no_faktur' => $h['nomor'],
                    'no_penerimaan' => $h['referensi'] ?? '',
                    'pbf_name' => $h['vendor'],
                    'due_date' => $h['jatuhTempo'],
                    'sisa_bayar' => $h['sisa'],
                    'is_overdue' => $isOverdue,
                    'days_diff' => abs((int) $diffDays),
                    'diff_raw' => $diffDays,
                ];
            }
        }
        usort($upcomingHutang, fn($a, $b) => $a['diff_raw'] <=> $b['diff_raw']);
        $upcomingHutang = array_slice($upcomingHutang, 0, 5);

        return Inertia::render('Finance/Dashboard', [
            'stats' => $stats,
            'recentMutasi' => $recentMutasi,
            'upcomingHutang' => $upcomingHutang,
            'accounts' => $accounts,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Pengelolaan Hutang Dagang (Faktur Pembelian Kredit).
     */
    public function hutang(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $hutangDagang = $this->getHutangData();
        $creditors = Creditor::orderBy('name')->get(['code', 'name']);
        $kasBankAccounts = FinanceAccount::where('category', 'Kas & Bank')->where('is_active', true)->orderBy('code')->get();
        $stats = $this->getStats($hutangDagang, null, null);

        return Inertia::render('Finance/Hutang', [
            'hutangDagang' => $hutangDagang,
            'creditors' => $creditors,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Pengelolaan Pembelian Cash (Tunai).
     */
    public function cash(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $pembelianCash = $this->getCashData();
        $creditors = Creditor::orderBy('name')->get(['code', 'name']);
        $kasBankAccounts = FinanceAccount::where('category', 'Kas & Bank')->where('is_active', true)->orderBy('code')->get();
        $stats = $this->getStats(null, $pembelianCash, null);

        return Inertia::render('Finance/Cash', [
            'pembelianCash' => $pembelianCash,
            'creditors' => $creditors,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Pengelolaan Piutang Penjualan (Kredit Pelanggan).
     */
    public function piutang(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $piutangPenjualan = $this->getPiutangData();
        $debtors = Debtors::orderBy('name')->get(['id', 'code', 'name', 'phone', 'city']);
        $kasBankAccounts = FinanceAccount::where('category', 'Kas & Bank')->where('is_active', true)->orderBy('code')->get();
        $stats = $this->getStats(null, null, $piutangPenjualan);

        return Inertia::render('Finance/Piutang', [
            'piutangPenjualan' => $piutangPenjualan,
            'debtors' => $debtors,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Pengelolaan Kas & Bank, Mutasi Kas, dan Bagan Akun (COA).
     */
    public function kasBank(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $accounts = FinanceAccount::orderBy('code')->get();
        $categories = $this->getCategories();
        $mutasiKasBank = $this->getMutasiData(500);
        $stats = $this->getStats(null, null, null);

        return Inertia::render('Finance/KasBank', [
            'accounts' => $accounts,
            'categories' => $categories,
            'mutasiKasBank' => $mutasiKasBank,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Helper: Ambil target pharmacy ID untuk filter data transaksi cabang.
     */
    protected function getTargetPharmacyIds(): array
    {
        $activeId = getActivePharmacyId();

        // Jika Gudang PMI (9) atau SAHABAT PMI (1) atau HO (6)
        if (in_array((int) $activeId, [1, 9, 6])) {
            return [1, 9];
        }

        if ($activeId > 0) {
            return [(int) $activeId];
        }

        return [1, 9];
    }

    /**
     * Helper: Ambil konteks cabang aktif untuk frontend.
     */
    protected function getBranchContext(): array
    {
        $activePharmacyId = getActivePharmacyId();
        $activePharmacy = Pharmacies::find($activePharmacyId);
        if (!$activePharmacy && in_array((int) $activePharmacyId, [1, 9, 6])) {
            $activePharmacy = Pharmacies::find(1);
        }

        $user = auth()->user();
        $isHoOrAdmin = $user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('General Manager'));

        $branches = [];
        if ($isHoOrAdmin) {
            $branches = Pharmacies::whereIn('id', [1, 9, 2, 3, 4, 5])
                ->orderByRaw('FIELD(id, 1, 9, 2, 3, 4, 5)')
                ->get(['id', 'name']);
        }

        return [
            'activePharmacy' => $activePharmacy ? [
                'id' => $activePharmacy->id,
                'name' => $activePharmacy->name,
            ] : [
                'id' => $activePharmacyId,
                'name' => 'Apotek Cabang',
            ],
            'branches' => $branches,
            'canSwitchBranch' => $isHoOrAdmin,
        ];
    }

    /**
     * Helper: Ambil data faktur pembelian KREDIT (Hutang Dagang).
     */
    protected function getHutangData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        $receivingsKredit = Receiving::with([
            'receiving_details' => function ($query) {
                $query->where('invoice_payment', 'KREDIT');
            },
            'receiving_details.creditor',
            'receiving_details.payments.account',
            'receiving_details.payments.creator',
            'receiving_details.receiving_items.order_items.medicines',
            'pharmacy'
        ])
            ->whereIn('status', [1, 2, 3])
            ->whereIn('pharmacy_id', $targetPharmacyIds)
            ->whereHas('receiving_details', function ($query) {
                $query->where('invoice_payment', 'KREDIT');
            })
            ->latest('updated_at')
            ->take(150)
            ->get();

        $hutangDagang = [];
        foreach ($receivingsKredit as $rec) {
            foreach ($rec->receiving_details as $detail) {
                if ($detail->invoice_payment !== 'KREDIT') continue;

                $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');
                $itemsList = [];
                $subtotal = 0;

                foreach ($detail->receiving_items as $item) {
                    $medName = $item->order_items->medicines->name ?? 'Obat';
                    $sku = $item->order_items->medicines->code ?? ('SKU-' . $item->id);
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $diskon = (float) ($item->discount ?? 0);
                    $totalItem = (float) ($item->total ?? ($qty * $harga));

                    $subtotal += $totalItem;

                    $itemsList[] = [
                        'sku' => $sku,
                        'nama' => $medName,
                        'qty' => $qty,
                        'satuan' => $item->order_items->medicines->unit ?? 'Pcs',
                        'diskon' => $diskon . '%',
                        'harga' => $harga,
                        'pajak' => strtoupper($detail->invoice_ppn ?? 'PPN11'),
                        'jumlah' => $totalItem,
                    ];
                }

                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
                $total = round($subtotal + $ppnNominal, 2);

                $paymentHistory = [];
                $totalTerbayar = 0;

                foreach ($detail->payments as $payment) {
                    $totalTerbayar += (float) $payment->amount;
                    $paymentHistory[] = [
                        'id' => $payment->id,
                        'tanggal' => \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y'),
                        'nominal' => (float) $payment->amount,
                        'noReferensi' => $payment->reference_number ?: '-',
                        'catatan' => $payment->notes ?: '-',
                        'akunId' => $payment->account_id,
                        'akunNama' => $payment->account->name ?? 'Kas & Bank',
                        'akunKode' => $payment->account->code ?? '',
                        'akunKategori' => $payment->account->category ?? 'Kas & Bank',
                        'diprosesOleh' => $payment->creator->name ?? ($payment->creator->username ?? 'Staf Finance'),
                        'waktuAudit' => \Carbon\Carbon::parse($payment->created_at)->format('d M Y, H:i'),
                    ];
                }

                $totalTerbayar = round($totalTerbayar, 2);
                $isLunas = ((int) $rec->status === 3) || ($totalTerbayar >= $total && $total > 0) || (($total - $totalTerbayar) < 0.005 && $total > 0);
                $sisa = $isLunas ? 0 : round(max(0, $total - $totalTerbayar), 2);

                $statusLabel = 'Belum Dibayar';
                if ($isLunas) {
                    $statusLabel = 'Lunas';
                } elseif ($totalTerbayar > 0) {
                    $statusLabel = 'Dibayar Sebagian';
                }

                $hutangDagang[] = [
                    'id' => $detail->id,
                    'receiving_id' => $rec->id,
                    'nomor' => $detail->invoice_number ?: $rec->code,
                    'vendor' => $vendorName,
                    'referensi' => $detail->receiving_details_code ?: $rec->code,
                    'tanggal' => $detail->invoice_date ? \Carbon\Carbon::parse($detail->invoice_date)->format('d/m/Y') : ($rec->date ?: '-'),
                    'jatuhTempo' => $detail->invoice_due ? \Carbon\Carbon::parse($detail->invoice_due)->format('d/m/Y') : '-',
                    'raw_jatuh_tempo' => $detail->invoice_due ? \Carbon\Carbon::parse($detail->invoice_due)->format('Y-m-d') : null,
                    'tanggalBayar' => $isLunas ? \Carbon\Carbon::parse($rec->updated_at)->format('d/m/Y') : '',
                    'status' => $statusLabel,
                    'subtotal' => $subtotal,
                    'ppn' => $ppnNominal,
                    'total' => $total,
                    'terbayar' => $totalTerbayar,
                    'sisa' => $sisa,
                    'gudang' => $rec->pharmacy->name ?? 'Gudang Utama',
                    'tglKirim' => $rec->date ?: '-',
                    'items' => $itemsList,
                    'payments' => $paymentHistory,
                    'lastModified' => \Carbon\Carbon::parse($rec->updated_at)->format('d M Y H:i'),
                ];
            }
        }
        return $hutangDagang;
    }

    /**
     * Helper: Ambil data faktur pembelian CASH (Tunai).
     */
    protected function getCashData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        $receivingsCash = Receiving::with([
            'receiving_details' => function ($query) {
                $query->where('invoice_payment', 'TUNAI');
            },
            'receiving_details.creditor',
            'receiving_details.payments.account',
            'receiving_details.payments.creator',
            'receiving_details.receiving_items.order_items.medicines',
            'pharmacy'
        ])
            ->whereIn('status', [1, 2, 3])
            ->whereIn('pharmacy_id', $targetPharmacyIds)
            ->whereHas('receiving_details', function ($query) {
                $query->where('invoice_payment', 'TUNAI');
            })
            ->latest('updated_at')
            ->take(100)
            ->get();

        $pembelianCash = [];
        foreach ($receivingsCash as $rec) {
            foreach ($rec->receiving_details as $detail) {
                if ($detail->invoice_payment !== 'TUNAI') continue;

                $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');
                $itemsList = [];
                $subtotal = 0;

                foreach ($detail->receiving_items as $item) {
                    $medName = $item->order_items->medicines->name ?? 'Obat';
                    $sku = $item->order_items->medicines->code ?? ('SKU-' . $item->id);
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $diskon = (float) ($item->discount ?? 0);
                    $totalItem = (float) ($item->total ?? ($qty * $harga));

                    $subtotal += $totalItem;

                    $itemsList[] = [
                        'sku' => $sku,
                        'nama' => $medName,
                        'qty' => $qty,
                        'satuan' => $item->order_items->medicines->unit ?? 'Pcs',
                        'diskon' => $diskon . '%',
                        'harga' => $harga,
                        'pajak' => strtoupper($detail->invoice_ppn ?? 'PPN11'),
                        'jumlah' => $totalItem,
                    ];
                }

                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
                $total = round($subtotal + $ppnNominal, 2);

                $latestPayment = $detail->payments->last();
                $akunPembayaran = null;
                if ($latestPayment && $latestPayment->account) {
                    $akunPembayaran = [
                        'id' => $latestPayment->account->id,
                        'nama' => $latestPayment->account->name,
                        'kode' => $latestPayment->account->code,
                        'kategori' => $latestPayment->account->category,
                        'noRekening' => $latestPayment->account->account_number,
                        'noReferensi' => $latestPayment->reference_number,
                        'tanggal' => \Carbon\Carbon::parse($latestPayment->payment_date)->format('d/m/Y'),
                        'diprosesOleh' => $latestPayment->creator->name ?? 'Staf',
                    ];
                }

                $pembelianCash[] = [
                    'id' => $detail->id,
                    'receiving_id' => $rec->id,
                    'nomor' => $detail->invoice_number ?: $rec->code,
                    'vendor' => $vendorName,
                    'referensi' => $detail->receiving_details_code ?: $rec->code,
                    'tanggal' => $detail->invoice_date ? \Carbon\Carbon::parse($detail->invoice_date)->format('d/m/Y') : ($rec->date ?: '-'),
                    'status' => 'Lunas (Cash)',
                    'subtotal' => $subtotal,
                    'ppn' => $ppnNominal,
                    'total' => $total,
                    'gudang' => $rec->pharmacy->name ?? 'Gudang Utama',
                    'akunPembayaran' => $akunPembayaran,
                    'items' => $itemsList,
                    'lastModified' => \Carbon\Carbon::parse($rec->updated_at)->format('d M Y H:i'),
                ];
            }
        }
        return $pembelianCash;
    }

    /**
     * Helper: Ambil data penjualan KREDIT (Piutang Penjualan).
     */
    protected function getPiutangData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        $creditTransactions = MedicineTransactions::with([
            'debtors',
            'doctors',
            'patients',
            'pharmacy',
            'payments.account',
            'payments.creator',
            'transactions.medicine',
        ])
            ->where('transaction_type', 'KREDIT')
            ->where('status', 1)
            ->whereIn('pharmacy_id', $targetPharmacyIds)
            ->latest('id')
            ->take(300)
            ->get();

        $piutangPenjualan = [];
        foreach ($creditTransactions as $tx) {
            $debtorName = $tx->debtors->name ?? ($tx->patient_id ? ('Pasien: ' . ($tx->patients->name ?? 'Umum')) : 'Pelanggan Umum');
            $itemsList = [];
            $calcSubtotal = 0;

            foreach ($tx->transactions as $cart) {
                $medName = $cart->medicine->name ?? 'Obat';
                $sku = $cart->medicine->code ?? ('SKU-' . $cart->medicine_id);
                $qty = (float) ($cart->quantity ?? 0);
                $harga = (float) ($cart->item_price ?? 0);
                $diskon = (float) ($cart->discount ?? 0);
                $finalPrice = (float) ($cart->final_price ?: ($cart->total_price ?: ($qty * $harga)));

                $calcSubtotal += $finalPrice;

                $itemsList[] = [
                    'id' => $cart->id,
                    'sku' => $sku,
                    'nama' => $medName,
                    'qty' => $qty,
                    'satuan' => $cart->medicine->unit ?? ($cart->package ?? 'Pcs'),
                    'diskon' => $diskon . '%',
                    'harga' => $harga,
                    'pajak' => 'NON',
                    'jumlah' => $finalPrice,
                ];
            }

            $total = (float) ($tx->subtotal > 0 ? $tx->subtotal : $calcSubtotal);

            $paymentHistory = [];
            $totalTerbayarViaFinance = 0;
            foreach ($tx->payments as $payment) {
                $totalTerbayarViaFinance += (float) $payment->amount;
                $paymentHistory[] = [
                    'id' => $payment->id,
                    'tanggal' => \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y'),
                    'nominal' => (float) $payment->amount,
                    'noReferensi' => $payment->reference_number ?: '-',
                    'catatan' => $payment->notes ?: '-',
                    'akunNama' => $payment->account->name ?? 'Kas & Bank',
                    'akunKode' => $payment->account->code ?? '-',
                    'akunKategori' => $payment->account->category ?? 'Kas & Bank',
                    'noRekening' => $payment->account->account_number ?? '-',
                    'diprosesOleh' => $payment->creator->name ?? 'Staf Finance',
                    'waktuAudit' => \Carbon\Carbon::parse($payment->created_at)->format('d M Y H:i'),
                ];
            }

            $legacyPaid = (float) ($tx->paid ?? 0);
            $totalTerbayar = round(max($totalTerbayarViaFinance, $legacyPaid), 2);
            $total = round($total, 2);
            if ($totalTerbayar > $total) {
                $totalTerbayar = $total;
            }
            $sisa = round(max(0, $total - $totalTerbayar), 2);

            $isLunas = ($sisa < 0.005 && $total > 0);
            $sisa = $isLunas ? 0 : $sisa;
            $statusLabel = $isLunas ? 'Lunas' : ($totalTerbayar > 0 ? 'Dibayar Sebagian' : 'Belum Bayar');

            $createdCarbon = $tx->created_at ? \Carbon\Carbon::parse($tx->created_at) : null;
            $dueCarbon = $createdCarbon ? $createdCarbon->copy()->addDays(30) : null;
            $isOverdue = ($dueCarbon && !$isLunas) ? $dueCarbon->isPast() : false;
            $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays(now()) : 0;
            $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas) ? (int) now()->diffInDays($dueCarbon) : 0;

            $piutangPenjualan[] = [
                'id' => $tx->id,
                'nomor' => $tx->transaction_code ?: ('TRX-' . $tx->id),
                'debtor' => $debtorName,
                'debtor_id' => $tx->debtor_id,
                'debtor_code' => $tx->debtors->code ?? '-',
                'dokter' => $tx->doctors->name ?? '-',
                'pasien' => $tx->patients->name ?? '-',
                'apotek' => $tx->pharmacy->name ?? 'Apotek Utama',
                'tanggal' => $createdCarbon ? $createdCarbon->format('d/m/Y') : '-',
                'raw_created_at' => $createdCarbon ? $createdCarbon->format('Y-m-d H:i:s') : null,
                'jatuhTempo' => $dueCarbon ? $dueCarbon->format('d/m/Y') : '-',
                'raw_jatuh_tempo' => $dueCarbon ? $dueCarbon->format('Y-m-d') : null,
                'is_overdue' => $isOverdue,
                'days_overdue' => $daysOverdue,
                'days_remaining' => $daysRemaining,
                'status' => $statusLabel,
                'subtotal' => $total,
                'total' => $total,
                'terbayar' => $totalTerbayar,
                'sisa' => $sisa,
                'items' => $itemsList,
                'payments' => $paymentHistory,
                'lastModified' => $tx->updated_at ? \Carbon\Carbon::parse($tx->updated_at)->format('d M Y H:i') : '-',
            ];
        }
        return $piutangPenjualan;
    }

    /**
     * Helper: Ambil riwayat Mutasi Transaksi Kas & Bank.
     */
    protected function getMutasiData($limit = 500)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        return FinancePayment::with([
            'account',
            'creator',
            'receivingDetail.creditor',
            'transaction.debtors',
            'transaction.patients',
        ])
            ->whereHas('account', function ($q) {
                $q->where('category', 'Kas & Bank');
            })
            ->where(function ($query) use ($targetPharmacyIds) {
                $query->whereHas('receiving', function ($q) use ($targetPharmacyIds) {
                    $q->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhereHas('transaction', function ($q) use ($targetPharmacyIds) {
                    $q->whereIn('pharmacy_id', $targetPharmacyIds);
                });
            })
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->take($limit)
            ->get()
            ->map(function ($p) {
                $isMasuk = ($p->payment_type === 'PIUTANG');
                $tipeLabel = $isMasuk ? 'Masuk (Debit)' : 'Keluar (Kredit)';
                
                $pihakTerkait = '-';
                $noDokumen = $p->reference_number ?: ('MUT-' . str_pad($p->id, 5, '0', STR_PAD_LEFT));
                $deskripsi = $p->notes;

                if ($p->payment_type === 'PIUTANG') {
                    $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
                    $doc = $p->transaction->invoice_number ?? $p->reference_number;
                    if ($doc) $noDokumen = $doc;
                    if (!$deskripsi) {
                        $deskripsi = "Penerimaan piutang penjualan dari {$pihakTerkait}";
                    }
                } elseif ($p->payment_type === 'KREDIT') {
                    $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
                    $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
                    if ($doc) $noDokumen = $doc;
                    if (!$deskripsi) {
                        $deskripsi = "Pelunasan hutang pembelian ke {$pihakTerkait}";
                    }
                } elseif ($p->payment_type === 'CASH') {
                    $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
                    $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
                    if ($doc) $noDokumen = $doc;
                    if (!$deskripsi) {
                        $deskripsi = "Pembelian tunai (cash) ke {$pihakTerkait}";
                    }
                }

                return [
                    'id' => $p->id,
                    'account_id' => $p->account_id,
                    'account_name' => $p->account->name ?? 'Akun Kas',
                    'account_code' => $p->account->code ?? '-',
                    'account_number' => $p->account->account_number ?? '-',
                    'date' => \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'raw_date' => \Carbon\Carbon::parse($p->payment_date)->format('Y-m-d'),
                    'amount' => (float) $p->amount,
                    'direction' => $isMasuk ? 'IN' : 'OUT',
                    'type_label' => $tipeLabel,
                    'category_type' => $p->payment_type, // PIUTANG, KREDIT, CASH
                    'document_no' => $noDokumen,
                    'party' => $pihakTerkait,
                    'description' => $deskripsi ?: 'Transaksi Keuangan',
                    'user' => $p->creator->name ?? 'Admin',
                ];
            });
    }

    /**
     * Helper: Ambil kategori akun.
     */
    protected function getCategories()
    {
        $defaultCategories = [
            'Kas & Bank',
            'Piutang Usaha',
            'Persediaan',
            'Aktiva Lancar Lainnya',
            'Aktiva Tetap',
            'Hutang Usaha',
            'Kewajiban Lancar Lainnya',
            'Kewajiban Jangka Panjang',
            'Ekuitas / Modal',
            'Pendapatan',
            'Harga Pokok Penjualan',
            'Beban Operasional',
            'Beban Lainnya',
        ];
        $existingCategories = FinanceAccount::select('category')->distinct()->pluck('category')->toArray();
        return array_values(array_unique(array_merge($defaultCategories, $existingCategories)));
    }

    /**
     * Helper: Hitung ringkasan statistik modul finance.
     */
    protected function getStats($hutang = null, $cash = null, $piutang = null)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $cacheKey = 'finance_stats_summary_' . implode('_', $targetPharmacyIds);

        // Jika data lengkap tidak disediakan, gunakan cache agar angka badge dan ringkasan selalu konsisten di semua halaman
        $isFullData = ($hutang !== null && $cash !== null && $piutang !== null);
        if (!$isFullData) {
            $cached = Cache::get($cacheKey);
            if ($cached) {
                return $cached;
            }
        }

        if ($hutang === null) {
            $hutang = $this->getHutangData();
        }
        if ($cash === null) {
            $cash = $this->getCashData();
        }
        if ($piutang === null) {
            $piutang = $this->getPiutangData();
        }

        $totalSaldoKasBank = (float) FinanceAccount::where('category', 'Kas & Bank')->where('is_active', true)->sum('balance');
        $accountsCount = FinanceAccount::where('category', 'Kas & Bank')->where('is_active', true)->count();

        $kasMasuk = (float) FinancePayment::where('payment_type', 'PIUTANG')
            ->whereHas('transaction', function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds);
            })
            ->sum('amount');

        $kasKeluar = (float) FinancePayment::whereIn('payment_type', ['KREDIT', 'CASH'])
            ->whereHas('receiving', function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds);
            })
            ->sum('amount');

        $netCashflow = $kasMasuk - $kasKeluar;

        // Hutang Stats
        $countHutangBelumLunas = 0;
        $countBelumBayar = 0;
        $countSebagian = 0;
        $countLunas = 0;
        $totalHutangSisa = 0;

        foreach ($hutang as $h) {
            $totalHutangSisa += $h['sisa'];
            if ($h['status'] === 'Lunas') {
                $countLunas++;
            } elseif ($h['status'] === 'Dibayar Sebagian') {
                $countSebagian++;
                $countHutangBelumLunas++;
            } else {
                $countBelumBayar++;
                $countHutangBelumLunas++;
            }
        }

        // Cash Stats
        $totalCashPurchases = array_sum(array_column($cash, 'total'));
        $totalCashCount = count($cash);

        // Piutang Stats
        $totalPiutangSisa = 0;
        $countPiutangBelumBayar = 0;
        $countPiutangLunas = 0;
        foreach ($piutang as $p) {
            $totalPiutangSisa += $p['sisa'];
            if ($p['status'] === 'Lunas') {
                $countPiutangLunas++;
            } else {
                $countPiutangBelumBayar++;
            }
        }

        $stats = [
            'totalSaldoKasBank' => $totalSaldoKasBank,
            'accountsCount' => $accountsCount,
            'kasMasuk' => $kasMasuk,
            'kasKeluar' => $kasKeluar,
            'netCashflow' => $netCashflow,
            'totalHutangSisa' => $totalHutangSisa,
            'totalSisaHutang' => $totalHutangSisa,
            'countHutangBelumLunas' => $countHutangBelumLunas,
            'countBelumBayar' => $countBelumBayar,
            'countSebagian' => $countSebagian,
            'countLunas' => $countLunas,
            'totalCashPurchases' => $totalCashPurchases,
            'totalCashSpent' => $totalCashPurchases,
            'totalCashCount' => $totalCashCount,
            'totalPiutangSisa' => $totalPiutangSisa,
            'totalSisaPiutang' => $totalPiutangSisa,
            'countPiutangBelumBayar' => $countPiutangBelumBayar,
            'countPiutangLunas' => $countPiutangLunas,
        ];

        Cache::put($cacheKey, $stats, 60);

        return $stats;
    }

    /**
     * Helper: Hapus cache ringkasan statistik modul finance.
     */
    protected function clearStatsCache()
    {
        Cache::forget('finance_stats_summary');
        $branches = [1, 2, 3, 4, 5, 6, 9];
        foreach ($branches as $b) {
            Cache::forget('finance_stats_summary_' . $b);
        }
        Cache::forget('finance_stats_summary_1_9');
    }

    /**
     * Catat pembayaran faktur hutang dagang (Kredit).
     */
    public function storePayment(Request $request)
    {
        $validated = $request->validate([
            'receiving_detail_id' => 'required|exists:receiving_details,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $detail = ReceivingDetails::with(['receiving_items', 'payments', 'receiving'])->findOrFail($validated['receiving_detail_id']);
        $account = FinanceAccount::findOrFail($validated['account_id']);

        // Verifikasi akun harus ber-kategori 'Kas & Bank'
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        // Hitung total invoice
        $subtotal = 0;
        foreach ($detail->receiving_items as $item) {
            $qty = (float) ($item->qty_received ?? 0);
            $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
            $totalItem = (float) ($item->total ?? ($qty * $harga));
            $subtotal += $totalItem;
        }
        $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
        $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
        $totalInvoice = round($subtotal + $ppnNominal, 2);

        // Akumulasi yang sudah terbayar
        $alreadyPaid = (float) FinancePayment::where('receiving_detail_id', $detail->id)->sum('amount');
        $sisaHutang = round(max(0, $totalInvoice - $alreadyPaid), 2);

        if (round($validated['amount'], 2) > $sisaHutang) {
            $formattedSisa = fmod($sisaHutang, 1) !== 0.0 ? number_format($sisaHutang, 2, ',', '.') : number_format($sisaHutang, 0, ',', '.');
            return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan (Rp ' . $formattedSisa . ').']);
        }

        DB::transaction(function () use ($validated, $detail, $account, $totalInvoice) {
            // 1. Simpan Payment
            FinancePayment::create([
                'receiving_id' => $detail->receiving_id,
                'receiving_detail_id' => $detail->id,
                'account_id' => $account->id,
                'payment_type' => 'KREDIT',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // 2. Kurangi saldo akun Kas & Bank
            $account->decrement('balance', $validated['amount']);

            // 3. Hitung akumulasi pembayaran yang sudah masuk
            $totalTerbayar = (float) FinancePayment::where('receiving_detail_id', $detail->id)->sum('amount');

            // 4. Update status receiving jika sudah lunas
            if ((round($totalTerbayar, 2) >= round($totalInvoice, 2) || ($totalInvoice - $totalTerbayar) < 0.005) && $detail->receiving) {
                $detail->receiving->update(['status' => 3]);
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Pembayaran hutang dagang berhasil dicatat.');
    }

    /**
     * Tetapkan atau ubah akun Kas & Bank untuk Pembelian Cash (Tunai).
     */
    public function assignCashAccount(Request $request)
    {
        $validated = $request->validate([
            'receiving_detail_id' => 'required|exists:receiving_details,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $detail = ReceivingDetails::with(['receiving_items', 'receiving'])->findOrFail($validated['receiving_detail_id']);
        $account = FinanceAccount::findOrFail($validated['account_id']);

        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        DB::transaction(function () use ($validated, $detail, $account) {
            // Hitung total invoice
            $subtotal = 0;
            foreach ($detail->receiving_items as $item) {
                $qty = (float) ($item->qty_received ?? 0);
                $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                $totalItem = (float) ($item->total ?? ($qty * $harga));
                $subtotal += $totalItem;
            }
            $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
            $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
            $totalInvoice = round($subtotal + $ppnNominal, 2);

            // Cek apakah sudah pernah ada payment record untuk detail ini
            $existingPayment = FinancePayment::where('receiving_detail_id', $detail->id)->first();

            if ($existingPayment) {
                // Balikkan saldo akun lama dan kurangi saldo akun baru jika berbeda
                if ($existingPayment->account_id != $account->id) {
                    $oldAccount = FinanceAccount::find($existingPayment->account_id);
                    if ($oldAccount) {
                        $oldAccount->increment('balance', $existingPayment->amount);
                    }
                    $account->decrement('balance', $totalInvoice);
                }
                $existingPayment->update([
                    'account_id' => $account->id,
                    'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                    'amount' => $totalInvoice,
                    'reference_number' => $validated['reference_number'] ?? $existingPayment->reference_number,
                    'notes' => $validated['notes'] ?? $existingPayment->notes,
                    'created_by' => Auth::id(),
                ]);
            } else {
                FinancePayment::create([
                    'receiving_id' => $detail->receiving_id,
                    'receiving_detail_id' => $detail->id,
                    'account_id' => $account->id,
                    'payment_type' => 'CASH',
                    'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                    'amount' => $totalInvoice,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['notes'] ?? 'Pembelian Cash via ' . $account->name,
                    'created_by' => Auth::id(),
                ]);
                $account->decrement('balance', $totalInvoice);
            }

            // Pastikan status receiving adalah 3 (Lunas)
            if ($detail->receiving) {
                $detail->receiving->update(['status' => 3]);
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Akun pembayaran pembelian cash berhasil disimpan.');
    }

    /**
     * Pelunasan massal (multi) faktur hutang dagang kredit (Maksimal 10 item).
     */
    public function storeBulkPayments(Request $request)
    {
        $validated = $request->validate([
            'receiving_detail_ids' => 'required|array|min:1|max:10',
            'receiving_detail_ids.*' => 'required|exists:receiving_details,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $account = FinanceAccount::findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $totalPaidAll = 0;
        $processedCount = 0;

        DB::transaction(function () use ($validated, $account, &$totalPaidAll, &$processedCount) {
            foreach ($validated['receiving_detail_ids'] as $detailId) {
                $detail = ReceivingDetails::with(['receiving_items', 'payments', 'receiving'])->find($detailId);
                if (!$detail || $detail->invoice_payment !== 'KREDIT') continue;

                // Hitung total invoice
                $subtotal = 0;
                foreach ($detail->receiving_items as $item) {
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $totalItem = (float) ($item->total ?? ($qty * $harga));
                    $subtotal += $totalItem;
                }
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
                $totalInvoice = round($subtotal + $ppnNominal, 2);

                // Hitung sisa yang belum terbayar
                $alreadyPaid = (float) FinancePayment::where('receiving_detail_id', $detail->id)->sum('amount');
                $remaining = round(max(0, $totalInvoice - $alreadyPaid), 2);

                if ($remaining < 0.005) {
                    if ($detail->receiving && (int)$detail->receiving->status !== 3) {
                        $detail->receiving->update(['status' => 3]);
                    }
                    continue;
                }

                FinancePayment::create([
                    'receiving_id' => $detail->receiving_id,
                    'receiving_detail_id' => $detail->id,
                    'account_id' => $account->id,
                    'payment_type' => 'KREDIT',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $remaining,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => !empty($validated['notes'])
                        ? ($validated['notes'] . ' (Pelunasan Massal)')
                        : ('Pelunasan Massal via ' . $account->name),
                    'created_by' => Auth::id(),
                ]);

                $totalPaidAll += $remaining;
                $processedCount++;

                if ($detail->receiving) {
                    $detail->receiving->update(['status' => 3]);
                }
            }

            if ($totalPaidAll > 0) {
                $account->decrement('balance', $totalPaidAll);
            }
        });

        $this->clearStatsCache();
        $formattedTotal = number_format($totalPaidAll, 0, ',', '.');
        return back()->with('success', "Berhasil melunasi {$processedCount} faktur hutang dagang sebesar Rp {$formattedTotal} via {$account->name}.");
    }

    /**
     * Tetapkan akun Kas & Bank massal (multi) untuk Pembelian Cash (Maksimal 10 item).
     */
    public function assignBulkCashAccount(Request $request)
    {
        $validated = $request->validate([
            'receiving_detail_ids' => 'required|array|min:1|max:10',
            'receiving_detail_ids.*' => 'required|exists:receiving_details,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'nullable|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $account = FinanceAccount::findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $processedCount = 0;
        $paymentDate = $validated['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($validated, $account, $paymentDate, &$processedCount) {
            foreach ($validated['receiving_detail_ids'] as $detailId) {
                $detail = ReceivingDetails::with(['receiving_items', 'receiving'])->find($detailId);
                if (!$detail || $detail->invoice_payment !== 'TUNAI') continue;

                $subtotal = 0;
                foreach ($detail->receiving_items as $item) {
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $totalItem = (float) ($item->total ?? ($qty * $harga));
                    $subtotal += $totalItem;
                }
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
                $totalInvoice = round($subtotal + $ppnNominal, 2);

                $existingPayment = FinancePayment::where('receiving_detail_id', $detail->id)->first();
                if ($existingPayment) {
                    if ($existingPayment->account_id != $account->id) {
                        $oldAccount = FinanceAccount::find($existingPayment->account_id);
                        if ($oldAccount) {
                            $oldAccount->increment('balance', $existingPayment->amount);
                        }
                        $account->decrement('balance', $totalInvoice);
                    }
                    $existingPayment->update([
                        'account_id' => $account->id,
                        'payment_date' => $paymentDate,
                        'amount' => $totalInvoice,
                        'reference_number' => $validated['reference_number'] ?? $existingPayment->reference_number,
                        'notes' => $validated['notes'] ?? $existingPayment->notes,
                        'created_by' => Auth::id(),
                    ]);
                } else {
                    FinancePayment::create([
                        'receiving_id' => $detail->receiving_id,
                        'receiving_detail_id' => $detail->id,
                        'account_id' => $account->id,
                        'payment_type' => 'CASH',
                        'payment_date' => $paymentDate,
                        'amount' => $totalInvoice,
                        'reference_number' => $validated['reference_number'] ?? null,
                        'notes' => $validated['notes'] ?? ('Pembelian Cash via ' . $account->name),
                        'created_by' => Auth::id(),
                    ]);
                    $account->decrement('balance', $totalInvoice);
                }

                if ($detail->receiving) {
                    $detail->receiving->update(['status' => 3]);
                }

                $processedCount++;
            }
        });

        $this->clearStatsCache();
        return back()->with('success', "Berhasil menetapkan akun {$account->name} untuk {$processedCount} transaksi pembelian cash.");
    }

    /**
     * Helper untuk membuat kode akun otomatis melanjutkan nomor sebelumnya
     */
    protected function generateNextAccountCode(string $category): string
    {
        $prefixMap = [
            'Kas & Bank' => '1-100',
            'Piutang Usaha' => '1-102',
            'Persediaan' => '1-103',
            'Aktiva Lancar Lainnya' => '1-108',
            'Aktiva Tetap' => '1-109',
            'Hutang Usaha' => '2-201',
            'Kewajiban Lancar Lainnya' => '2-202',
            'Kewajiban Jangka Panjang' => '2-205',
            'Ekuitas / Modal' => '3-300',
            'Pendapatan' => '4-400',
            'Harga Pokok Penjualan' => '5-500',
            'Beban Operasional' => '6-600',
            'Beban Lainnya' => '8-800',
        ];

        $prefix = $prefixMap[$category] ?? null;

        if (!$prefix) {
            $lastInSameCat = FinanceAccount::where('category', $category)->orderBy('code', 'desc')->first();
            if ($lastInSameCat && preg_match('/^(.+?)(\d+)$/', $lastInSameCat->code, $matches)) {
                $base = $matches[1];
                $num = (int)$matches[2] + 1;
                return $base . str_pad($num, strlen($matches[2]), '0', STR_PAD_LEFT);
            }
            $prefix = '9-900';
        }

        $existingCodes = FinanceAccount::where('code', 'LIKE', $prefix . '%')->pluck('code')->toArray();

        if (empty($existingCodes)) {
            return $prefix . '01';
        }

        $maxNum = 0;
        foreach ($existingCodes as $c) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $c, $m)) {
                $n = (int)$m[1];
                if ($n > $maxNum) {
                    $maxNum = $n;
                }
            }
        }

        $nextNum = $maxNum + 1;
        return $prefix . str_pad($nextNum, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Tambah akun baru (Chart of Accounts ala Kledo).
     */
    public function storeAccount(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'code' => 'nullable|string|max:50|unique:finance_accounts,code',
            'category' => 'required|string|max:100',
            'account_number' => 'nullable|string|max:100',
        ]);

        // Jika kode tidak diisi, otomatis buat kode melanjutkan nomor sebelumnya
        $code = !empty($validated['code'])
            ? trim($validated['code'])
            : $this->generateNextAccountCode($validated['category']);

        FinanceAccount::create([
            'name' => $validated['name'],
            'name_en' => !empty($validated['name_en']) ? $validated['name_en'] : $validated['name'],
            'code' => $code,
            'category' => $validated['category'],
            'account_number' => $validated['account_number'] ?? null,
            'balance' => 0,
            'is_active' => true,
        ]);

        $this->clearStatsCache();
        return back()->with('success', "Akun baru '{$validated['name']}' dengan kode {$code} berhasil ditambahkan.");
    }

    /**
     * Perbarui data akun yang ada.
     */
    public function updateAccount(Request $request, $id)
    {
        $account = FinanceAccount::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'code' => 'required|string|max:50|unique:finance_accounts,code,' . $account->id,
            'category' => 'required|string|max:100',
            'account_number' => 'nullable|string|max:100',
        ]);

        $account->update([
            'name' => $validated['name'],
            'name_en' => !empty($validated['name_en']) ? $validated['name_en'] : $validated['name'],
            'code' => trim($validated['code']),
            'category' => $validated['category'],
            'account_number' => $validated['account_number'] ?? null,
        ]);

        $this->clearStatsCache();
        return back()->with('success', "Akun '{$account->name}' berhasil diperbarui.");
    }

    /**
     * Hapus akun keuangan jika belum ada transaksi pembayaran terkait.
     */
    public function destroyAccount($id)
    {
        $account = FinanceAccount::withCount('payments')->findOrFail($id);

        if ($account->payments_count > 0) {
            return back()->withErrors([
                'account' => "Akun '{$account->name}' tidak dapat dihapus karena sudah memiliki riwayat {$account->payments_count} transaksi pembayaran."
            ]);
        }

        $accountName = $account->name;
        $account->delete();

        $this->clearStatsCache();
        return back()->with('success', "Akun '{$accountName}' berhasil dihapus.");
    }

    /**
     * Catat penerimaan pembayaran piutang penjualan (Kredit).
     */
    public function storePiutangPayment(Request $request)
    {
        $validated = $request->validate([
            'medicine_transaction_id' => 'required|exists:medicine_transactions,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $tx = MedicineTransactions::with(['payments'])->findOrFail($validated['medicine_transaction_id']);
        $account = FinanceAccount::findOrFail($validated['account_id']);

        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $totalTerbayarExisting = (float) $tx->payments->sum('amount');
        $totalNominal = (float) $tx->subtotal;
        $sisa = round(max(0, $totalNominal - $totalTerbayarExisting), 2);

        if (round($validated['amount'], 2) > $sisa) {
            $formattedSisa = fmod($sisa, 1) !== 0.0 ? number_format($sisa, 2, ',', '.') : number_format($sisa, 0, ',', '.');
            return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan piutang (Rp ' . $formattedSisa . ').']);
        }

        DB::beginTransaction();
        try {
            FinancePayment::create([
                'medicine_transaction_id' => $tx->id,
                'account_id' => $account->id,
                'payment_type' => 'PIUTANG',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'],
                'notes' => $validated['notes'],
                'created_by' => Auth::id(),
            ]);

            $newTotalPaid = round($totalTerbayarExisting + $validated['amount'], 2);
            $tx->paid = (string) $newTotalPaid;
            $tx->save();

            // Tambah saldo akun Kas & Bank (Penerimaan Piutang)
            $account->increment('balance', $validated['amount']);

            DB::commit();

            $this->clearStatsCache();
            return redirect()->back()->with('success', 'Penerimaan pembayaran piutang berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal mencatat penerimaan pembayaran piutang: ' . $e->getMessage()]);
        }
    }

    /**
     * Pelunasan massal piutang penjualan (Maks. 10 Faktur).
     */
    public function bulkPiutangPayment(Request $request)
    {
        // Mendukung kedua nama parameter: transaction_ids dan medicine_transaction_ids
        $ids = $request->input('transaction_ids') ?? $request->input('medicine_transaction_ids');
        if ($ids) {
            $request->merge(['transaction_ids' => $ids]);
        }

        $validated = $request->validate([
            'transaction_ids' => 'required|array|min:1|max:10',
            'transaction_ids.*' => 'required|exists:medicine_transactions,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ], [
            'transaction_ids.required' => 'Pilih minimal 1 transaksi piutang untuk dilunasi.',
            'transaction_ids.min' => 'Pilih minimal 1 transaksi piutang.',
            'transaction_ids.max' => 'Maksimal 10 transaksi piutang yang dapat dilunasi sekaligus.',
            'account_id.required' => 'Silakan pilih akun Kas & Bank penerima.',
        ]);

        $account = FinanceAccount::findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun kas/bank yang dipilih harus berkategori Kas & Bank.']);
        }

        $transactions = MedicineTransactions::with(['payments'])
            ->whereIn('id', $validated['transaction_ids'])
            ->get();

        DB::beginTransaction();
        try {
            $processedCount = 0;
            foreach ($transactions as $tx) {
                $totalPaid = (float) $tx->payments->sum('amount');
                $totalNominal = (float) $tx->subtotal;
                $sisa = round(max(0, $totalNominal - $totalPaid), 2);

                if ($sisa < 0.005) continue;

                FinancePayment::create([
                    'medicine_transaction_id' => $tx->id,
                    'account_id' => $account->id,
                    'payment_type' => 'PIUTANG',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $sisa,
                    'reference_number' => $validated['reference_number'] ?: ('BULK-PIUTANG-' . date('YmdHis')),
                    'notes' => $validated['notes'] ?: 'Pelunasan massal piutang penjualan',
                    'created_by' => Auth::id(),
                ]);

                $tx->paid = (string) $totalNominal;
                $tx->save();

                // Tambah saldo akun Kas & Bank
                $account->increment('balance', $sisa);

                $processedCount++;
            }

            DB::commit();

            $this->clearStatsCache();
            return redirect()->back()->with('success', "Berhasil melunasi {$processedCount} tagihan piutang penjualan.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal memproses pelunasan massal piutang: ' . $e->getMessage()]);
        }
    }

    /**
     * Export data Piutang Penjualan (Streaming CSV dengan performa tinggi & hemat memori).
     */
    public function exportPiutang(Request $request): StreamedResponse
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Export_Piutang_Penjualan_{$branchName}_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($targetPharmacyIds, $request) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'No',
                'No Transaksi',
                'Debitur / Instansi',
                'Kode Debitur',
                'Dokter',
                'Pasien',
                'Apotek Cabang',
                'Tanggal Transaksi',
                'Jatuh Tempo',
                'Status Jatuh Tempo',
                'Status Pembayaran',
                'Total Penjualan (Rp)',
                'Sudah Terbayar (Rp)',
                'Sisa Piutang (Rp)',
            ], ';');

            $query = MedicineTransactions::with([
                'debtors',
                'doctors',
                'patients',
                'pharmacy',
                'payments',
                'transactions.medicine',
            ])
                ->where('transaction_type', 'KREDIT')
                ->where('status', 1)
                ->whereIn('pharmacy_id', $targetPharmacyIds);

            if ($request->filled('search')) {
                $s = trim($request->search);
                $query->where(function ($q) use ($s) {
                    $q->where('transaction_code', 'like', "%{$s}%")
                      ->orWhereHas('debtors', fn($qd) => $qd->where('name', 'like', "%{$s}%"))
                      ->orWhereHas('patients', fn($qp) => $qp->where('name', 'like', "%{$s}%"))
                      ->orWhereHas('doctors', fn($qdc) => $qdc->where('name', 'like', "%{$s}%"));
                });
            }

            if ($request->filled('debtor_id')) {
                $query->where('debtor_id', $request->debtor_id);
            }

            $sortBy = $request->get('sort_by', 'created_at');
            $sortOrder = strtolower($request->get('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc';
            if ($sortBy === 'due_date') {
                $query->orderBy('created_at', $sortOrder);
            } else {
                $query->orderBy('created_at', $sortOrder);
            }

            $no = 1;
            foreach ($query->cursor() as $tx) {
                $calcSubtotal = 0;
                foreach ($tx->transactions as $cart) {
                    $qty = (float) ($cart->quantity ?? 0);
                    $harga = (float) ($cart->item_price ?? 0);
                    $calcSubtotal += (float) ($cart->final_price ?: ($cart->total_price ?: ($qty * $harga)));
                }

                $total = (float) ($tx->subtotal > 0 ? $tx->subtotal : $calcSubtotal);
                $totalTerbayarViaFinance = (float) $tx->payments->sum('amount');
                $legacyPaid = (float) ($tx->paid ?? 0);
                $totalTerbayar = round(max($totalTerbayarViaFinance, $legacyPaid), 2);
                if ($totalTerbayar > $total) $totalTerbayar = $total;
                $sisa = round(max(0, $total - $totalTerbayar), 2);
                $isLunas = ($sisa < 0.005 && $total > 0);
                $statusLabel = $isLunas ? 'Lunas' : ($totalTerbayar > 0 ? 'Dibayar Sebagian' : 'Belum Bayar');

                if ($request->filled('status')) {
                    if ($request->status === 'LUNAS' && !$isLunas) continue;
                    if ($request->status === 'BELUM_LUNAS' && $isLunas) continue;
                }

                $createdDate = $tx->created_at ? $tx->created_at->format('d/m/Y') : '-';
                $dueDate = $tx->created_at ? $tx->created_at->copy()->addDays(30) : null;
                $dueDateStr = $dueDate ? $dueDate->format('d/m/Y') : '-';
                
                $dueStatus = 'Aman';
                if (!$isLunas && $dueDate) {
                    if ($dueDate->isPast()) {
                        $days = (int) $dueDate->diffInDays(now());
                        $dueStatus = "Lewat {$days} Hari";
                    } else {
                        $days = (int) now()->diffInDays($dueDate);
                        $dueStatus = "Sisa {$days} Hari";
                    }
                } elseif ($isLunas) {
                    $dueStatus = 'Lunas';
                }

                if ($request->get('due_mode') === 'overdue' && ($isLunas || !$dueDate || !$dueDate->isPast())) {
                    continue;
                }
                if ($request->get('due_mode') === 'due_soon' && ($isLunas || !$dueDate || $dueDate->isPast() || now()->diffInDays($dueDate) > 7)) {
                    continue;
                }

                fputcsv($handle, [
                    $no++,
                    $tx->transaction_code ?: ('TRX-' . $tx->id),
                    $tx->debtors->name ?? ($tx->patient_id ? ('Pasien: ' . ($tx->patients->name ?? 'Umum')) : 'Pelanggan Umum'),
                    $tx->debtors->code ?? '-',
                    $tx->doctors->name ?? '-',
                    $tx->patients->name ?? '-',
                    $tx->pharmacy->name ?? '-',
                    $createdDate,
                    $dueDateStr,
                    $dueStatus,
                    $statusLabel,
                    number_format($total, 2, ',', '.'),
                    number_format($totalTerbayar, 2, ',', '.'),
                    number_format($sisa, 2, ',', '.'),
                ], ';');
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export data Hutang Dagang (Streaming CSV dengan performa tinggi & hemat memori).
     */
    public function exportHutang(Request $request): StreamedResponse
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Export_Hutang_Dagang_{$branchName}_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($targetPharmacyIds, $request) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'No',
                'No Faktur / Penerimaan',
                'Vendor (PBF)',
                'Kode NT',
                'Tanggal Faktur',
                'Jatuh Tempo',
                'Status',
                'Subtotal (Rp)',
                'PPN (Rp)',
                'Total Tagihan (Rp)',
                'Sudah Terbayar (Rp)',
                'Sisa Tagihan (Rp)',
                'Gudang / Cabang',
            ], ';');

            $query = Receiving::with([
                'receiving_details' => function ($query) {
                    $query->where('invoice_payment', 'KREDIT')->with(['creditor', 'payments', 'receiving_items.order_items.medicines']);
                },
                'pharmacy',
            ])
                ->whereIn('pharmacy_id', $targetPharmacyIds)
                ->whereHas('receiving_details', function ($query) {
                    $query->where('invoice_payment', 'KREDIT');
                })
                ->latest('updated_at');

            $no = 1;
            foreach ($query->cursor() as $rec) {
                foreach ($rec->receiving_details as $detail) {
                    if ($detail->invoice_payment !== 'KREDIT') continue;

                    $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');
                    
                    if ($request->filled('search')) {
                        $s = strtolower(trim($request->search));
                        $nomor = strtolower($detail->invoice_number ?: $rec->code);
                        $ref = strtolower($detail->receiving_details_code ?: $rec->code);
                        $vnd = strtolower($vendorName);
                        if (!str_contains($nomor, $s) && !str_contains($ref, $s) && !str_contains($vnd, $s)) {
                            continue;
                        }
                    }

                    if ($request->filled('pbf')) {
                        if (!str_contains(strtolower($vendorName), strtolower($request->pbf))) {
                            continue;
                        }
                    }

                    $subtotal = 0;
                    foreach ($detail->receiving_items as $item) {
                        $qty = (float) ($item->qty_received ?? 0);
                        $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                        $subtotal += (float) ($item->total ?? ($qty * $harga));
                    }

                    $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                    $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
                    $total = round($subtotal + $ppnNominal, 2);
                    $totalTerbayar = round((float) $detail->payments->sum('amount'), 2);
                    $isLunas = ((int) $rec->status === 3) || ($totalTerbayar >= $total && $total > 0) || (($total - $totalTerbayar) < 0.005 && $total > 0);
                    $sisa = $isLunas ? 0 : round(max(0, $total - $totalTerbayar), 2);
                    $statusLabel = $isLunas ? 'Lunas' : ($totalTerbayar > 0 ? 'Dibayar Sebagian' : 'Belum Dibayar');

                    if ($request->filled('status')) {
                        if ($request->status === 'LUNAS' && !$isLunas) continue;
                        if ($request->status === 'BELUM_LUNAS' && $isLunas) continue;
                    }

                    fputcsv($handle, [
                        $no++,
                        $detail->invoice_number ?: $rec->code,
                        $vendorName,
                        $detail->receiving_details_code ?: $rec->code,
                        $detail->invoice_date ? \Carbon\Carbon::parse($detail->invoice_date)->format('d/m/Y') : ($rec->date ?: '-'),
                        $detail->invoice_due ? \Carbon\Carbon::parse($detail->invoice_due)->format('d/m/Y') : '-',
                        $statusLabel,
                        number_format($subtotal, 2, ',', '.'),
                        number_format($ppnNominal, 2, ',', '.'),
                        number_format($total, 2, ',', '.'),
                        number_format($totalTerbayar, 2, ',', '.'),
                        number_format($sisa, 2, ',', '.'),
                        $rec->pharmacy->name ?? 'Gudang Utama',
                    ], ';');
                }
            }

            fclose($handle);
        }, 200, $headers);
    }

    /**
     * Export data Pembelian Cash (Streaming CSV dengan performa tinggi & hemat memori).
     */
    public function exportCash(Request $request): StreamedResponse
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Export_Pembelian_Cash_{$branchName}_" . date('Ymd_His') . ".csv";

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($targetPharmacyIds, $request) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM

            fputcsv($handle, [
                'No',
                'No Faktur / Pembelian',
                'Vendor (PBF)',
                'Kode NT',
                'Tanggal Faktur',
                'Status',
                'Subtotal (Rp)',
                'PPN (Rp)',
                'Total Pembelian (Rp)',
                'Akun Pembayaran',
                'Gudang / Cabang',
            ], ';');

            $query = Receiving::with([
                'receiving_details' => function ($query) {
                    $query->where('invoice_payment', 'TUNAI')->with(['creditor', 'payments.account', 'receiving_items.order_items.medicines']);
                },
                'pharmacy',
            ])
                ->whereIn('pharmacy_id', $targetPharmacyIds)
                ->whereHas('receiving_details', function ($query) {
                    $query->where('invoice_payment', 'TUNAI');
                })
                ->latest('updated_at');

            $no = 1;
            foreach ($query->cursor() as $rec) {
                foreach ($rec->receiving_details as $detail) {
                    if ($detail->invoice_payment !== 'TUNAI') continue;

                    $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');
                    
                    if ($request->filled('search')) {
                        $s = strtolower(trim($request->search));
                        $nomor = strtolower($detail->invoice_number ?: $rec->code);
                        $ref = strtolower($detail->receiving_details_code ?: $rec->code);
                        $vnd = strtolower($vendorName);
                        if (!str_contains($nomor, $s) && !str_contains($ref, $s) && !str_contains($vnd, $s)) {
                            continue;
                        }
                    }

                    if ($request->filled('pbf')) {
                        if (!str_contains(strtolower($vendorName), strtolower($request->pbf))) {
                            continue;
                        }
                    }

                    $subtotal = 0;
                    foreach ($detail->receiving_items as $item) {
                        $qty = (float) ($item->qty_received ?? 0);
                        $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                        $subtotal += (float) ($item->total ?? ($qty * $harga));
                    }

                    $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                    $ppnNominal = ($ppnType === 'EXCLUDE') ? round($subtotal * 0.11, 2) : 0;
                    $total = round($subtotal + $ppnNominal, 2);

                    $latestPayment = $detail->payments->last();
                    $akunName = $latestPayment && $latestPayment->account ? "{$latestPayment->account->name} ({$latestPayment->account->code})" : '-';

                    fputcsv($handle, [
                        $no++,
                        $detail->invoice_number ?: $rec->code,
                        $vendorName,
                        $detail->receiving_details_code ?: $rec->code,
                        $detail->invoice_date ? \Carbon\Carbon::parse($detail->invoice_date)->format('d/m/Y') : ($rec->date ?: '-'),
                        'Lunas (Cash)',
                        number_format($subtotal, 2, ',', '.'),
                        number_format($ppnNominal, 2, ',', '.'),
                        number_format($total, 2, ',', '.'),
                        $akunName,
                        $rec->pharmacy->name ?? 'Gudang Utama',
                    ], ';');
                }
            }

            fclose($handle);
        }, 200, $headers);
    }
}
