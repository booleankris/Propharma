<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinanceBalanceAdjustment;
use App\Models\FinancePayment;
use App\Models\User;
use App\Support\FinanceAccountBalanceResolver;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceSaldoAwalOpnameTest extends TestCase
{
    private User $financeUser;
    private FinanceAccount $kasAccount;
    private FinanceAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->createSchema();

        DB::table('pharmacies')->insert([
            ['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1],
        ]);

        $this->financeUser = User::create([
            'name' => 'FINANCE STAFF',
            'username' => 'finance_staff',
            'password' => bcrypt('secret'),
            'secret_pin' => '1234',
            'pharmacy_id' => 1,
        ]);

        $this->kasAccount = FinanceAccount::create([
            'code' => '1-10001',
            'pharmacy_id' => 1,
            'name' => 'Kas Apotek',
            'category' => 'Kas & Bank',
            'account_type' => 'kas_apotek',
            'balance' => 0,
            'is_active' => true,
        ]);

        $this->bankAccount = FinanceAccount::create([
            'code' => '1-10002',
            'pharmacy_id' => 1,
            'name' => 'Rekening BCA',
            'category' => 'Kas & Bank',
            'account_type' => 'bank',
            'balance' => 0,
            'is_active' => true,
        ]);
    }

    public function test_calculate_from_opening_subtracts_opname_out_and_retur_penjualan(): void
    {
        $opening = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'SALDO AWAL',
            'payment_date' => '2026-11-01',
            'amount' => 1000000,
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-02',
            'amount' => 500000,
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'RETUR PENJUALAN',
            'payment_date' => '2026-11-03',
            'amount' => 75000,
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-04',
            'amount' => 125000,
            'reference_number' => 'OPNAME-OUT-20261104-001',
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-05',
            'amount' => 25000,
            'reference_number' => 'OPNAME-IN-20261105-001',
            'created_by' => $this->financeUser->id,
        ]);

        // 1,000,000 + 500,000 - 75,000 - 125,000 + 25,000 = 1,325,000
        $balance = FinanceAccountBalanceResolver::calculateFromOpening(
            $this->kasAccount->id,
            $opening,
            '2026-11-10'
        );

        $this->assertSame(1325000.0, $balance);
    }

    public function test_opening_balance_rolls_forward_from_latest_opname_when_saldo_awal_is_missing(): void
    {
        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'system_balance' => 0,
            'real_balance' => 2500000,
            'difference' => 2500000,
            'adjustment_date' => '2026-10-31',
            'adjusted_at' => '2026-10-31 18:00:00',
            'notes' => 'Opname akhir Oktober',
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-02',
            'amount' => 300000,
            'created_by' => $this->financeUser->id,
        ]);

        $novOpening = FinanceAccountBalanceResolver::resolveOpeningBalanceForPeriod(
            $this->kasAccount->id,
            '2026-11-01',
            '2026-11-30'
        );
        $decOpening = FinanceAccountBalanceResolver::resolveOpeningBalanceForPeriod(
            $this->kasAccount->id,
            '2026-12-01',
            '2026-12-31'
        );

        $this->assertSame(2500000.0, $novOpening);
        $this->assertSame(2800000.0, $decOpening);
    }

    public function test_estimate_opening_for_account_back_calculates_from_opname_and_excludes_penyesuaian_saldo(): void
    {
        // Simulasi: Opname tanggal 2026-11-10 menemukan saldo riil Rp 5.000.000
        // Antara 2026-11-01..2026-11-10: Masuk Rp 3.000.000, Keluar Rp 1.000.000
        // Maka Saldo Awal per 2026-11-01 = 5.000.000 - 3.000.000 + 1.000.000 = 3.000.000
        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-05',
            'amount' => 3000000,
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'BIAYA',
            'payment_date' => '2026-11-06',
            'amount' => 1000000,
            'created_by' => $this->financeUser->id,
        ]);

        $opnamePayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-10',
            'amount' => 3000000,
            'reference_number' => 'OPNAME-IN-20261110-001',
            'created_by' => $this->financeUser->id,
        ]);

        FinanceBalanceAdjustment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'system_balance' => 2000000,
            'real_balance' => 5000000,
            'difference' => 3000000,
            'adjustment_date' => '2026-11-10',
            'adjusted_at' => '2026-11-10 17:00:00',
            'notes' => 'Opname fisik kas',
            'finance_payment_id' => $opnamePayment->id,
            'created_by' => $this->financeUser->id,
        ]);

        $estimate = FinanceAccountBalanceResolver::estimateOpeningForAccount(
            $this->kasAccount,
            '2026-11-01',
            'auto'
        );

        $this->assertSame('opname', $estimate['anchor_source']);
        $this->assertSame('2026-11-10', $estimate['anchor_date']);
        $this->assertSame(5000000.0, $estimate['anchor_balance']);
        $this->assertSame(3000000.0, $estimate['inflow']);
        $this->assertSame(1000000.0, $estimate['outflow']);
        $this->assertSame(1, $estimate['opname_payment_count']);
        $this->assertSame(3000000.0, $estimate['raw_estimated_opening']);
        $this->assertSame(3000000.0, $estimate['suggested_opening']);
        $this->assertFalse($estimate['is_negative']);
    }

    public function test_bulk_store_saldo_awal_clears_old_opname_payments_while_preserving_adjustment_logs(): void
    {
        $this->withoutMiddleware();
        $this->actingAs($this->financeUser);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-05',
            'amount' => 3000000,
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'BIAYA',
            'payment_date' => '2026-11-06',
            'amount' => 1000000,
            'created_by' => $this->financeUser->id,
        ]);

        $opnamePayment = FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENYESUAIAN SALDO',
            'payment_date' => '2026-11-10',
            'amount' => 3000000,
            'reference_number' => 'OPNAME-IN-20261110-001',
            'created_by' => $this->financeUser->id,
        ]);

        $log = FinanceBalanceAdjustment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'system_balance' => 2000000,
            'real_balance' => 5000000,
            'difference' => 3000000,
            'adjustment_date' => '2026-11-10',
            'adjusted_at' => '2026-11-10 17:00:00',
            'notes' => 'Opname fisik kas',
            'finance_payment_id' => $opnamePayment->id,
            'created_by' => $this->financeUser->id,
        ]);

        $response = $this->post('/finance/saldo-awal', [
            'payment_date' => '2026-11-01',
            'clear_prior_opname_payments' => true,
            'items' => [
                [
                    'account_id' => $this->kasAccount->id,
                    'amount' => 3000000,
                    'notes' => 'Hitung mundur dari Opname 2026-11-10',
                    'clear_until_date' => '2026-11-10',
                ],
                [
                    'account_id' => $this->bankAccount->id,
                    'amount' => 12000000,
                    'notes' => 'Saldo awal bank',
                    'clear_until_date' => '2026-11-10',
                ],
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseMissing('finance_payments', ['id' => $opnamePayment->id]);
        $this->assertDatabaseHas('finance_balance_adjustments', ['id' => $log->id]);

        // Kas: 3,000,000 + 3,000,000 - 1,000,000 = 5,000,000 (tepat sama dengan hasil opname!)
        $this->assertSame(5000000.0, (float) $this->kasAccount->fresh()->balance);
        $this->assertSame(12000000.0, (float) $this->bankAccount->fresh()->balance);
    }

    public function test_adjust_balance_with_as_opening_balance_sets_saldo_awal_and_recalculates_live_balance(): void
    {
        $this->withoutMiddleware();
        $this->actingAs($this->financeUser);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->bankAccount->id,
            'payment_type' => 'PENJUALAN NON TUNAI',
            'payment_date' => '2026-11-03',
            'amount' => 750000,
            'created_by' => $this->financeUser->id,
        ]);

        $response = $this->post('/finance/kas-bank/adjust-balance', [
            'account_id' => $this->bankAccount->id,
            'real_balance' => 10000000,
            'adjustment_date' => '2026-11-01',
            'as_opening_balance' => true,
            'notes' => 'Cut-off m-Banking awal November',
        ]);

        $response->assertRedirect();

        $opening = FinancePayment::where('account_id', $this->bankAccount->id)
            ->where('payment_type', 'SALDO AWAL')
            ->first();

        $this->assertNotNull($opening);
        $this->assertSame(10000000.0, (float) $opening->amount);
        $this->assertSame('2026-11-01', $opening->payment_date->format('Y-m-d'));

        // Live balance = 10,000,000 (saldo awal 2026-11-01) + 750,000 (penjualan 2026-11-03) = 10,750,000
        $this->assertSame(10750000.0, (float) $this->bankAccount->fresh()->balance);

        $log = FinanceBalanceAdjustment::where('account_id', $this->bankAccount->id)->first();
        $this->assertNotNull($log);
        $this->assertStringContainsString('[Cut-off Saldo Awal]', (string) $log->notes);
    }

    public function test_neraca_opening_and_closing_back_calculate_from_book_balance_when_saldo_awal_is_missing(): void
    {
        // Reproduksi kasus nyata: Kas Apotek saldo buku hari ini Rp 65.688.000, belum ada SALDO AWAL / Opname,
        // mutasi bulan November 2026: Masuk Rp 548.000, Keluar Rp 72.000 (net +Rp 476.000).
        $this->kasAccount->update(['balance' => 65688000]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'PENJUALAN TUNAI',
            'payment_date' => '2026-11-10',
            'amount' => 548000,
            'created_by' => $this->financeUser->id,
        ]);

        FinancePayment::create([
            'pharmacy_id' => 1,
            'account_id' => $this->kasAccount->id,
            'payment_type' => 'BIAYA',
            'payment_date' => '2026-11-12',
            'amount' => 72000,
            'created_by' => $this->financeUser->id,
        ]);

        $openingNov = FinanceAccountBalanceResolver::resolveOpeningBalanceAtStartDate(
            $this->kasAccount,
            '2026-11-01',
            '2026-11-21'
        );
        $closingNov = FinanceAccountBalanceResolver::resolveClosingBalanceAsOf(
            $this->kasAccount,
            '2026-11-21',
            '2026-11-21'
        );
        $closingOct = FinanceAccountBalanceResolver::resolveClosingBalanceAsOf(
            $this->kasAccount,
            '2026-10-31',
            '2026-11-21'
        );

        $this->assertSame(65212000.0, $openingNov);
        $this->assertSame(65688000.0, $closingNov);
        $this->assertSame(65212000.0, $closingOct);
        $this->assertSame(476000.0, round($closingNov - $openingNov, 2));
    }

    private function createSchema(): void
    {
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('status')->default(1);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->string('secret_pin')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('category')->default('Kas & Bank');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('balance', 18, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('transfer_account_id')->nullable();
            $table->unsignedBigInteger('expense_account_id')->nullable();
            $table->unsignedBigInteger('income_account_id')->nullable();
            $table->string('payment_type');
            $table->date('payment_date');
            $table->decimal('amount', 18, 2)->default(0);
            $table->string('recipient')->nullable();
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
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
    }
}
