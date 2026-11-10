<?php

namespace App\Http\Controllers;

use App\Exports\Finance\BiayaExport;
use App\Exports\Finance\CashExport;
use App\Exports\Finance\CashflowExport;
use App\Exports\Finance\CashflowTradeExport;
use App\Exports\Finance\HutangExport;
use App\Exports\Finance\KasBankExport;
use App\Exports\Finance\KinerjaOmzetExport;
use App\Exports\Finance\MonitoringPenjualanExport;
use App\Exports\Finance\NeracaExport;
use App\Exports\Finance\PiutangExport;
use App\Models\Creditor;
use App\Models\Debtors;
use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinanceCashbond;
use App\Models\FinancePayment;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\Retur;
use App\Services\Finance\CashflowReportService;
use App\Services\Finance\CashflowTradeReportService;
use App\Services\Finance\KeuanganReportService;
use App\Services\Finance\KinerjaOmzetService;
use App\Services\Finance\MonitoringPenjualanService;
use App\Services\Finance\NeracaReportService;
use App\Services\ConsignmentService;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use App\Support\ReceivingInvoiceTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\IOFactory;
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
            if ($tab === 'hutang')
                return redirect()->route('finance.hutang');
            if ($tab === 'cash')
                return redirect()->route('finance.cash');
            if ($tab === 'konsinyasi')
                return redirect()->route('finance.konsinyasi');
            if ($tab === 'piutang')
                return redirect()->route('finance.piutang');
            if ($tab === 'kas-bank' || $tab === 'bank')
                return redirect()->route('finance.kasBank');
        }

        $hutang = $this->getHutangData();
        $cash = $this->getCashData();
        $piutang = $this->getPiutangData();
        $stats = $this->getStats($hutang, $cash, $piutang);
        $recentMutasi = $this->getMutasiData(10);
        $accounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();

        // Filter periode Bulan & Tahun untuk Kartu Ringkasan (default: bulan & tahun berjalan)
        $now = \Carbon\Carbon::now();
        $selectedMonth = (int) $request->query('month', $now->month);
        $selectedYear = (int) $request->query('year', $now->year);
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $now->month;
        }
        if ($selectedYear < 2020 || $selectedYear > 2100) {
            $selectedYear = $now->year;
        }

        $periodDate = \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1);
        $rtStartDate = $periodDate->copy()->startOfMonth()->toDateString();
        $rtEndDate = $periodDate->copy()->endOfMonth()->toDateString();
        $periodLabel = $periodDate->translatedFormat('F Y');

        $realtimeMetrics = $this->buildRealtimeSummaryMetrics(
            $this->getTargetPharmacyIds(),
            $rtStartDate,
            $rtEndDate,
            'monthly',
            $periodLabel,
            $selectedYear,
            $selectedMonth
        );
        $realtimeMetrics['month'] = $selectedMonth;
        $realtimeMetrics['year'] = $selectedYear;

        // 5 Faktur Hutang yang mendekati atau melewati jatuh tempo
        $upcomingHutang = [];
        $overdueHutangCount = 0;
        $overdueHutangTotal = 0.0;
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
                if ($isOverdue) {
                    $overdueHutangCount++;
                    $overdueHutangTotal += (float) $h['sisa'];
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

        $branchContext = $this->getBranchContext();
        $pharmacyId = $branchContext['activePharmacy']['id'] ?? null;
        $returAlertService = new \App\Services\Finance\PurchaseReturnAlertService();
        $returAlertStats = $returAlertService->getQuickSummary($pharmacyId);

        return Inertia::render('Finance/Dashboard', [
            'stats' => array_merge($stats, [
                'returAlert' => $returAlertStats,
                'overdueHutangCount' => $overdueHutangCount,
                'overdueHutangTotal' => $overdueHutangTotal,
            ]),
            'realtime' => $realtimeMetrics,
            'recentMutasi' => $recentMutasi,
            'upcomingHutang' => $upcomingHutang,
            'accounts' => $accounts,
            'branchContext' => $branchContext,
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
        $kasBankAccounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();
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
        $kasBankAccounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();
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
     * Ringkasan omzet kasir per hari dan shift. Data bersumber dari transaksi
     * selesai; metode yang belum dikonfirmasi pemetaan akuntansinya tetap terpisah.
     */
    public function cashflow(Request $request, CashflowReportService $cashflowReport, MonitoringPenjualanService $monitoringPenjualan, KinerjaOmzetService $kinerjaOmzet, CashflowTradeReportService $tradeReport, KeuanganReportService $keuanganReport, NeracaReportService $neracaReport)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);
        $periodStart = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $periodEnd = $periodStart->copy()->endOfMonth();
        $startDate = $periodStart->toDateString();
        $endDate = $periodEnd->toDateString();
        $pharmacyIds = $this->getTargetPharmacyIds();

        $reportDays = $cashflowReport->build($pharmacyIds, $startDate, $endDate);
        $cashflowTransactionCount = (int) array_sum(array_map(
            fn(array $day) => (int) ($day['morning']['count'] ?? 0)
                + (int) ($day['evening']['count'] ?? 0)
                + (int) ($day['otherShift']['count'] ?? 0),
            $reportDays
        ));
        if ($request->boolean('download_xlsx')) {
            $fileName = 'cashflow-omzet_' . $startDate . '_sd_' . $endDate . '.xlsx';
            return Excel::download(new CashflowExport($reportDays), $fileName);
        }
        $kinerjaDays = $kinerjaOmzet->build($pharmacyIds, $startDate, $endDate);
        $neracaData = $neracaReport->build($pharmacyIds, $startDate, $endDate);
        $drawerAccount = $this->branchKasBankAccountsQuery()->where('account_type', 'kas_apotek')->first();
        $openingBalance = $drawerAccount
            ? FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $drawerAccount->id)->first()
            : null;
        $recapCashflow = $this->buildCashflowRecap($startDate, $endDate);

        return Inertia::render('Finance/Cashflow', [
            'days' => $reportDays,
            'monitoringDays' => $monitoringPenjualan->build($pharmacyIds, $startDate, $endDate),
            'kinerjaDays' => $kinerjaDays['days'],
            'kinerjaDebtors' => $kinerjaDays['debtors'],
            'neracaData' => $neracaData,
            'piutangDagang' => $tradeReport->receivables($pharmacyIds, $startDate, $endDate),
            'hutangDagang' => $tradeReport->payables($pharmacyIds, $startDate, $endDate),
            'keuanganDays' => $keuanganReport->build($pharmacyIds, $startDate, $endDate, $drawerAccount?->id, $openingBalance),
            'saldoAwalKas' => (float) ($openingBalance?->amount ?? 0),
            'recapCashflow' => $recapCashflow,
            'setorAccounts' => $this->branchKasBankAccountsQuery()->where('account_type', 'bank')->where('id', '!=', $drawerAccount?->id ?? 0)->orderBy('code')->get(['id', 'name', 'code']),
            'filters' => ['month' => $month, 'year' => $year],
            'stats' => array_merge($this->getStats(), [
                'cashflowTransactionCount' => $cashflowTransactionCount,
            ]),
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Pindahkan uang tunai dari Kas Apotek / Drawer ke rekening bank.
     */
    public function storeSetoran(Request $request)
    {
        $validated = $request->validate([
            'transfer_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $drawer = $this->branchKasBankAccountsQuery()->where('account_type', 'kas_apotek')->firstOrFail();
        $destination = $this->branchKasBankAccountsQuery()->where('account_type', 'bank')->findOrFail($validated['transfer_account_id']);
        if ($destination->id === $drawer->id) {
            return back()->withErrors(['transfer_account_id' => 'Pilih rekening tujuan selain Kas Apotek / Drawer.']);
        }

        DB::transaction(function () use ($validated, $drawer, $destination) {
            $source = FinanceAccount::whereKey($drawer->id)->lockForUpdate()->firstOrFail();
            $target = FinanceAccount::whereKey($destination->id)->lockForUpdate()->firstOrFail();
            FinancePayment::create([
                'pharmacy_id' => getActivePharmacyId() ?: null,
                'account_id' => $source->id,
                'transfer_account_id' => $target->id,
                'payment_type' => 'SETOR',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? 'Setoran tunai kasir ke rekening bank',
                'created_by' => Auth::id(),
            ]);
            $source->decrement('balance', $validated['amount']);
            $target->increment('balance', $validated['amount']);
            $this->clearStatsCache();
        });

        return back()->with('success', 'Setoran kas ke rekening berhasil dicatat.');
    }

    /**
     * Tarik tunai dari rekening bank ke Kas Apotek / Drawer.
     * Tarik tunai = kurangi saldo bank, tambah saldo kas apotek.
     */
    public function storeTarikTunai(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $sourceBank = $this->branchKasBankAccountsQuery()->where('account_type', 'bank')->findOrFail($validated['account_id']);
        $drawer = FinanceAccount::resolveBranchCashAccount((int) getActivePharmacyId(), false)
            ?? $this->branchKasBankAccountsQuery()->where('account_type', 'kas_apotek')->firstOrFail();

        if ($sourceBank->id === $drawer->id) {
            return back()->withErrors(['account_id' => 'Pilih rekening bank sumber dana selain Kas Apotek / Drawer.']);
        }

        DB::transaction(function () use ($validated, $sourceBank, $drawer) {
            $source = FinanceAccount::whereKey($sourceBank->id)->lockForUpdate()->firstOrFail();
            $target = FinanceAccount::whereKey($drawer->id)->lockForUpdate()->firstOrFail();

            FinancePayment::create([
                'pharmacy_id' => getActivePharmacyId() ?: null,
                'account_id' => $source->id,
                'transfer_account_id' => $target->id,
                'payment_type' => 'TARIK TUNAI',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? 'Tarik tunai dari rekening bank ke kas apotek',
                'created_by' => Auth::id(),
            ]);

            $source->decrement('balance', $validated['amount']);
            $target->increment('balance', $validated['amount']);
            $this->clearStatsCache();
        });

        return back()->with('success', 'Penarikan tunai dari rekening bank ke kas apotek berhasil dicatat.');
    }

    public function exportCashflow(Request $request, CashflowReportService $cashflowReport, MonitoringPenjualanService $monitoringPenjualan, KinerjaOmzetService $kinerjaOmzet, CashflowTradeReportService $tradeReport, KeuanganReportService $keuanganReport, NeracaReportService $neracaReport)
    {
        $request->merge(['download_xlsx' => true]);
        return $this->cashflow($request, $cashflowReport, $monitoringPenjualan, $kinerjaOmzet, $tradeReport, $keuanganReport, $neracaReport);
    }

    public function exportCashflowTrade(Request $request, CashflowTradeReportService $tradeReport)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
            'type' => 'required|in:piutang,hutang',
        ]);
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $isReceivable = $validated['type'] === 'piutang';
        $report = $isReceivable
            ? $tradeReport->receivables($this->getTargetPharmacyIds(), $start->toDateString(), $end->toDateString())
            : $tradeReport->payables($this->getTargetPharmacyIds(), $start->toDateString(), $end->toDateString());
        $label = $isReceivable ? 'Piutang Dagang' : 'Hutang Dagang';
        $period = $start->locale('id')->translatedFormat('F Y');

        return Excel::download(
            new CashflowTradeExport($report, strtoupper($label), $label, "Periode: {$period}"),
            sprintf('cashflow-%s_%04d-%02d.xlsx', $validated['type'], $year, $month)
        );
    }

    public function exportNeraca(Request $request, NeracaReportService $neracaReport)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $pharmacyIds = $this->getTargetPharmacyIds();
        $data = $neracaReport->build($pharmacyIds, $start->toDateString(), $end->toDateString());
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);

        return Excel::download(
            new NeracaExport($data, $activePharmacy),
            sprintf('neraca_%04d-%02d.xlsx', $year, $month)
        );
    }

    public function exportMonitoringPenjualan(Request $request, MonitoringPenjualanService $monitoringPenjualan)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);

        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $days = $monitoringPenjualan->build($this->getTargetPharmacyIds(), $start->toDateString(), $end->toDateString());

        return Excel::download(
            new MonitoringPenjualanExport($days, $month, $year),
            sprintf('monitoring-penjualan_%04d-%02d.xlsx', $year, $month)
        );
    }

    public function exportKinerjaOmzet(Request $request, KinerjaOmzetService $kinerjaOmzet)
    {
        $validated = $request->validate([
            'month' => 'required|integer|min:1|max:12',
            'year' => 'required|integer|min:2000|max:2100',
        ]);
        $month = (int) $validated['month'];
        $year = (int) $validated['year'];
        $start = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();
        $data = $kinerjaOmzet->build($this->getTargetPharmacyIds(), $start->toDateString(), $end->toDateString());
        return Excel::download(new KinerjaOmzetExport($data, $month, $year), sprintf('kinerja-omzet_%04d-%02d.xlsx', $year, $month));
    }

    /**
     * Halaman konsinyasi: kewajiban hanya terbentuk atas barang yang terjual.
     */
    public function konsinyasi(Request $request, ConsignmentService $consignmentService)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $consignments = $consignmentService->getData($this->getTargetPharmacyIds());
        $kasBankAccounts = $this
            ->branchKasBankAccountsQuery()
            ->orderBy('code')
            ->get();

        return Inertia::render('Finance/Konsinyasi', [
            'consignments' => $consignments,
            'kasBankAccounts' => $kasBankAccounts,
            'summary' => [
                'invoice_count' => count($consignments),
                'qty_received' => array_sum(array_column($consignments, 'qty_received')),
                'qty_sold' => array_sum(array_column($consignments, 'qty_sold')),
                'payable' => array_sum(array_column($consignments, 'payable')),
                'paid' => array_sum(array_column($consignments, 'paid')),
                'due' => array_sum(array_column($consignments, 'due')),
                'ready_count' => count(array_filter($consignments, fn($item) => $item['due'] > 0)),
            ],
            'stats' => array_merge($this->getStats(), [
                'countKonsinyasiSiapBayar' => count(array_filter($consignments, fn($item) => $item['due'] > 0)),
            ]),
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
        $creditReturns = $this->getCreditReturnsData();
        $debtors = Debtors::orderBy('name')->get(['id', 'code', 'name', 'phone', 'city']);
        $kasBankAccounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();
        $stats = $this->getStats(null, null, $piutangPenjualan);

        return Inertia::render('Finance/Piutang', [
            'piutangPenjualan' => $piutangPenjualan,
            'creditReturns' => $creditReturns,
            'debtors' => $debtors,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Pengelolaan Rekening Kas & Bank serta Buku Mutasi Kas.
     */
    public function kasBank(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        // Filter periode Bulan & Tahun untuk Mutasi Kas & Bank (default: bulan & tahun berjalan, reset otomatis tiap bulan)
        $now = \Carbon\Carbon::now();
        $selectedMonth = (int) $request->query('month', $now->month);
        $selectedYear = (int) $request->query('year', $now->year);
        if ($selectedMonth < 1 || $selectedMonth > 12) {
            $selectedMonth = $now->month;
        }
        if ($selectedYear < 2020 || $selectedYear > 2100) {
            $selectedYear = $now->year;
        }

        $periodDate = \Carbon\Carbon::createFromDate($selectedYear, $selectedMonth, 1);
        $monthStart = $periodDate->copy()->startOfMonth()->toDateString();
        $monthEnd = $periodDate->copy()->endOfMonth()->toDateString();
        $previousMonthEnd = $periodDate->copy()->startOfMonth()->subDay()->toDateString();
        $monthLabel = $periodDate->translatedFormat('F Y');

        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $isCustomRange = !empty($startDate) && !empty($endDate);

        $filterStart = $isCustomRange ? $startDate : $monthStart;
        $filterEnd = $isCustomRange ? $endDate : $monthEnd;
        if ($isCustomRange) {
            $monthLabel = \Carbon\Carbon::parse($filterStart)->format('d/m/Y') . ' - ' . \Carbon\Carbon::parse($filterEnd)->format('d/m/Y');
        }

        // Hanya akun berkategori Kas & Bank untuk halaman Kas & Bank
        $accounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();
        $categories = ['Kas & Bank'];
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        // Ringkasan statistik bulanan via agregasi SQL (cepat, tanpa memuat ribuan objek ke RAM)
        $kasBankSummary = $this->getMonthlyKasBankSummary($accounts, $filterStart, $filterEnd, $targetPharmacyIds);

        // Ambil mutasi transaksi dengan server-side pagination
        $mutasiKasBank = $this->getPaginatedMutasiData($request, $filterStart, $filterEnd, $targetPharmacyIds);
        $stats = $this->getStats(null, null, null);
        $openingBalances = FinancePayment::with('creator')
            ->where('payment_type', 'SALDO AWAL')
            ->whereIn('account_id', $accounts->pluck('id'))
            ->get()
            ->keyBy('account_id')
            ->map(fn($payment) => [
                'id' => $payment->id,
                'account_id' => $payment->account_id,
                'amount' => (float) $payment->amount,
                'payment_date' => $payment->payment_date?->format('Y-m-d'),
                'notes' => $payment->notes,
                'updated_by' => $payment->creator->name ?? 'Petugas',
            ]);

        $monthlyOpeningBalances = [];
        foreach ($accounts as $account) {
            $opening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $account->id)->first();
            if (!$opening) {
                $monthlyOpeningBalances[$account->id] = 0;
            } elseif ($opening->payment_date?->format('Y-m-d') >= $filterStart && $opening->payment_date?->format('Y-m-d') <= $filterEnd) {
                $monthlyOpeningBalances[$account->id] = (float) $opening->amount;
            } else {
                $monthlyOpeningBalances[$account->id] = $this->calculateBalanceFromOpening($account->id, $opening, $previousMonthEnd);
            }
        }

        $adjustmentLogs = $this->getBalanceAdjustmentLogs($accounts->pluck('id')->all());

        return Inertia::render('Finance/KasBank', [
            'accounts' => $accounts,
            'categories' => $categories,
            'mutasiKasBank' => $mutasiKasBank,
            'kasBankSummary' => $kasBankSummary,
            'filters' => [
                'account_id' => $request->query('account_id', 'all'),
                'direction' => $request->query('direction', 'all'),
                'search' => $request->query('search', ''),
                'per_page' => (int) $request->query('per_page', 25),
                'start_date' => $startDate ?: '',
                'end_date' => $endDate ?: '',
            ],
            'stats' => $stats,
            'openingBalances' => $openingBalances,
            'monthlyOpeningBalances' => $monthlyOpeningBalances,
            'adjustmentLogs' => $adjustmentLogs,
            'selectedMonth' => $selectedMonth,
            'selectedYear' => $selectedYear,
            'monthLabel' => $monthLabel,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Catat penerimaan di luar pelunasan piutang (termasuk Diskon Off / Diskon Lain-lain).
     */
    public function pendapatanLainLain(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $validated = $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);
        $month = (int) ($validated['month'] ?? now()->month);
        $year = (int) ($validated['year'] ?? now()->year);

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $pendapatanList = $this->getPendapatanData();
        $incomeAccounts = FinanceAccount::whereIn('category', ['Pendapatan', 'Diskon Lain-lain'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category', 'balance']);
        $kasBankAccounts = $this
            ->branchKasBankAccountsQuery()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'balance', 'account_number']);
        $stats = $this->getStats(null, null, null);
        $summary = $this->getPendapatanSummary($targetPharmacyIds);

        return Inertia::render('Finance/PendapatanLainLain', [
            'pendapatanList' => $pendapatanList,
            'pendapatan' => $pendapatanList,
            'incomeAccounts' => $incomeAccounts,
            'kasBankAccounts' => $kasBankAccounts,
            'accounts' => $kasBankAccounts,
            'filters' => ['month' => $month, 'year' => $year],
            'stats' => $stats,
            'summary' => $summary,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    public function storeSaldoAwal(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required|numeric|min:0',
            'notes' => 'nullable|string|max:500',
        ]);

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        DB::transaction(function () use ($validated, $account) {
            $lockedAccount = FinanceAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $opening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $lockedAccount->id)->lockForUpdate()->first();
            $payload = [
                'account_id' => $lockedAccount->id,
                'payment_type' => 'SALDO AWAL',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
                'pharmacy_id' => null,
            ];
            if ($opening)
                $opening->update($payload);
            else
                $opening = FinancePayment::create($payload);

            $lockedAccount->update([
                'balance' => $this->calculateBalanceFromOpening($lockedAccount->id, $opening, now()->toDateString()),
            ]);
            $this->clearStatsCache();
        });

        return back()->with('success', 'Saldo awal akun berhasil disimpan. Nilai ini tidak dihitung sebagai pendapatan.');
    }

    /**
     * Sesuaikan saldo riil (Opname Kas & Bank) kapan saja dan catat riwayat log lengkap.
     */
    public function adjustBalance(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required_without:adjustments|nullable|exists:finance_accounts,id',
            'real_balance' => 'required_without:adjustments|nullable',
            'adjustment_date' => 'nullable|date|before_or_equal:today',
            'notes' => 'nullable|string|max:500',
            'adjustments' => 'nullable|array|min:1',
            'adjustments.*.account_id' => 'required_with:adjustments|exists:finance_accounts,id',
            'adjustments.*.real_balance' => 'required_with:adjustments',
            'adjustments.*.notes' => 'nullable|string|max:500',
        ], [
            'adjustment_date.before_or_equal' => 'Tanggal penyesuaian tidak boleh melewati hari ini.',
        ]);

        $pharmacyId = (int) getActivePharmacyId();
        $adjustmentDate = !empty($validated['adjustment_date'])
            ? $validated['adjustment_date']
            : now()->toDateString();

        $items = [];
        if (!empty($validated['adjustments'])) {
            $items = $validated['adjustments'];
        } elseif (!empty($validated['account_id'])) {
            $items = [[
                'account_id' => $validated['account_id'],
                'real_balance' => $validated['real_balance'],
                'notes' => $validated['notes'] ?? null,
            ]];
        }

        if (empty($items)) {
            return back()->withErrors(['account_id' => 'Pilih rekening dan masukkan saldo riil yang akan disesuaikan.']);
        }

        $processedCount = 0;
        $totalDifference = 0.0;

        DB::transaction(function () use ($items, $pharmacyId, $adjustmentDate, &$processedCount, &$totalDifference) {
            foreach ($items as $item) {
                $accId = (int) $item['account_id'];
                $account = FinanceAccount::whereKey($accId)
                    ->where('category', 'Kas & Bank')
                    ->lockForUpdate()
                    ->first();

                if (!$account) {
                    continue;
                }

                // Normalisasi nominal jika berformat rupiah string atau angka
                $rawReal = (string) ($item['real_balance'] ?? '');
                $cleanReal = str_replace(['Rp', ' ', '.'], '', $rawReal);
                $cleanReal = str_replace(',', '.', $cleanReal);
                $realBalance = round(max(0, (float) $cleanReal), 2);

                $systemBalance = (float) $account->balance;
                $diff = round($realBalance - $systemBalance, 2);
                $itemNotes = trim((string) ($item['notes'] ?? ''));

                // 1. Update Saldo Akun ke Saldo Riil saat ini
                $account->update([
                    'balance' => $realBalance,
                ]);

                // 2. Catat Mutasi Kas & Bank jika ada selisih
                $payment = null;
                if (abs($diff) >= 0.01) {
                    $directionLabel = $diff > 0 ? '+' : '-';
                    $fmtSys = number_format($systemBalance, 0, ',', '.');
                    $fmtReal = number_format($realBalance, 0, ',', '.');
                    $fmtDiff = number_format(abs($diff), 0, ',', '.');

                    $paymentNotes = 'Opname Kas & Bank: Rp ' . $fmtSys . ' -> Rp ' . $fmtReal . ' (' . $directionLabel . 'Rp ' . $fmtDiff . ')';
                    if ($itemNotes !== '') {
                        $paymentNotes .= ' | Catatan: ' . $itemNotes;
                    }

                    $payment = FinancePayment::create([
                        'pharmacy_id' => $account->pharmacy_id ?: ($pharmacyId > 0 ? $pharmacyId : null),
                        'account_id' => $account->id,
                        'payment_type' => 'PENYESUAIAN SALDO',
                        'payment_date' => $adjustmentDate,
                        'amount' => abs($diff),
                        'reference_number' => $diff >= 0 ? 'OPNAME-IN' : 'OPNAME-OUT',
                        'notes' => $paymentNotes,
                        'created_by' => Auth::id(),
                    ]);
                }

                // 3. Catat di Audit Log Lengkap (FinanceBalanceAdjustment)
                FinanceBalanceAdjustment::create([
                    'pharmacy_id' => $account->pharmacy_id ?: ($pharmacyId > 0 ? $pharmacyId : null),
                    'account_id' => $account->id,
                    'system_balance' => $systemBalance,
                    'real_balance' => $realBalance,
                    'difference' => $diff,
                    'adjustment_date' => $adjustmentDate,
                    'adjusted_at' => now(),
                    'notes' => $itemNotes !== '' ? $itemNotes : null,
                    'finance_payment_id' => $payment?->id,
                    'created_by' => Auth::id(),
                ]);

                $processedCount++;
                $totalDifference += $diff;
            }

            $this->clearStatsCache();
        });

        $msg = $processedCount === 1
            ? 'Saldo riil berhasil disesuaikan dan tercatat di riwayat log opname.'
            : "Berhasil menyesuaikan {$processedCount} rekening kas & bank ke saldo riil. Riwayat lengkap tercatat di log opname.";

        return back()->with('success', $msg);
    }

    /**
     * Ambil riwayat log penyesuaian saldo riil (opname kas & bank).
     */
    protected function getBalanceAdjustmentLogs(array $accountIds = [], int $limit = 200): array
    {
        $pharmacyId = (int) getActivePharmacyId();
        $query = FinanceBalanceAdjustment::with(['account', 'creator.roles'])
            ->where(function ($q) use ($pharmacyId, $accountIds) {
                if ($pharmacyId > 0) {
                    $q->where('pharmacy_id', $pharmacyId);
                }
                if (!empty($accountIds)) {
                    $q->orWhereIn('account_id', $accountIds);
                }
            })
            ->latest('adjusted_at')
            ->latest('id')
            ->take($limit);

        return $query->get()->map(function ($item) {
            $diff = (float) $item->difference;
            return [
                'id' => $item->id,
                'account_id' => $item->account_id,
                'account_name' => $item->account->name ?? 'Akun Kas/Bank',
                'account_code' => $item->account->code ?? '-',
                'account_type' => $item->account->account_type ?? 'bank',
                'system_balance' => (float) $item->system_balance,
                'real_balance' => (float) $item->real_balance,
                'difference' => $diff,
                'direction' => $diff > 0 ? 'IN' : ($diff < 0 ? 'OUT' : 'EQUAL'),
                'adjustment_date' => $item->adjustment_date ? \Carbon\Carbon::parse($item->adjustment_date)->format('d/m/Y') : '-',
                'raw_date' => $item->adjustment_date ? \Carbon\Carbon::parse($item->adjustment_date)->format('Y-m-d') : null,
                'adjusted_at' => $item->adjusted_at ? \Carbon\Carbon::parse($item->adjusted_at)->format('d/m/Y H:i:s') : ($item->created_at ? $item->created_at->format('d/m/Y H:i:s') : '-'),
                'notes' => $item->notes ?: '',
                'user_name' => $item->creator->name ?? ($item->creator->username ?? 'Petugas'),
                'user_role' => $item->creator?->roles?->first()?->name ?? 'Finance',
            ];
        })->values()->all();
    }

    /**
     * Catat ulang penjualan tunai lama yang belum memiliki mutasi Kas & Bank.
     */
    public function backfillPenjualanTunai(Request $request)
    {
        $validated = $request->validate([
            'from_date' => 'required|date|before_or_equal:to_date',
            'to_date' => 'required|date|before_or_equal:today',
        ], [
            'from_date.before_or_equal' => 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.',
            'to_date.before_or_equal' => 'Tanggal akhir tidak boleh melewati hari ini.',
        ]);

        $pharmacyId = (int) getActivePharmacyId();
        $account = FinanceAccount::resolveBranchCashAccount($pharmacyId, false)
            ?? $this->branchKasBankAccountsQuery()->where('account_type', 'kas_apotek')->firstOrFail();
        $createdCount = 0;
        $createdTotal = 0.0;
        $createdGrossTotal = 0.0;

        DB::transaction(function () use ($validated, $pharmacyId, $account, &$createdCount, &$createdTotal, &$createdGrossTotal) {
            $lockedAccount = FinanceAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
                ->where('account_id', $lockedAccount->id)
                ->first();

            MedicineTransactions::query()
                ->where('pharmacy_id', $pharmacyId)
                ->where('status', 1)
                ->where('payment_method', 'CASH')
                ->whereNotIn('transaction_type', ['KREDIT'])
                ->whereRaw("UPPER(COALESCE(transaction_type, '')) NOT LIKE 'RETUR%'")
                ->whereBetween(DB::raw('DATE(created_at)'), [$validated['from_date'], $validated['to_date']])
                ->whereDoesntHave('payments', fn($query) => $query->where('payment_type', 'PENJUALAN TUNAI'))
                ->orderBy('id')
                ->chunkById(500, function ($transactions) use ($lockedAccount, $pharmacyId, $opening, &$createdCount, &$createdTotal, &$createdGrossTotal) {
                    foreach ($transactions as $transaction) {
                        $amount = round(max(0, (float) $transaction->subtotal - (float) ($transaction->discount ?? 0)), 2);
                        if ($amount <= 0)
                            continue;

                        // Recheck after locking the account so repeated imports stay idempotent.
                        $alreadyRecorded = FinancePayment::where('medicine_transaction_id', $transaction->id)
                            ->where('payment_type', 'PENJUALAN TUNAI')
                            ->exists();
                        if ($alreadyRecorded)
                            continue;

                        $paymentDate = $transaction->created_at?->toDateString() ?? now()->toDateString();
                        FinancePayment::create([
                            'pharmacy_id' => $pharmacyId,
                            'medicine_transaction_id' => $transaction->id,
                            'account_id' => $lockedAccount->id,
                            'payment_type' => 'PENJUALAN TUNAI',
                            'payment_date' => $paymentDate,
                            'amount' => $amount,
                            'reference_number' => $transaction->transaction_code,
                            'notes' => 'Backfill penjualan tunai lama',
                            'created_by' => Auth::id(),
                        ]);

                        $createdGrossTotal += $amount;
                        // Saldo awal adalah saldo aktual pada tanggal mulai pencatatan.
                        // Riwayat yang lebih lama hanya melengkapi mutasi dan tidak mengubah saldo aktual itu.
                        if (!$opening || $paymentDate >= $opening->payment_date?->format('Y-m-d')) {
                            $createdTotal += $amount;
                        }
                        $createdCount++;
                    }
                });

            if ($createdTotal > 0) {
                if ($opening) {
                    $lockedAccount->update([
                        'balance' => $this->calculateBalanceFromOpening($lockedAccount->id, $opening, now()->toDateString()),
                    ]);
                } else {
                    $lockedAccount->increment('balance', $createdTotal);
                }
            }
            $this->clearStatsCache();
        });

        return back()->with('success', $createdCount
            ? "Berhasil mencatat {$createdCount} penjualan tunai lama senilai Rp " . number_format($createdGrossTotal, 0, ',', '.') . '.'
            : 'Tidak ada penjualan tunai lama yang perlu ditambahkan pada rentang tanggal tersebut.');
    }

    /**
     * Catat ulang transaksi non-tunai (QRIS, Debit, Transfer, EDC) lama ke rekening bank masing-masing di cabang ini.
     */
    public function backfillPenjualanNonTunai(Request $request)
    {
        $validated = $request->validate([
            'from_date' => 'required|date|before_or_equal:to_date',
            'to_date' => 'required|date|before_or_equal:today',
        ], [
            'from_date.before_or_equal' => 'Tanggal mulai harus sebelum atau sama dengan tanggal akhir.',
            'to_date.before_or_equal' => 'Tanggal akhir tidak boleh melewati hari ini.',
        ]);

        $pharmacyId = (int) getActivePharmacyId();
        FinanceAccount::ensureBranchDefaultAccounts($pharmacyId);

        $createdCount = 0;
        $skippedCount = 0;
        $createdGrossTotal = 0.0;
        $balanceAdditions = [];
        $touchedAccountIds = [];

        DB::transaction(function () use ($validated, $pharmacyId, &$createdCount, &$skippedCount, &$createdGrossTotal, &$balanceAdditions, &$touchedAccountIds) {
            MedicineTransactions::query()
                ->where('pharmacy_id', $pharmacyId)
                ->where('status', 1)
                ->whereRaw("UPPER(COALESCE(transaction_type, '')) NOT IN ('KREDIT', 'RESEP KREDIT')")
                ->whereRaw("UPPER(COALESCE(transaction_type, '')) NOT LIKE 'RETUR%'")
                ->whereBetween(DB::raw('DATE(created_at)'), [$validated['from_date'], $validated['to_date']])
                ->where(function ($q) {
                    $q->whereRaw('UPPER(TRIM(COALESCE(payment_method, ""))) IN (?, ?, ?, ?)', ['QRIS', 'DEBIT', 'TRANSFER', 'EDC'])
                        ->orWhere(function ($ship) {
                            $ship->where('has_doctor_shipping_fee', 1)
                                ->where('doctor_shipping_fee', '>', 0)
                                ->whereRaw('UPPER(TRIM(COALESCE(doctor_shipping_payment_method, ""))) IN (?, ?, ?, ?)', ['QRIS', 'DEBIT', 'TRANSFER', 'EDC']);
                        });
                })
                ->orderBy('id')
                ->chunkById(500, function ($transactions) use ($pharmacyId, &$createdCount, &$skippedCount, &$createdGrossTotal, &$balanceAdditions, &$touchedAccountIds) {
                    foreach ($transactions as $transaction) {
                        $txPharmacyId = (int) ($transaction->pharmacy_id ?: $pharmacyId);
                        $paymentDate = $transaction->created_at?->toDateString() ?? now()->toDateString();
                        $methodUpper = strtoupper(trim((string) ($transaction->payment_method ?? '')));

                        // 1. Penjualan utama non-tunai
                        if (in_array($methodUpper, ['QRIS', 'DEBIT', 'TRANSFER', 'EDC'], true)) {
                            $amount = round(max(0, (float) $transaction->subtotal - (float) ($transaction->discount ?? 0)), 2);
                            if ($amount > 0) {
                                $alreadyRecorded = FinancePayment::where('medicine_transaction_id', $transaction->id)
                                    ->where('payment_type', 'PENJUALAN NON TUNAI')
                                    ->where(function ($q) {
                                        $q->whereNull('notes')
                                            ->orWhere('notes', 'not like', 'Penerimaan talangan ongkir%');
                                    })
                                    ->exists();

                                if (!$alreadyRecorded) {
                                    $account = FinanceAccount::resolveBranchBankAccount($txPharmacyId, $transaction->transfer_bank_name, true, true);
                                    if (!$account) {
                                        $skippedCount++;
                                    } else {
                                        $bankShort = FinanceAccount::extractShortBankName($transaction->transfer_bank_name ?: $account->name);
                                        FinancePayment::create([
                                            'pharmacy_id' => $txPharmacyId,
                                            'medicine_transaction_id' => $transaction->id,
                                            'account_id' => $account->id,
                                            'payment_type' => 'PENJUALAN NON TUNAI',
                                            'payment_date' => $paymentDate,
                                            'amount' => $amount,
                                            'reference_number' => $transaction->transaction_code,
                                            'notes' => "Penerimaan penjualan {$methodUpper} melalui {$bankShort}",
                                            'created_by' => Auth::id(),
                                        ]);

                                        $createdGrossTotal += $amount;
                                        $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
                                            ->where('account_id', $account->id)
                                            ->first();
                                        if (!$opening || $paymentDate >= $opening->payment_date?->format('Y-m-d')) {
                                            $balanceAdditions[$account->id] = ($balanceAdditions[$account->id] ?? 0) + $amount;
                                        }
                                        $touchedAccountIds[$account->id] = $account->id;
                                        $createdCount++;
                                    }
                                }
                            }
                        }

                        // 2. Talangan jasa dokter & ongkir non-tunai (jika ada)
                        $shipFee = round((float) ($transaction->doctor_shipping_fee ?? 0), 2);
                        $shipMethod = strtoupper(trim((string) ($transaction->doctor_shipping_payment_method ?? '')));
                        if ($transaction->has_doctor_shipping_fee && $shipFee > 0 && in_array($shipMethod, ['QRIS', 'DEBIT', 'TRANSFER', 'EDC'], true)) {
                            $alreadyRecordedShip = FinancePayment::where('medicine_transaction_id', $transaction->id)
                                ->where('payment_type', 'PENJUALAN NON TUNAI')
                                ->where('notes', 'like', 'Penerimaan talangan ongkir%')
                                ->exists();

                            if (!$alreadyRecordedShip) {
                                $shipBankName = $transaction->doctor_shipping_bank_name ?: $transaction->transfer_bank_name;
                                $shipAccount = FinanceAccount::resolveBranchBankAccount($txPharmacyId, $shipBankName, true, true);
                                if ($shipAccount) {
                                    $shipBankShort = FinanceAccount::extractShortBankName($shipBankName ?: $shipAccount->name);
                                    FinancePayment::create([
                                        'pharmacy_id' => $txPharmacyId,
                                        'medicine_transaction_id' => $transaction->id,
                                        'account_id' => $shipAccount->id,
                                        'payment_type' => 'PENJUALAN NON TUNAI',
                                        'payment_date' => $paymentDate,
                                        'amount' => $shipFee,
                                        'reference_number' => $transaction->transaction_code,
                                        'notes' => "Penerimaan talangan ongkir & jasa dokter ({$shipMethod} melalui {$shipBankShort})",
                                        'created_by' => Auth::id(),
                                    ]);

                                    $createdGrossTotal += $shipFee;
                                    $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
                                        ->where('account_id', $shipAccount->id)
                                        ->first();
                                    if (!$opening || $paymentDate >= $opening->payment_date?->format('Y-m-d')) {
                                        $balanceAdditions[$shipAccount->id] = ($balanceAdditions[$shipAccount->id] ?? 0) + $shipFee;
                                    }
                                    $touchedAccountIds[$shipAccount->id] = $shipAccount->id;
                                    $createdCount++;
                                }
                            }
                        }
                    }
                });

            foreach ($touchedAccountIds as $accountId) {
                $account = FinanceAccount::whereKey($accountId)->lockForUpdate()->first();
                if (!$account) {
                    continue;
                }
                $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
                    ->where('account_id', $account->id)
                    ->first();
                if ($opening) {
                    $account->update([
                        'balance' => $this->calculateBalanceFromOpening($account->id, $opening, now()->toDateString()),
                    ]);
                } elseif (($balanceAdditions[$account->id] ?? 0) > 0) {
                    $account->increment('balance', $balanceAdditions[$account->id]);
                }
            }

            $this->clearStatsCache();
        });

        $message = $createdCount
            ? "Berhasil mencatat {$createdCount} transaksi non-tunai ke rekening bank cabang senilai Rp " . number_format($createdGrossTotal, 0, ',', '.') . '.'
            : 'Tidak ada transaksi non-tunai lama yang perlu ditambahkan pada rentang tanggal tersebut.';
        if ($skippedCount > 0) {
            $message .= " {$skippedCount} transaksi dilewati karena belum memiliki nama bank tujuan.";
        }
        return back()->with('success', $message);
    }

    public function storePendapatanLainLain(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'nullable|exists:finance_accounts,id',
            'income_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $incomeAccount = FinanceAccount::whereIn('category', ['Pendapatan', 'Diskon Lain-lain'])
            ->where('is_active', true)
            ->findOrFail($validated['income_account_id']);
        $isDiskon = $incomeAccount->category === 'Diskon Lain-lain';

        if (!$isDiskon && empty($validated['account_id'])) {
            return back()->withErrors(['account_id' => 'Pilih akun tujuan Kas & Bank.']);
        }

        $account = !$isDiskon
            ? $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id'])
            : ($this->branchKasBankAccountsQuery()->find($validated['account_id'] ?? null) ?? $this->branchKasBankAccountsQuery()->orderBy('code')->first());

        if (!$account) {
            return back()->withErrors(['account_id' => 'Akun Kas & Bank tidak ditemukan untuk cabang ini.']);
        }

        $activePharmacyId = getActivePharmacyId();

        DB::transaction(function () use ($validated, $account, $incomeAccount, $isDiskon, $activePharmacyId) {
            FinancePayment::create([
                'pharmacy_id' => $activePharmacyId > 0 ? $activePharmacyId : null,
                'account_id' => $account->id,
                'income_account_id' => $incomeAccount->id,
                'expense_account_id' => $isDiskon ? $incomeAccount->id : null,
                'payment_type' => $isDiskon ? 'DISKON' : 'PENDAPATAN',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'recipient' => $validated['recipient'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            if (!$isDiskon) {
                $opening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $account->id)->first();
                if (!$opening || $validated['payment_date'] >= $opening->payment_date->format('Y-m-d')) {
                    FinanceAccount::whereKey($account->id)->increment('balance', $validated['amount']);
                }
            }
            $this->clearStatsCache();
        });

        return back()->with('success', $isDiskon ? 'Diskon off berhasil dicatat.' : 'Pendapatan lain-lain berhasil dicatat.');
    }

    /**
     * Perbarui transaksi pendapatan lain-lain (termasuk diskon off) yang sudah ada.
     */
    public function updatePendapatanLainLain(Request $request, $id)
    {
        $payment = FinancePayment::whereIn('payment_type', ['PENDAPATAN', 'DISKON'])->findOrFail($id);

        $validated = $request->validate([
            'account_id' => 'nullable|exists:finance_accounts,id',
            'income_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $incomeAccount = FinanceAccount::whereIn('category', ['Pendapatan', 'Diskon Lain-lain'])
            ->where('is_active', true)
            ->findOrFail($validated['income_account_id']);
        $isDiskon = $incomeAccount->category === 'Diskon Lain-lain';

        if (!$isDiskon && empty($validated['account_id'])) {
            return back()->withErrors(['account_id' => 'Pilih akun tujuan Kas & Bank.']);
        }

        $newAccount = !$isDiskon
            ? $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id'])
            : ($this->branchKasBankAccountsQuery()->find($validated['account_id'] ?? $payment->account_id) ?? $this->branchKasBankAccountsQuery()->orderBy('code')->first());

        if (!$newAccount || $newAccount->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun tujuan harus berkategori Kas & Bank.']);
        }

        DB::transaction(function () use ($payment, $validated, $newAccount, $incomeAccount, $isDiskon) {
            $wasPendapatan = $payment->payment_type === 'PENDAPATAN';

            if ($wasPendapatan && $payment->account_id) {
                $oldOpening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $payment->account_id)->first();
                $oldDate = $payment->payment_date instanceof \Carbon\Carbon
                    ? $payment->payment_date->format('Y-m-d')
                    : (string) $payment->payment_date;
                if (!$oldOpening || $oldDate >= $oldOpening->payment_date->format('Y-m-d')) {
                    FinanceAccount::whereKey($payment->account_id)->decrement('balance', $payment->amount);
                }
            }

            if (!$isDiskon) {
                $newOpening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $newAccount->id)->first();
                if (!$newOpening || $validated['payment_date'] >= $newOpening->payment_date->format('Y-m-d')) {
                    FinanceAccount::whereKey($newAccount->id)->increment('balance', $validated['amount']);
                }
            }

            $payment->update([
                'account_id' => $newAccount->id,
                'income_account_id' => $incomeAccount->id,
                'expense_account_id' => $isDiskon ? $incomeAccount->id : null,
                'payment_type' => $isDiskon ? 'DISKON' : 'PENDAPATAN',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'recipient' => $validated['recipient'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->clearStatsCache();
        });

        return back()->with('success', $isDiskon ? 'Diskon off berhasil diperbarui.' : 'Pendapatan lain-lain berhasil diperbarui.');
    }

    /**
     * Hapus transaksi pendapatan lain-lain / diskon off (dan sesuaikan saldo kas/bank bila PENDAPATAN).
     */
    public function destroyPendapatanLainLain($id)
    {
        $payment = FinancePayment::whereIn('payment_type', ['PENDAPATAN', 'DISKON'])->findOrFail($id);

        DB::transaction(function () use ($payment) {
            if ($payment->payment_type === 'PENDAPATAN' && $payment->account_id) {
                $opening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $payment->account_id)->first();
                $oldDate = $payment->payment_date instanceof \Carbon\Carbon
                    ? $payment->payment_date->format('Y-m-d')
                    : (string) $payment->payment_date;
                if (!$opening || $oldDate >= $opening->payment_date->format('Y-m-d')) {
                    FinanceAccount::whereKey($payment->account_id)->decrement('balance', $payment->amount);
                }
            }

            $payment->delete();

            $this->clearStatsCache();
        });

        return back()->with('success', 'Transaksi berhasil dihapus.');
    }

    private function calculateBalanceFromOpening(int $accountId, FinancePayment $opening, string $asOfDate): float
    {
        $openingDate = $opening->payment_date?->format('Y-m-d');
        if (!$openingDate || $openingDate > $asOfDate)
            return 0;

        $payments = FinancePayment::where('account_id', $accountId)
            ->where('id', '!=', $opening->id)
            ->whereDate('payment_date', '>=', $openingDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->get(['payment_type', 'amount', 'transfer_account_id']);

        $balance = (float) $opening->amount;
        foreach ($payments as $payment) {
            if (in_array($payment->payment_type, ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'], true))
                $balance += (float) $payment->amount;
            elseif (in_array($payment->payment_type, ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'SETOR', 'TARIK TUNAI', 'RETUR PENJUALAN'], true))
                $balance -= (float) $payment->amount;
            elseif ($payment->payment_type === 'PENYESUAIAN SALDO') {
                if ($payment->reference_number === 'OPNAME-OUT') {
                    $balance -= (float) $payment->amount;
                } else {
                    $balance += (float) $payment->amount;
                }
            }
        }
        $balance += (float) FinancePayment::whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->where('transfer_account_id', $accountId)
            ->whereDate('payment_date', '>=', $openingDate)
            ->whereDate('payment_date', '<=', $asOfDate)
            ->sum('amount');
        return $balance;
    }

    /**
     * Hitung sisa piutang penjualan cabang sampai tanggal tertentu.
     */
    private function receivableBalanceAtDate(array|int $pharmacyId, string $asOfDate): float
    {
        $pharmacyIds = is_array($pharmacyId) ? $pharmacyId : [$pharmacyId];
        $transactions = MedicineTransactions::query()
            ->with([
                'transactions' => fn($query) => $query->where('status', 1),
            ])
            ->withSum(['payments as finance_paid' => fn($query) => $query
                ->where('payment_type', 'PIUTANG')
                ->whereDate('payment_date', '<=', $asOfDate)], 'amount')
            ->whereIn('pharmacy_id', $pharmacyIds)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->where(function ($period) use ($asOfDate) {
                $period
                    ->where('created_at', '<', FinanceShift::endExclusive($asOfDate))
                    ->orWhere(function ($legacy) use ($asOfDate) {
                        $legacy->whereNull('created_at')->where('updated_at', '<', FinanceShift::endExclusive($asOfDate));
                    });
            })
            ->tap(fn($query) => LiphTransactionScope::applySalesStatus($query))
            ->get(['id', 'debtor_id', 'transaction_type', 'subtotal', 'paid']);

        $balancesByDebtor = [];
        $returnsByDebtor = [];

        foreach ($transactions as $transaction) {
            $debtorKey = (string) ((int) ($transaction->debtor_id ?? 0));

            if ($transaction->transaction_type === 'RETUR KREDIT') {
                $returnsByDebtor[$debtorKey] = ($returnsByDebtor[$debtorKey] ?? 0)
                    + abs((float) ($transaction->subtotal ?? 0));
                continue;
            }

            $total = (float) ($transaction->subtotal ?? 0);
            if ($total <= 0) {
                $total = (float) $transaction->transactions->sum(
                    fn($item) => $item->final_price ?: $item->total_price
                );
            }

            $total = max(0, $total);
            $paid = min($total, max(
                (float) ($transaction->finance_paid ?? 0),
                (float) ($transaction->paid ?? 0)
            ));
            $balancesByDebtor[$debtorKey] = ($balancesByDebtor[$debtorKey] ?? 0) + max(0, $total - $paid);
        }

        foreach ($returnsByDebtor as $debtorKey => $returnAmount) {
            if (isset($balancesByDebtor[$debtorKey])) {
                $balancesByDebtor[$debtorKey] = max(0, $balancesByDebtor[$debtorKey] - $returnAmount);
            }
        }

        return (float) array_sum($balancesByDebtor);
    }

    /**
     * Hitung sisa hutang dagang pembelian kredit cabang sampai tanggal tertentu.
     */
    private function payableBalanceAtDate(array|int $pharmacyId, string $asOfDate): float
    {
        $pharmacyIds = is_array($pharmacyId) ? $pharmacyId : [$pharmacyId];
        if (in_array(1, $pharmacyIds, true) && !in_array(9, $pharmacyIds, true)) {
            $pharmacyIds[] = 9;
        }

        $returnsByDetailId = [];
        $returnsByInvoiceNo = [];

        $returnItems = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where('pr.status', '!=', 'dibatalkan')
            ->whereIn('pr.pharmacy_id', $pharmacyIds)
            ->where(function ($dateQ) use ($asOfDate) {
                $dateQ->whereDate('pr.created_at', '<=', $asOfDate)
                    ->orWhereDate('pri.status_date', '<=', $asOfDate)
                    ->orWhereDate('pri.deduction_date', '<=', $asOfDate);
            })
            ->select('pr.receiving_id', 'pr.invoice_number', 'pri.subtotal')
            ->get();

        foreach ($returnItems as $item) {
            $amt = (float) ($item->subtotal ?? 0);
            if ($item->receiving_id) {
                $returnsByDetailId[$item->receiving_id] = ($returnsByDetailId[$item->receiving_id] ?? 0) + $amt;
            }
            if ($item->invoice_number) {
                $invKey = strtoupper(trim((string) $item->invoice_number));
                $returnsByInvoiceNo[$invKey] = ($returnsByInvoiceNo[$invKey] ?? 0) + $amt;
            }
        }

        $details = ReceivingDetails::query()
            ->with([
                'receiving:id,pharmacy_id,status,date,created_at',
                'creditor:code,ppn_type',
                'receiving_items:id,receiving_details_id,total,raw_price,qty_received',
                'payments' => fn($query) => $query->whereDate('payment_date', '<=', $asOfDate),
            ])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->whereHas('receiving', function ($query) use ($pharmacyIds, $asOfDate) {
                $query
                    ->whereIn('pharmacy_id', $pharmacyIds)
                    ->whereIn('status', [1, 2, 3])
                    ->where(function ($date) use ($asOfDate) {
                        $date->whereRaw("COALESCE(STR_TO_DATE(NULLIF(date, ''), '%d/%m/%Y'), STR_TO_DATE(NULLIF(date, ''), '%Y-%m-%d'), DATE(created_at)) <= ?", [$asOfDate]);
                    });
            })
            ->get();

        $balance = 0.0;
        foreach ($details as $detail) {
            $subtotal = 0.0;
            foreach ($detail->receiving_items as $item) {
                $lineTotal = (float) ($item->total ?? 0);
                if ($lineTotal <= 0) {
                    $lineTotal = (float) ($item->raw_price ?? 0) * (float) ($item->qty_received ?? 0);
                }
                $subtotal += $lineTotal;
            }

            $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
            $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];

            $returFromPr = 0.0;
            if (isset($returnsByDetailId[$detail->id])) {
                $returFromPr = (float) $returnsByDetailId[$detail->id];
            } elseif ($detail->invoice_number && isset($returnsByInvoiceNo[strtoupper(trim($detail->invoice_number))])) {
                $returFromPr = (float) $returnsByInvoiceNo[strtoupper(trim($detail->invoice_number))];
            }

            $paidCashBank = (float) $detail->payments->where('payment_type', '!=', 'RETUR')->sum(fn($p) => (float) ($p->amount ?? 0));
            $paidRetur = (float) $detail->payments->where('payment_type', 'RETUR')->sum(fn($p) => (float) ($p->amount ?? 0));
            $totalReturDeduction = max($paidRetur, $returFromPr);

            $balance += max(0, $invoiceTotal - $paidCashBank - $totalReturDeduction);
        }

        return $balance;
    }

    /**
     * Halaman Pengelolaan Master Bagan Akun (Chart of Accounts / COA).
     */
    public function accounts(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $accounts = FinanceAccount::where(function ($query) {
            $query->whereNull('pharmacy_id')->orWhere('pharmacy_id', getActivePharmacyId());
        })->where(function ($query) {
            $query->where('category', '!=', 'Kas & Bank')->orWhereNotNull('pharmacy_id');
        })->orderBy('code')->get();
        $categories = $this->getCategories();
        $stats = $this->getStats(null, null, null);

        return Inertia::render('Finance/Accounts', [
            'accounts' => $accounts,
            'categories' => $categories,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    public function biaya(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $biayaList = $this->getBiayaData();
        $expenseAccounts = FinanceAccount::whereNotIn('category', ['Kas & Bank', 'Diskon Lain-lain'])
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category', 'balance']);
        $kasBankAccounts = $this
            ->branchKasBankAccountsQuery()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'balance', 'account_number']);
        $stats = $this->getStats(null, null, null);
        $summary = $this->getBiayaSummary($targetPharmacyIds);

        return Inertia::render('Finance/Biaya', [
            'biayaList' => $biayaList,
            'expenseAccounts' => $expenseAccounts,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'summary' => $summary,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Pengelolaan Diskon Off (Diskon Tambahan Pembelian Baru).
     *
     * Diskon off dicatat mirip fitur Biaya, namun TIDAK mengurangi saldo akun
     * Kas & Bank karena merupakan potongan yang diterima dari supplier, bukan
     * pengeluaran kas. Data-nya dipakai sebagai kolom "DISKON OFF" pada laporan
     * Kinerja Omzet (mengurangi total pembelian).
     */
    public function diskon(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $diskonList = $this->getDiskonData();
        $discountAccounts = FinanceAccount::where('category', 'Diskon Lain-lain')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'category', 'balance']);
        $kasBankAccounts = $this
            ->branchKasBankAccountsQuery()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'balance', 'account_number']);
        $stats = $this->getStats(null, null, null);
        $summary = $this->getDiskonSummary($targetPharmacyIds);

        return Inertia::render('Finance/Diskon', [
            'diskonList' => $diskonList,
            'discountAccounts' => $discountAccounts,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'summary' => $summary,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Halaman Fitur Cashbond (Talangan Sementara Apotek).
     *
     * Sebagai penanda internal pengeluaran mendadak yang menggunakan dana kas/bank apotek
     * (misal keperluan pribadi Direktur) yang akan segera diganti/dikembalikan.
     * Cashbond TIDAK mengurangi saldo akun kas & bank dan TIDAK masuk ke laporan keuangan apapun.
     */
    public function cashbond(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $baseQuery = FinanceCashbond::with(['account', 'creator', 'settler'])
            ->where(function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds);
                if (in_array(1, $targetPharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            });

        // Search filter
        if ($request->filled('search')) {
            $search = trim($request->query('search'));
            $baseQuery->where(function ($q) use ($search) {
                $q->where('borrower_name', 'like', "%{$search}%")
                  ->orWhere('purpose', 'like', "%{$search}%")
                  ->orWhere('settlement_notes', 'like', "%{$search}%")
                  ->orWhereHas('account', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        // Status filter ('ALL', 'BELUM LUNAS', 'LUNAS')
        if ($request->filled('status') && in_array(strtoupper($request->query('status')), ['BELUM LUNAS', 'LUNAS'], true)) {
            $baseQuery->where('status', strtoupper($request->query('status')));
        }

        // Account filter
        if ($request->filled('account_id')) {
            $baseQuery->where('account_id', $request->query('account_id'));
        }

        // Date range filter
        if ($request->filled('start_date')) {
            $baseQuery->whereDate('transaction_date', '>=', $request->query('start_date'));
        }
        if ($request->filled('end_date')) {
            $baseQuery->whereDate('transaction_date', '<=', $request->query('end_date'));
        }

        $cashbondList = $baseQuery->orderByDesc('transaction_date')
            ->orderByDesc('id')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'pharmacy_id' => $item->pharmacy_id,
                    'account_id' => $item->account_id,
                    'account_name' => $item->account?->name ?? 'Kas / Bank',
                    'account_code' => $item->account?->code ?? '-',
                    'borrower_name' => $item->borrower_name,
                    'amount' => (float) $item->amount,
                    'transaction_date' => $item->transaction_date ? $item->transaction_date->format('Y-m-d') : null,
                    'purpose' => $item->purpose,
                    'status' => $item->status,
                    'settlement_date' => $item->settlement_date ? $item->settlement_date->format('Y-m-d') : null,
                    'settlement_notes' => $item->settlement_notes,
                    'proof_image' => $item->proof_image ? asset($item->proof_image) : null,
                    'created_by_name' => $item->creator?->name ?? 'Petugas',
                    'settled_by_name' => $item->settler?->name ?? '-',
                    'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i') : null,
                ];
            });

        // Summary calculations
        $summaryQuery = FinanceCashbond::where(function ($q) use ($targetPharmacyIds) {
            $q->whereIn('pharmacy_id', $targetPharmacyIds);
            if (in_array(1, $targetPharmacyIds, true)) {
                $q->orWhereNull('pharmacy_id');
            }
        });

        $totalActive = (float) (clone $summaryQuery)->where('status', 'BELUM LUNAS')->sum('amount');
        $countActive = (int) (clone $summaryQuery)->where('status', 'BELUM LUNAS')->count();
        $totalSettled = (float) (clone $summaryQuery)->where('status', 'LUNAS')->sum('amount');
        $countSettled = (int) (clone $summaryQuery)->where('status', 'LUNAS')->count();
        $totalAll = (float) (clone $summaryQuery)->sum('amount');
        $countAll = (int) (clone $summaryQuery)->count();

        // Akun Kas & Bank yang bisa dipilih sebagai sumber dana
        $kasBankAccounts = $this->branchKasBankAccountsQuery()
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'balance', 'account_number']);

        return Inertia::render('Finance/Cashbond', [
            'cashbondList' => $cashbondList,
            'kasBankAccounts' => $kasBankAccounts,
            'summary' => [
                'totalActive' => $totalActive,
                'countActive' => $countActive,
                'totalSettled' => $totalSettled,
                'countSettled' => $countSettled,
                'totalAll' => $totalAll,
                'countAll' => $countAll,
            ],
            'stats' => $this->getStats(null, null, null),
            'branchContext' => $this->getBranchContext(),
            'filters' => [
                'search' => $request->query('search', ''),
                'status' => $request->query('status', 'ALL'),
                'account_id' => $request->query('account_id', ''),
                'start_date' => $request->query('start_date', ''),
                'end_date' => $request->query('end_date', ''),
            ],
        ]);
    }

    /**
     * Simpan catatan transaksi cashbond baru.
     * Tidak mengubah saldo akun kas & bank (hanya sebagai penanda sumber dana).
     */
    public function storeCashbond(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'borrower_name' => 'required|string|max:150',
            'amount' => 'required|numeric|gt:0',
            'transaction_date' => 'required|date',
            'purpose' => 'nullable|string|max:1000',
            'proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:5120',
        ]);

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun sumber dana harus berkategori Kas & Bank.']);
        }

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $file = $request->file('proof');
            $fileName = 'cashbond_' . time() . '_' . Str::random(6) . '.' . $file->extension();
            $destination = public_path('uploads/cashbonds');
            if (!File::isDirectory($destination)) {
                File::makeDirectory($destination, 0755, true, true);
            }
            $file->move($destination, $fileName);
            $proofPath = 'uploads/cashbonds/' . $fileName;
        }

        $activePharmacyId = getActivePharmacyId();

        FinanceCashbond::create([
            'pharmacy_id' => $activePharmacyId > 0 ? $activePharmacyId : null,
            'account_id' => $account->id,
            'borrower_name' => $validated['borrower_name'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'purpose' => $validated['purpose'] ?? null,
            'status' => 'BELUM LUNAS',
            'proof_image' => $proofPath,
            'created_by' => Auth::id(),
        ]);

        $this->clearStatsCache();

        return back()->with('success', 'Catatan cashbond berhasil disimpan.');
    }

    /**
     * Perbarui data transaksi cashbond.
     */
    public function updateCashbond(Request $request, $id)
    {
        $cashbond = FinanceCashbond::findOrFail($id);

        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'borrower_name' => 'required|string|max:150',
            'amount' => 'required|numeric|gt:0',
            'transaction_date' => 'required|date',
            'purpose' => 'nullable|string|max:1000',
            'proof' => 'nullable|file|mimes:jpeg,png,jpg,pdf,webp|max:5120',
        ]);

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun sumber dana harus berkategori Kas & Bank.']);
        }

        $proofPath = $cashbond->proof_image;
        if ($request->hasFile('proof')) {
            if ($cashbond->proof_image && File::exists(public_path($cashbond->proof_image))) {
                File::delete(public_path($cashbond->proof_image));
            }
            $file = $request->file('proof');
            $fileName = 'cashbond_' . time() . '_' . Str::random(6) . '.' . $file->extension();
            $destination = public_path('uploads/cashbonds');
            if (!File::isDirectory($destination)) {
                File::makeDirectory($destination, 0755, true, true);
            }
            $file->move($destination, $fileName);
            $proofPath = 'uploads/cashbonds/' . $fileName;
        }

        $cashbond->update([
            'account_id' => $account->id,
            'borrower_name' => $validated['borrower_name'],
            'amount' => $validated['amount'],
            'transaction_date' => $validated['transaction_date'],
            'purpose' => $validated['purpose'] ?? null,
            'proof_image' => $proofPath,
        ]);

        $this->clearStatsCache();

        return back()->with('success', 'Data cashbond berhasil diperbarui.');
    }

    /**
     * Tandai cashbond sebagai lunas (uang telah dikembalikan/dibayar oleh Direktur).
     */
    public function settleCashbond(Request $request, $id)
    {
        $cashbond = FinanceCashbond::findOrFail($id);

        $validated = $request->validate([
            'settlement_date' => 'required|date',
            'settlement_notes' => 'nullable|string|max:500',
        ]);

        $cashbond->update([
            'status' => 'LUNAS',
            'settlement_date' => $validated['settlement_date'],
            'settlement_notes' => $validated['settlement_notes'] ?? 'Telah dikembalikan/dibayar oleh Direktur',
            'settled_by' => Auth::id(),
        ]);

        $this->clearStatsCache();

        return back()->with('success', 'Cashbond telah ditandai lunas (dana apotek telah kembali).');
    }

    /**
     * Kembalikan status cashbond menjadi Belum Lunas.
     */
    public function reopenCashbond($id)
    {
        $cashbond = FinanceCashbond::findOrFail($id);

        $cashbond->update([
            'status' => 'BELUM LUNAS',
            'settlement_date' => null,
            'settlement_notes' => null,
            'settled_by' => null,
        ]);

        $this->clearStatsCache();

        return back()->with('success', 'Status cashbond berhasil dikembalikan menjadi Belum Lunas.');
    }

    /**
     * Hapus catatan cashbond.
     */
    public function destroyCashbond($id)
    {
        $cashbond = FinanceCashbond::findOrFail($id);

        if ($cashbond->proof_image && File::exists(public_path($cashbond->proof_image))) {
            File::delete(public_path($cashbond->proof_image));
        }

        $cashbond->delete();

        $this->clearStatsCache();

        return back()->with('success', 'Catatan cashbond berhasil dihapus.');
    }

    /**
     * Helper: Ambil target pharmacy ID untuk filter data transaksi cabang.
     * Untuk PMI (Apotek ID 1 & Gudang ID 9), pemesanan dan pembelian dari supplier dicatat
     * di Gudang PMI (ID 9), sehingga sertakan [1, 9] agar seluruh pembelian & hutang masuk.
     */
    protected function getTargetPharmacyIds(): array
    {
        $activeId = (int) getActivePharmacyId();
        if ($activeId <= 0 || $activeId === 1 || $activeId === 9) {
            return [1, 9];
        }
        return [$activeId];
    }

    /**
     * Ringkasan posisi dana saat ini dan pergerakan keluar pada periode terpilih.
     */
    protected function buildCashflowRecap(string $startDate, string $endDate): array
    {
        $pharmacyId = (int) getActivePharmacyId();
        $pharmacyIds = $this->getTargetPharmacyIds();
        $accounts = $this
            ->branchKasBankAccountsQuery(false)
            ->where(fn($query) => $query->where('is_active', true)->orWhere('balance', '!=', 0))
            ->orderByRaw("CASE WHEN account_type = 'kas_apotek' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->get(['id', 'name', 'code', 'account_type', 'balance']);
        $totalCashBank = (float) $accounts->sum('balance');
        $todayOperationalDate = FinanceShift::operationalDate(now());
        $periodEnd = max($startDate, min($endDate, $todayOperationalDate));
        $dayBeforeStart = \Carbon\Carbon::parse($startDate)->subDay()->toDateString();
        $openingAccountRows = [];
        $closingAccountRows = [];
        foreach ($accounts as $account) {
            $opening = FinancePayment::where('payment_type', 'SALDO AWAL')->where('account_id', $account->id)->first();
            $openingDate = $opening?->payment_date?->toDateString();
            $openingBalance = 0.0;
            if ($opening && $openingDate <= $startDate) {
                $openingBalance = $openingDate === $startDate
                    ? (float) $opening->amount
                    : $this->calculateBalanceFromOpening($account->id, $opening, $dayBeforeStart);
            }
            $closingBalance = 0.0;
            if ($opening && $openingDate <= $periodEnd) {
                $closingBalance = $this->calculateBalanceFromOpening($account->id, $opening, $periodEnd);
            } elseif ($periodEnd === now()->toDateString()) {
                $closingBalance = (float) $account->balance;
            }
            $row = [
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'type' => $account->account_type,
            ];
            $openingAccountRows[] = $row + ['balance' => $openingBalance];
            $closingAccountRows[] = $row + ['balance' => $closingBalance];
        }

        $creditTransactions = MedicineTransactions::query()
            ->with(['debtors:id,name', 'transactions:id,transaction_id,final_price,total_price'])
            ->withSum(['payments as finance_paid' => fn($query) => $query->where('payment_type', 'PIUTANG')], 'amount')
            ->where('pharmacy_id', $pharmacyId)
            ->where('status', 1)
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT', 'RETUR KREDIT'])
            ->get(['id', 'debtor_id', 'transaction_type', 'subtotal', 'paid']);

        $receivablesByDebtor = [];
        $creditReturnsByDebtor = [];
        foreach ($creditTransactions as $transaction) {
            if ($transaction->transaction_type === 'RETUR KREDIT') {
                $debtorKey = (string) ((int) ($transaction->debtor_id ?? 0));
                $creditReturnsByDebtor[$debtorKey] = ($creditReturnsByDebtor[$debtorKey] ?? 0) + abs((float) $transaction->subtotal);
                continue;
            }
            $total = (float) $transaction->subtotal;
            if ($total <= 0) {
                $total = (float) $transaction->transactions->sum(fn($item) => $item->final_price ?: $item->total_price);
            }
            $total = max(0, $total);
            $paid = min($total, max((float) ($transaction->finance_paid ?? 0), (float) ($transaction->paid ?? 0)));
            $outstanding = max(0, $total - $paid);
            $debtorId = (int) ($transaction->debtor_id ?? 0);
            $key = (string) $debtorId;
            if (!isset($receivablesByDebtor[$key])) {
                $receivablesByDebtor[$key] = [
                    'name' => $transaction->debtors?->name ?: 'Debitur tidak tercatat',
                    'amount' => 0.0,
                ];
            }
            $receivablesByDebtor[$key]['amount'] += $outstanding;
        }
        foreach ($creditReturnsByDebtor as $debtorKey => $returnAmount) {
            if (isset($receivablesByDebtor[$debtorKey])) {
                $receivablesByDebtor[$debtorKey]['amount'] = max(0, $receivablesByDebtor[$debtorKey]['amount'] - $returnAmount);
            }
        }
        $receivablesByDebtor = array_values(array_filter($receivablesByDebtor, fn($row) => $row['amount'] > 0));
        usort($receivablesByDebtor, fn($a, $b) => $b['amount'] <=> $a['amount']);
        $totalReceivables = array_sum(array_column($receivablesByDebtor, 'amount'));

        $payablesByCreditor = [];
        $creditDetails = ReceivingDetails::with([
            'receiving:id,pharmacy_id,status',
            'creditor:code,name,ppn_type',
            'receiving_items:id,receiving_details_id,total,raw_price,qty_received',
            'payments:id,receiving_detail_id,amount',
        ])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->whereHas('receiving', fn($query) => $query->whereIn('pharmacy_id', $pharmacyIds)->whereIn('status', [1, 2, 3]))
            ->get();
        foreach ($creditDetails as $detail) {
            $subtotal = 0.0;
            foreach ($detail->receiving_items as $item) {
                $lineTotal = (float) ($item->total ?? 0);
                if ($lineTotal <= 0)
                    $lineTotal = (float) $item->raw_price * (float) $item->qty_received;
                $subtotal += $lineTotal;
            }
            $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
            $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
            $outstanding = max(0, $invoiceTotal - (float) $detail->payments->sum('amount'));
            if ($outstanding <= 0)
                continue;

            $creditorCode = (string) ($detail->creditor_code ?? '');
            $key = $creditorCode !== '' ? $creditorCode : 'unknown';
            if (!isset($payablesByCreditor[$key])) {
                $payablesByCreditor[$key] = [
                    'name' => $detail->creditor?->name ?: ($creditorCode !== '' ? "Supplier {$creditorCode}" : 'Supplier tidak tercatat'),
                    'amount' => 0.0,
                ];
            }
            $payablesByCreditor[$key]['amount'] += $outstanding;
        }
        $payablesByCreditor = array_values($payablesByCreditor);
        usort($payablesByCreditor, fn($a, $b) => $b['amount'] <=> $a['amount']);
        $totalPayables = array_sum(array_column($payablesByCreditor, 'amount'));

        $periodPayments = FinancePayment::with(['expenseAccount:id,name,category', 'account:id,pharmacy_id', 'receiving:id,pharmacy_id'])
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where(function ($query) use ($pharmacyId, $pharmacyIds) {
                $query->whereIn('pharmacy_id', $pharmacyIds)->orWhere(function ($legacy) use ($pharmacyId, $pharmacyIds) {
                    $legacy->whereNull('pharmacy_id')->where(function ($related) use ($pharmacyId, $pharmacyIds) {
                        $related
                            ->whereHas('account', fn($account) => $account->whereIn('pharmacy_id', $pharmacyIds))
                            ->orWhereHas('receiving', fn($receiving) => $receiving->whereIn('pharmacy_id', $pharmacyIds));
                    });
                });
            })
            ->whereIn('payment_type', ['BIAYA', 'CASH', 'KREDIT', 'KONSINYASI'])
            ->get();
        $expensesByAccount = [];
        $totalExpenses = 0.0;
        $cashPurchases = 0.0;
        $supplierPayments = 0.0;
        foreach ($periodPayments as $payment) {
            $amount = (float) $payment->amount;
            if ($payment->payment_type === 'BIAYA') {
                $label = $payment->expenseAccount?->name ?: 'Biaya tanpa akun';
                $expensesByAccount[$label] = ($expensesByAccount[$label] ?? 0) + $amount;
                $totalExpenses += $amount;
            } elseif ($payment->payment_type === 'CASH') {
                $cashPurchases += $amount;
            } else {
                $supplierPayments += $amount;
            }
        }
        arsort($expensesByAccount);
        $expenseRows = [];
        foreach (array_slice($expensesByAccount, 0, 8, true) as $name => $amount) {
            $expenseRows[] = ['name' => $name, 'amount' => (float) $amount];
        }

        $periodTransactions = MedicineTransactions::query()
            ->where('pharmacy_id', $pharmacyId)
            ->tap(fn($query) => LiphTransactionScope::applySalesStatus($query))
            ->tap(fn($query) => LiphTransactionScope::applyPeriod($query, \Carbon\Carbon::parse($startDate), \Carbon\Carbon::parse($endDate)))
            ->get(['transaction_type', 'payment_method', 'subtotal', 'discount']);
        $periodCashSales = 0.0;
        $periodEdcTransferSales = 0.0;
        $periodCreditSales = 0.0;
        foreach ($periodTransactions as $transaction) {
            $type = strtoupper(trim((string) $transaction->transaction_type));
            $method = strtoupper(trim((string) $transaction->payment_method));
            $amount = max(0, (float) $transaction->subtotal - (float) $transaction->discount);
            if (in_array($type, ['RETUR JUAL', 'RETUR KREDIT'], true)) {
                if ($type === 'RETUR KREDIT')
                    $periodCreditSales -= abs($amount);
                else
                    $periodCashSales -= abs($amount);
            } elseif (in_array($type, ['KREDIT', 'RESEP KREDIT'], true)) {
                $periodCreditSales += $amount;
            } elseif (in_array($method, ['CASH', 'TUNAI'], true)) {
                $periodCashSales += $amount;
            } elseif (in_array($method, ['DEBIT', 'EDC', 'TRANSFER', 'QRIS'], true)) {
                $periodEdcTransferSales += $amount;
            }
        }

        $periodReceipts = FinancePayment::with('transaction:id,pharmacy_id')
            ->whereBetween('payment_date', [$startDate, $endDate])
            ->where(function ($query) use ($pharmacyId) {
                $query
                    ->where('pharmacy_id', $pharmacyId)
                    ->orWhere(function ($legacy) use ($pharmacyId) {
                        $legacy
                            ->whereNull('pharmacy_id')
                            ->whereHas('transaction', fn($transaction) => $transaction->where('pharmacy_id', $pharmacyId));
                    });
            })
            ->whereIn('payment_type', ['PIUTANG', 'PENDAPATAN'])
            ->get(['payment_type', 'amount']);
        $periodReceivablePayments = (float) $periodReceipts->where('payment_type', 'PIUTANG')->sum('amount');
        $periodOtherIncome = (float) $periodReceipts->where('payment_type', 'PENDAPATAN')->sum('amount');
        $periodSupplierPaymentsByType = ['cash' => 0.0, 'bank' => 0.0];
        $periodCreditSupplierPayments = (float) $periodPayments
            ->where('payment_type', 'KREDIT')
            ->sum('amount');
        foreach ($periodPayments as $payment) {
            if (in_array($payment->payment_type, ['KREDIT', 'KONSINYASI'], true)) {
                $type = $payment->account?->account_type === 'bank' ? 'bank' : 'cash';
                $periodSupplierPaymentsByType[$type] += (float) $payment->amount;
            }
        }

        $periodCreditDetails = ReceivingDetails::with(['creditor:code,ppn_type', 'receiving_items' => fn($query) => $query
                ->whereRaw('created_at >= ? AND created_at < ?', [FinanceShift::start($startDate), FinanceShift::endExclusive($endDate)])])
            ->whereRaw('UPPER(TRIM(COALESCE(invoice_payment, ""))) = ?', ['KREDIT'])
            ->whereHas('receiving', fn($query) => $query->whereIn('pharmacy_id', $pharmacyIds)->whereIn('status', [1, 2, 3]))
            ->where(function ($dateQ) use ($startDate, $endDate) {
                $dateQ->whereBetween('receiving_details.created_at', [FinanceShift::start($startDate), FinanceShift::endExclusive($endDate)])
                    ->orWhereHas('receiving_items', fn($query) => $query
                        ->whereRaw('created_at >= ? AND created_at < ?', [FinanceShift::start($startDate), FinanceShift::endExclusive($endDate)]));
            })
            ->get();
        $periodCreditPurchases = (float) $periodCreditDetails->sum(function ($detail) {
            $ppnType = strtoupper(trim((string) ($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA')));
            return ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
        });
        $periodPurchaseReturns = (float) DB::table('items_log as il')
            ->join('receiving as r', 'r.code', '=', 'il.transaction_code')
            ->where('il.status', 4)
            ->whereIn('r.pharmacy_id', $pharmacyIds)
            ->whereRaw('il.date >= ? AND il.date < ?', [FinanceShift::start($startDate), FinanceShift::endExclusive($endDate)])
            ->sum(DB::raw('ABS(CAST(COALESCE(NULLIF(il.total, ""), "0") AS DECIMAL(15,2)))'));

        $openingReceivable = $this->receivableBalanceAtDate($pharmacyIds, $dayBeforeStart);
        $closingReceivable = $this->receivableBalanceAtDate($pharmacyIds, $periodEnd);
        $openingPayable = $this->payableBalanceAtDate($pharmacyIds, $dayBeforeStart);
        $closingPayable = $this->payableBalanceAtDate($pharmacyIds, $periodEnd);
        $openingCashBank = (float) array_sum(array_column($openingAccountRows, 'balance'));
        $closingCashBank = (float) array_sum(array_column($closingAccountRows, 'balance'));

        return [
            'asOf' => now()->toDateString(),
            'periodStart' => $startDate,
            'periodEnd' => $endDate,
            'periodEffectiveEnd' => $periodEnd,
            'accounts' => $accounts->map(fn($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'type' => $account->account_type,
                'balance' => (float) $account->balance,
            ])->values(),
            'totalCashBank' => $totalCashBank,
            'openingAccounts' => $openingAccountRows,
            'closingAccounts' => $closingAccountRows,
            'openingCashBank' => $openingCashBank,
            'closingCashBank' => $closingCashBank,
            'openingReceivable' => $openingReceivable,
            'closingReceivable' => $closingReceivable,
            'openingOtherReceivables' => 0,
            'closingOtherReceivables' => 0,
            'openingAssets' => $openingCashBank + $openingReceivable,
            'closingAssets' => $closingCashBank + $closingReceivable,
            'openingPayables' => $openingPayable,
            'closingPayables' => $closingPayable,
            'periodCashSales' => $periodCashSales,
            'periodEdcTransferSales' => $periodEdcTransferSales,
            'periodCreditSales' => $periodCreditSales,
            'periodReceivablePayments' => $periodReceivablePayments,
            'periodOtherIncome' => $periodOtherIncome,
            'periodCashbondReceipts' => 0,
            'periodAdjustments' => 0,
            'periodSupplierCashPayments' => $periodSupplierPaymentsByType['cash'],
            'periodSupplierBankPayments' => $periodSupplierPaymentsByType['bank'],
            'periodCreditSupplierPayments' => $periodCreditSupplierPayments,
            'periodCreditPurchases' => $periodCreditPurchases,
            'periodPurchaseReturns' => $periodPurchaseReturns,
            'periodCashPurchases' => $cashPurchases,
            'periodCashbondPayments' => 0,
            'periodOperatingExpenses' => $totalExpenses,
            'totalReceivables' => $totalReceivables,
            'receivables' => array_slice($receivablesByDebtor, 0, 8),
            'totalPayables' => $totalPayables,
            'payables' => array_slice($payablesByCreditor, 0, 8),
            'totalExpenses' => $totalExpenses,
            'expenses' => $expenseRows,
            'cashPurchases' => $cashPurchases,
            'supplierPayments' => $supplierPayments,
            'netCashAfterPayables' => $totalCashBank - $totalPayables,
            'netPositionIncludingReceivables' => $totalCashBank + $totalReceivables - $totalPayables,
        ];
    }

    /**
     * Akun Kas & Bank kini dimiliki cabang yang sedang dipilih.
     */
    protected function branchKasBankAccountsQuery(bool $activeOnly = true)
    {
        $pharmacyId = (int) getActivePharmacyId();
        FinanceAccount::ensureBranchDefaultAccounts($pharmacyId);

        return FinanceAccount::where('category', 'Kas & Bank')
            ->where('pharmacy_id', $pharmacyId)
            ->when($activeOnly, fn($query) => $query->where('is_active', true));
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

    private function normalizeFinanceDate($value): ?string
    {
        if (empty($value)) {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        $date = trim((string) $value);
        foreach (['!Y-m-d', '!d/m/Y', '!Y-m-d H:i:s', '!d/m/Y H:i:s'] as $format) {
            try {
                $parsed = \Carbon\Carbon::createFromFormat($format, $date);
                if ($parsed !== false) {
                    return $parsed->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                // Try the next known legacy date format.
            }
        }

        try {
            return \Carbon\Carbon::parse($date)->format('Y-m-d');
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Helper: Ambil total retur pembelian yang sudah potong tagihan untuk receiving detail.
     */
    protected function getReceivingDetailReturTotal(ReceivingDetails $detail): float
    {
        $sudahPotongItemTotal = (float) DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where(function ($q) use ($detail) {
                $q->where('pr.receiving_id', $detail->id);
                if (!empty($detail->invoice_number)) {
                    $q->orWhere('pr.invoice_number', $detail->invoice_number);
                }
            })
            ->where('pr.status', '!=', 'dibatalkan')
            ->where('pri.status', 'sudah_potong')
            ->sum('pri.subtotal');

        $financeReturTotal = (float) FinancePayment::where('receiving_detail_id', $detail->id)
            ->where('payment_type', 'RETUR')
            ->sum('amount');

        return round(max($sudahPotongItemTotal, $financeReturTotal), 2);
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
            ->get();

        // Kumpulkan detail IDs dan invoice numbers untuk eager query retur pembelian
        $allDetailIds = [];
        $allInvoiceNumbers = [];
        foreach ($receivingsKredit as $rec) {
            foreach ($rec->receiving_details as $detail) {
                $allDetailIds[] = $detail->id;
                if (!empty($detail->invoice_number)) {
                    $allInvoiceNumbers[] = trim($detail->invoice_number);
                }
            }
        }
        $allInvoiceNumbers = array_values(array_filter(array_unique($allInvoiceNumbers)));

        $returnsQuery = DB::table('purchase_return_items as pri')
            ->join('purchase_returns as pr', 'pr.id', '=', 'pri.purchase_return_id')
            ->where(function ($q) use ($allDetailIds, $allInvoiceNumbers) {
                if (!empty($allDetailIds)) {
                    $q->whereIn('pr.receiving_id', $allDetailIds);
                }
                if (!empty($allInvoiceNumbers)) {
                    $q->orWhereIn('pr.invoice_number', $allInvoiceNumbers);
                }
            })
            ->where('pr.status', '!=', 'dibatalkan')
            ->select(
                'pri.id',
                'pri.purchase_return_id',
                'pri.medicine_id',
                'pri.medicine_name',
                'pri.batch_number',
                'pri.expired_date',
                'pri.qty',
                'pri.unit',
                'pri.unit_price',
                'pri.subtotal',
                'pri.status',
                'pri.status_date',
                'pri.deduction_date',
                'pri.deduction_note',
                'pri.reason',
                'pri.notes',
                'pr.return_no',
                'pr.receiving_id as pr_receiving_id',
                'pr.invoice_number as pr_invoice_number',
                'pr.created_at as pr_created_at'
            )
            ->get();

        $returnsByDetailId = $returnsQuery->filter(fn($r) => !empty($r->pr_receiving_id))->groupBy('pr_receiving_id');
        $returnsByInvoiceNumber = $returnsQuery->filter(fn($r) => !empty($r->pr_invoice_number))->groupBy(fn($r) => strtoupper(trim((string) $r->pr_invoice_number)));

        $hutangDagang = [];
        foreach ($receivingsKredit as $rec) {
            foreach ($rec->receiving_details as $detail) {
                if ($detail->invoice_payment !== 'KREDIT')
                    continue;

                $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');
                $itemsList = [];
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $formatDiscount = static function ($value) {
                    $value = (float) $value;
                    return $value > 100
                        ? 'Rp ' . number_format($value, 0, ',', '.')
                        : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
                };

                foreach ($detail->receiving_items as $item) {
                    $medName = $item->order_items->medicines->name ?? 'Obat';
                    $sku = $item->order_items->medicines->code ?? ('SKU-' . $item->id);
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $diskon = (float) ($item->discount ?? 0);
                    $extraDiskon = (float) ($item->extra_discount ?? 0);
                    $lineAmounts = ReceivingInvoiceTotals::lineAmounts($item, $ppnType);

                    $itemsList[] = [
                        'sku' => $sku,
                        'nama' => $medName,
                        'qty' => $qty,
                        'satuan' => $item->order_items->medicines->unit ?? 'Pcs',
                        'diskon' => $formatDiscount($diskon),
                        'extraDiskon' => $formatDiscount($extraDiskon),
                        'harga' => $harga,
                        'pajak' => $ppnType,
                        'jumlah' => $lineAmounts['total'],
                    ];
                }

                $invoiceAmounts = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType);
                $subtotal = $invoiceAmounts['subtotal'];
                $ppnNominal = $invoiceAmounts['ppn'];
                $total = $invoiceAmounts['total'];

                // Cari riwayat retur pembelian terkait
                $matchedReturns = collect();
                if (isset($returnsByDetailId[$detail->id])) {
                    $matchedReturns = $matchedReturns->merge($returnsByDetailId[$detail->id]);
                }
                $invNorm = strtoupper(trim((string) $detail->invoice_number));
                if ($invNorm !== '' && isset($returnsByInvoiceNumber[$invNorm])) {
                    $matchedReturns = $matchedReturns->merge($returnsByInvoiceNumber[$invNorm]);
                }
                $matchedReturns = $matchedReturns->unique('id')->values();

                $retursList = [];
                $sudahPotongTotal = 0;
                foreach ($matchedReturns as $retItem) {
                    $subtotalRetur = (float) $retItem->subtotal;
                    if ($retItem->status === 'sudah_potong') {
                        $sudahPotongTotal += $subtotalRetur;
                    }
                    $tglRetur = $retItem->deduction_date ?: ($retItem->status_date ?: ($retItem->pr_created_at ? substr($retItem->pr_created_at, 0, 10) : null));
                    $retursList[] = [
                        'id' => $retItem->id,
                        'purchase_return_id' => $retItem->purchase_return_id,
                        'code' => $retItem->return_no ?: ('RET-' . $retItem->purchase_return_id),
                        'tanggal' => $tglRetur ? \Carbon\Carbon::parse($tglRetur)->format('d/m/Y') : '-',
                        'raw_date' => $tglRetur,
                        'nama' => $retItem->medicine_name ?? 'Obat',
                        'batch_number' => $retItem->batch_number ?? '-',
                        'expired_date' => $retItem->expired_date ? \Carbon\Carbon::parse($retItem->expired_date)->format('d/m/Y') : '-',
                        'qty' => (float) $retItem->qty,
                        'satuan' => $retItem->unit ?? 'Pcs',
                        'harga' => (float) $retItem->unit_price,
                        'subtotal' => $subtotalRetur,
                        'nominal' => $subtotalRetur,
                        'status' => $retItem->status,
                        'status_label' => match ($retItem->status) {
                            'sudah_potong' => 'Sudah Potong Tagihan',
                            'sudah_diambil' => 'Sudah Diambil PBF',
                            'dikonfirmasi' => 'Dikonfirmasi',
                            'barang_kembali_pmi' => 'Barang Kembali PMI',
                            default => 'Diajukan',
                        },
                        'reason' => $retItem->reason ?: '-',
                        'deduction_note' => $retItem->deduction_note ?: '-',
                        'notes' => $retItem->notes ?: '-',
                    ];
                }

                $paidCashBank = 0;
                $paidReturFinance = 0;
                $paymentHistory = [];

                foreach ($detail->payments as $payment) {
                    if ($payment->payment_type === 'RETUR') {
                        $paidReturFinance += (float) $payment->amount;
                    } else {
                        $paidCashBank += (float) $payment->amount;
                    }
                    $paymentHistory[] = [
                        'id' => $payment->id,
                        'tanggal' => \Carbon\Carbon::parse($payment->payment_date)->format('d/m/Y'),
                        'nominal' => (float) $payment->amount,
                        'tipe' => $payment->payment_type,
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

                $totalRetur = round(max($paidReturFinance, $sudahPotongTotal), 2);
                $netTotal = round(max(0, $total - $totalRetur), 2);
                $totalTerbayar = round(min($netTotal, $paidCashBank), 2);
                $sisa = round(max(0, $netTotal - $totalTerbayar), 2);

                $isLunas = ($sisa < 0.005 && $total > 0) || ((int) $rec->status === 3 && $sisa <= 0.005);
                $sisa = $isLunas ? 0 : $sisa;

                $statusLabel = 'Belum Dibayar';
                if ($isLunas) {
                    if ($totalRetur >= $total && $total > 0) {
                        $statusLabel = 'Diretur Penuh';
                    } elseif ($totalRetur > 0) {
                        $statusLabel = 'Lunas (Ada Retur)';
                    } else {
                        $statusLabel = 'Lunas';
                    }
                } else {
                    if ($totalTerbayar > 0) {
                        $statusLabel = 'Dibayar Sebagian';
                    } elseif ($totalRetur > 0) {
                        $statusLabel = 'Diretur Sebagian';
                    } else {
                        $statusLabel = 'Belum Dibayar';
                    }
                }

                $invoiceDate = $this->normalizeFinanceDate($detail->invoice_date);
                $dueDate = $this->normalizeFinanceDate($detail->invoice_due);
                $dueCarbon = $dueDate ? \Carbon\Carbon::parse($dueDate)->endOfDay() : null;
                $isOverdue = ($dueCarbon && !$isLunas) ? $dueCarbon->isPast() : false;
                $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays(now()) : 0;
                $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas) ? (int) now()->diffInDays($dueCarbon) : 0;

                $hutangDagang[] = [
                    'id' => $detail->id,
                    'receiving_id' => $rec->id,
                    'nomor' => $detail->invoice_number ?: $rec->code,
                    'vendor' => $vendorName,
                    'vendor_code' => $detail->creditor_code ?: ($detail->creditor->code ?? ''),
                    'referensi' => $detail->receiving_details_code ?: $rec->code,
                    'tanggal' => $invoiceDate ? \Carbon\Carbon::parse($invoiceDate)->format('d/m/Y') : ($rec->date ?: '-'),
                    'raw_tanggal' => $invoiceDate,
                    'raw_tanggal_terima' => $this->normalizeFinanceDate($rec->date),
                    'jatuhTempo' => $dueCarbon ? $dueCarbon->format('d/m/Y') : '-',
                    'raw_jatuh_tempo' => $dueCarbon ? $dueCarbon->format('Y-m-d') : null,
                    'is_overdue' => $isOverdue,
                    'days_overdue' => $daysOverdue,
                    'days_remaining' => $daysRemaining,
                    'tanggalBayar' => $isLunas
                        ? ($detail->payments->sortByDesc('payment_date')->first()?->payment_date
                            ? \Carbon\Carbon::parse($detail->payments->sortByDesc('payment_date')->first()->payment_date)->format('d/m/Y')
                            : \Carbon\Carbon::parse($rec->updated_at)->format('d/m/Y'))
                        : '',
                    'status' => $statusLabel,
                    'subtotal' => $subtotal,
                    'ppn' => $ppnNominal,
                    'total' => $total,
                    'total_retur' => $totalRetur,
                    'net_total' => $netTotal,
                    'terbayar' => $totalTerbayar,
                    'sisa' => $sisa,
                    'returs' => $retursList,
                    'gudang' => $rec->pharmacy->name ?? 'Gudang Utama',
                    'tglKirim' => $rec->date ?: '-',
                    'items' => $itemsList,
                    'payments' => $paymentHistory,
                    'is_submitted' => (bool) ($detail->is_submitted ?? false),
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
                if ($detail->invoice_payment !== 'TUNAI')
                    continue;

                $vendorName = $detail->creditor->name ?? ($detail->creditor_code ?: 'PBF / Vendor');
                $itemsList = [];
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $formatDiscount = static function ($value) {
                    $value = (float) $value;
                    return $value > 100
                        ? 'Rp ' . number_format($value, 0, ',', '.')
                        : rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') . '%';
                };

                foreach ($detail->receiving_items as $item) {
                    $medName = $item->order_items->medicines->name ?? 'Obat';
                    $sku = $item->order_items->medicines->code ?? ('SKU-' . $item->id);
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $diskon = (float) ($item->discount ?? 0);
                    $extraDiskon = (float) ($item->extra_discount ?? 0);
                    $lineAmounts = ReceivingInvoiceTotals::lineAmounts($item, $ppnType);

                    $itemsList[] = [
                        'sku' => $sku,
                        'nama' => $medName,
                        'qty' => $qty,
                        'satuan' => $item->order_items->medicines->unit ?? 'Pcs',
                        'diskon' => $formatDiscount($diskon),
                        'extraDiskon' => $formatDiscount($extraDiskon),
                        'harga' => $harga,
                        'pajak' => $ppnType,
                        'jumlah' => $lineAmounts['total'],
                    ];
                }

                $invoiceAmounts = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType);
                $subtotal = $invoiceAmounts['subtotal'];
                $ppnNominal = $invoiceAmounts['ppn'];
                $total = $invoiceAmounts['total'];

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
                    'raw_tanggal' => $detail->invoice_date ? \Carbon\Carbon::parse($detail->invoice_date)->format('Y-m-d') : ($rec->date ?: null),
                    'status' => 'Lunas (Cash)',
                    'subtotal' => $subtotal,
                    'ppn' => $ppnNominal,
                    'total' => $total,
                    'gudang' => $rec->pharmacy->name ?? 'Gudang Utama',
                    'akunPembayaran' => $akunPembayaran,
                    'has_account' => !empty($akunPembayaran),
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
            'returs.medicine',
        ])
            ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
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

            // Riwayat Retur Kredit untuk faktur ini
            $retursList = [];
            $totalRetur = 0;
            foreach ($tx->returs as $ret) {
                $retNominal = (float) ($ret->total_retur ?? 0);
                $totalRetur += $retNominal;
                $retCreated = $ret->created_at ? \Carbon\Carbon::parse($ret->created_at) : null;
                $retursList[] = [
                    'id' => $ret->id,
                    'code' => $ret->code ?: ('RT-' . $ret->id),
                    'tanggal' => $retCreated ? $retCreated->format('d/m/Y') : '-',
                    'raw_created_at' => $retCreated ? $retCreated->format('Y-m-d H:i:s') : null,
                    'medicine_id' => $ret->medicine_id,
                    'nama' => $ret->medicine->name ?? 'Obat',
                    'sku' => $ret->medicine->code ?? ('SKU-' . $ret->medicine_id),
                    'satuan' => $ret->medicine->unit ?? 'Pcs',
                    'qty' => (float) ($ret->qty_retur ?? 0),
                    'nominal' => $retNominal,
                ];
            }
            $totalRetur = round($totalRetur, 2);

            $total = (float) ($tx->subtotal > 0 ? $tx->subtotal : $calcSubtotal);
            $total = round($total, 2);
            $netTotal = round(max(0, $total - $totalRetur), 2);

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
            if ($totalTerbayar > $netTotal) {
                $totalTerbayar = $netTotal;
            }
            $sisa = round(max(0, $netTotal - $totalTerbayar), 2);

            $isLunas = ($sisa < 0.005);
            $sisa = $isLunas ? 0 : $sisa;

            if ($isLunas) {
                if ($totalRetur >= $total && $total > 0) {
                    $statusLabel = 'Diretur Penuh';
                } elseif ($totalRetur > 0) {
                    $statusLabel = 'Lunas (Ada Retur)';
                } else {
                    $statusLabel = 'Lunas';
                }
            } else {
                if ($totalTerbayar > 0) {
                    $statusLabel = 'Dibayar Sebagian';
                } elseif ($totalRetur > 0) {
                    $statusLabel = 'Diretur Sebagian';
                } else {
                    $statusLabel = 'Belum Bayar';
                }
            }

            $createdCarbon = $tx->created_at ? \Carbon\Carbon::parse($tx->created_at) : null;
            $dueCarbon = $createdCarbon ? $createdCarbon->copy()->addDays(30) : null;
            $isOverdue = ($dueCarbon && !$isLunas) ? $dueCarbon->isPast() : false;
            $daysOverdue = ($isOverdue && $dueCarbon) ? (int) $dueCarbon->diffInDays(now()) : 0;
            $daysRemaining = (!$isOverdue && $dueCarbon && !$isLunas) ? (int) now()->diffInDays($dueCarbon) : 0;
            $tanggalLunas = '';
            if ($isLunas) {
                $lastPayment = $tx->payments->sortByDesc('payment_date')->first();
                $tanggalLunas = $lastPayment?->payment_date
                    ? \Carbon\Carbon::parse($lastPayment->payment_date)->format('d/m/Y')
                    : ($tx->updated_at ? \Carbon\Carbon::parse($tx->updated_at)->format('d/m/Y') : '');
            }

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
                'tanggalLunas' => $tanggalLunas,
                'subtotal' => $total,
                'total' => $total,
                'total_retur' => $totalRetur,
                'net_total' => $netTotal,
                'terbayar' => $totalTerbayar,
                'sisa' => $sisa,
                'items' => $itemsList,
                'payments' => $paymentHistory,
                'returs' => $retursList,
                'is_submitted' => (bool) ($tx->is_submitted ?? false),
                'lastModified' => $tx->updated_at ? \Carbon\Carbon::parse($tx->updated_at)->format('d M Y H:i') : '-',
            ];
        }
        return $piutangPenjualan;
    }

    /**
     * Helper: Ambil daftar seluruh Transaksi Retur Penjualan Kredit.
     */
    protected function getCreditReturnsData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        $returs = Retur::with([
            'medicine',
            'transaction.debtors',
            'transaction.patients',
            'transaction.doctors',
            'transaction.pharmacy',
        ])
            ->whereHas('transaction', function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
                    ->whereIn('pharmacy_id', $targetPharmacyIds);
            })
            ->where('status', 1)
            ->latest('id')
            ->take(300)
            ->get();

        $creditReturns = [];
        foreach ($returs as $ret) {
            $tx = $ret->transaction;
            $debtorName = $tx?->debtors?->name ?? ($tx?->patient_id ? ('Pasien: ' . ($tx->patients->name ?? 'Umum')) : 'Pelanggan Umum');
            $createdCarbon = $ret->created_at ? \Carbon\Carbon::parse($ret->created_at) : null;

            $creditReturns[] = [
                'id' => $ret->id,
                'code' => $ret->code ?: ('RT-' . $ret->id),
                'transaction_id' => $ret->transaction_id,
                'transaction_code' => $tx?->transaction_code ?: ('TRX-' . $ret->transaction_id),
                'debtor' => $debtorName,
                'debtor_id' => $tx?->debtor_id,
                'debtor_code' => $tx?->debtors?->code ?? '-',
                'dokter' => $tx?->doctors?->name ?? '-',
                'pasien' => $tx?->patients?->name ?? '-',
                'apotek' => $tx?->pharmacy?->name ?? 'Apotek Utama',
                'tanggal' => $createdCarbon ? $createdCarbon->format('d/m/Y') : '-',
                'raw_created_at' => $createdCarbon ? $createdCarbon->format('Y-m-d H:i:s') : null,
                'medicine_id' => $ret->medicine_id,
                'medicine_name' => $ret->medicine?->name ?? 'Obat',
                'medicine_sku' => $ret->medicine?->code ?? ('SKU-' . $ret->medicine_id),
                'satuan' => $ret->medicine?->unit ?? 'Pcs',
                'qty_retur' => (float) ($ret->qty_retur ?? 0),
                'total_retur' => (float) ($ret->total_retur ?? 0),
                'status' => 'Selesai',
            ];
        }

        return $creditReturns;
    }

    /**
     * Helper: Ambil riwayat Mutasi Transaksi Kas & Bank.
     */
    protected function getMutasiData($limit = 500, $startDate = null, $endDate = null)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        $query = FinancePayment::with([
            'account',
            'expenseAccount',
            'incomeAccount',
            'transferAccount',
            'creator',
            'pharmacy',
            'receivingDetail.creditor',
            'transaction.debtors',
            'transaction.patients',
        ])
            ->whereHas('account', function ($q) {
                $q->where('category', 'Kas & Bank');
                $q->where('pharmacy_id', getActivePharmacyId());
            })
            ->where(function ($query) use ($targetPharmacyIds) {
                $query->whereHas('receiving', function ($q) use ($targetPharmacyIds) {
                    $q->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhereHas('transaction', function ($q) use ($targetPharmacyIds) {
                    $q->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($q) use ($targetPharmacyIds) {
                    $q
                        ->where('payment_type', 'BIAYA')
                        ->where(function ($sub) use ($targetPharmacyIds) {
                            $sub
                                ->whereIn('pharmacy_id', $targetPharmacyIds)
                                ->orWhereNull('pharmacy_id');
                        });
                })->orWhere(function ($q) use ($targetPharmacyIds) {
                    $q->where('payment_type', 'PENDAPATAN')->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($q) use ($targetPharmacyIds) {
                    $q->where('payment_type', 'SETOR')->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($q) use ($targetPharmacyIds) {
                    $q->where('payment_type', 'TARIK TUNAI')->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($q) use ($targetPharmacyIds) {
                    $q->whereIn('payment_type', ['PENJUALAN TUNAI', 'PENJUALAN NON TUNAI', 'RETUR PENJUALAN'])
                        ->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere('payment_type', 'SALDO AWAL')
                  ->orWhere('payment_type', 'PENYESUAIAN SALDO');
            });

        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        $query->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($limit) {
            $query->take($limit);
        }

        return $query->get()
            ->flatMap(function ($p) {
                $isSetor = $p->payment_type === 'SETOR';
                $isTarikTunai = $p->payment_type === 'TARIK TUNAI';
                $row = $this->formatMutasiRow($p);
                if (!$isSetor && !$isTarikTunai) {
                    return [$row];
                }
                $destRow = $this->formatMutasiRow($p, $p->transfer_account_id);
                return [$row, $destRow];
            });
    }

    /**
     * Helper: Format satu baris transaksi mutasi kas & bank.
     */
    protected function formatMutasiRow($p, $forcedAccountId = null): array
    {
        $isMasuk = in_array($p->payment_type, ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'], true);
        $isSaldoAwal = $p->payment_type === 'SALDO AWAL';
        $isSetor = $p->payment_type === 'SETOR';
        $isTarikTunai = $p->payment_type === 'TARIK TUNAI';
        $isPenyesuaian = $p->payment_type === 'PENYESUAIAN SALDO';
        $isOpnameOut = $isPenyesuaian && $p->reference_number === 'OPNAME-OUT';

        if ($isSaldoAwal) {
            $direction = 'OPENING';
            $tipeLabel = 'Saldo Awal';
        } elseif ($isPenyesuaian) {
            $direction = $isOpnameOut ? 'OUT' : 'IN';
            $tipeLabel = $isOpnameOut ? 'Opname Keluar (-)' : 'Opname Masuk (+)';
        } elseif ($isSetor) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $direction = 'IN';
                $tipeLabel = 'Setor Masuk';
            } else {
                $direction = 'OUT';
                $tipeLabel = 'Setor Kas';
            }
        } elseif ($isTarikTunai) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $direction = 'IN';
                $tipeLabel = 'Tarik Masuk (Kas)';
            } else {
                $direction = 'OUT';
                $tipeLabel = 'Tarik Tunai';
            }
        } elseif ($p->payment_type === 'RETUR PENJUALAN') {
            $direction = 'OUT';
            $tipeLabel = 'Retur Jual (Refund)';
        } else {
            $direction = $isMasuk ? 'IN' : 'OUT';
            $tipeLabel = $isMasuk ? 'Masuk (Debit)' : 'Keluar (Kredit)';
        }

        $pihakTerkait = '-';
        $noDokumen = $p->reference_number ?: ('MUT-' . str_pad($p->id, 5, '0', STR_PAD_LEFT));
        $deskripsi = $p->notes;

        if ($p->payment_type === 'PENJUALAN TUNAI') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->transaction_code ?? $p->reference_number;
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = 'Penerimaan penjualan tunai kasir';
        } elseif ($p->payment_type === 'PENJUALAN NON TUNAI') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->transaction_code ?? $p->reference_number;
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) {
                $method = $p->transaction->payment_method ?? 'NON-TUNAI';
                $bank = $p->transaction->transfer_bank_name ?? ($p->account->name ?? 'Bank');
                $deskripsi = "Penerimaan penjualan {$method} melalui {$bank}";
            }
        } elseif ($p->payment_type === 'PIUTANG') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->invoice_number ?? $p->reference_number;
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = "Penerimaan piutang penjualan dari {$pihakTerkait}";
        } elseif ($p->payment_type === 'KREDIT') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = "Pelunasan hutang pembelian ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'CASH') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = "Pembelian tunai (cash) ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'KONSINYASI') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = "Pembayaran barang konsinyasi terjual ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'BIAYA') {
            $pihakTerkait = $p->recipient ?: ($p->expenseAccount->name ?? 'Biaya Operasional');
            $doc = $p->reference_number ?: ('BY-' . str_pad($p->id, 5, '0', STR_PAD_LEFT));
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = 'Pengeluaran biaya: ' . ($p->expenseAccount->name ?? 'Operasional');
        } elseif ($p->payment_type === 'PENDAPATAN') {
            $pihakTerkait = $p->recipient ?: ($p->incomeAccount->name ?? 'Pendapatan Lain-lain');
            if (!$p->reference_number) $noDokumen = 'PL-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            if (!$deskripsi) $deskripsi = 'Pendapatan lain-lain: ' . ($p->incomeAccount->name ?? 'Pendapatan');
        } elseif ($p->payment_type === 'RETUR PENJUALAN') {
            $pihakTerkait = $p->transaction->patients->name ?? ($p->transaction->debtors->name ?? 'Pelanggan Umum');
            $doc = $p->reference_number ?: ('RT-' . $p->id);
            if ($doc) $noDokumen = $doc;
            if (!$deskripsi) $deskripsi = 'Pengembalian dana retur penjualan tunai kasir';
        } elseif ($isSaldoAwal) {
            $pihakTerkait = $p->account->name ?? 'Kas / Bank';
            $noDokumen = 'SA-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            $deskripsi = $p->notes ?: 'Saldo awal akun Kas & Bank';
        } elseif ($isSetor) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $pihakTerkait = $p->account->name ?? 'Kas Apotek / Drawer';
                $deskripsi = $p->notes ?: 'Setoran masuk dari kas';
            } else {
                $pihakTerkait = $p->transferAccount->name ?? 'Rekening tujuan';
                $deskripsi = $p->notes ?: 'Setoran kas ke rekening';
            }
        } elseif ($isTarikTunai) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $pihakTerkait = $p->account->name ?? 'Rekening Bank';
                $deskripsi = $p->notes ?: 'Penerimaan tarik tunai dari rekening bank ke kas';
            } else {
                $pihakTerkait = $p->transferAccount->name ?? 'Kas Apotek / Drawer';
                $deskripsi = $p->notes ?: 'Tarik tunai dari rekening bank ke kas';
            }
        } elseif ($isPenyesuaian) {
            $pihakTerkait = $p->account->name ?? 'Kas / Bank';
            $noDokumen = 'OPN-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            $deskripsi = $p->notes ?: 'Penyesuaian Saldo Riil (Opname)';
        }

        $accountToShow = ($forcedAccountId && $p->transfer_account_id == $forcedAccountId)
            ? $p->transferAccount
            : $p->account;

        return [
            'id' => $p->id . ($forcedAccountId && $p->transfer_account_id == $forcedAccountId ? '-in' : ''),
            'account_id' => $accountToShow?->id ?? $p->account_id,
            'account_name' => $accountToShow?->name ?? 'Akun Kas',
            'account_code' => $accountToShow?->code ?? '-',
            'account_number' => $accountToShow?->account_number ?? '-',
            'date' => \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y'),
            'raw_date' => \Carbon\Carbon::parse($p->payment_date)->format('Y-m-d'),
            'amount' => (float) $p->amount,
            'direction' => $direction,
            'type_label' => $tipeLabel,
            'category_type' => $p->payment_type,
            'document_no' => $noDokumen,
            'party' => $pihakTerkait,
            'description' => $deskripsi ?: 'Transaksi Keuangan',
            'user' => $p->creator->name ?? 'Admin',
        ];
    }

    /**
     * Helper: Hitung ringkasan bulanan Kas & Bank via SQL agregasi (sangat cepat, hemat RAM).
     */
    protected function getMonthlyKasBankSummary($accounts, $monthStart, $monthEnd, array $targetPharmacyIds): array
    {
        $accountIds = $accounts->pluck('id')->all();
        $totalSaldo = (float) $accounts->sum('balance');

        $accountStats = [];
        foreach ($accounts as $acc) {
            $accountStats[$acc->id] = [
                'id' => $acc->id,
                'name' => $acc->name,
                'code' => $acc->code,
                'category' => $acc->category,
                'balance' => (float) $acc->balance,
                'account_number' => $acc->account_number,
                'inflow' => 0.0,
                'outflow' => 0.0,
                'net' => 0.0,
                'txCount' => 0,
                'sharePercent' => 0,
            ];
        }

        if (empty($accountIds)) {
            return [
                'totalSaldo' => 0,
                'totalMasuk' => 0,
                'totalKeluar' => 0,
                'netCashflow' => 0,
                'totalFlowVolume' => 0,
                'accountStats' => [],
            ];
        }

        // 1. Inflows: PIUTANG, PENDAPATAN, PENJUALAN TUNAI, PENJUALAN NON TUNAI
        $regularInflows = DB::table('finance_payments')
            ->select('account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('account_id')
            ->get();

        foreach ($regularInflows as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['inflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 2. Outflows: KREDIT, CASH, KONSINYASI, BIAYA, RETUR PENJUALAN
        $regularOutflows = DB::table('finance_payments')
            ->select('account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'RETUR PENJUALAN'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('account_id')
            ->get();

        foreach ($regularOutflows as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['outflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 3. Penyesuaian Saldo (Opname in / out)
        $adjustments = DB::table('finance_payments')
            ->select('account_id', 'reference_number', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->where('payment_type', 'PENYESUAIAN SALDO')
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('account_id', 'reference_number')
            ->get();

        foreach ($adjustments as $row) {
            if (isset($accountStats[$row->account_id])) {
                if ($row->reference_number === 'OPNAME-OUT') {
                    $accountStats[$row->account_id]['outflow'] += (float) $row->total_amount;
                } else {
                    $accountStats[$row->account_id]['inflow'] += (float) $row->total_amount;
                }
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 4. Saldo Awal count
        $openingCounts = DB::table('finance_payments')
            ->select('account_id', DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->where('payment_type', 'SALDO AWAL')
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('account_id')
            ->get();

        foreach ($openingCounts as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 5. Transfers: SETOR & TARIK TUNAI
        $transfersOut = DB::table('finance_payments')
            ->select('account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('account_id')
            ->get();

        foreach ($transfersOut as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['outflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        $transfersIn = DB::table('finance_payments')
            ->select('transfer_account_id as account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('transfer_account_id', $accountIds)
            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd])
            ->groupBy('transfer_account_id')
            ->get();

        foreach ($transfersIn as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['inflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        $totalMasuk = 0.0;
        $totalKeluar = 0.0;
        $statsList = array_values($accountStats);

        foreach ($statsList as &$st) {
            $st['net'] = $st['inflow'] - $st['outflow'];
            $totalMasuk += $st['inflow'];
            $totalKeluar += $st['outflow'];
        }
        unset($st);

        $totalFlowVolume = $totalMasuk + $totalKeluar;
        foreach ($statsList as &$st) {
            $accVolume = $st['inflow'] + $st['outflow'];
            $st['sharePercent'] = $totalFlowVolume > 0 ? (int) round(($accVolume / $totalFlowVolume) * 100) : 0;
        }
        unset($st);

        return [
            'totalSaldo' => $totalSaldo,
            'totalMasuk' => $totalMasuk,
            'totalKeluar' => $totalKeluar,
            'netCashflow' => $totalMasuk - $totalKeluar,
            'totalFlowVolume' => $totalFlowVolume,
            'accountStats' => $statsList,
        ];
    }

    /**
     * Helper: Ambil data mutasi Kas & Bank dengan Paginasi Server-Side.
     */
    protected function getPaginatedMutasiData(Request $request, $monthStart, $monthEnd, array $targetPharmacyIds)
    {
        $perPage = (int) $request->query('per_page', 25);
        if ($perPage < 5 || $perPage > 200) {
            $perPage = 25;
        }

        $filterAccountId = $request->query('account_id');
        if ($filterAccountId === 'all' || empty($filterAccountId)) {
            $filterAccountId = null;
        }

        $filterDirection = strtoupper(trim((string) $request->query('direction', 'all')));
        if (!in_array($filterDirection, ['ALL', 'IN', 'OUT'], true)) {
            $filterDirection = 'ALL';
        }

        $search = trim((string) $request->query('search', ''));

        $query = FinancePayment::with([
            'account',
            'expenseAccount',
            'incomeAccount',
            'transferAccount',
            'creator',
            'pharmacy',
            'receivingDetail.creditor',
            'transaction.debtors',
            'transaction.patients',
        ])
            ->whereHas('account', function ($q) {
                $q->where('category', 'Kas & Bank');
                $q->where('pharmacy_id', getActivePharmacyId());
            })
            ->where(function ($q) use ($targetPharmacyIds) {
                $q->whereHas('receiving', function ($sub) use ($targetPharmacyIds) {
                    $sub->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhereHas('transaction', function ($sub) use ($targetPharmacyIds) {
                    $sub->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub
                        ->where('payment_type', 'BIAYA')
                        ->where(function ($sub2) use ($targetPharmacyIds) {
                            $sub2
                                ->whereIn('pharmacy_id', $targetPharmacyIds)
                                ->orWhereNull('pharmacy_id');
                        });
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('payment_type', 'PENDAPATAN')->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('payment_type', 'SETOR')->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('payment_type', 'TARIK TUNAI')->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->whereIn('payment_type', ['PENJUALAN TUNAI', 'PENJUALAN NON TUNAI', 'RETUR PENJUALAN'])
                        ->whereIn('pharmacy_id', $targetPharmacyIds);
                })->orWhere('payment_type', 'SALDO AWAL')
                  ->orWhere('payment_type', 'PENYESUAIAN SALDO');
            });

        // Date range
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        $rangeStart = (!empty($startDate) && !empty($endDate)) ? $startDate : $monthStart;
        $rangeEnd = (!empty($startDate) && !empty($endDate)) ? $endDate : $monthEnd;

        if ($rangeStart && $rangeEnd) {
            $query->whereBetween('payment_date', [$rangeStart, $rangeEnd]);
        }

        // Account filter
        if ($filterAccountId) {
            $query->where(function ($q) use ($filterAccountId) {
                $q->where('account_id', $filterAccountId)
                  ->orWhere(function ($sub) use ($filterAccountId) {
                      $sub->where('transfer_account_id', $filterAccountId)
                          ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                  });
            });
        }

        // Direction filter
        if ($filterDirection === 'IN') {
            $query->where(function ($q) use ($filterAccountId) {
                $q->whereIn('payment_type', ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'])
                  ->orWhere(function ($sub) {
                      $sub->where('payment_type', 'PENYESUAIAN SALDO')
                          ->where('reference_number', '!=', 'OPNAME-OUT');
                  });
                if ($filterAccountId) {
                    $q->orWhere(function ($sub) use ($filterAccountId) {
                        $sub->where('transfer_account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
                }
            });
        } elseif ($filterDirection === 'OUT') {
            $query->where(function ($q) use ($filterAccountId) {
                $q->whereIn('payment_type', ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'RETUR PENJUALAN'])
                  ->orWhere(function ($sub) {
                      $sub->where('payment_type', 'PENYESUAIAN SALDO')
                          ->where('reference_number', '=', 'OPNAME-OUT');
                  });
                if ($filterAccountId) {
                    $q->orWhere(function ($sub) use ($filterAccountId) {
                        $sub->where('account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
                }
            });
        }

        // Search query
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%")
                  ->orWhere('recipient', 'like', "%{$search}%")
                  ->orWhereHas('transaction', function ($sub) use ($search) {
                      $sub->where('transaction_code', 'like', "%{$search}%")
                          ->orWhere('invoice_number', 'like', "%{$search}%")
                          ->orWhereHas('debtors', fn($d) => $d->where('name', 'like', "%{$search}%"))
                          ->orWhereHas('patients', fn($p) => $p->where('name', 'like', "%{$search}%"));
                  })
                  ->orWhereHas('receivingDetail', function ($sub) use ($search) {
                      $sub->where('invoice_number', 'like', "%{$search}%")
                          ->orWhereHas('creditor', fn($c) => $c->where('name', 'like', "%{$search}%"));
                  })
                  ->orWhereHas('account', function ($sub) use ($search) {
                      $sub->where('name', 'like', "%{$search}%")
                          ->orWhere('code', 'like', "%{$search}%");
                  });
            });
        }

        $query->orderByDesc('payment_date')->orderByDesc('id');

        $paginator = $query->paginate($perPage)->withQueryString();

        $paginator->getCollection()->transform(function ($payment) use ($filterAccountId) {
            return $this->formatMutasiRow($payment, $filterAccountId);
        });

        return $paginator;
    }

    /**
     * Helper: Ambil data biaya operasional.
     */
    protected function getBiayaData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        return FinancePayment::with(['account', 'expenseAccount', 'creator', 'pharmacy'])
            ->where('payment_type', 'BIAYA')
            ->where(function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->latest('payment_date')
            ->latest('id')
            ->take(500)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'reference_number' => $p->reference_number ?: ('BY-' . str_pad($p->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => $p->payment_date,
                    'formatted_date' => \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'account_id' => $p->account_id,
                    'account_name' => $p->account->name ?? 'Kas / Bank',
                    'account_code' => $p->account->code ?? '-',
                    'expense_account_id' => $p->expense_account_id,
                    'expense_account_name' => $p->expenseAccount->name ?? 'Biaya Operasional',
                    'expense_account_code' => $p->expenseAccount->code ?? '-',
                    'expense_category' => $p->expenseAccount->category ?? 'Beban Operasional',
                    'amount' => (float) $p->amount,
                    'recipient' => $p->recipient ?: '-',
                    'notes' => $p->notes ?: '',
                    'user_name' => $p->creator->name ?? 'Petugas',
                    'pharmacy_name' => $p->pharmacy->name ?? null,
                    'created_at' => $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-',
                ];
            });
    }

    /**
     * Helper: Ringkasan statistik modul biaya operasional.
     */
    protected function getBiayaSummary(array $targetPharmacyIds): array
    {
        $baseQuery = FinancePayment::where('payment_type', 'BIAYA')
            ->where(function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            });

        $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = \Carbon\Carbon::now()->endOfMonth()->toDateString();
        $today = \Carbon\Carbon::today()->toDateString();

        $totalAllTime = (float) (clone $baseQuery)->sum('amount');
        $totalMonth = (float) (clone $baseQuery)->whereBetween('payment_date', [$startOfMonth, $endOfMonth])->sum('amount');
        $totalToday = (float) (clone $baseQuery)->whereDate('payment_date', $today)->sum('amount');
        $countTotal = (int) (clone $baseQuery)->count();
        $countMonth = (int) (clone $baseQuery)->whereBetween('payment_date', [$startOfMonth, $endOfMonth])->count();

        // Breakdown top 5 kategori beban
        $topExpenses = (clone $baseQuery)
            ->select('expense_account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(id) as total_count'))
            ->with('expenseAccount:id,code,name')
            ->groupBy('expense_account_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'account_name' => $item->expenseAccount->name ?? 'Lain-lain',
                    'account_code' => $item->expenseAccount->code ?? '-',
                    'total_amount' => (float) $item->total_amount,
                    'total_count' => (int) $item->total_count,
                ];
            });

        return [
            'totalAllTime' => $totalAllTime,
            'totalMonth' => $totalMonth,
            'totalToday' => $totalToday,
            'countTotal' => $countTotal,
            'countMonth' => $countMonth,
            'topExpenses' => $topExpenses,
        ];
    }

    /**
     * Helper: Ambil data transaksi diskon off.
     */
    protected function getDiskonData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        return FinancePayment::with(['account', 'expenseAccount', 'creator', 'pharmacy'])
            ->where('payment_type', 'DISKON')
            ->where(function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->latest('payment_date')
            ->latest('id')
            ->take(500)
            ->get()
            ->map(function ($p) {
                return [
                    'id' => $p->id,
                    'reference_number' => $p->reference_number ?: ('DC-' . str_pad($p->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => $p->payment_date,
                    'formatted_date' => \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'account_id' => $p->account_id,
                    'account_name' => $p->account->name ?? 'Kas / Bank',
                    'account_code' => $p->account->code ?? '-',
                    'discount_account_id' => $p->expense_account_id,
                    'discount_account_name' => $p->expenseAccount->name ?? 'Diskon Off',
                    'discount_account_code' => $p->expenseAccount->code ?? '-',
                    'discount_category' => $p->expenseAccount->category ?? 'Diskon Lain-lain',
                    'amount' => (float) $p->amount,
                    'recipient' => $p->recipient ?: '-',
                    'notes' => $p->notes ?: '',
                    'user_name' => $p->creator->name ?? 'Petugas',
                    'pharmacy_name' => $p->pharmacy->name ?? null,
                    'created_at' => $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-',
                ];
            });
    }

    /**
     * Helper: Ringkasan statistik modul diskon off.
     */
    protected function getDiskonSummary(array $targetPharmacyIds): array
    {
        $baseQuery = FinancePayment::where('payment_type', 'DISKON')
            ->where(function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            });

        $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = \Carbon\Carbon::now()->endOfMonth()->toDateString();
        $today = \Carbon\Carbon::today()->toDateString();

        $totalAllTime = (float) (clone $baseQuery)->sum('amount');
        $totalMonth = (float) (clone $baseQuery)->whereBetween('payment_date', [$startOfMonth, $endOfMonth])->sum('amount');
        $totalToday = (float) (clone $baseQuery)->whereDate('payment_date', $today)->sum('amount');
        $countTotal = (int) (clone $baseQuery)->count();
        $countMonth = (int) (clone $baseQuery)->whereBetween('payment_date', [$startOfMonth, $endOfMonth])->count();

        // Breakdown top 5 jenis diskon
        $topDiscounts = (clone $baseQuery)
            ->select('expense_account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(id) as total_count'))
            ->with('expenseAccount:id,code,name')
            ->groupBy('expense_account_id')
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get()
            ->map(function ($item) {
                return [
                    'account_name' => $item->expenseAccount->name ?? 'Diskon Off',
                    'account_code' => $item->expenseAccount->code ?? '-',
                    'total_amount' => (float) $item->total_amount,
                    'total_count' => (int) $item->total_count,
                ];
            });

        return [
            'totalAllTime' => $totalAllTime,
            'totalMonth' => $totalMonth,
            'totalToday' => $totalToday,
            'countTotal' => $countTotal,
            'countMonth' => $countMonth,
            'topDiscounts' => $topDiscounts,
        ];
    }

    /**
     * Helper: Ambil data transaksi pendapatan lain-lain (termasuk Diskon Off / Diskon Lain-lain).
     */
    protected function getPendapatanData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();

        return FinancePayment::with(['account', 'incomeAccount', 'expenseAccount', 'creator', 'pharmacy'])
            ->whereIn('payment_type', ['PENDAPATAN', 'DISKON'])
            ->where(function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->latest('payment_date')
            ->latest('id')
            ->take(500)
            ->get()
            ->map(function ($p) {
                $isDiskon = $p->payment_type === 'DISKON' || ($p->incomeAccount?->category === 'Diskon Lain-lain');
                $targetIncomeAccount = $p->incomeAccount ?? $p->expenseAccount;
                $defaultPrefix = $isDiskon ? 'DC-' : 'PL-';

                return [
                    'id' => $p->id,
                    'payment_type' => $p->payment_type,
                    'is_diskon' => $isDiskon,
                    'reference_number' => $p->reference_number ?: ($defaultPrefix . str_pad($p->id, 5, '0', STR_PAD_LEFT)),
                    'payment_date' => $p->payment_date instanceof \Carbon\Carbon ? $p->payment_date->format('Y-m-d') : $p->payment_date,
                    'formatted_date' => \Carbon\Carbon::parse($p->payment_date)->format('d/m/Y'),
                    'account_id' => $isDiskon ? null : $p->account_id,
                    'account_name' => $isDiskon ? 'Tidak Masuk Kas/Bank' : ($p->account->name ?? 'Kas / Bank'),
                    'account_code' => $isDiskon ? '-' : ($p->account->code ?? '-'),
                    'income_account_id' => $p->income_account_id ?? $p->expense_account_id,
                    'income_account_name' => $targetIncomeAccount->name ?? ($isDiskon ? 'Diskon Off' : 'Pendapatan'),
                    'income_account_code' => $targetIncomeAccount->code ?? '-',
                    'income_category' => $targetIncomeAccount->category ?? ($isDiskon ? 'Diskon Lain-lain' : 'Pendapatan'),
                    'amount' => (float) $p->amount,
                    'recipient' => $p->recipient ?: '-',
                    'notes' => $p->notes ?: '',
                    'user_name' => $p->creator->name ?? 'Petugas',
                    'pharmacy_name' => $p->pharmacy->name ?? null,
                    'created_at' => $p->created_at ? $p->created_at->format('d/m/Y H:i') : '-',
                ];
            });
    }

    /**
     * Helper: Ringkasan statistik modul pendapatan lain-lain (termasuk Diskon Off).
     */
    protected function getPendapatanSummary(array $targetPharmacyIds): array
    {
        $baseQuery = FinancePayment::whereIn('payment_type', ['PENDAPATAN', 'DISKON'])
            ->where(function ($q) use ($targetPharmacyIds) {
                $q
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            });

        $startOfMonth = \Carbon\Carbon::now()->startOfMonth()->toDateString();
        $endOfMonth = \Carbon\Carbon::now()->endOfMonth()->toDateString();
        $today = \Carbon\Carbon::today()->toDateString();

        $totalAllTime = (float) (clone $baseQuery)->sum('amount');
        $totalMonth = (float) (clone $baseQuery)->whereBetween('payment_date', [$startOfMonth, $endOfMonth])->sum('amount');
        $totalToday = (float) (clone $baseQuery)->whereDate('payment_date', $today)->sum('amount');
        $countTotal = (int) (clone $baseQuery)->count();
        $countMonth = (int) (clone $baseQuery)->whereBetween('payment_date', [$startOfMonth, $endOfMonth])->count();

        // Breakdown top 5 jenis pendapatan (termasuk Diskon Off)
        $rawGroups = (clone $baseQuery)
            ->select(
                DB::raw('COALESCE(income_account_id, expense_account_id) as target_account_id'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('COUNT(id) as total_count')
            )
            ->groupBy(DB::raw('COALESCE(income_account_id, expense_account_id)'))
            ->orderByDesc('total_amount')
            ->limit(5)
            ->get();

        $accountIds = $rawGroups->pluck('target_account_id')->filter()->unique()->values()->all();
        $accountsById = FinanceAccount::whereIn('id', $accountIds)
            ->get(['id', 'code', 'name', 'category'])
            ->keyBy('id');

        $topIncomes = $rawGroups->map(function ($item) use ($accountsById) {
            $acc = $accountsById->get($item->target_account_id);
            return [
                'account_name' => $acc->name ?? 'Pendapatan Lain-lain',
                'account_code' => $acc->code ?? '-',
                'account_category' => $acc->category ?? 'Pendapatan',
                'total_amount' => (float) $item->total_amount,
                'total_count' => (int) $item->total_count,
            ];
        });

        return [
            'totalAllTime' => $totalAllTime,
            'totalMonth' => $totalMonth,
            'totalToday' => $totalToday,
            'countTotal' => $countTotal,
            'countMonth' => $countMonth,
            'topIncomes' => $topIncomes,
        ];
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
            'Diskon Lain-lain',
        ];
        $existingCategories = FinanceAccount::select('category')->distinct()->pluck('category')->toArray();
        return array_values(array_unique(array_merge($defaultCategories, $existingCategories)));
    }

    /**
     * Helper: Hitung metrik kartu Tipe Real-Time untuk halaman Ringkasan Keuangan.
     *
     * 1. Penjualan: 100% sesuai LIPH (melalui LiphTransactionScope & BankSalesExport::calculateTransactionAmount)
     * 2. Pembelian: 100% sesuai Laporan Pembelian Faktur (InvoiceSingleSheetExport::queryDailyInvoices) dikurangi Retur Pembelian
     * 3. Biaya: Data dari Finance > Biaya (payment_type = BIAYA)
     * 4. Pendapatan: Data dari Finance > Pendapatan Lain-lain (payment_type IN [PENDAPATAN, DISKON])
     * 5. Laba: Penjualan - Pembelian - Biaya + Pendapatan
     * 6. HPP %: (Pembelian Netto / Penjualan) * 100
     */
    protected function buildRealtimeSummaryMetrics(
        array $pharmacyIds,
        string $startDate,
        string $endDate,
        string $preset,
        string $periodLabel,
        ?int $selectedYear = null,
        ?int $selectedMonth = null
    ): array {
        if ($selectedYear === null || $selectedMonth === null) {
            $parsedDate = \Carbon\Carbon::parse($startDate);
            $selectedYear = (int) $parsedDate->year;
            $selectedMonth = (int) $parsedDate->month;
        }

        $kinerjaService = new KinerjaOmzetService();
        $kinerja = $kinerjaService->build($pharmacyIds, $startDate, $endDate);
        $days = $kinerja['days'] ?? [];

        $penjualanTotal = 0.0;
        $penjualanTunaiBank = 0.0;
        $penjualanKredit = 0.0;

        $pembelianBruto = 0.0;
        $pembelianPbf = 0.0;
        $pembelianCash = 0.0;
        $pembelianKonsinyasi = 0.0;
        $pembelianRetur = 0.0;

        $biayaTotal = 0.0;
        $pendapatanLainTotal = 0.0;
        $diskonOffTotal = 0.0;

        foreach ($days as $day) {
            $penjualanTotal += (float) ($day['sales']['total'] ?? 0);
            $penjualanTunaiBank += (float) ($day['sales']['cashBankTotal'] ?? 0);
            $penjualanKredit += (float) ($day['sales']['creditTotal'] ?? 0);

            $pbf = (float) ($day['purchases']['pbf'] ?? 0);
            $cash = (float) ($day['purchases']['cash'] ?? 0);
            $cons = (float) ($day['purchases']['consignment'] ?? 0);
            $ret = (float) ($day['purchases']['return'] ?? 0);

            $pembelianPbf += $pbf;
            $pembelianCash += $cash;
            $pembelianKonsinyasi += $cons;
            $pembelianBruto += ($pbf + $cash + $cons);
            $pembelianRetur += $ret;

            $biayaTotal += (float) ($day['expense'] ?? 0);
            $pendapatanLainTotal += (float) ($day['other_income'] ?? 0);
            $diskonOffTotal += (float) ($day['purchases']['discountOff'] ?? 0);
        }

        // Pembelian sesuai Laporan Pembelian Faktur dikurangi Retur Pembelian
        // (Diskon Off dipisahkan dan dihitung di dalam kartu Pendapatan)
        $pembelianNetto = $pembelianBruto - $pembelianRetur;

        // Pendapatan = Pendapatan Lain-lain + Diskon Off
        $pendapatanTotal = $pendapatanLainTotal + $diskonOffTotal;

        // Laba = Penjualan - Pembelian - Biaya + Pendapatan
        $labaTotal = $penjualanTotal - $pembelianNetto - $biayaTotal + $pendapatanTotal;

        $marginPersen = $penjualanTotal > 0
            ? round(($labaTotal / $penjualanTotal) * 100, 1)
            : 0.0;

        // Presentase HPP untuk periode yang dipilih
        $hppPersen = $penjualanTotal > 0
            ? round(($pembelianNetto / $penjualanTotal) * 100, 1)
            : 0.0;

        // Hitung Akumulasi (Year-To-Date dari awal tahun buku s/d periode yang dipilih)
        $penjualanAkumulasi = $penjualanTotal;
        $pembelianAkumulasi = $pembelianNetto;
        $biayaAkumulasi = $biayaTotal;
        $pendapatanAkumulasi = $pendapatanTotal;

        if ($selectedMonth > 1) {
            for ($m = 1; $m < $selectedMonth; $m++) {
                $cacheKey = 'finance_monthly_sum_' . implode('_', $pharmacyIds) . "_{$selectedYear}_{$m}";
                $pastTotals = Cache::remember($cacheKey, 86400, function () use ($pharmacyIds, $selectedYear, $m) {
                    $mStart = sprintf('%04d-%02d-01', $selectedYear, $m);
                    $mEnd = \Carbon\Carbon::createFromDate($selectedYear, $m, 1)->endOfMonth()->toDateString();
                    return $this->computePeriodTotals($pharmacyIds, $mStart, $mEnd);
                });

                $penjualanAkumulasi += (float) ($pastTotals['penjualan'] ?? 0);
                $pembelianAkumulasi += (float) ($pastTotals['pembelian'] ?? 0);
                $biayaAkumulasi += (float) ($pastTotals['biaya'] ?? 0);
                $pendapatanAkumulasi += (float) ($pastTotals['pendapatan'] ?? 0);
            }
        }

        $labaAkumulasi = $penjualanAkumulasi - $pembelianAkumulasi - $biayaAkumulasi + $pendapatanAkumulasi;
        $hppPersenAkumulasi = $penjualanAkumulasi > 0
            ? round(($pembelianAkumulasi / $penjualanAkumulasi) * 100, 1)
            : 0.0;

        return [
            'preset' => $preset,
            'startDate' => $startDate,
            'endDate' => $endDate,
            'periodLabel' => $periodLabel,
            'penjualan' => [
                'total' => round($penjualanTotal, 2),
                'tunaiBank' => round($penjualanTunaiBank, 2),
                'kredit' => round($penjualanKredit, 2),
                'akumulasi' => round($penjualanAkumulasi, 2),
            ],
            'pembelian' => [
                'total' => round($pembelianNetto, 2),
                'fakturBruto' => round($pembelianBruto, 2),
                'retur' => round($pembelianRetur, 2),
                'pbf' => round($pembelianPbf, 2),
                'cash' => round($pembelianCash, 2),
                'konsinyasi' => round($pembelianKonsinyasi, 2),
                'akumulasi' => round($pembelianAkumulasi, 2),
            ],
            'biaya' => [
                'total' => round($biayaTotal, 2),
                'akumulasi' => round($biayaAkumulasi, 2),
            ],
            'pendapatan' => [
                'total' => round($pendapatanTotal, 2),
                'pendapatanLain' => round($pendapatanLainTotal, 2),
                'diskonOff' => round($diskonOffTotal, 2),
                'akumulasi' => round($pendapatanAkumulasi, 2),
            ],
            'laba' => [
                'total' => round($labaTotal, 2),
                'marginPersen' => $marginPersen,
                'akumulasi' => round($labaAkumulasi, 2),
            ],
            'hpp' => [
                'persen' => $hppPersen,
                'total' => round($pembelianNetto, 2),
                'persenAkumulasi' => $hppPersenAkumulasi,
                'totalAkumulasi' => round($pembelianAkumulasi, 2),
            ],
        ];
    }

    /**
     * Helper: Hitung total periode ringkasan keuangan untuk akumulasi tahun buku.
     */
    protected function computePeriodTotals(array $pharmacyIds, string $startDate, string $endDate): array
    {
        $kinerjaService = new KinerjaOmzetService();
        $kinerja = $kinerjaService->build($pharmacyIds, $startDate, $endDate);
        $days = $kinerja['days'] ?? [];

        $penjualanTotal = 0.0;
        $pembelianBruto = 0.0;
        $pembelianRetur = 0.0;
        $biayaTotal = 0.0;
        $pendapatanLainTotal = 0.0;
        $diskonOffTotal = 0.0;

        foreach ($days as $day) {
            $penjualanTotal += (float) ($day['sales']['total'] ?? 0);
            $pbf = (float) ($day['purchases']['pbf'] ?? 0);
            $cash = (float) ($day['purchases']['cash'] ?? 0);
            $cons = (float) ($day['purchases']['consignment'] ?? 0);
            $ret = (float) ($day['purchases']['return'] ?? 0);

            $pembelianBruto += ($pbf + $cash + $cons);
            $pembelianRetur += $ret;

            $biayaTotal += (float) ($day['expense'] ?? 0);
            $pendapatanLainTotal += (float) ($day['other_income'] ?? 0);
            $diskonOffTotal += (float) ($day['purchases']['discountOff'] ?? 0);
        }

        $pembelianNetto = $pembelianBruto - $pembelianRetur;
        $pendapatanTotal = $pendapatanLainTotal + $diskonOffTotal;
        $labaTotal = $penjualanTotal - $pembelianNetto - $biayaTotal + $pendapatanTotal;

        return [
            'penjualan' => round($penjualanTotal, 2),
            'pembelian' => round($pembelianNetto, 2),
            'biaya' => round($biayaTotal, 2),
            'pendapatan' => round($pendapatanTotal, 2),
            'laba' => round($labaTotal, 2),
        ];
    }

    /**
     * Helper: Hitung nilai stok aktual.
     * Cabang lain: stok etalase (medicine_transfer_items)
     * PMI: stok etalase (medicine_transfer_items) + stok gudang (batches)
     * Formula: Nilai stok = (raw_price * 1.11) * stok
     */
    protected function getStockValuation(): array
    {
        $activePharmacyId = (int) getActivePharmacyId();
        $cacheKey = 'finance_stock_valuation_' . $activePharmacyId;

        return Cache::remember($cacheKey, 60, function () use ($activePharmacyId) {
            $isPmi = ($activePharmacyId <= 0 || $activePharmacyId === 1 || $activePharmacyId === 9);
            $counterPharmacyId = $isPmi ? 1 : $activePharmacyId;
            $warehouseId = function_exists('getWarehousePharmacyId') ? (int) getWarehousePharmacyId() : 9;

            // 1. Nilai Stok Etalase (medicine_transfer_items)
            $counterRow = DB::table('medicine_transfer_items as mti')
                ->join('batches as b', 'b.id', '=', 'mti.batches_id')
                ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->where('b.pharmacy_id', $counterPharmacyId)
                ->where('mti.status', 1)
                ->where('mti.qty', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('mti.source_type')->orWhere('mti.source_type', '!=', 'retur_gudang');
                })
                ->selectRaw('
                    COALESCE(SUM(mti.qty * CAST(REPLACE(COALESCE(NULLIF(m.raw_price, ""), NULLIF(m.pharmacy_net_price, ""), "0"), ",", ".") AS DECIMAL(15, 4)) * 1.11), 0) as total_val,
                    COALESCE(SUM(mti.qty), 0) as total_qty,
                    COUNT(DISTINCT b.medicine_id) as total_items
                ')
                ->first();

            $counterVal = (float) ($counterRow->total_val ?? 0);
            $counterQty = (float) ($counterRow->total_qty ?? 0);
            $counterItems = (int) ($counterRow->total_items ?? 0);

            // 2. Nilai Stok Gudang (hanya jika PMI)
            $storageVal = 0.0;
            $storageQty = 0.0;
            $storageItems = 0;

            if ($isPmi) {
                $storageRow = DB::table('batches as b')
                    ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                    ->where('b.pharmacy_id', $warehouseId)
                    ->whereRaw('CAST(REPLACE(COALESCE(NULLIF(b.stock, ""), "0"), ",", ".") AS DECIMAL(15, 2)) > 0')
                    ->selectRaw('
                        COALESCE(SUM(CAST(REPLACE(COALESCE(NULLIF(b.stock, ""), "0"), ",", ".") AS DECIMAL(15, 2)) * CAST(REPLACE(COALESCE(NULLIF(m.raw_price, ""), NULLIF(m.pharmacy_net_price, ""), "0"), ",", ".") AS DECIMAL(15, 4)) * 1.11), 0) as total_val,
                        COALESCE(SUM(CAST(REPLACE(COALESCE(NULLIF(b.stock, ""), "0"), ",", ".") AS DECIMAL(15, 2))), 0) as total_qty,
                        COUNT(DISTINCT b.medicine_id) as total_items
                    ')
                    ->first();

                $storageVal = (float) ($storageRow->total_val ?? 0);
                $storageQty = (float) ($storageRow->total_qty ?? 0);
                $storageItems = (int) ($storageRow->total_items ?? 0);
            }

            $totalVal = $counterVal + $storageVal;
            $totalQty = $counterQty + $storageQty;
            $totalItems = $isPmi ? ($counterItems + $storageItems) : $counterItems;

            return [
                'total' => round($totalVal, 2),
                'totalQty' => $totalQty,
                'totalItems' => $totalItems,
                'isPmi' => $isPmi,
                'etalase' => round($counterVal, 2),
                'etalaseQty' => $counterQty,
                'etalaseItems' => $counterItems,
                'gudang' => round($storageVal, 2),
                'gudangQty' => $storageQty,
                'gudangItems' => $storageItems,
            ];
        });
    }

    /**
     * Helper: Hitung ringkasan statistik modul finance.
     */
    protected function getStats($hutang = null, $cash = null, $piutang = null)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $cacheKey = 'finance_stats_summary_branch_' . getActivePharmacyId() . '_' . implode('_', $targetPharmacyIds);

        // Jika data lengkap tidak disediakan, gunakan cache agar angka badge dan ringkasan selalu konsisten di semua halaman
        $isFullData = ($hutang !== null && $cash !== null && $piutang !== null);
        if (!$isFullData) {
            $cached = Cache::get($cacheKey);
            if ($cached && isset($cached['nilaiStok']) && isset($cached['countCashbondActive'])) {
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

        $totalSaldoKasBank = (float) $this->branchKasBankAccountsQuery()->sum('balance');
        $accountsCount = $this->branchKasBankAccountsQuery()->count();

        $kasMasukPiutang = (float) FinancePayment::where('payment_type', 'PIUTANG')
            ->whereHas('transaction', function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds);
            })
            ->sum('amount');
        $kasMasukPenjualanTunai = (float) FinancePayment::where('payment_type', 'PENJUALAN TUNAI')
            ->where('pharmacy_id', getActivePharmacyId())
            ->sum('amount');
        $kasMasukPenjualanNonTunai = (float) FinancePayment::where('payment_type', 'PENJUALAN NON TUNAI')
            ->where('pharmacy_id', getActivePharmacyId())
            ->sum('amount');
        $kasMasukPendapatan = (float) FinancePayment::where('payment_type', 'PENDAPATAN')
            ->whereIn('pharmacy_id', $targetPharmacyIds)
            ->sum('amount');
        $kasMasuk = $kasMasukPiutang + $kasMasukPendapatan + $kasMasukPenjualanTunai + $kasMasukPenjualanNonTunai;

        $kasKeluarHutangCash = (float) FinancePayment::whereIn('payment_type', ['KREDIT', 'CASH', 'KONSINYASI'])
            ->whereHas('receiving', function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds);
            })
            ->sum('amount');

        $kasKeluarBiaya = (float) FinancePayment::where('payment_type', 'BIAYA')
            ->where(function ($sub) use ($targetPharmacyIds) {
                $sub
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->sum('amount');

        $kasKeluarReturJual = (float) FinancePayment::where('payment_type', 'RETUR PENJUALAN')
            ->whereIn('pharmacy_id', $targetPharmacyIds)
            ->sum('amount');

        $kasKeluar = $kasKeluarHutangCash + $kasKeluarBiaya + $kasKeluarReturJual;

        $netCashflow = $kasMasuk - $kasKeluar;

        // Hutang Stats
        $countHutangBelumLunas = 0;
        $countBelumBayar = 0;
        $countSebagian = 0;
        $countLunas = 0;
        $countDiajukan = 0;
        $totalHutangSisa = 0;

        foreach ($hutang as $h) {
            $totalHutangSisa += $h['sisa'];
            if (!empty($h['is_submitted']) && $h['status'] !== 'Lunas') {
                $countDiajukan++;
            }
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
        $countSudahPilihAkun = 0;
        $countBelumPilihAkun = 0;
        $totalNominalBelumPilihAkun = 0;
        foreach ($cash as $c) {
            if (!empty($c['akunPembayaran'])) {
                $countSudahPilihAkun++;
            } else {
                $countBelumPilihAkun++;
                $totalNominalBelumPilihAkun += (float) ($c['total'] ?? 0);
            }
        }

        // Piutang Stats
        $totalPiutangBruto = 0;
        $totalPiutangRetur = 0;
        $totalPiutangTerbayar = 0;
        $totalPiutangSisa = 0;
        $countPiutangBelumBayar = 0;
        $countPiutangDiajukan = 0;
        $countPiutangLunas = 0;
        $countPiutangDiretur = 0;

        foreach ($piutang as $p) {
            $totalPiutangBruto += (float) ($p['total'] ?? 0);
            $totalPiutangRetur += (float) ($p['total_retur'] ?? 0);
            $totalPiutangTerbayar += (float) ($p['terbayar'] ?? 0);
            $totalPiutangSisa += (float) ($p['sisa'] ?? 0);

            if (!empty($p['is_submitted']) && !in_array($p['status'], ['Lunas', 'Diretur Penuh'], true) && $p['sisa'] > 0.005) {
                $countPiutangDiajukan++;
            }
            if ($p['status'] === 'Diretur Penuh') {
                $countPiutangDiretur++;
            } elseif ($p['status'] === 'Lunas' || $p['status'] === 'Lunas (Ada Retur)' || $p['sisa'] <= 0.005) {
                $countPiutangLunas++;
            } else {
                $countPiutangBelumBayar++;
            }
        }

        $countBiayaTotal = (int) FinancePayment::where('payment_type', 'BIAYA')
            ->where(function ($sub) use ($targetPharmacyIds) {
                $sub
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->count();

        $countBiayaMonth = (int) FinancePayment::where('payment_type', 'BIAYA')
            ->where(function ($sub) use ($targetPharmacyIds) {
                $sub
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->whereBetween('payment_date', [
                \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                \Carbon\Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->count();

        $countDiskonTotal = (int) FinancePayment::where('payment_type', 'DISKON')
            ->where(function ($sub) use ($targetPharmacyIds) {
                $sub
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->count();

        $countDiskonMonth = (int) FinancePayment::where('payment_type', 'DISKON')
            ->where(function ($sub) use ($targetPharmacyIds) {
                $sub
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->orWhereNull('pharmacy_id');
            })
            ->whereBetween('payment_date', [
                \Carbon\Carbon::now()->startOfMonth()->toDateString(),
                \Carbon\Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->count();

        $nilaiStok = $this->getStockValuation();

        $cashbondQuery = FinanceCashbond::where('status', 'BELUM LUNAS')
            ->where(function ($q) use ($targetPharmacyIds) {
                $q->whereIn('pharmacy_id', $targetPharmacyIds);
                if (in_array(1, $targetPharmacyIds, true)) {
                    $q->orWhereNull('pharmacy_id');
                }
            });
        $countCashbondActive = (int) (clone $cashbondQuery)->count();
        $totalCashbondActive = (float) (clone $cashbondQuery)->sum('amount');

        $stats = [
            'totalSaldoKasBank' => $totalSaldoKasBank,
            'accountsCount' => $accountsCount,
            'totalNilaiStok' => $nilaiStok['total'],
            'nilaiStok' => $nilaiStok,
            'kasMasuk' => $kasMasuk,
            'kasKeluar' => $kasKeluar,
            'netCashflow' => $netCashflow,
            'totalHutangSisa' => $totalHutangSisa,
            'totalSisaHutang' => $totalHutangSisa,
            'countHutangBelumLunas' => $countHutangBelumLunas,
            'countBelumBayar' => $countBelumBayar,
            'countSebagian' => $countSebagian,
            'countDiajukan' => $countDiajukan,
            'countLunas' => $countLunas,
            'totalCashPurchases' => $totalCashPurchases,
            'totalCashSpent' => $totalCashPurchases,
            'totalCashCount' => $totalCashCount,
            'countSudahPilihAkun' => $countSudahPilihAkun,
            'countBelumPilihAkun' => $countBelumPilihAkun,
            'totalNominalBelumPilihAkun' => $totalNominalBelumPilihAkun,
            'totalPiutangBruto' => $totalPiutangBruto,
            'totalPiutangRetur' => $totalPiutangRetur,
            'totalPiutangTerbayar' => $totalPiutangTerbayar,
            'totalPiutangSisa' => $totalPiutangSisa,
            'totalSisaPiutang' => $totalPiutangSisa,
            'countPiutangBelumBayar' => $countPiutangBelumBayar,
            'countPiutangDiajukan' => $countPiutangDiajukan,
            'countPiutangLunas' => $countPiutangLunas,
            'countPiutangDiretur' => $countPiutangDiretur,
            'totalBiaya' => $kasKeluarBiaya,
            'countBiayaTotal' => $countBiayaTotal,
            'countBiayaMonth' => $countBiayaMonth,
            'countDiskonTotal' => $countDiskonTotal,
            'countDiskonMonth' => $countDiskonMonth,
            'countCashbondActive' => $countCashbondActive,
            'totalCashbondActive' => $totalCashbondActive,
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
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        Cache::forget('finance_stats_summary_branch_' . getActivePharmacyId() . '_' . implode('_', $targetPharmacyIds));
        $branches = [1, 2, 3, 4, 5, 6, 9];
        foreach ($branches as $b) {
            Cache::forget('finance_stats_summary_' . $b);
            Cache::forget('finance_stock_valuation_' . $b);
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
        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);

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
        $totalInvoice = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
        $totalRetur = $this->getReceivingDetailReturTotal($detail);
        $netInvoice = round(max(0, $totalInvoice - $totalRetur), 2);

        // Akumulasi yang sudah terbayar
        $alreadyPaid = (float) FinancePayment::where('receiving_detail_id', $detail->id)
            ->where('payment_type', '!=', 'RETUR')
            ->sum('amount');
        $sisaHutang = round(max(0, $netInvoice - $alreadyPaid), 2);

        if (round($validated['amount'], 2) > $sisaHutang) {
            $formattedSisa = fmod($sisaHutang, 1) !== 0.0 ? number_format($sisaHutang, 2, ',', '.') : number_format($sisaHutang, 0, ',', '.');
            return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan (Rp ' . $formattedSisa . ').']);
        }

        DB::transaction(function () use ($validated, $detail, $account, $netInvoice) {
            // 1. Simpan Payment
            FinancePayment::create([
                'pharmacy_id' => $detail->receiving?->pharmacy_id,
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
            $totalTerbayar = (float) FinancePayment::where('receiving_detail_id', $detail->id)
                ->where('payment_type', '!=', 'RETUR')
                ->sum('amount');

            // 4. Update status receiving jika sudah lunas
            if ((round($totalTerbayar, 2) >= round($netInvoice, 2) || ($netInvoice - $totalTerbayar) < 0.005) && $detail->receiving) {
                $detail->receiving->update(['status' => 3]);
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Pembayaran hutang dagang berhasil dicatat.');
    }

    /**
     * Bayar kewajiban konsinyasi yang sudah terbentuk dari penjualan bersih.
     */
    public function storeConsignmentPayment(Request $request, ConsignmentService $consignmentService)
    {
        $validated = $request->validate([
            'receiving_detail_id' => 'required|exists:receiving_details,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $detail = ReceivingDetails::with('receiving')->findOrFail($validated['receiving_detail_id']);
        abort_unless(in_array((int) $detail->receiving?->pharmacy_id, $this->getTargetPharmacyIds(), true), 403);

        if (strtoupper((string) $detail->invoice_payment) !== 'KONSINYASI') {
            return back()->withErrors(['receiving_detail_id' => 'Faktur ini bukan pembelian konsinyasi.']);
        }

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $amount = round((float) $validated['amount'], 2);

        DB::transaction(function () use ($validated, $detail, $account, $amount, $consignmentService): void {
            ReceivingDetails::whereKey($detail->id)->lockForUpdate()->firstOrFail();
            $lockedAccount = FinanceAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $consignments = $consignmentService->getData([(int) $detail->receiving->pharmacy_id]);
            $consignment = collect($consignments)->firstWhere('id', $detail->id);
            $due = round((float) ($consignment['due'] ?? 0), 2);

            if ($amount > $due || $due <= 0) {
                throw ValidationException::withMessages([
                    'amount' => 'Nominal pembayaran tidak boleh melebihi nilai barang terjual yang belum dibayar (Rp ' . number_format($due, 0, ',', '.') . ').',
                ]);
            }

            FinancePayment::create([
                'pharmacy_id' => $detail->receiving->pharmacy_id,
                'receiving_id' => $detail->receiving_id,
                'receiving_detail_id' => $detail->id,
                'account_id' => $lockedAccount->id,
                'payment_type' => 'KONSINYASI',
                'payment_date' => $validated['payment_date'],
                'amount' => $amount,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? 'Pembayaran barang konsinyasi yang telah terjual',
                'created_by' => Auth::id(),
            ]);

            $lockedAccount->decrement('balance', $amount);
        });

        $this->clearStatsCache();

        return back()->with('success', 'Pembayaran konsinyasi berhasil dicatat sesuai nilai barang yang telah terjual.');
    }

    /**
     * Pelunasan massal seluruh sisa kewajiban konsinyasi pada faktur terpilih.
     */
    public function storeBulkConsignmentPayments(Request $request, ConsignmentService $consignmentService)
    {
        $validated = $request->validate([
            'receiving_detail_ids' => 'required|array|min:1',
            'receiving_detail_ids.*' => 'required|integer|distinct|exists:receiving_details,id',
            'account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);
        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank' || !$account->is_active) {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus akun Kas & Bank yang aktif.']);
        }

        $pharmacyIds = $this->getTargetPharmacyIds();
        $totalPaid = 0.0;
        DB::transaction(function () use ($validated, $account, $pharmacyIds, $consignmentService, &$totalPaid): void {
            $lockedAccount = FinanceAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
            $details = ReceivingDetails::with('receiving')
                ->whereIn('id', $validated['receiving_detail_ids'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($details->count() !== count($validated['receiving_detail_ids'])) {
                throw ValidationException::withMessages(['receiving_detail_ids' => 'Sebagian faktur tidak dapat ditemukan.']);
            }
            foreach ($details as $detail) {
                if ($detail->invoice_payment !== 'KONSINYASI' ||
                        !in_array((int) $detail->receiving?->pharmacy_id, $pharmacyIds, true)) {
                    throw ValidationException::withMessages(['receiving_detail_ids' => "Faktur {$detail->invoice_number} bukan faktur konsinyasi yang dapat diakses."]);
                }
            }

            $consignments = collect($consignmentService->getData($pharmacyIds))->keyBy('id');
            foreach ($details as $detail) {
                $due = round((float) ($consignments->get($detail->id)['due'] ?? 0), 2);
                if ($due <= 0) {
                    throw ValidationException::withMessages(['receiving_detail_ids' => "Faktur {$detail->invoice_number} tidak memiliki sisa kewajiban konsinyasi."]);
                }
                FinancePayment::create([
                    'pharmacy_id' => $detail->receiving?->pharmacy_id,
                    'receiving_id' => $detail->receiving_id,
                    'receiving_detail_id' => $detail->id,
                    'account_id' => $lockedAccount->id,
                    'payment_type' => 'KONSINYASI',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $due,
                    'reference_number' => $validated['reference_number'] ?? null,
                    'notes' => $validated['notes'] ?: 'Pelunasan massal konsinyasi barang terjual',
                    'created_by' => Auth::id(),
                ]);
                $totalPaid += $due;
            }
            $lockedAccount->decrement('balance', $totalPaid);
        });

        $this->clearStatsCache();
        return back()->with('success', 'Berhasil melunasi ' . count($validated['receiving_detail_ids']) . ' faktur konsinyasi sebesar Rp ' . number_format($totalPaid, 0, ',', '.') . '.');
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
        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);

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
            $totalInvoice = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];

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
                    'pharmacy_id' => $detail->receiving?->pharmacy_id,
                    'account_id' => $account->id,
                    'payment_date' => $validated['payment_date'] ?? now()->toDateString(),
                    'amount' => $totalInvoice,
                    'reference_number' => $validated['reference_number'] ?? $existingPayment->reference_number,
                    'notes' => $validated['notes'] ?? $existingPayment->notes,
                    'created_by' => Auth::id(),
                ]);
            } else {
                FinancePayment::create([
                    'pharmacy_id' => $detail->receiving?->pharmacy_id,
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

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $totalPaidAll = 0;
        $processedCount = 0;

        DB::transaction(function () use ($validated, $account, &$totalPaidAll, &$processedCount) {
            foreach ($validated['receiving_detail_ids'] as $detailId) {
                $detail = ReceivingDetails::with(['receiving_items', 'payments', 'receiving'])->find($detailId);
                if (!$detail || $detail->invoice_payment !== 'KREDIT')
                    continue;

                // Hitung total invoice
                $subtotal = 0;
                foreach ($detail->receiving_items as $item) {
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $totalItem = (float) ($item->total ?? ($qty * $harga));
                    $subtotal += $totalItem;
                }
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $totalInvoice = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
                $totalRetur = $this->getReceivingDetailReturTotal($detail);
                $netInvoice = round(max(0, $totalInvoice - $totalRetur), 2);

                // Hitung sisa yang belum terbayar
                $alreadyPaid = (float) FinancePayment::where('receiving_detail_id', $detail->id)
                    ->where('payment_type', '!=', 'RETUR')
                    ->sum('amount');
                $remaining = round(max(0, $netInvoice - $alreadyPaid), 2);

                if ($remaining < 0.005) {
                    if ($detail->receiving && (int) $detail->receiving->status !== 3) {
                        $detail->receiving->update(['status' => 3]);
                    }
                    continue;
                }

                FinancePayment::create([
                    'pharmacy_id' => $detail->receiving?->pharmacy_id,
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
     * Import pelunasan hutang dari spreadsheet dengan kolom faktur, tanggal bayar, dan kode bank.
     */
    public function importHutangPayments(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
        ]);

        try {
            $file = $request->file('file');
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
                $firstLine = (string) strtok((string) file_get_contents($file->getRealPath()), "\n");
                $delimiters = [',', ';', "\t"];
                usort($delimiters, fn($a, $b) => substr_count($firstLine, $b) <=> substr_count($firstLine, $a));
                $reader->setDelimiter($delimiters[0]);
            }
            $sheet = $reader->load($file->getRealPath())->getActiveSheet();
            $rows = $sheet->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca. Gunakan template impor Hutang Dagang.']);
        }

        if (count($rows) < 2) {
            throw ValidationException::withMessages(['file' => 'File belum berisi baris data.']);
        }

        // Format tetap: kolom A = No. Faktur, B = Tanggal Bayar, C = Kode Bank.
        // Lewati baris pertama sebagai header tanpa memeriksa teksnya.
        $headerRowIndex = 0;
        $invoiceColumn = 0;
        $dateColumn = 1;
        $bankColumn = 2;
        $rows = array_slice($rows, 1);

        $accountsByCode = $this
            ->branchKasBankAccountsQuery()
            ->get()
            ->keyBy(fn($account) => mb_strtolower(trim((string) $account->code)));
        $pharmacyIds = $this->getTargetPharmacyIds();
        $prepared = [];
        $errors = [];
        $seenInvoices = [];

        foreach ($rows as $index => $row) {
            $line = $headerRowIndex + $index + 2;
            $invoiceNumber = trim((string) ($row[$invoiceColumn] ?? ''));
            $bankCode = trim((string) ($row[$bankColumn] ?? ''));
            $rawDate = $row[$dateColumn] ?? null;
            if ($invoiceNumber === '' && $bankCode === '' && ($rawDate === null || $rawDate === ''))
                continue;

            if ($invoiceNumber === '')
                $errors[] = "Baris {$line}: No. Faktur kosong.";
            if ($bankCode === '')
                $errors[] = "Baris {$line}: Kode Bank kosong.";
            if ($invoiceNumber !== '' && isset($seenInvoices[mb_strtolower($invoiceNumber)])) {
                $errors[] = "Baris {$line}: No. Faktur {$invoiceNumber} tercantum lebih dari sekali.";
            }
            if ($invoiceNumber !== '')
                $seenInvoices[mb_strtolower($invoiceNumber)] = true;

            $account = $accountsByCode->get(mb_strtolower($bankCode));
            if (!$account && $bankCode !== '')
                $errors[] = "Baris {$line}: Kode Bank '{$bankCode}' tidak ditemukan atau tidak aktif.";

            try {
                if (is_numeric($rawDate) && (float) $rawDate > 20000) {
                    $paymentDate = ExcelDate::excelToDateTimeObject((float) $rawDate)->format('Y-m-d');
                } else {
                    $dateText = trim((string) $rawDate);
                    $paymentDate = \Carbon\Carbon::createFromFormat('!Y-m-d', $dateText)->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                try {
                    $paymentDate = \Carbon\Carbon::createFromFormat('!d/m/Y', trim((string) $rawDate))->format('Y-m-d');
                } catch (\Throwable $ignored) {
                    $paymentDate = null;
                }
            }
            if (!$paymentDate)
                $errors[] = "Baris {$line}: Tanggal Bayar harus format YYYY-MM-DD atau DD/MM/YYYY.";

            $matches = ReceivingDetails::with(['receiving_items.order_items', 'receiving_items.order_items.medicines', 'receiving', 'creditor'])
                ->where('invoice_payment', 'KREDIT')
                ->where('invoice_number', $invoiceNumber)
                ->whereHas('receiving', fn($query) => $query->whereIn('pharmacy_id', $pharmacyIds)->whereIn('status', [1, 2, 3]))
                ->get();
            if ($invoiceNumber !== '' && $matches->count() === 0) {
                $errors[] = "Baris {$line}: Faktur '{$invoiceNumber}' tidak ditemukan pada cabang yang dapat diakses.";
            } elseif ($matches->count() > 1) {
                $errors[] = "Baris {$line}: Faktur '{$invoiceNumber}' cocok ke lebih dari satu data. Periksa nomor faktur.";
            }

            if ($matches->count() === 1 && $account && $paymentDate) {
                $prepared[] = ['line' => $line, 'detail' => $matches->first(), 'account' => $account, 'payment_date' => $paymentDate];
            }
        }

        if (!$prepared && !$errors)
            $errors[] = 'Tidak ada baris data untuk diimpor.';
        if ($errors)
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);

        $totalPaid = 0.0;
        DB::transaction(function () use ($prepared, &$totalPaid) {
            foreach ($prepared as $entry) {
                $detail = ReceivingDetails::whereKey($entry['detail']->id)->lockForUpdate()->firstOrFail();
                $account = FinanceAccount::whereKey($entry['account']->id)->lockForUpdate()->firstOrFail();
                $detail->load(['receiving_items.order_items', 'creditor', 'receiving']);
                $subtotal = 0.0;
                foreach ($detail->receiving_items as $item) {
                    $qty = (float) ($item->qty_received ?? 0);
                    $price = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $subtotal += (float) ($item->total ?? ($qty * $price));
                }
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $invoiceTotal = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];
                $totalRetur = $this->getReceivingDetailReturTotal($detail);
                $netInvoice = round(max(0, $invoiceTotal - $totalRetur), 2);
                $alreadyPaid = (float) FinancePayment::where('receiving_detail_id', $detail->id)
                    ->where('payment_type', '!=', 'RETUR')
                    ->sum('amount');
                $remaining = round(max(0, $netInvoice - $alreadyPaid), 2);
                if ($remaining < 0.005) {
                    throw ValidationException::withMessages(['file' => "Baris {$entry['line']}: Faktur '{$detail->invoice_number}' sudah lunas."]);
                }

                FinancePayment::create([
                    'pharmacy_id' => $detail->receiving?->pharmacy_id,
                    'receiving_id' => $detail->receiving_id,
                    'receiving_detail_id' => $detail->id,
                    'account_id' => $account->id,
                    'payment_type' => 'KREDIT',
                    'payment_date' => $entry['payment_date'],
                    'amount' => $remaining,
                    'reference_number' => null,
                    'notes' => 'Pelunasan Hutang Dagang melalui impor file',
                    'created_by' => Auth::id(),
                ]);
                $account->decrement('balance', $remaining);
                $totalPaid += $remaining;
                if ($detail->receiving)
                    $detail->receiving->update(['status' => 3]);
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Impor berhasil: ' . count($prepared) . ' faktur dilunasi dengan total Rp ' . number_format($totalPaid, 0, ',', '.') . '.');
    }

    public function downloadHutangPaymentTemplate()
    {
        return response("No. Faktur,Tanggal Bayar,Kode Bank\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=template-impor-hutang-dagang.csv',
        ]);
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

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $processedCount = 0;
        $paymentDate = $validated['payment_date'] ?? now()->toDateString();

        DB::transaction(function () use ($validated, $account, $paymentDate, &$processedCount) {
            foreach ($validated['receiving_detail_ids'] as $detailId) {
                $detail = ReceivingDetails::with(['receiving_items', 'receiving'])->find($detailId);
                if (!$detail || $detail->invoice_payment !== 'TUNAI')
                    continue;

                $subtotal = 0;
                foreach ($detail->receiving_items as $item) {
                    $qty = (float) ($item->qty_received ?? 0);
                    $harga = (float) ($item->raw_price ?? ($item->order_items->price ?? 0));
                    $totalItem = (float) ($item->total ?? ($qty * $harga));
                    $subtotal += $totalItem;
                }
                $ppnType = strtoupper(trim($detail->invoice_ppn ?? $detail->creditor?->ppn_type ?? 'TANPA'));
                $totalInvoice = ReceivingInvoiceTotals::calculate($detail->receiving_items, $ppnType)['total'];

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
                        'pharmacy_id' => $detail->receiving?->pharmacy_id,
                        'account_id' => $account->id,
                        'payment_date' => $paymentDate,
                        'amount' => $totalInvoice,
                        'reference_number' => $validated['reference_number'] ?? $existingPayment->reference_number,
                        'notes' => $validated['notes'] ?? $existingPayment->notes,
                        'created_by' => Auth::id(),
                    ]);
                } else {
                    FinancePayment::create([
                        'pharmacy_id' => $detail->receiving?->pharmacy_id,
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
            'Diskon Lain-lain' => '8-800',
        ];

        $prefix = $prefixMap[$category] ?? null;

        if (!$prefix) {
            $lastInSameCat = FinanceAccount::where('category', $category)
                ->when($category === 'Kas & Bank', fn($query) => $query->where('pharmacy_id', getActivePharmacyId()))
                ->orderBy('code', 'desc')
                ->first();
            if ($lastInSameCat && preg_match('/^(.+?)(\d+)$/', $lastInSameCat->code, $matches)) {
                $base = $matches[1];
                $num = (int) $matches[2] + 1;
                return $base . str_pad($num, strlen($matches[2]), '0', STR_PAD_LEFT);
            }
            $prefix = '9-900';
        }

        $existingCodes = FinanceAccount::where('code', 'LIKE', $prefix . '%')
            ->when($category === 'Kas & Bank', fn($query) => $query->where('pharmacy_id', getActivePharmacyId()))
            ->pluck('code')
            ->toArray();

        if (empty($existingCodes)) {
            return $prefix . '01';
        }

        $maxNum = 0;
        foreach ($existingCodes as $c) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $c, $m)) {
                $n = (int) $m[1];
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
            'code' => 'nullable|string|max:50',
            'category' => 'required|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'account_type' => 'nullable|in:kas_apotek,bank',
        ]);

        $isCashBank = $validated['category'] === 'Kas & Bank';
        $pharmacyId = $isCashBank ? getActivePharmacyId() : null;
        // Kolom account_type wajib terisi di database. Untuk kategori selain
        // Kas & Bank nilainya tidak dipakai, tetapi tetap gunakan default bank.
        $accountType = $isCashBank ? ($validated['account_type'] ?? 'bank') : 'bank';

        // Jika kode tidak diisi, otomatis buat kode melanjutkan nomor sebelumnya
        $code = $accountType === 'kas_apotek' ? '1-10001' : (!empty($validated['code'])
            ? trim($validated['code'])
            : $this->generateNextAccountCode($validated['category']));

        $duplicateCode = FinanceAccount::where('code', $code)
            ->when($isCashBank, fn($query) => $query->where('pharmacy_id', $pharmacyId), fn($query) => $query->whereNull('pharmacy_id'))
            ->exists();
        if ($duplicateCode) {
            return redirect()
                ->route('finance.accounts')
                ->withErrors(['code' => 'Kode akun sudah digunakan pada cabang ini.'])
                ->withInput();
        }
        if ($accountType === 'kas_apotek' && $this->branchKasBankAccountsQuery()->where('account_type', 'kas_apotek')->exists()) {
            return redirect()
                ->route('finance.accounts')
                ->withErrors(['account_type' => 'Cabang ini sudah memiliki akun Kas Apotek.'])
                ->withInput();
        }

        FinanceAccount::create([
            'pharmacy_id' => $pharmacyId,
            'name' => $validated['name'],
            'name_en' => !empty($validated['name_en']) ? $validated['name_en'] : $validated['name'],
            'code' => $code,
            'category' => $validated['category'],
            'account_type' => $accountType,
            'account_number' => $isCashBank && $accountType === 'bank' ? ($validated['account_number'] ?? null) : null,
            'balance' => 0,
            'is_active' => true,
        ]);

        $this->clearStatsCache();
        return redirect()
            ->route('finance.accounts')
            ->with('success', "Akun baru '{$validated['name']}' dengan kode {$code} berhasil ditambahkan.");
    }

    /**
     * Perbarui data akun yang ada.
     */
    public function updateAccount(Request $request, $id)
    {
        $account = FinanceAccount::where(function ($query) {
            $query->whereNull('pharmacy_id')->orWhere('pharmacy_id', getActivePharmacyId());
        })->findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'name_en' => 'nullable|string|max:255',
            'code' => 'required|string|max:50',
            'category' => 'required|string|max:100',
            'account_number' => 'nullable|string|max:100',
            'account_type' => 'nullable|in:kas_apotek,bank',
        ]);

        $isCashBank = $validated['category'] === 'Kas & Bank';
        $pharmacyId = $isCashBank ? getActivePharmacyId() : null;
        $accountType = $isCashBank ? ($validated['account_type'] ?? $account->account_type ?? 'bank') : 'bank';
        if ($account->account_type === 'kas_apotek' && $accountType !== 'kas_apotek') {
            return back()->withErrors(['account_type' => 'Kategori akun Kas Apotek tidak dapat diubah karena dipakai untuk pencatatan otomatis penjualan tunai.']);
        }
        $code = $accountType === 'kas_apotek' ? '1-10001' : trim($validated['code']);
        $duplicateCode = FinanceAccount::where('code', $code)
            ->when($isCashBank, fn($query) => $query->where('pharmacy_id', $pharmacyId), fn($query) => $query->whereNull('pharmacy_id'))
            ->where('id', '!=', $account->id)
            ->exists();
        if ($duplicateCode)
            return back()->withErrors(['code' => 'Kode akun sudah digunakan pada cabang ini.']);
        if ($accountType === 'kas_apotek' && $this->branchKasBankAccountsQuery()->where('account_type', 'kas_apotek')->where('id', '!=', $account->id)->exists()) {
            return back()->withErrors(['account_type' => 'Cabang ini sudah memiliki akun Kas Apotek.']);
        }

        $account->update([
            'name' => $validated['name'],
            'name_en' => !empty($validated['name_en']) ? $validated['name_en'] : $validated['name'],
            'code' => $code,
            'category' => $validated['category'],
            'pharmacy_id' => $pharmacyId,
            'account_type' => $accountType,
            'account_number' => $isCashBank && $accountType === 'bank' ? ($validated['account_number'] ?? null) : null,
        ]);

        $this->clearStatsCache();
        return back()->with('success', "Akun '{$account->name}' berhasil diperbarui.");
    }

    /**
     * Hapus akun keuangan jika belum ada transaksi pembayaran terkait.
     */
    public function destroyAccount($id)
    {
        $account = FinanceAccount::where(function ($query) {
            $query->whereNull('pharmacy_id')->orWhere('pharmacy_id', getActivePharmacyId());
        })->where(function ($query) {
            $query->where('category', '!=', 'Kas & Bank')->orWhereNotNull('pharmacy_id');
        })->findOrFail($id);

        if ($account->account_type === 'kas_apotek') {
            return back()->withErrors(['account' => 'Akun Kas Apotek wajib tersedia agar penjualan tunai cabang tercatat otomatis.']);
        }

        $paymentsCount = FinancePayment::where('account_id', $account->id)
            ->orWhere('transfer_account_id', $account->id)
            ->count();
        if ($paymentsCount > 0) {
            return back()->withErrors([
                'account' => "Akun '{$account->name}' tidak dapat dihapus karena sudah memiliki riwayat {$paymentsCount} transaksi pembayaran."
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

        $tx = MedicineTransactions::with(['payments', 'returs'])->findOrFail($validated['medicine_transaction_id']);
        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);

        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
        }

        $totalRetur = (float) $tx->returs->sum('total_retur');
        $totalTerbayarExisting = (float) $tx->payments->sum('amount');
        $totalNominal = (float) $tx->subtotal;
        $netNominal = max(0, $totalNominal - $totalRetur);
        $sisa = round(max(0, $netNominal - $totalTerbayarExisting), 2);

        if (round($validated['amount'], 2) > $sisa) {
            $formattedSisa = fmod($sisa, 1) !== 0.0 ? number_format($sisa, 2, ',', '.') : number_format($sisa, 0, ',', '.');
            return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan piutang (Rp ' . $formattedSisa . ').']);
        }

        DB::beginTransaction();
        try {
            FinancePayment::create([
                'pharmacy_id' => $tx->pharmacy_id,
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

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun kas/bank yang dipilih harus berkategori Kas & Bank.']);
        }

        $transactions = MedicineTransactions::with(['payments', 'returs'])
            ->whereIn('id', $validated['transaction_ids'])
            ->get();

        DB::beginTransaction();
        try {
            $processedCount = 0;
            foreach ($transactions as $tx) {
                $totalRetur = (float) $tx->returs->sum('total_retur');
                $totalPaid = (float) $tx->payments->sum('amount');
                $totalNominal = (float) $tx->subtotal;
                $netNominal = max(0, $totalNominal - $totalRetur);
                $sisa = round(max(0, $netNominal - $totalPaid), 2);

                if ($sisa < 0.005)
                    continue;

                FinancePayment::create([
                    'pharmacy_id' => $tx->pharmacy_id,
                    'medicine_transaction_id' => $tx->id,
                    'account_id' => $account->id,
                    'payment_type' => 'PIUTANG',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $sisa,
                    'reference_number' => $validated['reference_number'] ?: ('BULK-PIUTANG-' . date('YmdHis')),
                    'notes' => $validated['notes'] ?: 'Pelunasan massal piutang penjualan',
                    'created_by' => Auth::id(),
                ]);

                $tx->paid = (string) round($totalPaid + $sisa, 2);
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
     * Impor pelunasan piutang dari file dengan kolom A-C dan baris pertama sebagai header.
     */
    public function importPiutangPayments(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240']);

        try {
            $file = $request->file('file');
            $reader = IOFactory::createReaderForFile($file->getRealPath());
            if ($reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Csv) {
                $firstLine = (string) strtok((string) file_get_contents($file->getRealPath()), "\n");
                $delimiters = [',', ';', "\t"];
                usort($delimiters, fn($a, $b) => substr_count($firstLine, $b) <=> substr_count($firstLine, $a));
                $reader->setDelimiter($delimiters[0]);
            }
            $rows = $reader->load($file->getRealPath())->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages(['file' => 'File tidak dapat dibaca. Gunakan format kolom impor Piutang.']);
        }

        // Baris pertama selalu dilewati. Kolom A = No. Faktur/Transaksi, B = Tanggal Bayar, C = Kode Bank.
        $rows = array_slice($rows, 1);
        $accountsByCode = $this
            ->branchKasBankAccountsQuery()
            ->get()
            ->keyBy(fn($account) => mb_strtolower(trim((string) $account->code)));
        $pharmacyIds = $this->getTargetPharmacyIds();
        $prepared = [];
        $errors = [];
        $seenInvoices = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $invoiceNumber = trim((string) ($row[0] ?? ''));
            $rawDate = $row[1] ?? null;
            $bankCode = trim((string) ($row[2] ?? ''));
            if ($invoiceNumber === '' && ($rawDate === null || $rawDate === '') && $bankCode === '')
                continue;

            if ($invoiceNumber === '')
                $errors[] = "Baris {$line}: No. Faktur/Transaksi kosong.";
            if ($bankCode === '')
                $errors[] = "Baris {$line}: Kode Bank kosong.";
            $invoiceKey = mb_strtolower($invoiceNumber);
            if ($invoiceNumber !== '' && isset($seenInvoices[$invoiceKey]))
                $errors[] = "Baris {$line}: No. Faktur/Transaksi '{$invoiceNumber}' tercantum lebih dari sekali.";
            if ($invoiceNumber !== '')
                $seenInvoices[$invoiceKey] = true;

            $account = $accountsByCode->get(mb_strtolower($bankCode));
            if (!$account && $bankCode !== '')
                $errors[] = "Baris {$line}: Kode Bank '{$bankCode}' tidak ditemukan atau tidak aktif.";

            $paymentDate = null;
            try {
                if (is_numeric($rawDate) && (float) $rawDate > 20000) {
                    $paymentDate = ExcelDate::excelToDateTimeObject((float) $rawDate)->format('Y-m-d');
                } else {
                    $paymentDate = \Carbon\Carbon::createFromFormat('!Y-m-d', trim((string) $rawDate))->format('Y-m-d');
                }
            } catch (\Throwable $e) {
                try {
                    $paymentDate = \Carbon\Carbon::createFromFormat('!d/m/Y', trim((string) $rawDate))->format('Y-m-d');
                } catch (\Throwable $ignored) {
                    $paymentDate = null;
                }
            }
            if (!$paymentDate)
                $errors[] = "Baris {$line}: Tanggal Bayar harus format YYYY-MM-DD atau DD/MM/YYYY.";

            $matches = MedicineTransactions::where('transaction_type', 'KREDIT')
                ->where('status', 1)
                ->where('transaction_code', $invoiceNumber)
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->with('transactions')
                ->get();
            if ($invoiceNumber !== '' && $matches->isEmpty()) {
                $errors[] = "Baris {$line}: Faktur/Transaksi '{$invoiceNumber}' tidak ditemukan pada cabang yang dapat diakses.";
            } elseif ($matches->count() > 1) {
                $errors[] = "Baris {$line}: Nomor '{$invoiceNumber}' cocok ke lebih dari satu transaksi.";
            }

            if ($matches->count() === 1 && $account && $paymentDate) {
                $prepared[] = ['line' => $line, 'transaction' => $matches->first(), 'account' => $account, 'payment_date' => $paymentDate];
            }
        }

        if (!$prepared && !$errors)
            $errors[] = 'Tidak ada baris data untuk diimpor.';
        if ($errors)
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);

        $totalReceived = 0.0;
        DB::transaction(function () use ($prepared, &$totalReceived) {
            foreach ($prepared as $entry) {
                $tx = MedicineTransactions::whereKey($entry['transaction']->id)->lockForUpdate()->firstOrFail();
                $tx->load(['transactions', 'returs']);
                $account = FinanceAccount::whereKey($entry['account']->id)->lockForUpdate()->firstOrFail();
                $totalRetur = (float) $tx->returs->sum('total_retur');
                $paymentsTotal = (float) FinancePayment::where('medicine_transaction_id', $tx->id)->sum('amount');
                $alreadyPaid = max($paymentsTotal, (float) ($tx->paid ?? 0));
                $calculatedTotal = $tx->transactions->sum(function ($item) {
                    return (float) ($item->final_price ?: ($item->total_price ?: ((float) ($item->quantity ?? 0) * (float) ($item->item_price ?? 0))));
                });
                $total = round((float) ($tx->subtotal > 0 ? $tx->subtotal : $calculatedTotal), 2);
                $netTotal = round(max(0, $total - $totalRetur), 2);
                $remaining = round(max(0, $netTotal - $alreadyPaid), 2);
                if ($remaining < 0.005) {
                    throw ValidationException::withMessages(['file' => "Baris {$entry['line']}: Transaksi '{$tx->transaction_code}' sudah lunas atau sudah diretur penuh."]);
                }

                FinancePayment::create([
                    'pharmacy_id' => $tx->pharmacy_id,
                    'medicine_transaction_id' => $tx->id,
                    'account_id' => $account->id,
                    'payment_type' => 'PIUTANG',
                    'payment_date' => $entry['payment_date'],
                    'amount' => $remaining,
                    'reference_number' => null,
                    'notes' => 'Pelunasan Piutang melalui impor file',
                    'created_by' => Auth::id(),
                ]);
                $tx->paid = (string) round($alreadyPaid + $remaining, 2);
                $tx->save();
                $account->increment('balance', $remaining);
                $totalReceived += $remaining;
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Impor berhasil: ' . count($prepared) . ' transaksi piutang dilunasi dengan total penerimaan Rp ' . number_format($totalReceived, 0, ',', '.') . '.');
    }

    public function downloadPiutangPaymentTemplate()
    {
        return response("No. Faktur,Tanggal Bayar,Kode Bank\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename=template-impor-piutang.csv',
        ]);
    }

    /**
     * Export data Piutang Penjualan ke format Excel (.xlsx) dengan tampilan rapi & estetik.
     */
    public function exportPiutang(Request $request)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Laporan_Piutang_Penjualan_{$branchName}_" . date('Ymd_His') . '.xlsx';

        $filters = [
            'search' => $request->query('search', ''),
            'debtor_id' => $request->query('debtor_id', ''),
            'status' => $request->query('status', ''),
            'sort_order' => $request->query('sort_order', 'desc'),
            'due_mode' => $request->query('due_mode', 'all'),
        ];

        return Excel::download(new PiutangExport($targetPharmacyIds, $filters, $activePharmacy), $filename);
    }

    /**
     * Export data Hutang Dagang ke format Excel (.xlsx) dengan tampilan rapi & estetik.
     */
    public function exportHutang(Request $request)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Laporan_Hutang_Dagang_{$branchName}_" . date('Ymd_His') . '.xlsx';

        $filters = [
            'search' => $request->query('search', ''),
            'pbf' => $request->query('pbf', ''),
            'status' => $request->query('status', ''),
            'due_mode' => $request->query('due_mode', 'all'),
            'date_field' => $request->query('date_field', 'due'),
            'start_date' => $request->query('start_date', ''),
            'end_date' => $request->query('end_date', ''),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        return Excel::download(new HutangExport($targetPharmacyIds, $filters, $activePharmacy), $filename);
    }

    /**
     * Export data Pembelian Cash ke format Excel (.xlsx) dengan tampilan rapi & estetik.
     */
    public function exportCash(Request $request)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Laporan_Pembelian_Cash_{$branchName}_" . date('Ymd_His') . '.xlsx';

        $filters = [
            'search' => $request->query('search', ''),
            'pbf' => $request->query('pbf', ''),
            'account_status' => $request->query('account_status', 'ALL'),
            'sort_order' => $request->query('sort_order', 'desc'),
        ];

        return Excel::download(new CashExport($targetPharmacyIds, $filters, $activePharmacy), $filename);
    }

    /**
     * Simpan transaksi biaya operasional baru.
     */
    public function storeBiaya(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'expense_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun sumber dana harus berkategori Kas & Bank.']);
        }

        $activePharmacyId = getActivePharmacyId();

        DB::transaction(function () use ($validated, $account, $activePharmacyId) {
            FinancePayment::create([
                'pharmacy_id' => $activePharmacyId > 0 ? $activePharmacyId : null,
                'account_id' => $account->id,
                'expense_account_id' => $validated['expense_account_id'],
                'payment_type' => 'BIAYA',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'recipient' => $validated['recipient'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
                'created_by' => Auth::id(),
            ]);

            // Kurangi saldo akun Kas & Bank
            $account->decrement('balance', $validated['amount']);

            // Bersihkan cache statistik
            $this->clearStatsCache();
        });

        return back()->with('success', 'Biaya operasional berhasil dicatat.');
    }

    /**
     * Perbarui transaksi biaya operasional yang sudah ada.
     */
    public function updateBiaya(Request $request, $id)
    {
        $payment = FinancePayment::where('payment_type', 'BIAYA')->findOrFail($id);

        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'expense_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $newAccount = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($newAccount->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun sumber dana harus berkategori Kas & Bank.']);
        }

        DB::transaction(function () use ($payment, $validated, $newAccount) {
            // Revert saldo akun lama (tambahkan kembali nominal lama)
            if ($payment->account_id) {
                FinanceAccount::where('id', $payment->account_id)->increment('balance', $payment->amount);
            }

            // Kurangi saldo akun kas yang baru dipilih
            $newAccount->decrement('balance', $validated['amount']);

            // Perbarui data record pembayaran
            $payment->update([
                'account_id' => $newAccount->id,
                'expense_account_id' => $validated['expense_account_id'],
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'recipient' => $validated['recipient'] ?? null,
                'reference_number' => $validated['reference_number'] ?? null,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->clearStatsCache();
        });

        return back()->with('success', 'Biaya operasional berhasil diperbarui.');
    }

    /**
     * Hapus transaksi biaya operasional (dan kembalikan saldo kas).
     */
    public function destroyBiaya($id)
    {
        $payment = FinancePayment::where('payment_type', 'BIAYA')->findOrFail($id);

        DB::transaction(function () use ($payment) {
            if ($payment->account_id) {
                FinanceAccount::where('id', $payment->account_id)->increment('balance', $payment->amount);
            }

            $payment->delete();

            $this->clearStatsCache();
        });

        return back()->with('success', 'Transaksi biaya berhasil dihapus dan saldo kas dikembalikan.');
    }

    /**
     * Simpan transaksi diskon off baru.
     *
     * Catatan: Diskon off TIDAK mengurangi saldo akun Kas & Bank karena
     * merupakan potongan yang diterima dari supplier (bukan pengeluaran kas).
     */
    public function storeDiskon(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'expense_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($account->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun sumber dana harus berkategori Kas & Bank.']);
        }

        $discountAccount = FinanceAccount::findOrFail($validated['expense_account_id']);
        if ($discountAccount->category !== 'Diskon Lain-lain') {
            return back()->withErrors(['expense_account_id' => 'Jenis diskon harus akun berkategori "Diskon Lain-lain".']);
        }

        $activePharmacyId = getActivePharmacyId();

        FinancePayment::create([
            'pharmacy_id' => $activePharmacyId > 0 ? $activePharmacyId : null,
            'account_id' => $account->id,
            'expense_account_id' => $discountAccount->id,
            'payment_type' => 'DISKON',
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'recipient' => $validated['recipient'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => Auth::id(),
        ]);

        // Diskon off tidak mengurangi saldo kas/bank, hanya bersihkan cache statistik.
        $this->clearStatsCache();

        return back()->with('success', 'Diskon off berhasil dicatat.');
    }

    /**
     * Perbarui transaksi diskon off yang sudah ada.
     */
    public function updateDiskon(Request $request, $id)
    {
        $payment = FinancePayment::where('payment_type', 'DISKON')->findOrFail($id);

        $validated = $request->validate([
            'account_id' => 'required|exists:finance_accounts,id',
            'expense_account_id' => 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'recipient' => 'nullable|string|max:150',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
        ]);

        $newAccount = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
        if ($newAccount->category !== 'Kas & Bank') {
            return back()->withErrors(['account_id' => 'Akun sumber dana harus berkategori Kas & Bank.']);
        }

        $discountAccount = FinanceAccount::findOrFail($validated['expense_account_id']);
        if ($discountAccount->category !== 'Diskon Lain-lain') {
            return back()->withErrors(['expense_account_id' => 'Jenis diskon harus akun berkategori "Diskon Lain-lain".']);
        }

        $payment->update([
            'account_id' => $newAccount->id,
            'expense_account_id' => $discountAccount->id,
            'payment_date' => $validated['payment_date'],
            'amount' => $validated['amount'],
            'recipient' => $validated['recipient'] ?? null,
            'reference_number' => $validated['reference_number'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        $this->clearStatsCache();

        return back()->with('success', 'Diskon off berhasil diperbarui.');
    }

    /**
     * Hapus transaksi diskon off. Tidak ada pengembalian saldo kas karena
     * pencatatan diskon off tidak pernah mengurangi saldo kas/bank.
     */
    public function destroyDiskon($id)
    {
        $payment = FinancePayment::where('payment_type', 'DISKON')->findOrFail($id);
        $payment->delete();

        $this->clearStatsCache();

        return back()->with('success', 'Transaksi diskon off berhasil dihapus.');
    }

    /**
     * Export data Biaya Operasional ke Excel (.xlsx).
     */
    public function exportBiaya(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Laporan_Biaya_Operasional_{$branchName}_" . date('Ymd_His') . '.xlsx';

        $filters = [
            'search' => $request->query('search', ''),
            'expense_account_id' => $request->query('expense_account_id', ''),
            'account_id' => $request->query('account_id', ''),
            'start_date' => $request->query('start_date', ''),
            'end_date' => $request->query('end_date', ''),
            'sort_order' => $request->query('sort_order', 'desc'),
            'pharmacy_id' => $request->query('pharmacy_id', ''),
        ];

        return Excel::download(new BiayaExport($targetPharmacyIds, $filters, $activePharmacy), $filename);
    }

    /**
     * Export data Buku Mutasi Kas & Bank ke format Excel (.xlsx) lengkap, rapi, dan mudah dibaca orang awam.
     */
    public function exportKasBank(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $activePharmacy = Pharmacies::find(getActivePharmacyId()) ?? Pharmacies::find(1);
        $branchName = $activePharmacy ? preg_replace('/[^A-Za-z0-9_]/', '_', $activePharmacy->name) : 'Apotek';
        $filename = "Buku_Mutasi_Kas_Bank_{$branchName}_" . date('Ymd_His') . '.xlsx';

        // Date range resolution: prioritaskan custom date range jika ada, atau fallback ke bulan/tahun
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');
        if (empty($startDate) || empty($endDate)) {
            $year = (int) $request->query('year', \Carbon\Carbon::now()->year);
            $month = (int) $request->query('month', \Carbon\Carbon::now()->month);
            if ($month < 1 || $month > 12) $month = \Carbon\Carbon::now()->month;
            if ($year < 2020 || $year > 2100) $year = \Carbon\Carbon::now()->year;

            $periodDate = \Carbon\Carbon::createFromDate($year, $month, 1)->startOfMonth();
            $startDate = $periodDate->copy()->startOfMonth()->toDateString();
            $endDate = $periodDate->copy()->endOfMonth()->toDateString();
        }

        $filters = [
            'search' => $request->query('search', ''),
            'account_id' => $request->query('account_id', 'all'),
            'direction' => $request->query('direction', 'all'),
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pharmacy_id' => $request->query('pharmacy_id', ''),
        ];

        return Excel::download(new KasBankExport($targetPharmacyIds, $filters, $activePharmacy), $filename);
    }

    // --- Submit Logic (Pengajuan) ---
    public function submitHutang(Request $request, $id)
    {
        $validated = $request->validate(['is_submitted' => 'required|boolean']);
        $item = ReceivingDetails::findOrFail($id);

        $item->is_submitted = $validated['is_submitted'];
        $item->save();

        return redirect()->back()->with('success', 'Status pengajuan hutang berhasil diperbarui.');
    }

    public function submitPiutang(Request $request, $id)
    {
        $validated = $request->validate(['is_submitted' => 'required|boolean']);
        $item = MedicineTransactions::findOrFail($id);

        $item->is_submitted = $validated['is_submitted'];
        $item->save();

        return redirect()->back()->with('success', 'Status pengajuan piutang berhasil diperbarui.');
    }

    public function bulkSubmitHutang(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:receiving_details,id',
            'is_submitted' => 'required|boolean'
        ]);

        foreach ($validated['ids'] as $id) {
            $item = ReceivingDetails::find($id);
            if ($item) {
                $item->is_submitted = $validated['is_submitted'];
                $item->save();
            }
        }

        $this->clearStatsCache();

        return redirect()->back()->with('success', 'Status pengajuan hutang massal berhasil diperbarui.');
    }

    public function bulkSubmitPiutang(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'exists:medicine_transactions,id',
            'is_submitted' => 'required|boolean'
        ]);

        foreach ($validated['ids'] as $id) {
            $item = MedicineTransactions::find($id);
            if ($item) {
                $item->is_submitted = $validated['is_submitted'];
                $item->save();
            }
        }

        $this->clearStatsCache();

        return redirect()->back()->with('success', 'Status pengajuan piutang massal berhasil diperbarui.');
    }
}
