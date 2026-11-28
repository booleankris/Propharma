<?php

namespace Tests\Unit;

use App\Exports\Finance\NeracaExport;
use App\Http\Controllers\FinanceController;
use App\Models\Creditor;
use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinanceOpeningInvoice;
use App\Models\FinancePayment;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\User;
use App\Services\ConsignmentService;
use App\Services\Finance\CashflowTradeReportService;
use App\Services\Finance\NeracaReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Excel as ExcelFormat;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Tests\TestCase;

class NeracaOpnameAndOpeningAdjustmentTest extends TestCase
{
    private Pharmacies $branch;
    private FinanceAccount $kas;
    private FinanceAccount $bank;
    private Creditor $creditor;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::create(2026, 12, 5, 12, 0, 0));

        ConsignmentService::clearStockFilterCache();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        Schema::dropAllTables();

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('role_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table) {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('ppn_type')->default('TANPA');
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->decimal('raw_price', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name')->nullable();
            $table->date('expired_date')->nullable();
            $table->decimal('stock', 15, 2)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transfer_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('etalases_id')->nullable();
            $table->string('source_type')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('original_qty', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(2);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
            $table->string('sp_code')->nullable();
            $table->string('receiving_details_code')->nullable();
            $table->string('creditor_code')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('invoice_date')->nullable();
            $table->string('invoice_due')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->string('invoice_ppn')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id');
            $table->unsignedBigInteger('order_items_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty_received', 15, 2)->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('raw_price', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('extra_discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->string('type')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('qty_before', 15, 2)->default(0);
            $table->decimal('qty_after', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(2);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('return_no')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('selesai');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('status')->default('diajukan');
            $table->string('status_date')->nullable();
            $table->string('deduction_date')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->string('transaction_code')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->boolean('is_submitted')->default(false);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('quantity', 15, 2)->default(0);
            $table->decimal('item_price', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('final_price', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('retur', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('total_retur', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('category')->default('Kas & Bank');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_balance_adjustments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->decimal('system_balance', 18, 2)->default(0);
            $table->decimal('real_balance', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->default(0);
            $table->date('adjustment_date');
            $table->timestamp('adjusted_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('finance_payment_id')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_opening_invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('type');
            $table->string('creditor_code')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->string('invoice_number');
            $table->date('invoice_date');
            $table->date('due_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->boolean('is_submitted')->default(false);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->unsignedBigInteger('opening_invoice_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('transfer_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->unsignedBigInteger('income_account_id')->nullable();
            $table->string('payment_type');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        $this->branch = Pharmacies::create([
            'id' => 2,
            'name' => 'SAHABAT MULAWARMAN',
            'status' => 1,
        ]);

        $user = User::create([
            'name' => 'Finance Mulawarman',
            'username' => 'fin_mulawarman',
            'password' => 'x',
            'pharmacy_id' => 2,
        ]);
        $this->actingAs($user);

        $this->kas = FinanceAccount::create([
            'pharmacy_id' => 2,
            'code' => '1-10001',
            'name' => 'Kas Apotek',
            'category' => 'Kas & Bank',
            'account_type' => 'kas_apotek',
            'balance' => 0,
            'is_active' => true,
        ]);

        $this->bank = FinanceAccount::create([
            'pharmacy_id' => 2,
            'code' => '1-10002',
            'name' => 'Bank Mandiri',
            'category' => 'Kas & Bank',
            'account_type' => 'bank',
            'balance' => 0,
            'is_active' => true,
        ]);

        $this->creditor = Creditor::create([
            'code' => 'PBF1',
            'name' => 'PBF UTAMA',
            'ppn_type' => 'TANPA',
        ]);
    }

    protected function tearDown(): void
    {
        ConsignmentService::clearStockFilterCache();
        Cache::flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_mulawarman_opname_and_opening_hutang_saldoakhir_do_not_distort_neraca_laba_or_dec_rollover(): void
    {
        // Penjualan operasional November: Rp 10.000.000 masuk ke Kas
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-05',
            'amount' => 10000000.00,
        ]);

        // Opname KasBank tengah periode: +Rp 173.769.215,55
        $opPay = FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->bank->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-17',
            'amount' => 173769215.55,
            'reference_number' => 'OPNAME-IN',
        ]);
        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->bank->id,
            'system_balance' => 0,
            'real_balance' => 173769215.55,
            'difference' => 173769215.55,
            'adjustment_date' => '2026-11-17',
            'finance_payment_id' => $opPay->id,
        ]);

        $this->kas->update(['balance' => 10000000.00]);
        $this->bank->update(['balance' => 173769215.55]);

        // Opening Hutang SALDOAKHIR bertanggal di tengah November: Rp 171.967.970,76
        FinanceOpeningInvoice::create([
            'pharmacy_id' => 2,
            'type' => 'HUTANG',
            'creditor_code' => 'PBF1',
            'invoice_number' => 'SALDOAKHIR',
            'invoice_date' => '2026-11-17',
            'due_date' => '2026-12-17',
            'amount' => 171967970.76,
        ]);

        $service = app(NeracaReportService::class);
        $nov = $service->build([2], '2026-11-01', '2026-11-30', 2);

        // Posisi riil AKHIR tetap utuh (10jt + 173.769.215,55 - 171.967.970,76 = 11.801.244,79)
        $this->assertSame(11801244.79, $nov['laba_tanpa_stok']['akhir']);
        $this->assertSame(11801244.79, $nov['saldo']['akhir']);
        $this->assertSame(11801244.79, $nov['laba_tanpa_stok']['raw_selisih']);

        // Selisih Laba di Neraca BERSIH dari distorsi +1.801.244,79 -> tepat Rp 10.000.000,00!
        $this->assertSame(10000000.00, $nov['laba_tanpa_stok']['selisih']);
        $this->assertSame(10000000.00, $nov['laba_dengan_stok']['selisih']);

        $adj = $nov['penyesuaian_non_operasional'];
        $this->assertTrue($adj['has_adjustments']);
        $this->assertSame(173769215.55, $adj['opname_kas_bank']);
        $this->assertSame(-171967970.76, $adj['opening_hutang']);
        $this->assertSame(1801244.79, $adj['total']);

        // Bulan berikutnya (Desember 2026): AWAL Desember HARUS === AKHIR November
        $dec = $service->build([2], '2026-12-01', '2026-12-31', 2);
        $this->assertSame($nov['saldo']['akhir'], $dec['saldo']['awal']);
        $this->assertSame($nov['kewajiban']['akhir'], $dec['kewajiban']['awal']);
        $this->assertSame(0.0, $dec['laba_tanpa_stok']['selisih']);
        $this->assertFalse($dec['penyesuaian_non_operasional']['has_adjustments']);

        // Export Excel berjalan mulus dan memuat blok rekonsiliasi non-operasional
        $binary = Excel::raw(new NeracaExport($nov, $this->branch), ExcelFormat::XLSX);
        $tmpPath = tempnam(sys_get_temp_dir(), 'neraca_op_') . '.xlsx';
        file_put_contents($tmpPath, $binary);
        $sheet = IOFactory::load($tmpPath)->getActiveSheet();
        @unlink($tmpPath);

        $flatText = '';
        $foundRawSelisih = false;
        foreach ($sheet->toArray(null, true, false) as $row) {
            $flatText .= implode(' | ', array_map('strval', $row)) . "\n";
            if (($row[1] ?? '') === 'SELISIH POSISI BERSIH TANPA STOK (AKHIR - AWAL)') {
                $foundRawSelisih = true;
                $this->assertSame(11801244.79, round((float) $row[4], 2));
            }
        }
        $this->assertStringContainsString('REKONSILIASI PENYESUAIAN NON-OPERASIONAL', $flatText);
        $this->assertTrue($foundRawSelisih);

        // Pastikan buildCashflowRecap mengisi periodAdjustments dengan net Opname KasBank (+173.769.215,55)
        session(['active_pharmacy_id' => 2, 'ho_pharmacy_id' => 2]);
        $recap = (new class extends FinanceController {
            public function __construct()
            {
            }

            public function recap(string $startDate, string $endDate): array
            {
                return $this->buildCashflowRecap($startDate, $endDate);
            }
        })->recap('2026-11-01', '2026-11-30');
        $this->assertSame(173769215.55, round((float) $recap['periodAdjustments'], 2));
    }

    public function test_mid_period_opening_hutang_paid_partially_or_fully_with_kas_still_keeps_laba_accurate(): void
    {
        // Modal awal 200jt pada 1 Nov
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->bank->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 200000000.00,
        ]);

        // Operasional Nov: penjualan +2.000.000, biaya -500.000 -> Laba murni 1.500.000
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-05',
            'amount' => 2000000.00,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'BIAYA',
            'payment_date' => '2026-11-06',
            'amount' => 500000.00,
        ]);
        $this->kas->update(['balance' => 1500000.00]);

        // Opening Hutang SALDOAKHIR Rp 171.967.970,76 bertanggal 17 Nov
        $opening = FinanceOpeningInvoice::create([
            'pharmacy_id' => 2,
            'type' => 'HUTANG',
            'creditor_code' => 'PBF1',
            'invoice_number' => 'SALDOAKHIR',
            'invoice_date' => '2026-11-17',
            'amount' => 171967970.76,
        ]);

        // Sebagian (50jt) dibayar pakai Bank Mandiri pada 20 Nov
        FinancePayment::create([
            'pharmacy_id' => 2,
            'opening_invoice_id' => $opening->id,
            'account_id' => $this->bank->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-20',
            'amount' => 50000000.00,
        ]);
        $this->bank->update(['balance' => 150000000.00]);

        $service = app(NeracaReportService::class);
        $partial = $service->build([2], '2026-11-01', '2026-11-30', 2);
        $this->assertSame(121967970.76, $partial['kewajiban']['akhir']);
        $this->assertSame(1500000.00, $partial['laba_tanpa_stok']['selisih']);

        // Sisa 121.967.970,76 DILUNASI 100% pada 25 Nov (Hutang SALDOAKHIR di AKHIR menjadi Rp 0!)
        FinancePayment::create([
            'pharmacy_id' => 2,
            'opening_invoice_id' => $opening->id,
            'account_id' => $this->bank->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-25',
            'amount' => 121967970.76,
        ]);
        $this->bank->update(['balance' => 28032029.24]);

        $full = $service->build([2], '2026-11-01', '2026-11-30', 2);
        $this->assertSame(0.0, $full['kewajiban']['akhir']);
        $this->assertSame(1500000.00, $full['laba_tanpa_stok']['selisih']);
    }

    public function test_opname_out_prefixed_opening_piutang_and_cutoff_settlements_exclude_correctly(): void
    {
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 1000000.00,
        ]);

        // Penjualan operasional: +500.000
        FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-05',
            'amount' => 500000.00,
        ]);

        // Opname Keluar dengan ref OPNAME-OUT-20261110-001 (menguji prefix match di Resolver): -75.000
        $opOut = FinancePayment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-10',
            'amount' => 75000.00,
            'reference_number' => 'OPNAME-OUT-20261110-001',
        ]);
        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 2,
            'account_id' => $this->kas->id,
            'system_balance' => 1500000.00,
            'real_balance' => 1425000.00,
            'difference' => -75000.00,
            'adjustment_date' => '2026-11-10',
            'finance_payment_id' => $opOut->id,
        ]);

        // Opening Piutang bertanggal 15 Nov Rp 80.000, dilunasi tunai Rp 30.000 pada 20 Nov + PIUTANG CUTOFF Rp 20.000 pada 22 Nov
        $openingPiutang = FinanceOpeningInvoice::create([
            'pharmacy_id' => 2,
            'type' => 'PIUTANG',
            'debtor_id' => 1,
            'invoice_number' => 'PIU-SA-01',
            'invoice_date' => '2026-11-15',
            'amount' => 80000.00,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'opening_invoice_id' => $openingPiutang->id,
            'account_id' => $this->kas->id,
            'payment_type' => 'PIUTANG',
            'payment_date' => '2026-11-20',
            'amount' => 30000.00,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'opening_invoice_id' => $openingPiutang->id,
            'account_id' => $this->kas->id,
            'payment_type' => 'PIUTANG CUTOFF',
            'payment_date' => '2026-11-22',
            'amount' => 20000.00,
        ]);

        // Faktur reguler Oktober Rp 200.000 yang ditandai KREDIT CUTOFF di 12 Nov (tanpa potong kas)
        $recOct = DB::table('receiving')->insertGetId([
            'code' => '2610RE001',
            'date' => '20/10/2026',
            'pharmacy_id' => 2,
            'status' => 3,
            'created_at' => '2026-10-20 10:00:00',
            'updated_at' => '2026-10-20 10:00:00',
        ]);
        $detOct = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recOct,
            'creditor_code' => 'PBF1',
            'invoice_number' => 'INV-OCT-CUT',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $detOct,
            'batches_id' => 1,
            'qty_received' => 2,
            'qty' => 2,
            'raw_price' => 100000,
            'total' => 200000,
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'receiving_id' => $recOct,
            'receiving_detail_id' => $detOct,
            'account_id' => $this->bank->id,
            'payment_type' => 'KREDIT CUTOFF',
            'payment_date' => '2026-11-12',
            'amount' => 200000.00,
            'reference_number' => 'CUTOFF-HUTANG',
        ]);

        // Penjualan kredit Oktober Rp 90.000 ditandai PIUTANG CUTOFF di 14 Nov
        $txOct = MedicineTransactions::create([
            'pharmacy_id' => 2,
            'debtor_id' => 1,
            'transaction_code' => 'TRX-OCT-1',
            'transaction_type' => 'KREDIT',
            'subtotal' => 90000.00,
            'discount' => 0,
            'paid' => 0,
            'status' => 1,
        ]);
        DB::table('medicine_transactions')->where('id', $txOct->id)->update([
            'created_at' => '2026-10-25 10:00:00',
            'updated_at' => '2026-10-25 10:00:00',
        ]);
        FinancePayment::create([
            'pharmacy_id' => 2,
            'medicine_transaction_id' => $txOct->id,
            'account_id' => $this->kas->id,
            'payment_type' => 'PIUTANG CUTOFF',
            'payment_date' => '2026-11-14',
            'amount' => 90000.00,
        ]);

        $this->kas->update(['balance' => 1455000.00]); // 1,000,000 + 500,000 - 75,000 + 30,000

        $service = app(NeracaReportService::class);
        $nov = $service->build([2], '2026-11-01', '2026-11-30', 2);

        // Piutang akhir tersisa = 80k - 30k - 20k = 30k; Hutang akhir = 0; Kas akhir = 1.455k
        $itemsByLabel = collect($nov['items'])->keyBy('label');
        $this->assertSame(1455000.00, $itemsByLabel['KAS']['akhir']);
        $this->assertSame(30000.00, $itemsByLabel['PIUTANG DAGANG']['akhir']);
        $this->assertSame(0.0, $nov['kewajiban']['akhir']);

        // Laba operasional murni tetap tepat Rp 500.000!
        $this->assertSame(500000.00, $nov['laba_tanpa_stok']['selisih']);
        $this->assertSame(-75000.00, $nov['penyesuaian_non_operasional']['opname_kas_bank']);
        $this->assertSame(60000.00, $nov['penyesuaian_non_operasional']['opening_piutang']); // +80k (opening) - 20k (PIUTANG CUTOFF)
        $this->assertSame(110000.00, $nov['penyesuaian_non_operasional']['cutoff_settlement']); // +200k (KREDIT CUTOFF) - 90k (PIUTANG CUTOFF)
    }

    public function test_cashflow_trade_puts_mid_period_opening_hutang_in_opening_balance_not_period_purchases(): void
    {
        FinanceOpeningInvoice::create([
            'pharmacy_id' => 2,
            'type' => 'HUTANG',
            'creditor_code' => 'PBF1',
            'invoice_number' => 'SALDOAKHIR',
            'invoice_date' => '2026-11-17',
            'amount' => 171967970.76,
        ]);

        // Pembelian kredit riil pada 20 Nov senilai Rp 1.000.000
        $recNov = DB::table('receiving')->insertGetId([
            'code' => '2611RE001',
            'date' => '20/11/2026',
            'pharmacy_id' => 2,
            'status' => 2,
            'created_at' => '2026-11-20 10:00:00',
            'updated_at' => '2026-11-20 10:00:00',
        ]);
        $detNov = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recNov,
            'creditor_code' => 'PBF1',
            'invoice_number' => 'INV-NOV-01',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
            'created_at' => '2026-11-20 10:00:00',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $detNov,
            'batches_id' => 10,
            'qty_received' => 10,
            'qty' => 10,
            'raw_price' => 100000,
            'total' => 1000000,
            'created_at' => '2026-11-20 10:00:00',
        ]);

        // Pembayaran 50jt atas SALDOAKHIR pada 22 Nov
        $opening = FinanceOpeningInvoice::first();
        FinancePayment::create([
            'pharmacy_id' => 2,
            'opening_invoice_id' => $opening->id,
            'account_id' => $this->bank->id,
            'payment_type' => 'KREDIT',
            'payment_date' => '2026-11-22',
            'amount' => 50000000.00,
        ]);

        $tradeService = app(CashflowTradeReportService::class);
        $novPayables = $tradeService->payables([2], '2026-11-01', '2026-11-30');

        // SALDOAKHIR masuk ke Saldo Awal (bukan Pembelian kredit bersih!)
        $this->assertSame(171967970.76, round($novPayables['openingBalance'], 2));
        $this->assertSame(1000000.00, round($novPayables['periodTotal'], 2));
        $this->assertSame(0.0, round($novPayables['grandTotalsByDate']['2026-11-17'], 2));
        $this->assertSame(50000000.00, round($novPayables['periodPayments'], 2));
        $this->assertSame(122967970.76, round($novPayables['closingBalance'], 2));

        // Harus persis sama dengan Neraca payableBalanceAtDate per 30 Nov
        $neracaPayableEnd = app(NeracaReportService::class)->payableBalanceAtDate([2], '2026-11-30');
        $this->assertSame($neracaPayableEnd, round($novPayables['closingBalance'], 2));

        // Oktober (sebelum SALDOAKHIR dibuat) tidak ikut terisi SALDOAKHIR
        $octPayables = $tradeService->payables([2], '2026-10-01', '2026-10-31');
        $this->assertSame(0.0, round($octPayables['openingBalance'], 2));
        $this->assertSame(0.0, round($octPayables['closingBalance'], 2));
    }
}
