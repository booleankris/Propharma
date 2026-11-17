<?php

namespace Tests\Unit;

use App\Exports\Finance\HutangExport;
use App\Exports\Finance\PiutangExport;
use App\Http\Controllers\FinanceController;
use App\Models\Creditor;
use App\Models\Debtors;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\User;
use App\Services\Finance\PayablesReceivablesService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Tests\TestCase;

class PayablesReceivablesOptimizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::reconnect('sqlite');

        $this->createMinimalSchema();
    }

    protected function createMinimalSchema(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('ppn_type')->default('EXCLUDE');
            $table->timestamps();
        });

        Schema::create('debtors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->string('city')->nullable();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->string('raw_price')->nullable();
            $table->string('pharmacy_net_price')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->string('source_type')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('date')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id');
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
            $table->decimal('qty_received', 15, 2)->default(0);
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('raw_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('extra_discount', 15, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('purchase_returns', function (Blueprint $table) {
            $table->id();
            $table->string('return_no')->nullable();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->string('status')->default('diajukan');
            $table->timestamps();
        });

        Schema::create('purchase_return_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('purchase_return_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->string('medicine_name')->nullable();
            $table->string('batch_number')->nullable();
            $table->string('expired_date')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->string('unit')->nullable();
            $table->decimal('unit_price', 15, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->string('status')->default('diajukan');
            $table->string('status_date')->nullable();
            $table->string('deduction_date')->nullable();
            $table->string('deduction_note')->nullable();
            $table->string('reason')->nullable();
            $table->string('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('transaction_type')->nullable();
            $table->string('payment_method')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('paid', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->boolean('is_submitted')->default(false);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
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

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('item_price', 15, 2)->default(0);
            $table->decimal('discount', 15, 2)->default(0);
            $table->string('package')->nullable();
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('final_price', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('name_en')->nullable();
            $table->string('category')->default('Kas & Bank');
            $table->string('account_type')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->unsignedBigInteger('receiving_detail_id')->nullable();
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->unsignedBigInteger('account_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('payment_type')->nullable();
            $table->date('payment_date')->nullable();
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->string('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_cashbonds', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('status')->default('BELUM LUNAS');
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('retur', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('qty_retur', 15, 2)->default(0);
            $table->decimal('total_retur', 15, 2)->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->decimal('qty', 15, 2)->default(0);
            $table->decimal('buy_price', 15, 2)->default(0);
            $table->timestamps();
        });

        DB::table('pharmacies')->insert(['id' => 9, 'name' => 'Apotek Cabang Sembilan']);
        DB::table('pharmacies')->insert(['id' => 10, 'name' => 'Apotek Cabang Sepuluh']);
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Finance Tester',
            'username' => 'fintest',
            'pharmacy_id' => 9,
        ]);
        DB::table('finance_accounts')->insert([
            'id' => 1,
            'code' => 'BCA-01',
            'name' => 'Bank BCA Cabang 9',
            'category' => 'Kas & Bank',
            'balance' => 10000000,
            'pharmacy_id' => 9,
        ]);

        $this->actingAs(User::find(1));
    }

    public function test_finance_indexes_migration_is_idempotent_and_reversible(): void
    {
        $migration = require database_path('migrations/2026_11_18_000001_add_finance_hutang_piutang_indexes.php');

        $migration->up();
        $migration->up(); // Idempotent re-run must not throw

        $rdIndexes = collect(DB::select("PRAGMA index_list('receiving_details')"))->pluck('name')->all();
        $mtIndexes = collect(DB::select("PRAGMA index_list('medicine_transactions')"))->pluck('name')->all();
        $fpIndexes = collect(DB::select("PRAGMA index_list('finance_payments')"))->pluck('name')->all();

        $this->assertContains('idx_rd_payment_receiving', $rdIndexes);
        $this->assertContains('idx_mt_pharmacy_type_status_id', $mtIndexes);
        $this->assertContains('idx_fp_rd_type', $fpIndexes);
        $this->assertContains('idx_fp_mt_type', $fpIndexes);

        $migration->down();

        $rdIndexesAfter = collect(DB::select("PRAGMA index_list('receiving_details')"))->pluck('name')->all();
        $this->assertNotContains('idx_rd_payment_receiving', $rdIndexesAfter);
    }

    public function test_hutang_dataset_supports_server_pagination_filters_and_lazy_detail_loading(): void
    {
        DB::table('creditors')->insert([
            ['code' => 'KR01', 'name' => 'PT Kimia Alpha', 'ppn_type' => 'EXCLUDE'],
            ['code' => 'KR02', 'name' => 'PT Pharma Beta', 'ppn_type' => 'TANPA'],
        ]);
        DB::table('medicines')->insert([
            'id' => 5,
            'code' => 'OBT-005',
            'name' => 'Paracetamol 500mg',
            'unit' => 'Box',
        ]);
        DB::table('order_items')->insert([
            'id' => 50,
            'medicine_id' => 5,
            'price' => 10000,
        ]);

        // Seed 15 credit invoices across branches, statuses, and due dates
        for ($i = 1; $i <= 15; $i++) {
            $recId = DB::table('receiving')->insertGetId([
                'code' => "RCV-{$i}",
                'date' => '2026-10-01',
                'pharmacy_id' => 9,
                'status' => 1,
                'created_at' => now(),
                'updated_at' => now()->addSeconds($i),
            ]);
            $detailId = DB::table('receiving_details')->insertGetId([
                'receiving_id' => $recId,
                'receiving_details_code' => "NT-{$i}",
                'creditor_code' => $i <= 10 ? 'KR01' : 'KR02',
                'invoice_number' => "INV-HUT-{$i}",
                'invoice_date' => '2026-10-10',
                'invoice_due' => $i === 1 ? '2020-01-01' : sprintf('2026-12-%02d', $i),
                'invoice_payment' => 'KREDIT',
                'invoice_ppn' => 'EXCLUDE',
                'is_submitted' => $i === 2,
                'created_at' => '2026-10-11 10:00:00',
                'updated_at' => now(),
            ]);
            DB::table('receiving_items')->insert([
                'receiving_details_id' => $detailId,
                'order_items_id' => 50,
                'batches_id' => 100 + $i,
                'qty_received' => 10,
                'qty' => 10,
                'raw_price' => 10000,
                'discount' => 0,
                'extra_discount' => 0,
                'total' => 100000,
            ]);

            if ($i === 3) {
                // Full payment = Lunas
                DB::table('finance_payments')->insert([
                    'receiving_id' => $recId,
                    'receiving_detail_id' => $detailId,
                    'account_id' => 1,
                    'payment_type' => 'KREDIT',
                    'payment_date' => '2026-10-15',
                    'amount' => 111000,
                    'created_by' => 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            if ($i === 4) {
                // Purchase return approved deduction
                $prId = DB::table('purchase_returns')->insertGetId([
                    'return_no' => 'RET-PBF-04',
                    'receiving_id' => $detailId,
                    'invoice_number' => 'INV-HUT-4',
                    'status' => 'selesai',
                    'created_at' => '2026-10-12 09:00:00',
                ]);
                DB::table('purchase_return_items')->insert([
                    'purchase_return_id' => $prId,
                    'medicine_id' => 5,
                    'medicine_name' => 'Paracetamol 500mg',
                    'qty' => 2,
                    'unit' => 'Box',
                    'unit_price' => 11100,
                    'subtotal' => 22200,
                    'status' => 'sudah_potong',
                    'deduction_date' => '2026-10-13',
                ]);
            }
        }

        // Cross-branch invoice (must not appear for pharmacy 9)
        $otherRecId = DB::table('receiving')->insertGetId([
            'code' => 'RCV-OTHER',
            'pharmacy_id' => 10,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $otherDetailId = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $otherRecId,
            'creditor_code' => 'KR01',
            'invoice_number' => 'INV-OTHER-10',
            'invoice_payment' => 'KREDIT',
        ]);
        DB::table('receiving_items')->insert([
            'receiving_details_id' => $otherDetailId,
            'batches_id' => 999,
            'qty_received' => 5,
            'raw_price' => 10000,
        ]);

        $service = app(PayablesReceivablesService::class);

        $page1 = $service->getHutangDataset([9], [], false, 10, 1);
        $this->assertCount(10, $page1['rows']);
        $this->assertSame(15, $page1['pagination']['total']);
        $this->assertSame(2, $page1['pagination']['last_page']);
        $this->assertSame(15, $page1['stats']['totalHutangCount']);
        $this->assertSame(1, $page1['stats']['countLunas']);
        $this->assertSame(1, $page1['stats']['countDiajukan']);
        $this->assertGreaterThanOrEqual(1, $page1['dueStats']['overdue']);
        $this->assertEmpty($page1['rows'][0]['items']); // Lean list payload

        $page2 = $service->getHutangDataset([9], [], false, 10, 2);
        $this->assertCount(5, $page2['rows']);

        // Status filters: RETUR & DIAJUKAN work even with lean list rows
        $returFiltered = $service->getHutangDataset([9], ['status' => 'RETUR'], false, 10, 1);
        $this->assertCount(1, $returFiltered['rows']);
        $this->assertSame('INV-HUT-4', $returFiltered['rows'][0]['nomor']);
        $this->assertEquals(22200.0, $returFiltered['rows'][0]['total_retur']);

        $submittedFiltered = $service->getHutangDataset([9], ['status' => 'DIAJUKAN'], false, 10, 1);
        $this->assertCount(1, $submittedFiltered['rows']);
        $this->assertSame('INV-HUT-2', $submittedFiltered['rows'][0]['nomor']);

        // PBF partial name & search
        $pbfFiltered = $service->getHutangDataset([9], ['pbf' => 'Beta'], false, 10, 1);
        $this->assertSame(5, $pbfFiltered['pagination']['total']);

        // Lazy detail endpoint & cross-branch isolation
        $detail = $service->getHutangDetail($page1['rows'][0]['id'], [9]);
        $this->assertNotNull($detail);
        $this->assertCount(1, $detail['items']);
        $this->assertSame('Paracetamol 500mg', $detail['items'][0]['nama']);

        $this->assertNull($service->getHutangDetail($otherDetailId, [9]));

        // Export parity check
        $exportRows = (new HutangExport([9], ['status' => 'RETUR']))->array();
        $this->assertSame('INV-HUT-4', $exportRows[5][1]);
    }

    public function test_piutang_removes_300_limit_applies_sales_scope_and_supports_lazy_details_and_import(): void
    {
        DB::table('debtors')->insert([
            'id' => 1,
            'code' => 'DB-01',
            'name' => 'PT Debitur Jaya',
        ]);
        DB::table('medicines')->insert([
            'id' => 7,
            'code' => 'MED-07',
            'name' => 'Amoxicillin 500mg',
            'unit' => 'Strip',
        ]);

        // Seed 315 credit transactions (> 300 legacy limit), mixing KREDIT, RESEP KREDIT,
        // and a status=0 transaction recovered via LiphTransactionScope (medicine_cart.status=1).
        for ($i = 1; $i <= 315; $i++) {
            $type = $i % 2 === 0 ? 'RESEP KREDIT' : 'KREDIT';
            $isZeroStatusRecovered = ($i === 315);

            $txId = DB::table('medicine_transactions')->insertGetId([
                'transaction_code' => sprintf('TRX-KRD-%03d', $i),
                'transaction_type' => $type,
                'debtor_id' => 1,
                'pharmacy_id' => 9,
                'subtotal' => 50000,
                'paid' => 0,
                'status' => $isZeroStatusRecovered ? 0 : 1,
                'is_submitted' => $i === 1,
                'created_at' => sprintf('2026-11-%02d 09:00:00', (($i - 1) % 28) + 1),
                'updated_at' => now(),
            ]);

            DB::table('medicine_cart')->insert([
                'transaction_id' => $txId,
                'medicine_id' => 7,
                'quantity' => 2,
                'item_price' => 25000,
                'discount' => 0,
                'total_price' => 50000,
                'final_price' => 50000,
                'status' => 1,
            ]);

            if ($i <= 2) {
                DB::table('retur')->insert([
                    'code' => "RT-KRD-{$i}",
                    'transaction_id' => $txId,
                    'medicine_id' => 7,
                    'qty_retur' => 1,
                    'total_retur' => 25000,
                    'status' => 1,
                    'created_at' => '2026-11-10 10:00:00',
                ]);
            }
        }

        // Draft/void zero-status transaction without cart status=1 must remain excluded
        $voidTxId = DB::table('medicine_transactions')->insertGetId([
            'transaction_code' => 'TRX-VOID-001',
            'transaction_type' => 'KREDIT',
            'debtor_id' => 1,
            'pharmacy_id' => 9,
            'subtotal' => 99000,
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('medicine_cart')->insert([
            'transaction_id' => $voidTxId,
            'medicine_id' => 7,
            'quantity' => 1,
            'item_price' => 99000,
            'final_price' => 99000,
            'status' => 0,
        ]);

        $service = app(PayablesReceivablesService::class);
        $page1 = $service->getPiutangDataset([9], [], false, 10, 1);

        $this->assertCount(10, $page1['rows']);
        $this->assertSame(315, $page1['pagination']['total']);
        $this->assertSame(32, $page1['pagination']['last_page']);
        $this->assertSame(315, $page1['stats']['totalPiutangCount']);
        $this->assertEmpty($page1['rows'][0]['items']); // Lean rows on list

        // Ensure TRX-KRD-315 (RESEP KREDIT with mt.status=0 + cart.status=1) is included
        $found315 = $service->getPiutangDataset([9], ['search' => 'TRX-KRD-315'], false, 10, 1);
        $this->assertCount(1, $found315['rows']);
        $this->assertEquals(50000.0, $found315['rows'][0]['sisa']);

        // Ensure VOID transaction is NOT included
        $foundVoid = $service->getPiutangDataset([9], ['search' => 'TRX-VOID-001'], false, 10, 1);
        $this->assertCount(0, $foundVoid['rows']);

        // Lazy detail & Riwayat Retur Kredit linking
        $returs = $service->getCreditReturnsDataset([9], [], 10, 1);
        $this->assertSame(2, $returs['pagination']['total']);
        $returTargetTxId = $returs['rows'][0]['transaction_id'];
        $fullTxDetail = $service->getPiutangDetail($returTargetTxId, [9]);
        $this->assertNotNull($fullTxDetail);
        $this->assertCount(1, $fullTxDetail['items']);
        $this->assertCount(1, $fullTxDetail['returs']);
        $this->assertEquals(25000.0, $fullTxDetail['sisa']);

        // PiutangExport includes all 315 records and honors start_date / end_date filters
        $exportAll = (new PiutangExport([9], []))->array();
        $summaryRowsOnly = array_values(array_filter($exportAll, fn($r) => is_array($r) && is_numeric($r[0] ?? null)));
        $this->assertCount(315, $summaryRowsOnly);

        $exportSearch = (new PiutangExport([9], ['search' => 'TRX-KRD-315']))->array();
        $this->assertSame('TRX-KRD-315', $exportSearch[5][1]);

        // Controller clearStatsCache + importPiutangPayments on RESEP KREDIT (tx #315)
        $controller = new class extends FinanceController {
            public function setBranchCache(): void
            {
                $ids = $this->getTargetPharmacyIds();
                $keyV2 = 'finance_stats_summary_branch_v2_' . getActivePharmacyId() . '_' . implode('_', $ids);
                Cache::put($keyV2, ['stale' => true], 60);
            }

            public function getCacheKeyV2(): string
            {
                $ids = $this->getTargetPharmacyIds();
                return 'finance_stats_summary_branch_v2_' . getActivePharmacyId() . '_' . implode('_', $ids);
            }

            public function callClearStatsCache(): void
            {
                $this->clearStatsCache();
            }
        };

        $controller->setBranchCache();
        $this->assertTrue(Cache::has($controller->getCacheKeyV2()));

        $csvPath = tempnam(sys_get_temp_dir(), 'piutang_import_') . '.csv';
        file_put_contents($csvPath, "No. Faktur,Tanggal Bayar,Kode Bank\nTRX-KRD-315,2026-11-18,BCA-01\n");
        $upload = new UploadedFile($csvPath, 'import.csv', 'text/csv', null, true);

        $req = Request::create('/finance/piutang/import-payments', 'POST', [], [], ['file' => $upload]);
        $req->setUserResolver(fn() => User::find(1));

        $controller->importPiutangPayments($req);

        $this->assertFalse(Cache::has($controller->getCacheKeyV2()));
        $afterImport = $service->getPiutangDataset([9], ['search' => 'TRX-KRD-315'], false, 10, 1);
        $this->assertEquals(0.0, $afterImport['rows'][0]['sisa']);
        $this->assertSame('Lunas', $afterImport['rows'][0]['status']);

        @unlink($csvPath);
    }

    public function test_finance_controller_endpoints_render_paginated_props_and_detail_json(): void
    {
        DB::table('creditors')->insert(['code' => 'KR09', 'name' => 'PT Controller Test', 'ppn_type' => 'TANPA']);
        $recId = DB::table('receiving')->insertGetId(['code' => 'RCV-9', 'pharmacy_id' => 9, 'status' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $rdId = DB::table('receiving_details')->insertGetId([
            'receiving_id' => $recId,
            'creditor_code' => 'KR09',
            'invoice_number' => 'INV-CTRL-1',
            'invoice_payment' => 'KREDIT',
            'invoice_ppn' => 'TANPA',
            'created_at' => now(),
        ]);
        DB::table('receiving_items')->insert(['receiving_details_id' => $rdId, 'batches_id' => 1, 'qty_received' => 4, 'raw_price' => 5000]);

        $txId = DB::table('medicine_transactions')->insertGetId([
            'transaction_code' => 'TRX-CTRL-1',
            'transaction_type' => 'KREDIT',
            'pharmacy_id' => 9,
            'subtotal' => 30000,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('medicine_cart')->insert(['transaction_id' => $txId, 'quantity' => 1, 'item_price' => 30000, 'final_price' => 30000, 'status' => 1]);

        $controller = app(FinanceController::class);

        $hutangDetailRes = $controller->hutangDetail($rdId);
        $this->assertSame(200, $hutangDetailRes->getStatusCode());
        $this->assertSame('INV-CTRL-1', $hutangDetailRes->getData(true)['data']['nomor']);

        $missingHutangRes = $controller->hutangDetail(999999);
        $this->assertSame(404, $missingHutangRes->getStatusCode());

        $piutangDetailRes = $controller->piutangDetail($txId);
        $this->assertSame(200, $piutangDetailRes->getStatusCode());
        $this->assertSame('TRX-CTRL-1', $piutangDetailRes->getData(true)['data']['nomor']);

        $missingPiutangRes = $controller->piutangDetail(999999);
        $this->assertSame(404, $missingPiutangRes->getStatusCode());

        // Pre-seed unrelated heavy stock/cashbond cache keys to verify getStats() merges fresh Hutang & Piutang stats on cache hit
        $targetIdsMethod = new \ReflectionMethod($controller, 'getTargetPharmacyIds');
        $targetIdsMethod->setAccessible(true);
        $targetIds = $targetIdsMethod->invoke($controller);
        $cacheKey = 'finance_stats_summary_branch_v2_' . getActivePharmacyId() . '_' . implode('_', $targetIds);
        Cache::put($cacheKey, [
            'nilaiStok' => ['total' => 0],
            'countCashbondActive' => 0,
        ], 60);

        // Verify hutang() and piutang() render paginated Inertia responses with lean rows & global stats
        $hutangReq = Request::create('/finance/hutang', 'GET', ['page' => 1]);
        $hutangReq->headers->set('X-Inertia', 'true');
        $hutangHttpRes = $controller->hutang($hutangReq)->toResponse($hutangReq);
        $this->assertSame(200, $hutangHttpRes->getStatusCode());
        $hutangPayload = json_decode($hutangHttpRes->getContent(), true);
        $this->assertSame('Finance/Hutang', $hutangPayload['component']);
        $this->assertSame(1, $hutangPayload['props']['pagination']['total']);
        $this->assertSame(1, $hutangPayload['props']['stats']['totalHutangCount']);
        $this->assertSame([], $hutangPayload['props']['hutangDagang'][0]['items']);

        $piutangReq = Request::create('/finance/piutang', 'GET', ['page' => 1]);
        $piutangReq->headers->set('X-Inertia', 'true');
        $piutangHttpRes = $controller->piutang($piutangReq)->toResponse($piutangReq);
        $this->assertSame(200, $piutangHttpRes->getStatusCode());
        $piutangPayload = json_decode($piutangHttpRes->getContent(), true);
        $this->assertSame('Finance/Piutang', $piutangPayload['component']);
        $this->assertSame(1, $piutangPayload['props']['pagination']['total']);
        $this->assertSame(1, $piutangPayload['props']['stats']['totalPiutangCount']);
        $this->assertSame([], $piutangPayload['props']['piutangPenjualan'][0]['items']);
        $this->assertArrayHasKey('returPagination', $piutangPayload['props']);
    }
}
