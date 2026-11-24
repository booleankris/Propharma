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
use App\Models\FinanceOpeningInvoice;
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
use App\Support\FinanceAccountBalanceResolver;
use App\Support\FinanceShift;
use App\Support\LiphTransactionScope;
use App\Support\ReceivingInvoiceTotals;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
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
            if (!$user || !hasFeatureAccess('finance.manage', ['Finance', 'General Manager', 'administrator', 'HO'], $user)) {
                abort(403, 'Akses ditolak: Modul SAHABAT Finances hanya dapat diakses oleh role yang memiliki izin Finance.');
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
    protected function payablesReceivablesService(): \App\Services\Finance\PayablesReceivablesService
    {
        return app(\App\Services\Finance\PayablesReceivablesService::class);
    }

    /**
     * Halaman Hutang Dagang dengan server-side pagination, filter, dan lazy detail loading.
     */
    public function hutang(Request $request)
    {
        if ($request->filled('pharmacy_id') && auth()->check() && (auth()->user()->hasRole('HO') || auth()->user()->hasRole('administrator') || auth()->user()->hasRole('General Manager'))) {
            session(['ho_pharmacy_id' => (int) $request->pharmacy_id]);
        }

        $filters = [
            'search' => (string) $request->query('search', ''),
            'pbf' => (string) $request->query('pbf', ''),
            'status' => (string) $request->query('status', 'ALL'),
            'due_mode' => (string) $request->query('due_mode', 'all'),
            'date_field' => (string) $request->query('date_field', 'due'),
            'date_preset' => (string) $request->query('date_preset', 'all'),
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
            'sort_order' => strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc',
        ];
        $perPage = max(5, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));

        $dataset = $this->payablesReceivablesService()->getHutangDataset(
            $this->getTargetPharmacyIds(),
            $filters,
            false,
            $perPage,
            $page
        );

        $creditors = Creditor::orderBy('name')->get(['code', 'name']);
        $kasBankAccounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();
        $stats = $this->getStats($dataset['stats'], null, null);

        return Inertia::render('Finance/Hutang', [
            'hutangDagang' => $dataset['rows'],
            'pagination' => $dataset['pagination'],
            'dueStats' => $dataset['dueStats'],
            'filters' => $filters,
            'creditors' => $creditors,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Endpoint JSON on-demand untuk memuat detail item, pembayaran, & retur 1 faktur Hutang Dagang.
     */
    public function hutangDetail($id)
    {
        $detail = $this->payablesReceivablesService()->getHutangDetail(
            $id,
            $this->getTargetPharmacyIds()
        );

        if (!$detail) {
            return response()->json(['message' => 'Faktur hutang tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $detail]);
    }

    /**
     * Preview Saldo Awal Hutang Dagang / Piutang per tanggal cut-off (identik dengan Neraca).
     */
    public function previewOpeningBalance(Request $request)
    {
        $type = strtoupper(trim((string) $request->query('type', 'HUTANG')));
        $date = trim((string) $request->query('date', ''));
        if ($date === '') {
            $date = \Carbon\Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        } else {
            try {
                $date = \Carbon\Carbon::parse($date)->toDateString();
            } catch (\Throwable $e) {
                $date = \Carbon\Carbon::now()->subMonthNoOverflow()->endOfMonth()->toDateString();
            }
        }

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $neracaService = app(\App\Services\Finance\NeracaReportService::class);
        $balance = $type === 'PIUTANG'
            ? $neracaService->receivableBalanceAtDate($targetPharmacyIds, $date)
            : $neracaService->payableBalanceAtDate($targetPharmacyIds, $date);

        return response()->json([
            'type' => $type === 'PIUTANG' ? 'PIUTANG' : 'HUTANG',
            'date' => $date,
            'balance' => round((float) $balance, 2),
        ]);
    }

    /**
     * Pastikan tabel finance_opening_invoices tersedia meskipun php artisan migrate
     * sempat tertahan oleh permission file cache di server.
     */
    private function ensureOpeningInvoicesSchema(): void
    {
        if (!Schema::hasTable('finance_opening_invoices')) {
            Schema::create('finance_opening_invoices', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('pharmacy_id')->index();
                $table->string('type', 20)->index();
                $table->string('creditor_code', 100)->nullable()->index();
                $table->unsignedBigInteger('debtor_id')->nullable()->index();
                $table->string('invoice_number', 150)->index();
                $table->date('invoice_date')->index();
                $table->date('due_date')->nullable();
                $table->decimal('amount', 15, 2);
                $table->boolean('is_submitted')->default(false);
                $table->text('notes')->nullable();
                $table->unsignedBigInteger('created_by')->nullable();
                $table->timestamps();

                $table->index(['pharmacy_id', 'type', 'invoice_date'], 'foi_pharmacy_type_date_idx');
            });
        }

        if (Schema::hasTable('finance_payments') && !Schema::hasColumn('finance_payments', 'opening_invoice_id')) {
            Schema::table('finance_payments', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->unsignedBigInteger('opening_invoice_id')->nullable()->after('medicine_transaction_id')->index();
            });
        }
    }

    /**
     * Tambah Tagihan Saldo Awal Hutang Dagang per faktur (tanpa menambah stok obat & tanpa ubah Kas/Bank).
     */
    public function storeOpeningHutang(Request $request)
    {
        $this->ensureOpeningInvoicesSchema();
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $pharmacyId = in_array(9, $targetPharmacyIds, true)
            ? 9
            : (int) ($targetPharmacyIds[0] ?? getActivePharmacyId());

        $validated = $request->validate([
            'creditor_code' => 'nullable|string|exists:creditors,code',
            'invoice_number' => 'required|string|max:150',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'amount' => ['required', 'numeric', 'not_in:0'],
            'notes' => 'nullable|string|max:500',
        ], [
            'creditor_code.exists' => 'PBF / Supplier yang dipilih tidak ditemukan.',
            'invoice_number.required' => 'Nomor faktur wajib diisi.',
            'invoice_date.required' => 'Tanggal faktur / cut-off wajib diisi.',
            'amount.required' => 'Nominal sisa hutang wajib diisi.',
            'amount.not_in' => 'Nominal tidak boleh 0.',
        ]);

        $dueDate = !empty($validated['due_date'])
            ? $validated['due_date']
            : \Carbon\Carbon::parse($validated['invoice_date'])->addDays(30)->toDateString();

        FinanceOpeningInvoice::create([
            'pharmacy_id' => $pharmacyId,
            'type' => 'HUTANG',
            'creditor_code' => !empty($validated['creditor_code']) ? trim($validated['creditor_code']) : null,
            'invoice_number' => trim($validated['invoice_number']),
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $dueDate,
            'amount' => round((float) $validated['amount'], 2),
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->clearStatsCache();
        return back()->with('success', 'Tagihan saldo awal hutang dagang berhasil ditambahkan (tanpa mengubah stok & Kas/Bank).');
    }

    /**
     * Tambah Tagihan Saldo Awal Piutang per tagihan (tanpa mengurangi stok, tanpa masuk LIPH/Omzet, tanpa ubah Kas/Bank).
     */
    public function storeOpeningPiutang(Request $request)
    {
        $this->ensureOpeningInvoicesSchema();
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $pharmacyId = in_array(1, $targetPharmacyIds, true)
            ? 1
            : (int) ($targetPharmacyIds[0] ?? getActivePharmacyId());

        $validated = $request->validate([
            'debtor_id' => 'nullable|integer|exists:debtors,id',
            'invoice_number' => 'required|string|max:150',
            'invoice_date' => 'required|date',
            'due_date' => 'nullable|date',
            'amount' => ['required', 'numeric', 'not_in:0'],
            'notes' => 'nullable|string|max:500',
        ], [
            'debtor_id.exists' => 'Debitur / Instansi yang dipilih tidak ditemukan.',
            'invoice_number.required' => 'Nomor tagihan / faktur wajib diisi.',
            'invoice_date.required' => 'Tanggal tagihan / cut-off wajib diisi.',
            'amount.required' => 'Nominal sisa piutang wajib diisi.',
            'amount.not_in' => 'Nominal tidak boleh 0.',
        ]);

        $dueDate = !empty($validated['due_date'])
            ? $validated['due_date']
            : \Carbon\Carbon::parse($validated['invoice_date'])->addDays(30)->toDateString();

        FinanceOpeningInvoice::create([
            'pharmacy_id' => $pharmacyId,
            'type' => 'PIUTANG',
            'debtor_id' => !empty($validated['debtor_id']) ? (int) $validated['debtor_id'] : null,
            'invoice_number' => trim($validated['invoice_number']),
            'invoice_date' => $validated['invoice_date'],
            'due_date' => $dueDate,
            'amount' => round((float) $validated['amount'], 2),
            'notes' => trim((string) ($validated['notes'] ?? '')) ?: null,
            'created_by' => Auth::id(),
        ]);

        $this->clearStatsCache();
        return back()->with('success', 'Tagihan saldo awal piutang berhasil ditambahkan (tanpa mengubah stok, omzet, & Kas/Bank).');
    }

    /**
     * Hapus tagihan saldo awal (hanya diizinkan jika belum memiliki pelunasan Kas & Bank reguler;
     * pelunasan pra-cut-off non-kas aman ikut terhapus karena tidak menyentuh saldo Kas & Bank).
     */
    public function destroyOpeningInvoice($id)
    {
        $opening = FinanceOpeningInvoice::whereIn('pharmacy_id', $this->getTargetPharmacyIds())->findOrFail($id);
        $hasRegularPayments = $opening->payments()
            ->whereNotIn('payment_type', ['KREDIT CUTOFF', 'PIUTANG CUTOFF'])
            ->exists();

        if ($hasRegularPayments) {
            return back()->withErrors(['error' => 'Tagihan saldo awal ini sudah memiliki pelunasan Kas & Bank dan tidak dapat dihapus.']);
        }

        $label = $opening->type === 'HUTANG' ? 'hutang dagang' : 'piutang';
        $opening->delete();
        $this->clearStatsCache();

        return back()->with('success', "Tagihan saldo awal {$label} berhasil dihapus.");
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
        $neracaData = $neracaReport->build($pharmacyIds, $startDate, $endDate, (int) getActivePharmacyId());
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
        $data = $neracaReport->build($pharmacyIds, $start->toDateString(), $end->toDateString(), (int) getActivePharmacyId());
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

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $filters = [
            'tab' => in_array($request->query('tab', 'faktur'), ['faktur', 'retur'], true) ? $request->query('tab', 'faktur') : 'faktur',
            'search' => (string) $request->query('search', ''),
            'debtor_id' => (string) $request->query('debtor_id', ''),
            'status' => (string) $request->query('status', 'ALL'),
            'due_mode' => (string) $request->query('due_mode', 'all'),
            'date_preset' => (string) $request->query('date_preset', 'all'),
            'start_date' => (string) $request->query('start_date', ''),
            'end_date' => (string) $request->query('end_date', ''),
            'sort_order' => strtolower((string) $request->query('sort_order', 'desc')) === 'asc' ? 'asc' : 'desc',
            'retur_search' => (string) $request->query('retur_search', ''),
            'retur_debtor_id' => (string) $request->query('retur_debtor_id', ''),
        ];

        $perPage = max(5, min(100, (int) $request->query('per_page', 10)));
        $page = max(1, (int) $request->query('page', 1));
        $returPerPage = max(5, min(100, (int) $request->query('retur_per_page', 10)));
        $returPage = max(1, (int) $request->query('retur_page', 1));

        $dataset = $this->payablesReceivablesService()->getPiutangDataset(
            $targetPharmacyIds,
            $filters,
            false,
            $perPage,
            $page
        );
        $returDataset = $this->payablesReceivablesService()->getCreditReturnsDataset(
            $targetPharmacyIds,
            $filters,
            $returPerPage,
            $returPage
        );

        $debtors = Debtors::orderBy('name')->get(['id', 'code', 'name', 'phone', 'city']);
        $kasBankAccounts = $this->branchKasBankAccountsQuery()->orderBy('code')->get();
        $stats = $this->getStats(null, null, $dataset['stats']);

        return Inertia::render('Finance/Piutang', [
            'piutangPenjualan' => $dataset['rows'],
            'pagination' => $dataset['pagination'],
            'dueStats' => $dataset['dueStats'],
            'creditReturns' => $returDataset['rows'],
            'returPagination' => $returDataset['pagination'],
            'filters' => $filters,
            'debtors' => $debtors,
            'kasBankAccounts' => $kasBankAccounts,
            'stats' => $stats,
            'branchContext' => $this->getBranchContext(),
        ]);
    }

    /**
     * Endpoint JSON on-demand untuk memuat detail item, pembayaran, & retur 1 faktur Piutang.
     */
    public function piutangDetail($id)
    {
        $detail = $this->payablesReceivablesService()->getPiutangDetail(
            $id,
            $this->getTargetPharmacyIds()
        );

        if (!$detail) {
            return response()->json(['message' => 'Transaksi piutang tidak ditemukan.'], 404);
        }

        return response()->json(['data' => $detail]);
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
        $latestOpnames = [];
        foreach ($accounts as $account) {
            $monthlyOpeningBalances[$account->id] = FinanceAccountBalanceResolver::resolveOpeningBalanceForPeriod(
                $account->id,
                $filterStart,
                $filterEnd
            );

            $latestAdj = FinanceAccountBalanceResolver::latestOpnameOnOrBefore($account->id, now()->toDateString());
            if ($latestAdj) {
                $latestOpnames[$account->id] = [
                    'id' => $latestAdj->id,
                    'account_id' => $account->id,
                    'real_balance' => (float) $latestAdj->real_balance,
                    'adjustment_date' => $latestAdj->adjustment_date?->format('Y-m-d'),
                    'notes' => $latestAdj->notes,
                ];
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
            'latestOpnames' => $latestOpnames,
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

    /**
     * Hitung mundur estimasi Saldo Awal per tanggal target dari hasil Opname terakhir (atau saldo buku saat ini).
     * Rumus: Saldo Awal = Saldo Akhir Acuan - Total Masuk + Total Keluar.
     */
    public function estimateSaldoAwal(Request $request)
    {
        $validated = $request->validate([
            'target_date' => 'required|date|before_or_equal:today',
            'anchor_mode' => 'nullable|in:auto,book',
        ]);

        $targetDate = $validated['target_date'];
        $anchorMode = $validated['anchor_mode'] ?? 'auto';

        $accounts = $this
            ->branchKasBankAccountsQuery()
            ->where('is_active', true)
            ->orderByRaw("CASE WHEN account_type = 'kas_apotek' THEN 0 ELSE 1 END")
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_type', 'balance']);

        $estimates = $accounts->map(
            fn(FinanceAccount $account) => FinanceAccountBalanceResolver::estimateOpeningForAccount($account, $targetDate, $anchorMode)
        )->values();

        return response()->json([
            'target_date' => $targetDate,
            'anchor_mode' => $anchorMode,
            'estimates' => $estimates,
        ]);
    }

    public function storeSaldoAwal(Request $request)
    {
        $validated = $request->validate([
            'account_id' => 'required_without:items|nullable|exists:finance_accounts,id',
            'payment_date' => 'required|date|before_or_equal:today',
            'amount' => 'required_without:items|nullable|numeric|min:0',
            'notes' => 'nullable|string|max:500',
            'clear_prior_opname_payments' => 'nullable|boolean',
            'clear_until_date' => 'nullable|date',
            'items' => 'nullable|array|min:1',
            'items.*.account_id' => 'required_with:items|exists:finance_accounts,id',
            'items.*.amount' => 'required_with:items|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.clear_until_date' => 'nullable|date',
        ]);

        $clearOpnamePayments = (bool) ($validated['clear_prior_opname_payments'] ?? false);
        $defaultClearUntil = $validated['clear_until_date'] ?? now()->toDateString();

        $entries = !empty($validated['items'])
            ? $validated['items']
            : [[
                'account_id' => $validated['account_id'],
                'amount' => $validated['amount'],
                'notes' => $validated['notes'] ?? null,
                'clear_until_date' => $defaultClearUntil,
            ]];

        $allowedAccountIds = array_map('intval', $this->branchKasBankAccountsQuery(false)->pluck('id')->all());
        $savedCount = 0;

        DB::transaction(function () use ($entries, $validated, $allowedAccountIds, $clearOpnamePayments, $defaultClearUntil, &$savedCount) {
            foreach ($entries as $entry) {
                $accountId = (int) $entry['account_id'];
                if (!in_array($accountId, $allowedAccountIds, true)) {
                    continue;
                }

                $lockedAccount = FinanceAccount::whereKey($accountId)->lockForUpdate()->firstOrFail();

                if ($clearOpnamePayments) {
                    $untilDate = max($validated['payment_date'], $entry['clear_until_date'] ?? $defaultClearUntil);
                    FinancePayment::where('account_id', $lockedAccount->id)
                        ->where('payment_type', 'PENYESUAIAN SALDO')
                        ->whereDate('payment_date', '>=', $validated['payment_date'])
                        ->whereDate('payment_date', '<=', $untilDate)
                        ->delete();
                }

                $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
                    ->where('account_id', $lockedAccount->id)
                    ->lockForUpdate()
                    ->first();

                $payload = [
                    'account_id' => $lockedAccount->id,
                    'payment_type' => 'SALDO AWAL',
                    'payment_date' => $validated['payment_date'],
                    'amount' => round((float) $entry['amount'], 2),
                    'notes' => $entry['notes'] ?? ($validated['notes'] ?? null),
                    'created_by' => Auth::id(),
                    'pharmacy_id' => $lockedAccount->pharmacy_id ?: null,
                ];

                if ($opening) {
                    $opening->update($payload);
                } else {
                    $opening = FinancePayment::create($payload);
                }

                $lockedAccount->update([
                    'balance' => $this->calculateBalanceFromOpening($lockedAccount->id, $opening, now()->toDateString()),
                ]);
                $savedCount++;
            }

            $this->clearStatsCache();
        });

        $referer = (string) $request->headers->get('referer', '');
        $redirectTarget = str_contains($referer, '/finance/kas-bank')
            ? $referer
            : route('finance.kasBank');

        if ($savedCount === 0) {
            return redirect()->to($redirectTarget)->withErrors([
                'account_id' => 'Tidak ada rekening cabang aktif yang cocok untuk disimpan. Pastikan cabang aktif sudah sesuai lalu coba lagi.',
            ]);
        }

        $msg = $savedCount > 1
            ? "Saldo awal berhasil disimpan untuk {$savedCount} rekening Kas & Bank dan saldo buku telah diselaraskan."
            : 'Saldo awal akun berhasil disimpan. Nilai ini tidak dihitung sebagai pendapatan.';

        return redirect()->to($redirectTarget)->with('success', $msg);
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
            'as_opening_balance' => 'nullable|boolean',
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
        $asOpeningBalance = (bool) ($validated['as_opening_balance'] ?? false);

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

        DB::transaction(function () use ($items, $pharmacyId, $adjustmentDate, $asOpeningBalance, &$processedCount, &$totalDifference) {
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
                if (is_int($item['real_balance']) || is_float($item['real_balance']) || is_numeric($item['real_balance'])) {
                    $realBalance = round(max(0, (float) $item['real_balance']), 2);
                } else {
                    $rawReal = (string) ($item['real_balance'] ?? '');
                    $cleanReal = str_replace(['Rp', ' ', '.'], '', $rawReal);
                    $cleanReal = str_replace(',', '.', $cleanReal);
                    $realBalance = round(max(0, (float) $cleanReal), 2);
                }

                $systemBalance = (float) $account->balance;
                $diff = round($realBalance - $systemBalance, 2);
                $itemNotes = trim((string) ($item['notes'] ?? ''));

                if ($asOpeningBalance) {
                    // Mode Cut-off Saldo Awal: simpan sebagai SALDO AWAL pada adjustment_date
                    // dan hitung ulang saldo berjalan sampai hari ini tanpa membuat PENYESUAIAN SALDO.
                    FinancePayment::where('account_id', $account->id)
                        ->where('payment_type', 'PENYESUAIAN SALDO')
                        ->whereDate('payment_date', $adjustmentDate)
                        ->delete();

                    $opening = FinancePayment::where('payment_type', 'SALDO AWAL')
                        ->where('account_id', $account->id)
                        ->lockForUpdate()
                        ->first();

                    $openingNotes = 'Cut-off Saldo Awal dari Opname Kas & Bank' . ($itemNotes !== '' ? ' | ' . $itemNotes : '');
                    $openingPayload = [
                        'pharmacy_id' => $account->pharmacy_id ?: ($pharmacyId > 0 ? $pharmacyId : null),
                        'account_id' => $account->id,
                        'payment_type' => 'SALDO AWAL',
                        'payment_date' => $adjustmentDate,
                        'amount' => $realBalance,
                        'notes' => $openingNotes,
                        'created_by' => Auth::id(),
                    ];

                    if ($opening) {
                        $opening->update($openingPayload);
                    } else {
                        $opening = FinancePayment::create($openingPayload);
                    }

                    $recalculatedBalance = $this->calculateBalanceFromOpening($account->id, $opening, now()->toDateString());
                    $account->update([
                        'balance' => $recalculatedBalance,
                    ]);

                    FinanceBalanceAdjustment::create([
                        'pharmacy_id' => $account->pharmacy_id ?: ($pharmacyId > 0 ? $pharmacyId : null),
                        'account_id' => $account->id,
                        'system_balance' => $systemBalance,
                        'real_balance' => $realBalance,
                        'difference' => $diff,
                        'adjustment_date' => $adjustmentDate,
                        'adjusted_at' => now(),
                        'notes' => '[Cut-off Saldo Awal] ' . ($itemNotes !== '' ? $itemNotes : 'Ditetapkan sebagai Saldo Awal per ' . $adjustmentDate),
                        'finance_payment_id' => $opening->id,
                        'created_by' => Auth::id(),
                    ]);
                } else {
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
                }

                $processedCount++;
                $totalDifference += $diff;
            }

            $this->clearStatsCache();
        });

        if ($asOpeningBalance) {
            $msg = $processedCount === 1
                ? "Saldo riil berhasil ditetapkan sebagai Saldo Awal per {$adjustmentDate} dan tercatat di log opname."
                : "Berhasil menetapkan Saldo Awal untuk {$processedCount} rekening Kas & Bank per {$adjustmentDate}.";
        } else {
            $msg = $processedCount === 1
                ? 'Saldo riil berhasil disesuaikan dan tercatat di riwayat log opname.'
                : "Berhasil menyesuaikan {$processedCount} rekening kas & bank ke saldo riil. Riwayat lengkap tercatat di log opname.";
        }

        $referer = (string) $request->headers->get('referer', '');
        $redirectTarget = str_contains($referer, '/finance/kas-bank')
            ? $referer
            : route('finance.kasBank');

        return redirect()->to($redirectTarget)->with('success', $msg);
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
                    $q
                        ->whereRaw('UPPER(TRIM(COALESCE(payment_method, ""))) IN (?, ?, ?, ?)', ['QRIS', 'DEBIT', 'TRANSFER', 'EDC'])
                        ->orWhere(function ($ship) {
                            $ship
                                ->where('has_doctor_shipping_fee', 1)
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
                                        $q
                                            ->whereNull('notes')
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
        return FinanceAccountBalanceResolver::calculateFromOpening($accountId, $opening, $asOfDate);
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
                ->whereIn('payment_type', ['PIUTANG', 'PIUTANG CUTOFF'])
                ->whereDate('payment_date', '<=', $asOfDate)], 'amount')
            ->withSum(['payments as finance_paid_total' => fn($query) => $query
                ->whereIn('payment_type', ['PIUTANG', 'PIUTANG CUTOFF'])], 'amount')
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
            $legacyPaid = max(0, (float) ($transaction->paid ?? 0) - (float) ($transaction->finance_paid_total ?? 0));
            $paid = min($total, max(0, (float) ($transaction->finance_paid ?? 0) + $legacyPaid));
            $balancesByDebtor[$debtorKey] = ($balancesByDebtor[$debtorKey] ?? 0) + max(0, $total - $paid);
        }

        foreach ($returnsByDebtor as $debtorKey => $returnAmount) {
            if (isset($balancesByDebtor[$debtorKey])) {
                $balancesByDebtor[$debtorKey] = max(0, $balancesByDebtor[$debtorKey] - $returnAmount);
            }
        }

        $totalBalance = (float) array_sum($balancesByDebtor);

        if (Schema::hasTable('finance_opening_invoices')) {
            $openingReceivables = FinanceOpeningInvoice::query()
                ->withSum(['payments as paid_as_of' => fn($q) => $q->whereDate('payment_date', '<=', $asOfDate)], 'amount')
                ->where('type', 'PIUTANG')
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->whereDate('invoice_date', '<=', $asOfDate)
                ->get(['id', 'amount']);

            foreach ($openingReceivables as $oi) {
                $amt = (float) $oi->amount;
                if ($amt < 0) {
                    $totalBalance += $amt;
                } else {
                    $totalBalance += max(0.0, $amt - (float) ($oi->paid_as_of ?? 0));
                }
            }
        }

        return max(0.0, $totalBalance);
    }

    /**
     * Hitung sisa hutang dagang pembelian kredit cabang sampai tanggal tertentu.
     */
    private function payableBalanceAtDate(array|int $pharmacyId, string $asOfDate): float
    {
        $pharmacyIds = is_array($pharmacyId) ? $pharmacyId : [$pharmacyId];

        return app(NeracaReportService::class)->payableBalanceAtDate($pharmacyIds, $asOfDate);
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
                $q
                    ->where('borrower_name', 'like', "%{$search}%")
                    ->orWhere('purpose', 'like', "%{$search}%")
                    ->orWhere('settlement_notes', 'like', "%{$search}%")
                    ->orWhereHas('account', function ($sub) use ($search) {
                        $sub
                            ->where('name', 'like', "%{$search}%")
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

        $cashbondList = $baseQuery
            ->orderByDesc('transaction_date')
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
        $kasBankAccounts = $this
            ->branchKasBankAccountsQuery()
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
            $openingBalance = FinanceAccountBalanceResolver::resolveOpeningBalanceAtStartDate($account, $startDate);
            $closingBalance = FinanceAccountBalanceResolver::resolveClosingBalanceAsOf($account, $periodEnd, now()->toDateString());
            $row = [
                'id' => $account->id,
                'name' => $account->name,
                'code' => $account->code,
                'type' => $account->account_type,
            ];
            $openingAccountRows[] = $row + ['balance' => $openingBalance];
            $closingAccountRows[] = $row + ['balance' => $closingBalance];
        }

        $piutangRows = $this->payablesReceivablesService()->getPiutangDataset($pharmacyIds, [], false)['rows'];
        $receivablesByDebtor = [];
        foreach ($piutangRows as $row) {
            $outstanding = (float) ($row['sisa'] ?? 0);
            if ($outstanding <= 0) {
                continue;
            }
            $key = (string) ((int) ($row['debtor_id'] ?? 0));
            if (!isset($receivablesByDebtor[$key])) {
                $receivablesByDebtor[$key] = [
                    'name' => ($row['debtor'] ?? '') ?: 'Debitur tidak tercatat',
                    'amount' => 0.0,
                ];
            }
            $receivablesByDebtor[$key]['amount'] += $outstanding;
        }
        $receivablesByDebtor = array_values(array_filter($receivablesByDebtor, fn($row) => $row['amount'] > 0));
        usort($receivablesByDebtor, fn($a, $b) => $b['amount'] <=> $a['amount']);
        $totalReceivables = array_sum(array_column($receivablesByDebtor, 'amount'));

        $hutangRows = $this->payablesReceivablesService()->getHutangDataset($pharmacyIds, [], false, null, 1, false)['rows'];
        $payablesByCreditor = [];
        foreach ($hutangRows as $row) {
            $outstanding = (float) ($row['sisa'] ?? 0);
            if ($outstanding <= 0) {
                continue;
            }
            $creditorCode = (string) ($row['vendor_code'] ?? '');
            $key = $creditorCode !== '' ? $creditorCode : 'unknown';
            if (!isset($payablesByCreditor[$key])) {
                $payablesByCreditor[$key] = [
                    'name' => ($row['vendor'] ?? '') ?: ($creditorCode !== '' ? "Supplier {$creditorCode}" : 'Supplier tidak tercatat'),
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
            ->postedToStock()
            ->whereHas('receiving', fn($query) => $query->whereIn('pharmacy_id', $pharmacyIds))
            ->where(function ($dateQ) use ($startDate, $endDate) {
                $dateQ
                    ->whereBetween('receiving_details.created_at', [FinanceShift::start($startDate), FinanceShift::endExclusive($endDate)])
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
        return $this->payablesReceivablesService()->getHutangDataset(
            $this->getTargetPharmacyIds(),
            [],
            true,
            null,
            1
        )['rows'];
    }

    /**
     * Helper: Ambil data faktur pembelian CASH (Tunai).
     */
    protected function getCashData()
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        Receiving::syncPostedZeroStatus($targetPharmacyIds);

        $receivingsCash = Receiving::with([
            'receiving_details' => function ($query) {
                $query->where('invoice_payment', 'TUNAI')->postedToStock();
            },
            'receiving_details.creditor',
            'receiving_details.payments.account',
            'receiving_details.payments.creator',
            'receiving_details.receiving_items' => fn($query) => $query->whereNotNull('batches_id')->with('order_items.medicines'),
            'pharmacy'
        ])
            ->whereIn('pharmacy_id', $targetPharmacyIds)
            ->whereHas('receiving_details', function ($query) {
                $query->where('invoice_payment', 'TUNAI')->postedToStock();
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
        return $this->payablesReceivablesService()->getPiutangDataset(
            $this->getTargetPharmacyIds(),
            [],
            true,
            null,
            1
        )['rows'];
    }

    /**
     * Helper: Ambil daftar seluruh Transaksi Retur Penjualan Kredit.
     */
    protected function getCreditReturnsData()
    {
        return $this->payablesReceivablesService()->getCreditReturnsDataset(
            $this->getTargetPharmacyIds(),
            [],
            null,
            1
        )['rows'];
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
            'openingInvoice.creditor',
            'openingInvoice.debtor',
        ])
            ->whereHas('account', function ($q) {
                $q->where('category', 'Kas & Bank');
                $q->where('pharmacy_id', getActivePharmacyId());
            });
        $this->applyMutasiBranchScope($query, $targetPharmacyIds);

        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        }

        $query
            ->orderByDesc('payment_date')
            ->orderByDesc('id');

        if ($limit) {
            $query->take($limit);
        }

        return $query
            ->get()
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
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = 'Penerimaan penjualan tunai kasir';
        } elseif ($p->payment_type === 'PENJUALAN NON TUNAI') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->transaction_code ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi) {
                $method = $p->transaction->payment_method ?? 'NON-TUNAI';
                $bank = $p->transaction->transfer_bank_name ?? ($p->account->name ?? 'Bank');
                $deskripsi = "Penerimaan penjualan {$method} melalui {$bank}";
            }
        } elseif ($p->payment_type === 'PIUTANG') {
            $pihakTerkait = $p->openingInvoice?->debtor?->name
                ?? $p->transaction?->debtors?->name
                ?? ($p->transaction?->patients?->name ?? 'Pelanggan Umum');
            $doc = $p->openingInvoice?->invoice_number ?? $p->transaction?->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Penerimaan piutang penjualan dari {$pihakTerkait}";
        } elseif ($p->payment_type === 'KREDIT') {
            $pihakTerkait = $p->openingInvoice?->creditor?->name
                ?? ($p->openingInvoice?->creditor_code ?: null)
                ?? $p->receivingDetail?->creditor?->name
                ?? ($p->receivingDetail?->creditor_code ?? 'PBF / Vendor');
            $doc = $p->openingInvoice?->invoice_number ?? $p->receivingDetail?->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Pelunasan hutang pembelian ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'CASH') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Pembelian tunai (cash) ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'KONSINYASI') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Pembayaran barang konsinyasi terjual ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'BIAYA') {
            $pihakTerkait = $p->recipient ?: ($p->expenseAccount->name ?? 'Biaya Operasional');
            $doc = $p->reference_number ?: ('BY-' . str_pad($p->id, 5, '0', STR_PAD_LEFT));
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = 'Pengeluaran biaya: ' . ($p->expenseAccount->name ?? 'Operasional');
        } elseif ($p->payment_type === 'PENDAPATAN') {
            $pihakTerkait = $p->recipient ?: ($p->incomeAccount->name ?? 'Pendapatan Lain-lain');
            if (!$p->reference_number)
                $noDokumen = 'PL-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            if (!$deskripsi)
                $deskripsi = 'Pendapatan lain-lain: ' . ($p->incomeAccount->name ?? 'Pendapatan');
        } elseif ($p->payment_type === 'RETUR PENJUALAN') {
            $pihakTerkait = $p->transaction->patients->name ?? ($p->transaction->debtors->name ?? 'Pelanggan Umum');
            $doc = $p->reference_number ?: ('RT-' . $p->id);
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = 'Pengembalian dana retur penjualan tunai kasir';
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
     * Helper: Filter cabang tunggal untuk seluruh query Mutasi & Ringkasan Kas & Bank
     * agar angka pada kartu rekening selalu konsisten 100% dengan tabel mutasi di bawahnya.
     */
    protected function applyMutasiBranchScope($query, array $targetPharmacyIds)
    {
        return $query
            ->whereNotIn('payment_type', ['KREDIT CUTOFF', 'PIUTANG CUTOFF', 'RETUR'])
            ->where(function ($q) use ($targetPharmacyIds) {
            $q
                ->whereHas('receiving', function ($sub) use ($targetPharmacyIds) {
                    $sub->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhereHas('transaction', function ($sub) use ($targetPharmacyIds) {
                    $sub->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->whereNotNull('opening_invoice_id')
                        ->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub
                        ->where('payment_type', 'BIAYA')
                        ->where(function ($sub2) use ($targetPharmacyIds) {
                            $sub2
                                ->whereIn('pharmacy_id', $targetPharmacyIds)
                                ->orWhereNull('pharmacy_id');
                        });
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('payment_type', 'PENDAPATAN')->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('payment_type', 'SETOR')->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub->where('payment_type', 'TARIK TUNAI')->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhere(function ($sub) use ($targetPharmacyIds) {
                    $sub
                        ->whereIn('payment_type', ['PENJUALAN TUNAI', 'PENJUALAN NON TUNAI', 'RETUR PENJUALAN'])
                        ->whereIn('pharmacy_id', $targetPharmacyIds);
                })
                ->orWhere('payment_type', 'SALDO AWAL')
                ->orWhere('payment_type', 'PENYESUAIAN SALDO');
        });
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
                'account_type' => $acc->account_type ?: ($acc->code === '1-10001' ? 'kas_apotek' : 'bank'),
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
        $regularInflowsQuery = FinancePayment::query()
            ->select('account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd]);
        $this->applyMutasiBranchScope($regularInflowsQuery, $targetPharmacyIds);
        $regularInflows = $regularInflowsQuery->groupBy('account_id')->get();

        foreach ($regularInflows as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['inflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 2. Outflows: KREDIT, CASH, KONSINYASI, BIAYA, RETUR PENJUALAN
        $regularOutflowsQuery = FinancePayment::query()
            ->select('account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'RETUR PENJUALAN'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd]);
        $this->applyMutasiBranchScope($regularOutflowsQuery, $targetPharmacyIds);
        $regularOutflows = $regularOutflowsQuery->groupBy('account_id')->get();

        foreach ($regularOutflows as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['outflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 3. Penyesuaian Saldo (Opname) & Saldo Awal: dikecualikan dari Masuk/Keluar operasional, hanya dihitung pada jumlah mutasi
        $nonOperationalCountsQuery = FinancePayment::query()
            ->select('account_id', DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['PENYESUAIAN SALDO', 'SALDO AWAL'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd]);
        $this->applyMutasiBranchScope($nonOperationalCountsQuery, $targetPharmacyIds);
        $nonOperationalCounts = $nonOperationalCountsQuery->groupBy('account_id')->get();

        foreach ($nonOperationalCounts as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        // 4. Transfers: SETOR & TARIK TUNAI
        $transfersOutQuery = FinancePayment::query()
            ->select('account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('account_id', $accountIds)
            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd]);
        $this->applyMutasiBranchScope($transfersOutQuery, $targetPharmacyIds);
        $transfersOut = $transfersOutQuery->groupBy('account_id')->get();

        foreach ($transfersOut as $row) {
            if (isset($accountStats[$row->account_id])) {
                $accountStats[$row->account_id]['outflow'] += (float) $row->total_amount;
                $accountStats[$row->account_id]['txCount'] += (int) $row->total_count;
            }
        }

        $transfersInQuery = FinancePayment::query()
            ->select('transfer_account_id as account_id', DB::raw('SUM(amount) as total_amount'), DB::raw('COUNT(*) as total_count'))
            ->whereIn('transfer_account_id', $accountIds)
            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI'])
            ->whereBetween('payment_date', [$monthStart, $monthEnd]);
        $this->applyMutasiBranchScope($transfersInQuery, $targetPharmacyIds);
        $transfersIn = $transfersInQuery->groupBy('transfer_account_id')->get();

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
            'openingInvoice.creditor',
            'openingInvoice.debtor',
        ])
            ->whereHas('account', function ($q) {
                $q->where('category', 'Kas & Bank');
                $q->where('pharmacy_id', getActivePharmacyId());
                $q->where('is_active', true);
            });
        $this->applyMutasiBranchScope($query, $targetPharmacyIds);

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
                $q
                    ->where('account_id', $filterAccountId)
                    ->orWhere(function ($sub) use ($filterAccountId) {
                        $sub
                            ->where('transfer_account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
            });
        }

        // Direction filter (kecualikan PENYESUAIAN SALDO agar konsisten dengan Masuk/Keluar pada kartu rekening)
        if ($filterDirection === 'IN') {
            $query->where(function ($q) use ($filterAccountId) {
                $q->whereIn('payment_type', ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI']);
                if ($filterAccountId) {
                    $q->orWhere(function ($sub) use ($filterAccountId) {
                        $sub
                            ->where('transfer_account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
                }
            });
        } elseif ($filterDirection === 'OUT') {
            $query->where(function ($q) use ($filterAccountId) {
                $q->whereIn('payment_type', ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'RETUR PENJUALAN']);
                if ($filterAccountId) {
                    $q->orWhere(function ($sub) use ($filterAccountId) {
                        $sub
                            ->where('account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
                }
            });
        }

        // Search query
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q
                    ->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('recipient', 'like', "%{$search}%")
                    ->orWhereHas('transaction', function ($sub) use ($search) {
                        $sub
                            ->where('transaction_code', 'like', "%{$search}%")
                            ->orWhere('invoice_number', 'like', "%{$search}%")
                            ->orWhereHas('debtors', fn($d) => $d->where('name', 'like', "%{$search}%"))
                            ->orWhereHas('patients', fn($p) => $p->where('name', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('receivingDetail', function ($sub) use ($search) {
                        $sub
                            ->where('invoice_number', 'like', "%{$search}%")
                            ->orWhereHas('creditor', fn($c) => $c->where('name', 'like', "%{$search}%"));
                    })
                    ->orWhereHas('account', function ($sub) use ($search) {
                        $sub
                            ->where('name', 'like', "%{$search}%")
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
                $cacheKey = 'finance_monthly_sum_v2_' . implode('_', $pharmacyIds) . "_{$selectedYear}_{$m}";
                $computePastTotals = function () use ($pharmacyIds, $selectedYear, $m) {
                    $mStart = sprintf('%04d-%02d-01', $selectedYear, $m);
                    $mEnd = \Carbon\Carbon::createFromDate($selectedYear, $m, 1)->endOfMonth()->toDateString();
                    return $this->computePeriodTotals($pharmacyIds, $mStart, $mEnd);
                };
                try {
                    $pastTotals = Cache::remember($cacheKey, 86400, $computePastTotals);
                } catch (\Throwable $e) {
                    $pastTotals = $computePastTotals();
                }

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
     * Helper: Hitung nilai stok aktual (tidak termasuk barang titipan / Konsinyasi).
     * Cabang lain: stok etalase (medicine_transfer_items)
     * PMI: stok etalase (medicine_transfer_items) + stok gudang (batches)
     * Formula: Nilai stok = (raw_price * 1.11) * stok
     */
    protected function getStockValuation(): array
    {
        $activePharmacyId = (int) getActivePharmacyId();
        $cacheKey = 'finance_stock_valuation_v2_' . $activePharmacyId;

        $computeValuation = function () use ($activePharmacyId) {
            $isPmi = ($activePharmacyId <= 0 || $activePharmacyId === 1 || $activePharmacyId === 9);
            $counterPharmacyId = $isPmi ? 1 : $activePharmacyId;
            $warehouseId = function_exists('getWarehousePharmacyId') ? (int) getWarehousePharmacyId() : 9;

            // 1. Nilai Stok Etalase (medicine_transfer_items, kecualikan Konsinyasi)
            $counterQuery = DB::table('medicine_transfer_items as mti')
                ->join('batches as b', 'b.id', '=', 'mti.batches_id')
                ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                ->where('b.pharmacy_id', $counterPharmacyId)
                ->where('mti.status', 1)
                ->where('mti.qty', '>', 0)
                ->where(function ($q) {
                    $q->whereNull('mti.source_type')->orWhere('mti.source_type', '!=', 'retur_gudang');
                });

            ConsignmentService::excludeConsignmentFromTransferItemsQuery($counterQuery, 'mti', 'b');

            $counterRow = $counterQuery
                ->selectRaw('
                    COALESCE(SUM(mti.qty * CAST(REPLACE(COALESCE(NULLIF(m.raw_price, ""), NULLIF(m.pharmacy_net_price, ""), "0"), ",", ".") AS DECIMAL(15, 4)) * 1.11), 0) as total_val,
                    COALESCE(SUM(mti.qty), 0) as total_qty,
                    COUNT(DISTINCT b.medicine_id) as total_items
                ')
                ->first();

            $counterVal = (float) ($counterRow->total_val ?? 0);
            $counterQty = (float) ($counterRow->total_qty ?? 0);
            $counterItems = (int) ($counterRow->total_items ?? 0);

            // 2. Nilai Stok Gudang (hanya jika PMI, kecualikan Konsinyasi)
            $storageVal = 0.0;
            $storageQty = 0.0;
            $storageItems = 0;

            if ($isPmi) {
                $storageQuery = DB::table('batches as b')
                    ->join('medicines as m', 'm.id', '=', 'b.medicine_id')
                    ->where('b.pharmacy_id', $warehouseId)
                    ->whereRaw('CAST(REPLACE(COALESCE(NULLIF(b.stock, ""), "0"), ",", ".") AS DECIMAL(15, 2)) > 0');

                ConsignmentService::excludeConsignmentFromBatchesQuery($storageQuery, 'b');

                $storageRow = $storageQuery
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
        };

        try {
            return Cache::remember($cacheKey, 60, $computeValuation);
        } catch (\Throwable $e) {
            return $computeValuation();
        }
    }

    /**
     * Helper: Hitung ringkasan statistik modul finance.
     */
    protected function getStats($hutang = null, $cash = null, $piutang = null)
    {
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $cacheKey = 'finance_stats_summary_branch_v2_' . getActivePharmacyId() . '_' . implode('_', $targetPharmacyIds);

        $isPrecomputedHutangStats = is_array($hutang) && array_key_exists('totalHutangCount', $hutang);
        $isPrecomputedPiutangStats = is_array($piutang) && array_key_exists('totalPiutangCount', $piutang);

        // Jika data lengkap tidak disediakan, gunakan cache agar angka badge dan ringkasan selalu konsisten di semua halaman
        $isFullData = ($hutang !== null && $cash !== null && $piutang !== null);
        if (!$isFullData) {
            try {
                $cached = Cache::get($cacheKey);
            } catch (\Throwable $e) {
                $cached = null;
            }
            if ($cached && isset($cached['nilaiStok']) && isset($cached['countCashbondActive'])) {
                if ($hutang !== null) {
                    $cached = array_merge($cached, $isPrecomputedHutangStats ? $hutang : $this->summarizeHutangStats($hutang));
                }
                if ($piutang !== null) {
                    $cached = array_merge($cached, $isPrecomputedPiutangStats ? $piutang : $this->payablesReceivablesService()->summarizePiutangStats($piutang));
                }
                return $cached;
            }
        }

        if ($cash === null) {
            $cash = $this->getCashData();
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
        $hutangStats = $hutang === null
            ? $this->payablesReceivablesService()->getHutangDataset($targetPharmacyIds, [], false)['stats']
            : ($isPrecomputedHutangStats ? $hutang : $this->summarizeHutangStats($hutang));

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
        $piutangStats = $piutang === null
            ? $this->payablesReceivablesService()->getPiutangDataset($targetPharmacyIds, [], false)['stats']
            : ($isPrecomputedPiutangStats ? $piutang : $this->payablesReceivablesService()->summarizePiutangStats($piutang));

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

        $stats = array_merge([
            'totalSaldoKasBank' => $totalSaldoKasBank,
            'accountsCount' => $accountsCount,
            'totalNilaiStok' => $nilaiStok['total'],
            'nilaiStok' => $nilaiStok,
            'kasMasuk' => $kasMasuk,
            'kasKeluar' => $kasKeluar,
            'netCashflow' => $netCashflow,
            'totalCashPurchases' => $totalCashPurchases,
            'totalCashSpent' => $totalCashPurchases,
            'totalCashCount' => $totalCashCount,
            'countSudahPilihAkun' => $countSudahPilihAkun,
            'countBelumPilihAkun' => $countBelumPilihAkun,
            'totalNominalBelumPilihAkun' => $totalNominalBelumPilihAkun,
            'totalBiaya' => $kasKeluarBiaya,
            'countBiayaTotal' => $countBiayaTotal,
            'countBiayaMonth' => $countBiayaMonth,
            'countDiskonTotal' => $countDiskonTotal,
            'countDiskonMonth' => $countDiskonMonth,
            'countCashbondActive' => $countCashbondActive,
            'totalCashbondActive' => $totalCashbondActive,
        ], $piutangStats, $hutangStats);

        try {
            Cache::put($cacheKey, $stats, 60);
        } catch (\Throwable $e) {
            // Ignore file cache permission error on shared storage
        }

        return $stats;
    }

    /**
     * Helper: Hitung ringkasan saldo & status faktur hutang dagang.
     */
    protected function summarizeHutangStats(array $hutang): array
    {
        return $this->payablesReceivablesService()->summarizeHutangStats($hutang);
    }

    /**
     * Helper: Hapus cache ringkasan statistik modul finance.
     */
    protected function clearStatsCache()
    {
        try {
            Cache::forget('finance_stats_summary');
            $targetPharmacyIds = $this->getTargetPharmacyIds();
            $activeId = getActivePharmacyId();
            $branchSuffix = $activeId . '_' . implode('_', $targetPharmacyIds);
            Cache::forget('finance_stats_summary_branch_' . $branchSuffix);
            Cache::forget('finance_stats_summary_branch_v2_' . $branchSuffix);
            Cache::forget('finance_stats_summary_branch_v2_' . $activeId . '_' . implode('_', $targetPharmacyIds));
            $branches = [0, 1, 2, 3, 4, 5, 6, 9];
            foreach ($branches as $b) {
                Cache::forget('finance_stats_summary_' . $b);
                Cache::forget('finance_stock_valuation_' . $b);
                Cache::forget('finance_stock_valuation_v2_' . $b);
            }
            $currentYear = (int) now()->year;
            $pharmKey = implode('_', $targetPharmacyIds);
            for ($m = 1; $m <= 12; $m++) {
                Cache::forget("finance_monthly_sum_{$pharmKey}_{$currentYear}_{$m}");
                Cache::forget("finance_monthly_sum_v2_{$pharmKey}_{$currentYear}_{$m}");
            }
            Cache::forget('finance_stats_summary_1_9');
        } catch (\Throwable $e) {
            // Ignore file cache permission error on shared storage
        }
    }

    /**
     * Resolve akun referensi non-kas (FK account_id wajib terisi di tabel finance_payments)
     * untuk pelunasan pra-cut-off; saldo akun ini TIDAK pernah ditambah/dikurangi.
     */
    private function resolveCutoffReferenceAccountId(?int $pharmacyId = null): int
    {
        $targetPharmacyId = ($pharmacyId && $pharmacyId > 0) ? $pharmacyId : (int) getActivePharmacyId();
        $account = $this->branchKasBankAccountsQuery()
            ->orderByRaw("CASE WHEN account_type = 'kas_apotek' OR code = '1-10001' THEN 0 ELSE 1 END")
            ->orderBy('id')
            ->first();

        if (!$account && $targetPharmacyId > 0) {
            FinanceAccount::ensureBranchDefaultAccounts($targetPharmacyId);
            $account = FinanceAccount::where('category', 'Kas & Bank')
                ->where('pharmacy_id', $targetPharmacyId)
                ->orderBy('id')
                ->first();
        }

        if (!$account) {
            $account = FinanceAccount::where('category', 'Kas & Bank')->orderBy('id')->firstOrFail();
        }

        return (int) $account->id;
    }

    /**
     * Catat pembayaran faktur hutang dagang (Kredit).
     */
    public function storePayment(Request $request)
    {
        $isCutoff = $request->boolean('is_cutoff');

        $validated = $request->validate([
            'receiving_detail_id' => 'required',
            'account_id' => $isCutoff ? 'nullable|exists:finance_accounts,id' : 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'is_cutoff' => 'nullable|boolean',
        ]);

        $account = null;
        if (!$isCutoff) {
            $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);

            // Verifikasi akun harus ber-kategori 'Kas & Bank'
            if ($account->category !== 'Kas & Bank') {
                return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
            }
        }

        $openingId = FinanceOpeningInvoice::parseCompositeId($validated['receiving_detail_id']);
        if ($openingId !== null) {
            abort_unless(Schema::hasTable('finance_opening_invoices'), 404);
            $opening = FinanceOpeningInvoice::where('type', 'HUTANG')
                ->whereIn('pharmacy_id', $this->getTargetPharmacyIds())
                ->findOrFail($openingId);

            $alreadyPaid = (float) FinancePayment::where('opening_invoice_id', $opening->id)
                ->where('payment_type', '!=', 'RETUR')
                ->sum('amount');
            $sisaHutang = round(max(0, (float) $opening->amount - $alreadyPaid), 2);

            if (round($validated['amount'], 2) > $sisaHutang) {
                $formattedSisa = fmod($sisaHutang, 1) !== 0.0 ? number_format($sisaHutang, 2, ',', '.') : number_format($sisaHutang, 0, ',', '.');
                return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan (Rp ' . $formattedSisa . ').']);
            }

            $paymentAccountId = $isCutoff
                ? $this->resolveCutoffReferenceAccountId($opening->pharmacy_id)
                : $account->id;
            $notes = trim((string) ($validated['notes'] ?? ''));
            if ($isCutoff) {
                $notes = $notes !== ''
                    ? (str_contains(strtolower($notes), 'cut-off') ? $notes : '[Pra-Cut-off] ' . $notes)
                    : 'Pelunasan Pra-Cut-off Saldo Awal Hutang (Tanpa Potong Kas & Bank)';
            }

            DB::transaction(function () use ($validated, $opening, $account, $paymentAccountId, $isCutoff, $notes) {
                FinancePayment::create([
                    'pharmacy_id' => $opening->pharmacy_id,
                    'opening_invoice_id' => $opening->id,
                    'account_id' => $paymentAccountId,
                    'payment_type' => $isCutoff ? 'KREDIT CUTOFF' : 'KREDIT',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $validated['amount'],
                    'reference_number' => $validated['reference_number'] ?? ($isCutoff ? 'CUTOFF-HUTANG' : null),
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => Auth::id(),
                ]);

                if (!$isCutoff && $account) {
                    $account->decrement('balance', $validated['amount']);
                }
            });

            $this->clearStatsCache();
            return back()->with('success', $isCutoff
                ? 'Pelunasan pra-cut-off tagihan saldo awal hutang berhasil dicatat tanpa memotong Kas & Bank.'
                : 'Pembayaran tagihan saldo awal hutang dagang berhasil dicatat.');
        }

        $detail = ReceivingDetails::with(['receiving_items', 'payments', 'receiving'])->findOrFail($validated['receiving_detail_id']);

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

        $paymentAccountId = $isCutoff
            ? $this->resolveCutoffReferenceAccountId($detail->receiving?->pharmacy_id)
            : $account->id;
        $notes = trim((string) ($validated['notes'] ?? ''));
        if ($isCutoff) {
            $notes = $notes !== ''
                ? (str_contains(strtolower($notes), 'cut-off') ? $notes : '[Pra-Cut-off] ' . $notes)
                : 'Pelunasan Pra-Cut-off (Tanpa Potong Kas & Bank)';
        }

        DB::transaction(function () use ($validated, $detail, $account, $paymentAccountId, $isCutoff, $notes, $netInvoice) {
            // 1. Simpan Payment
            FinancePayment::create([
                'pharmacy_id' => $detail->receiving?->pharmacy_id,
                'receiving_id' => $detail->receiving_id,
                'receiving_detail_id' => $detail->id,
                'account_id' => $paymentAccountId,
                'payment_type' => $isCutoff ? 'KREDIT CUTOFF' : 'KREDIT',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? ($isCutoff ? 'CUTOFF-HUTANG' : null),
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => Auth::id(),
            ]);

            // 2. Kurangi saldo akun Kas & Bank (hanya untuk pembayaran kas/bank reguler, bukan pra-cut-off)
            if (!$isCutoff && $account) {
                $account->decrement('balance', $validated['amount']);
            }

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
        return back()->with('success', $isCutoff
            ? 'Pelunasan pra-cut-off hutang dagang berhasil dicatat tanpa memotong Kas & Bank.'
            : 'Pembayaran hutang dagang berhasil dicatat.');
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
     * Pelunasan massal (multi) faktur hutang dagang kredit (Maksimal 10 item, atau 50 item untuk Pra-Cut-off).
     */
    public function storeBulkPayments(Request $request)
    {
        $isCutoff = $request->boolean('is_cutoff');
        $maxItems = $isCutoff ? 50 : 10;

        $validated = $request->validate([
            'receiving_detail_ids' => "required|array|min:1|max:{$maxItems}",
            'receiving_detail_ids.*' => 'required',
            'account_id' => $isCutoff ? 'nullable|exists:finance_accounts,id' : 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'is_cutoff' => 'nullable|boolean',
        ]);

        $account = null;
        if (!$isCutoff) {
            $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
            if ($account->category !== 'Kas & Bank') {
                return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
            }
        }
        $cutoffAccountId = $isCutoff ? $this->resolveCutoffReferenceAccountId() : null;
        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $hasOpeningTable = Schema::hasTable('finance_opening_invoices');

        $totalPaidAll = 0;
        $processedCount = 0;

        DB::transaction(function () use ($validated, $account, $isCutoff, $cutoffAccountId, $targetPharmacyIds, $hasOpeningTable, &$totalPaidAll, &$processedCount) {
            foreach ($validated['receiving_detail_ids'] as $detailId) {
                $openingId = FinanceOpeningInvoice::parseCompositeId($detailId);
                if ($openingId !== null) {
                    if (!$hasOpeningTable) {
                        continue;
                    }
                    $opening = FinanceOpeningInvoice::where('type', 'HUTANG')
                        ->whereIn('pharmacy_id', $targetPharmacyIds)
                        ->find($openingId);
                    if (!$opening) {
                        continue;
                    }
                    $alreadyPaid = (float) FinancePayment::where('opening_invoice_id', $opening->id)
                        ->where('payment_type', '!=', 'RETUR')
                        ->sum('amount');
                    $remaining = round(max(0, (float) $opening->amount - $alreadyPaid), 2);
                    if ($remaining < 0.005) {
                        continue;
                    }

                    $rawNotes = trim((string) ($validated['notes'] ?? ''));
                    $finalNotes = $isCutoff
                        ? ($rawNotes !== '' ? (str_contains(strtolower($rawNotes), 'cut-off') ? $rawNotes : '[Pra-Cut-off] ' . $rawNotes) : 'Pelunasan Pra-Cut-off Massal Saldo Awal Hutang (Tanpa Potong Kas & Bank)')
                        : ($rawNotes !== '' ? ($rawNotes . ' (Pelunasan Massal)') : ('Pelunasan Massal via ' . $account->name));

                    FinancePayment::create([
                        'pharmacy_id' => $opening->pharmacy_id,
                        'opening_invoice_id' => $opening->id,
                        'account_id' => $isCutoff ? $cutoffAccountId : $account->id,
                        'payment_type' => $isCutoff ? 'KREDIT CUTOFF' : 'KREDIT',
                        'payment_date' => $validated['payment_date'],
                        'amount' => $remaining,
                        'reference_number' => $validated['reference_number'] ?? ($isCutoff ? 'CUTOFF-HUTANG' : null),
                        'notes' => $finalNotes,
                        'created_by' => Auth::id(),
                    ]);

                    $totalPaidAll += $remaining;
                    $processedCount++;
                    continue;
                }

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

                $rawNotes = trim((string) ($validated['notes'] ?? ''));
                $finalNotes = $isCutoff
                    ? ($rawNotes !== '' ? (str_contains(strtolower($rawNotes), 'cut-off') ? $rawNotes : '[Pra-Cut-off] ' . $rawNotes) : 'Pelunasan Pra-Cut-off Massal (Tanpa Potong Kas & Bank)')
                    : ($rawNotes !== '' ? ($rawNotes . ' (Pelunasan Massal)') : ('Pelunasan Massal via ' . $account->name));

                FinancePayment::create([
                    'pharmacy_id' => $detail->receiving?->pharmacy_id,
                    'receiving_id' => $detail->receiving_id,
                    'receiving_detail_id' => $detail->id,
                    'account_id' => $isCutoff ? $cutoffAccountId : $account->id,
                    'payment_type' => $isCutoff ? 'KREDIT CUTOFF' : 'KREDIT',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $remaining,
                    'reference_number' => $validated['reference_number'] ?? ($isCutoff ? 'CUTOFF-HUTANG' : null),
                    'notes' => $finalNotes,
                    'created_by' => Auth::id(),
                ]);

                $totalPaidAll += $remaining;
                $processedCount++;

                if ($detail->receiving) {
                    $detail->receiving->update(['status' => 3]);
                }
            }

            if (!$isCutoff && $account && $totalPaidAll > 0) {
                $account->decrement('balance', $totalPaidAll);
            }
        });

        $this->clearStatsCache();
        $formattedTotal = number_format($totalPaidAll, 0, ',', '.');
        return back()->with('success', $isCutoff
            ? "Berhasil melunasi pra-cut-off {$processedCount} faktur hutang dagang sebesar Rp {$formattedTotal} (tanpa memotong Kas & Bank)."
            : "Berhasil melunasi {$processedCount} faktur hutang dagang sebesar Rp {$formattedTotal} via {$account->name}.");
    }

    /**
     * Import pelunasan hutang dari spreadsheet dengan kolom faktur, tanggal bayar, dan kode bank.
     */
    public function importHutangPayments(Request $request)
    {
        $isCutoffImport = $request->boolean('is_cutoff');

        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'is_cutoff' => 'nullable|boolean',
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
            $rowIsCutoff = $isCutoffImport || in_array(mb_strtoupper($bankCode), ['CUTOFF', 'CUT-OFF', 'PRA-CUTOFF', 'NON-KAS'], true);
            if (!$rowIsCutoff && $bankCode === '')
                $errors[] = "Baris {$line}: Kode Bank kosong.";
            if ($invoiceNumber !== '' && isset($seenInvoices[mb_strtolower($invoiceNumber)])) {
                $errors[] = "Baris {$line}: No. Faktur {$invoiceNumber} tercantum lebih dari sekali.";
            }
            if ($invoiceNumber !== '')
                $seenInvoices[mb_strtolower($invoiceNumber)] = true;

            $account = $rowIsCutoff ? null : $accountsByCode->get(mb_strtolower($bankCode));
            if (!$rowIsCutoff && !$account && $bankCode !== '')
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
                ->postedToStock()
                ->whereHas('receiving', fn($query) => $query->whereIn('pharmacy_id', $pharmacyIds))
                ->get();
            $openingMatches = (Schema::hasTable('finance_opening_invoices') && $invoiceNumber !== '')
                ? FinanceOpeningInvoice::where('type', 'HUTANG')
                    ->where('invoice_number', $invoiceNumber)
                    ->whereIn('pharmacy_id', $pharmacyIds)
                    ->get()
                : collect();
            $totalMatches = $matches->count() + $openingMatches->count();

            if ($invoiceNumber !== '' && $totalMatches === 0) {
                $errors[] = "Baris {$line}: Faktur '{$invoiceNumber}' tidak ditemukan pada cabang yang dapat diakses.";
            } elseif ($totalMatches > 1) {
                $errors[] = "Baris {$line}: Faktur '{$invoiceNumber}' cocok ke lebih dari satu data. Periksa nomor faktur.";
            }

            if ($totalMatches === 1 && ($rowIsCutoff || $account) && $paymentDate) {
                $prepared[] = [
                    'line' => $line,
                    'detail' => $matches->count() === 1 ? $matches->first() : null,
                    'opening' => $openingMatches->count() === 1 ? $openingMatches->first() : null,
                    'account' => $account,
                    'is_cutoff' => $rowIsCutoff,
                    'payment_date' => $paymentDate,
                ];
            }
        }

        if (!$prepared && !$errors)
            $errors[] = 'Tidak ada baris data untuk diimpor.';
        if ($errors)
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);

        $hasAnyCutoff = collect($prepared)->contains(fn($e) => !empty($e['is_cutoff']));
        $cutoffAccountId = $hasAnyCutoff ? $this->resolveCutoffReferenceAccountId() : null;

        $totalPaid = 0.0;
        DB::transaction(function () use ($prepared, $cutoffAccountId, &$totalPaid) {
            foreach ($prepared as $entry) {
                $entryIsCutoff = !empty($entry['is_cutoff']);
                $account = $entryIsCutoff ? null : FinanceAccount::whereKey($entry['account']->id)->lockForUpdate()->firstOrFail();

                if (!empty($entry['opening'])) {
                    $opening = FinanceOpeningInvoice::whereKey($entry['opening']->id)->lockForUpdate()->firstOrFail();
                    $alreadyPaid = (float) FinancePayment::where('opening_invoice_id', $opening->id)
                        ->where('payment_type', '!=', 'RETUR')
                        ->sum('amount');
                    $remaining = round(max(0, (float) $opening->amount - $alreadyPaid), 2);
                    if ($remaining < 0.005) {
                        throw ValidationException::withMessages(['file' => "Baris {$entry['line']}: Tagihan saldo awal '{$opening->invoice_number}' sudah lunas."]);
                    }

                    FinancePayment::create([
                        'pharmacy_id' => $opening->pharmacy_id,
                        'opening_invoice_id' => $opening->id,
                        'account_id' => $entryIsCutoff ? $cutoffAccountId : $account->id,
                        'payment_type' => $entryIsCutoff ? 'KREDIT CUTOFF' : 'KREDIT',
                        'payment_date' => $entry['payment_date'],
                        'amount' => $remaining,
                        'reference_number' => $entryIsCutoff ? 'CUTOFF-HUTANG' : null,
                        'notes' => $entryIsCutoff
                            ? 'Pelunasan Pra-Cut-off Saldo Awal Hutang melalui impor file (Tanpa Potong Kas & Bank)'
                            : 'Pelunasan Saldo Awal Hutang Dagang melalui impor file',
                        'created_by' => Auth::id(),
                    ]);
                    if (!$entryIsCutoff && $account) {
                        $account->decrement('balance', $remaining);
                    }
                    $totalPaid += $remaining;
                    continue;
                }

                $detail = ReceivingDetails::whereKey($entry['detail']->id)->lockForUpdate()->firstOrFail();
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
                    'account_id' => $entryIsCutoff ? $cutoffAccountId : $account->id,
                    'payment_type' => $entryIsCutoff ? 'KREDIT CUTOFF' : 'KREDIT',
                    'payment_date' => $entry['payment_date'],
                    'amount' => $remaining,
                    'reference_number' => $entryIsCutoff ? 'CUTOFF-HUTANG' : null,
                    'notes' => $entryIsCutoff
                        ? 'Pelunasan Pra-Cut-off Hutang Dagang melalui impor file (Tanpa Potong Kas & Bank)'
                        : 'Pelunasan Hutang Dagang melalui impor file',
                    'created_by' => Auth::id(),
                ]);
                if (!$entryIsCutoff && $account) {
                    $account->decrement('balance', $remaining);
                }
                $totalPaid += $remaining;
                if ($detail->receiving)
                    $detail->receiving->update(['status' => 3]);
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Impor berhasil: ' . count($prepared) . ' faktur dilunasi dengan total Rp ' . number_format($totalPaid, 0, ',', '.') . ($isCutoffImport ? ' (Pra-Cut-off, tanpa memotong Kas & Bank).' : '.'));
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
        $isCutoff = $request->boolean('is_cutoff');

        $validated = $request->validate([
            'medicine_transaction_id' => 'required',
            'account_id' => $isCutoff ? 'nullable|exists:finance_accounts,id' : 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'amount' => 'required|numeric|gt:0',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'is_cutoff' => 'nullable|boolean',
        ]);

        $account = null;
        if (!$isCutoff) {
            $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);

            if ($account->category !== 'Kas & Bank') {
                return back()->withErrors(['account_id' => 'Akun pembayaran harus berkategori Kas & Bank.']);
            }
        }

        $openingId = FinanceOpeningInvoice::parseCompositeId($validated['medicine_transaction_id']);
        if ($openingId !== null) {
            abort_unless(Schema::hasTable('finance_opening_invoices'), 404);
            $opening = FinanceOpeningInvoice::where('type', 'PIUTANG')
                ->whereIn('pharmacy_id', $this->getTargetPharmacyIds())
                ->findOrFail($openingId);

            $totalTerbayarExisting = (float) FinancePayment::where('opening_invoice_id', $opening->id)
                ->where('payment_type', '!=', 'RETUR')
                ->sum('amount');
            $sisa = round(max(0, (float) $opening->amount - $totalTerbayarExisting), 2);

            if (round($validated['amount'], 2) > $sisa) {
                $formattedSisa = fmod($sisa, 1) !== 0.0 ? number_format($sisa, 2, ',', '.') : number_format($sisa, 0, ',', '.');
                return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan piutang (Rp ' . $formattedSisa . ').']);
            }

            $paymentAccountId = $isCutoff
                ? $this->resolveCutoffReferenceAccountId($opening->pharmacy_id)
                : $account->id;
            $notes = trim((string) ($validated['notes'] ?? ''));
            if ($isCutoff) {
                $notes = $notes !== ''
                    ? (str_contains(strtolower($notes), 'cut-off') ? $notes : '[Pra-Cut-off] ' . $notes)
                    : 'Pelunasan Pra-Cut-off Saldo Awal Piutang (Tanpa Tambah Kas & Bank)';
            }

            DB::beginTransaction();
            try {
                FinancePayment::create([
                    'pharmacy_id' => $opening->pharmacy_id,
                    'opening_invoice_id' => $opening->id,
                    'account_id' => $paymentAccountId,
                    'payment_type' => $isCutoff ? 'PIUTANG CUTOFF' : 'PIUTANG',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $validated['amount'],
                    'reference_number' => $validated['reference_number'] ?? ($isCutoff ? 'CUTOFF-PIUTANG' : null),
                    'notes' => $notes !== '' ? $notes : null,
                    'created_by' => Auth::id(),
                ]);

                if (!$isCutoff && $account) {
                    $account->increment('balance', $validated['amount']);
                }

                DB::commit();
                $this->clearStatsCache();
                return redirect()->back()->with('success', $isCutoff
                    ? 'Pelunasan pra-cut-off tagihan saldo awal piutang berhasil dicatat tanpa menambah Kas & Bank.'
                    : 'Penerimaan pembayaran tagihan saldo awal piutang berhasil dicatat.');
            } catch (\Exception $e) {
                DB::rollBack();
                return back()->withErrors(['error' => 'Gagal mencatat penerimaan pembayaran piutang: ' . $e->getMessage()]);
            }
        }

        $tx = MedicineTransactions::with(['payments', 'returs'])->findOrFail($validated['medicine_transaction_id']);

        $totalRetur = (float) $tx->returs->sum('total_retur');
        $totalTerbayarExisting = (float) $tx->payments->sum('amount');
        $totalNominal = (float) $tx->subtotal;
        $netNominal = max(0, $totalNominal - $totalRetur);
        $sisa = round(max(0, $netNominal - $totalTerbayarExisting), 2);

        if (round($validated['amount'], 2) > $sisa) {
            $formattedSisa = fmod($sisa, 1) !== 0.0 ? number_format($sisa, 2, ',', '.') : number_format($sisa, 0, ',', '.');
            return back()->withErrors(['amount' => 'Nominal pembayaran tidak boleh melebihi sisa tagihan piutang (Rp ' . $formattedSisa . ').']);
        }

        $paymentAccountId = $isCutoff
            ? $this->resolveCutoffReferenceAccountId($tx->pharmacy_id)
            : $account->id;
        $notes = trim((string) ($validated['notes'] ?? ''));
        if ($isCutoff) {
            $notes = $notes !== ''
                ? (str_contains(strtolower($notes), 'cut-off') ? $notes : '[Pra-Cut-off] ' . $notes)
                : 'Pelunasan Pra-Cut-off Piutang (Tanpa Tambah Kas & Bank)';
        }

        DB::beginTransaction();
        try {
            FinancePayment::create([
                'pharmacy_id' => $tx->pharmacy_id,
                'medicine_transaction_id' => $tx->id,
                'account_id' => $paymentAccountId,
                'payment_type' => $isCutoff ? 'PIUTANG CUTOFF' : 'PIUTANG',
                'payment_date' => $validated['payment_date'],
                'amount' => $validated['amount'],
                'reference_number' => $validated['reference_number'] ?? ($isCutoff ? 'CUTOFF-PIUTANG' : null),
                'notes' => $notes !== '' ? $notes : null,
                'created_by' => Auth::id(),
            ]);

            $newTotalPaid = round($totalTerbayarExisting + $validated['amount'], 2);
            $tx->paid = (string) $newTotalPaid;
            $tx->save();

            // Tambah saldo akun Kas & Bank (hanya untuk penerimaan kas/bank reguler, bukan pra-cut-off)
            if (!$isCutoff && $account) {
                $account->increment('balance', $validated['amount']);
            }

            DB::commit();

            $this->clearStatsCache();
            return redirect()->back()->with('success', $isCutoff
                ? 'Pelunasan pra-cut-off piutang berhasil dicatat tanpa menambah Kas & Bank.'
                : 'Penerimaan pembayaran piutang berhasil dicatat.');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->withErrors(['error' => 'Gagal mencatat penerimaan pembayaran piutang: ' . $e->getMessage()]);
        }
    }

    /**
     * Pelunasan massal piutang penjualan (Maks. 10 Faktur, atau 50 untuk Pra-Cut-off).
     */
    public function bulkPiutangPayment(Request $request)
    {
        // Mendukung kedua nama parameter: transaction_ids dan medicine_transaction_ids
        $ids = $request->input('transaction_ids') ?? $request->input('medicine_transaction_ids');
        if ($ids) {
            $request->merge(['transaction_ids' => $ids]);
        }

        $isCutoff = $request->boolean('is_cutoff');
        $maxItems = $isCutoff ? 50 : 10;

        $validated = $request->validate([
            'transaction_ids' => "required|array|min:1|max:{$maxItems}",
            'transaction_ids.*' => 'required',
            'account_id' => $isCutoff ? 'nullable|exists:finance_accounts,id' : 'required|exists:finance_accounts,id',
            'payment_date' => 'required|date',
            'reference_number' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:500',
            'is_cutoff' => 'nullable|boolean',
        ], [
            'transaction_ids.required' => 'Pilih minimal 1 transaksi piutang untuk dilunasi.',
            'transaction_ids.min' => 'Pilih minimal 1 transaksi piutang.',
            'transaction_ids.max' => "Maksimal {$maxItems} transaksi piutang yang dapat dilunasi sekaligus.",
            'account_id.required' => 'Silakan pilih akun Kas & Bank penerima.',
        ]);

        $account = null;
        if (!$isCutoff) {
            $account = $this->branchKasBankAccountsQuery()->findOrFail($validated['account_id']);
            if ($account->category !== 'Kas & Bank') {
                return back()->withErrors(['account_id' => 'Akun kas/bank yang dipilih harus berkategori Kas & Bank.']);
            }
        }
        $cutoffAccountId = $isCutoff ? $this->resolveCutoffReferenceAccountId() : null;

        $openingIds = [];
        $txIds = [];
        foreach ($validated['transaction_ids'] as $rawId) {
            $parsedOpeningId = FinanceOpeningInvoice::parseCompositeId($rawId);
            if ($parsedOpeningId !== null) {
                $openingIds[] = $parsedOpeningId;
            } elseif (is_numeric($rawId)) {
                $txIds[] = (int) $rawId;
            }
        }

        $transactions = !empty($txIds)
            ? MedicineTransactions::with(['payments', 'returs'])->whereIn('id', $txIds)->get()
            : collect();
        $openingInvoices = (!empty($openingIds) && Schema::hasTable('finance_opening_invoices'))
            ? FinanceOpeningInvoice::where('type', 'PIUTANG')
                ->whereIn('pharmacy_id', $this->getTargetPharmacyIds())
                ->whereIn('id', $openingIds)
                ->get()
            : collect();

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

                $rawNotes = trim((string) ($validated['notes'] ?? ''));
                $finalNotes = $isCutoff
                    ? ($rawNotes !== '' ? (str_contains(strtolower($rawNotes), 'cut-off') ? $rawNotes : '[Pra-Cut-off] ' . $rawNotes) : 'Pelunasan Pra-Cut-off Massal Piutang (Tanpa Tambah Kas & Bank)')
                    : ($rawNotes ?: 'Pelunasan massal piutang penjualan');

                FinancePayment::create([
                    'pharmacy_id' => $tx->pharmacy_id,
                    'medicine_transaction_id' => $tx->id,
                    'account_id' => $isCutoff ? $cutoffAccountId : $account->id,
                    'payment_type' => $isCutoff ? 'PIUTANG CUTOFF' : 'PIUTANG',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $sisa,
                    'reference_number' => $validated['reference_number'] ?: ($isCutoff ? 'CUTOFF-PIUTANG' : ('BULK-PIUTANG-' . date('YmdHis'))),
                    'notes' => $finalNotes,
                    'created_by' => Auth::id(),
                ]);

                $tx->paid = (string) round($totalPaid + $sisa, 2);
                $tx->save();

                // Tambah saldo akun Kas & Bank (hanya untuk penerimaan reguler)
                if (!$isCutoff && $account) {
                    $account->increment('balance', $sisa);
                }

                $processedCount++;
            }

            foreach ($openingInvoices as $opening) {
                $totalPaid = (float) FinancePayment::where('opening_invoice_id', $opening->id)
                    ->where('payment_type', '!=', 'RETUR')
                    ->sum('amount');
                $sisa = round(max(0, (float) $opening->amount - $totalPaid), 2);

                if ($sisa < 0.005) {
                    continue;
                }

                $rawNotes = trim((string) ($validated['notes'] ?? ''));
                $finalNotes = $isCutoff
                    ? ($rawNotes !== '' ? (str_contains(strtolower($rawNotes), 'cut-off') ? $rawNotes : '[Pra-Cut-off] ' . $rawNotes) : 'Pelunasan Pra-Cut-off Massal Saldo Awal Piutang (Tanpa Tambah Kas & Bank)')
                    : ($rawNotes ?: 'Pelunasan massal saldo awal piutang');

                FinancePayment::create([
                    'pharmacy_id' => $opening->pharmacy_id,
                    'opening_invoice_id' => $opening->id,
                    'account_id' => $isCutoff ? $cutoffAccountId : $account->id,
                    'payment_type' => $isCutoff ? 'PIUTANG CUTOFF' : 'PIUTANG',
                    'payment_date' => $validated['payment_date'],
                    'amount' => $sisa,
                    'reference_number' => $validated['reference_number'] ?: ($isCutoff ? 'CUTOFF-PIUTANG' : ('BULK-PIUTANG-' . date('YmdHis'))),
                    'notes' => $finalNotes,
                    'created_by' => Auth::id(),
                ]);

                if (!$isCutoff && $account) {
                    $account->increment('balance', $sisa);
                }

                $processedCount++;
            }

            DB::commit();

            $this->clearStatsCache();
            return redirect()->back()->with('success', $isCutoff
                ? "Berhasil melunasi pra-cut-off {$processedCount} tagihan piutang penjualan (tanpa menambah Kas & Bank)."
                : "Berhasil melunasi {$processedCount} tagihan piutang penjualan.");
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
        $isCutoffImport = $request->boolean('is_cutoff');
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv,txt|max:10240',
            'is_cutoff' => 'nullable|boolean',
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
            $rowIsCutoff = $isCutoffImport || in_array(mb_strtoupper($bankCode), ['CUTOFF', 'CUT-OFF', 'PRA-CUTOFF', 'NON-KAS'], true);
            if (!$rowIsCutoff && $bankCode === '')
                $errors[] = "Baris {$line}: Kode Bank kosong.";
            $invoiceKey = mb_strtolower($invoiceNumber);
            if ($invoiceNumber !== '' && isset($seenInvoices[$invoiceKey]))
                $errors[] = "Baris {$line}: No. Faktur/Transaksi '{$invoiceNumber}' tercantum lebih dari sekali.";
            if ($invoiceNumber !== '')
                $seenInvoices[$invoiceKey] = true;

            $account = $rowIsCutoff ? null : $accountsByCode->get(mb_strtolower($bankCode));
            if (!$rowIsCutoff && !$account && $bankCode !== '')
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

            $matchesQuery = MedicineTransactions::whereIn('transaction_type', ['KREDIT', 'RESEP KREDIT'])
                ->where('transaction_code', $invoiceNumber)
                ->whereIn('pharmacy_id', $pharmacyIds)
                ->with('transactions');
            \App\Support\LiphTransactionScope::applySalesStatus($matchesQuery);
            $matches = $matchesQuery->get();
            $openingMatches = (Schema::hasTable('finance_opening_invoices') && $invoiceNumber !== '')
                ? FinanceOpeningInvoice::where('type', 'PIUTANG')
                    ->where('invoice_number', $invoiceNumber)
                    ->whereIn('pharmacy_id', $pharmacyIds)
                    ->get()
                : collect();
            $totalMatches = $matches->count() + $openingMatches->count();

            if ($invoiceNumber !== '' && $totalMatches === 0) {
                $errors[] = "Baris {$line}: Faktur/Transaksi '{$invoiceNumber}' tidak ditemukan pada cabang yang dapat diakses.";
            } elseif ($totalMatches > 1) {
                $errors[] = "Baris {$line}: Nomor '{$invoiceNumber}' cocok ke lebih dari satu transaksi.";
            }

            if ($totalMatches === 1 && ($rowIsCutoff || $account) && $paymentDate) {
                $prepared[] = [
                    'line' => $line,
                    'transaction' => $matches->count() === 1 ? $matches->first() : null,
                    'opening' => $openingMatches->count() === 1 ? $openingMatches->first() : null,
                    'account' => $account,
                    'is_cutoff' => $rowIsCutoff,
                    'payment_date' => $paymentDate,
                ];
            }
        }

        if (!$prepared && !$errors)
            $errors[] = 'Tidak ada baris data untuk diimpor.';
        if ($errors)
            throw ValidationException::withMessages(['file' => implode("\n", $errors)]);

        $hasAnyCutoff = collect($prepared)->contains(fn($e) => !empty($e['is_cutoff']));
        $cutoffAccountId = $hasAnyCutoff ? $this->resolveCutoffReferenceAccountId() : null;

        $totalReceived = 0.0;
        DB::transaction(function () use ($prepared, $cutoffAccountId, &$totalReceived) {
            foreach ($prepared as $entry) {
                $entryIsCutoff = !empty($entry['is_cutoff']);
                $account = $entryIsCutoff ? null : FinanceAccount::whereKey($entry['account']->id)->lockForUpdate()->firstOrFail();

                if (!empty($entry['opening'])) {
                    $opening = FinanceOpeningInvoice::whereKey($entry['opening']->id)->lockForUpdate()->firstOrFail();
                    $alreadyPaid = (float) FinancePayment::where('opening_invoice_id', $opening->id)
                        ->where('payment_type', '!=', 'RETUR')
                        ->sum('amount');
                    $remaining = round(max(0, (float) $opening->amount - $alreadyPaid), 2);
                    if ($remaining < 0.005) {
                        throw ValidationException::withMessages(['file' => "Baris {$entry['line']}: Tagihan saldo awal piutang '{$opening->invoice_number}' sudah lunas."]);
                    }

                    FinancePayment::create([
                        'pharmacy_id' => $opening->pharmacy_id,
                        'opening_invoice_id' => $opening->id,
                        'account_id' => $entryIsCutoff ? $cutoffAccountId : $account->id,
                        'payment_type' => $entryIsCutoff ? 'PIUTANG CUTOFF' : 'PIUTANG',
                        'payment_date' => $entry['payment_date'],
                        'amount' => $remaining,
                        'reference_number' => $entryIsCutoff ? 'CUTOFF-PIUTANG' : null,
                        'notes' => $entryIsCutoff
                            ? 'Pelunasan Pra-Cut-off Saldo Awal Piutang melalui impor file (Tanpa Tambah Kas & Bank)'
                            : 'Pelunasan Saldo Awal Piutang melalui impor file',
                        'created_by' => Auth::id(),
                    ]);
                    if (!$entryIsCutoff && $account) {
                        $account->increment('balance', $remaining);
                    }
                    $totalReceived += $remaining;
                    continue;
                }

                $tx = MedicineTransactions::whereKey($entry['transaction']->id)->lockForUpdate()->firstOrFail();
                $tx->load(['transactions', 'returs']);
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
                    'account_id' => $entryIsCutoff ? $cutoffAccountId : $account->id,
                    'payment_type' => $entryIsCutoff ? 'PIUTANG CUTOFF' : 'PIUTANG',
                    'payment_date' => $entry['payment_date'],
                    'amount' => $remaining,
                    'reference_number' => $entryIsCutoff ? 'CUTOFF-PIUTANG' : null,
                    'notes' => $entryIsCutoff
                        ? 'Pelunasan Pra-Cut-off Piutang melalui impor file (Tanpa Tambah Kas & Bank)'
                        : 'Pelunasan Piutang melalui impor file',
                    'created_by' => Auth::id(),
                ]);
                $tx->paid = (string) round($alreadyPaid + $remaining, 2);
                $tx->save();
                if (!$entryIsCutoff && $account) {
                    $account->increment('balance', $remaining);
                }
                $totalReceived += $remaining;
            }
        });

        $this->clearStatsCache();
        return back()->with('success', 'Impor berhasil: ' . count($prepared) . ' transaksi piutang dilunasi dengan total Rp ' . number_format($totalReceived, 0, ',', '.') . ($isCutoffImport ? ' (Pra-Cut-off, tanpa menambah Kas & Bank).' : '.'));
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
            'start_date' => $request->query('start_date', ''),
            'end_date' => $request->query('end_date', ''),
            'sort_by' => $request->query('sort_by', ''),
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
            if ($month < 1 || $month > 12)
                $month = \Carbon\Carbon::now()->month;
            if ($year < 2020 || $year > 2100)
                $year = \Carbon\Carbon::now()->year;

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

        $openingId = FinanceOpeningInvoice::parseCompositeId($id);
        if ($openingId !== null) {
            abort_unless(Schema::hasTable('finance_opening_invoices'), 404);
            $opening = FinanceOpeningInvoice::where('type', 'HUTANG')
                ->whereIn('pharmacy_id', $this->getTargetPharmacyIds())
                ->findOrFail($openingId);
            $opening->is_submitted = $validated['is_submitted'];
            $opening->save();
            return redirect()->back()->with('success', 'Status pengajuan hutang berhasil diperbarui.');
        }

        $item = ReceivingDetails::findOrFail($id);

        $item->is_submitted = $validated['is_submitted'];
        $item->save();

        return redirect()->back()->with('success', 'Status pengajuan hutang berhasil diperbarui.');
    }

    public function submitPiutang(Request $request, $id)
    {
        $validated = $request->validate(['is_submitted' => 'required|boolean']);

        $openingId = FinanceOpeningInvoice::parseCompositeId($id);
        if ($openingId !== null) {
            abort_unless(Schema::hasTable('finance_opening_invoices'), 404);
            $opening = FinanceOpeningInvoice::where('type', 'PIUTANG')
                ->whereIn('pharmacy_id', $this->getTargetPharmacyIds())
                ->findOrFail($openingId);
            $opening->is_submitted = $validated['is_submitted'];
            $opening->save();
            return redirect()->back()->with('success', 'Status pengajuan piutang berhasil diperbarui.');
        }

        $item = MedicineTransactions::findOrFail($id);

        $item->is_submitted = $validated['is_submitted'];
        $item->save();

        return redirect()->back()->with('success', 'Status pengajuan piutang berhasil diperbarui.');
    }

    public function bulkSubmitHutang(Request $request)
    {
        $validated = $request->validate([
            'ids' => 'required|array',
            'ids.*' => 'required',
            'is_submitted' => 'required|boolean'
        ]);

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $hasOpeningTable = Schema::hasTable('finance_opening_invoices');

        foreach ($validated['ids'] as $id) {
            $openingId = FinanceOpeningInvoice::parseCompositeId($id);
            if ($openingId !== null) {
                if (!$hasOpeningTable) {
                    continue;
                }
                $opening = FinanceOpeningInvoice::where('type', 'HUTANG')
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->find($openingId);
                if ($opening) {
                    $opening->is_submitted = $validated['is_submitted'];
                    $opening->save();
                }
                continue;
            }
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
            'ids.*' => 'required',
            'is_submitted' => 'required|boolean'
        ]);

        $targetPharmacyIds = $this->getTargetPharmacyIds();
        $hasOpeningTable = Schema::hasTable('finance_opening_invoices');

        foreach ($validated['ids'] as $id) {
            $openingId = FinanceOpeningInvoice::parseCompositeId($id);
            if ($openingId !== null) {
                if (!$hasOpeningTable) {
                    continue;
                }
                $opening = FinanceOpeningInvoice::where('type', 'PIUTANG')
                    ->whereIn('pharmacy_id', $targetPharmacyIds)
                    ->find($openingId);
                if ($opening) {
                    $opening->is_submitted = $validated['is_submitted'];
                    $opening->save();
                }
                continue;
            }
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
