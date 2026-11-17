<?php

namespace Tests\Feature;

use App\Exports\Report\PatientMedicineCardExport;
use App\Http\Middleware\CheckRole;
use App\Models\User;
use App\Services\PatientMedicineCardService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class PatientMedicineCardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $tables = [
            'users' => ['name', 'email', 'password', 'pharmacy_id'],
            'roles' => ['name', 'guard_name'],
            'model_has_roles' => ['role_id', 'model_type', 'model_id'],
            'pharmacies' => ['name', 'status'],
            'patients' => ['code', 'name', 'phone', 'city', 'status'],
            'medicines' => ['code', 'name', 'barcode', 'unit', 'status'],
            'medicine_transactions' => [
                'pharmacy_id', 'debtor_id', 'doctor_id', 'patient_id',
                'transaction_type', 'transaction_code', 'subtotal', 'discount',
                'status', 'user_id', 'payment_method', 'created_at', 'updated_at',
            ],
            'medicine_cart' => [
                'user_id', 'medicine_id', 'transaction_id', 'quantity',
                'item_price', 'total_price', 'final_price', 'status', 'created_at',
            ],
            'retur' => [
                'code', 'transaction_id', 'medicine_id', 'qty_retur',
                'total_retur', 'status', 'created_at',
            ],
        ];

        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    $table->string($column)->nullable();
                }
            });
        }

        DB::table('pharmacies')->insert([
            ['id' => 1, 'name' => 'SAHABAT PMI', 'status' => 1],
            ['id' => 2, 'name' => 'SAHABAT MULAWARMAN', 'status' => 1],
        ]);
        DB::table('users')->insert([
            'id' => 1,
            'name' => 'Kasir Test',
            'email' => 'kasir@test.local',
            'password' => 'secret',
            'pharmacy_id' => 2,
        ]);
        DB::table('patients')->insert([
            ['id' => 10, 'code' => 'PT0010', 'name' => 'Budi Santoso', 'phone' => '08123456789', 'city' => 'Samarinda', 'status' => 1],
            ['id' => 11, 'code' => 'PT0011', 'name' => 'Siti Aminah', 'phone' => '08987654321', 'city' => 'Samarinda', 'status' => 1],
        ]);
        DB::table('medicines')->insert([
            ['id' => 100, 'code' => 'OB001', 'name' => 'Paracetamol 500mg', 'barcode' => '111', 'unit' => 'Strip', 'status' => 1],
            ['id' => 101, 'code' => 'OB002', 'name' => 'Amoxicillin 500mg', 'barcode' => '222', 'unit' => 'Strip', 'status' => 1],
        ]);

        // Pembelian 1 Budi (2 strip Paracetamol)
        DB::table('medicine_transactions')->insert([
            'id' => 1, 'pharmacy_id' => 2, 'patient_id' => 10,
            'transaction_type' => 'UPDS', 'transaction_code' => 'TRX-001',
            'subtotal' => 20000, 'discount' => 0, 'status' => 1, 'user_id' => 1,
            'payment_method' => 'TUNAI', 'created_at' => '2026-11-17 09:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 1, 'user_id' => 1, 'medicine_id' => 100, 'transaction_id' => 1,
            'quantity' => 2, 'item_price' => 10000, 'total_price' => 20000, 'final_price' => 20000,
            'status' => 1, 'created_at' => '2026-11-17 09:00:00',
        ]);

        // Pembelian 2 Budi (3 strip Paracetamol) - kredit belum bayar (status=0, cart status=1)
        DB::table('medicine_transactions')->insert([
            'id' => 2, 'pharmacy_id' => 2, 'patient_id' => 10,
            'transaction_type' => 'KREDIT', 'transaction_code' => 'TRX-002',
            'subtotal' => 30000, 'discount' => 0, 'status' => 0, 'user_id' => 1,
            'payment_method' => 'BELUM BAYAR', 'created_at' => '2026-11-17 13:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 2, 'user_id' => 1, 'medicine_id' => 100, 'transaction_id' => 2,
            'quantity' => 3, 'item_price' => 10000, 'total_price' => 30000, 'final_price' => 30000,
            'status' => 1, 'created_at' => '2026-11-17 13:00:00',
        ]);

        // Retur 1 strip Paracetamol dari TRX-001
        DB::table('retur')->insert([
            'id' => 1, 'code' => 'RT-001', 'transaction_id' => 1, 'medicine_id' => 100,
            'qty_retur' => 1, 'total_retur' => 10000, 'status' => 1,
            'created_at' => '2026-11-17 15:00:00',
        ]);

        // Transaksi walk-in tanpa patient_id (tidak boleh masuk kartu obat pasien)
        DB::table('medicine_transactions')->insert([
            'id' => 3, 'pharmacy_id' => 2, 'patient_id' => null,
            'transaction_type' => 'HV', 'transaction_code' => 'TRX-003',
            'subtotal' => 15000, 'discount' => 0, 'status' => 1, 'user_id' => 1,
            'payment_method' => 'TUNAI', 'created_at' => '2026-11-17 10:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 3, 'user_id' => 1, 'medicine_id' => 101, 'transaction_id' => 3,
            'quantity' => 1, 'item_price' => 15000, 'total_price' => 15000, 'final_price' => 15000,
            'status' => 1, 'created_at' => '2026-11-17 10:00:00',
        ]);

        // Pembelian Siti di cabang 1 (terfilter jika hanya cabang 2)
        DB::table('medicine_transactions')->insert([
            'id' => 4, 'pharmacy_id' => 1, 'patient_id' => 11,
            'transaction_type' => 'RESEP', 'transaction_code' => 'TRX-004',
            'subtotal' => 25000, 'discount' => 0, 'status' => 1, 'user_id' => 1,
            'payment_method' => 'TUNAI', 'created_at' => '2026-11-17 11:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 4, 'user_id' => 1, 'medicine_id' => 101, 'transaction_id' => 4,
            'quantity' => 2, 'item_price' => 12500, 'total_price' => 25000, 'final_price' => 25000,
            'status' => 1, 'created_at' => '2026-11-17 11:00:00',
        ]);
    }

    public function test_service_aggregates_frequency_qty_returns_and_timeline_balance(): void
    {
        $service = new PatientMedicineCardService();
        $result = $service->build([
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'pharmacy_id' => 2,
        ]);

        $this->assertSame(1, $result['stats']['total_patients']);
        $this->assertSame(3, $result['stats']['total_transactions']); // 2 beli + 1 retur
        $this->assertSame(2, $result['stats']['total_freq']);
        $this->assertEquals(5.0, $result['stats']['total_qty_beli']);
        $this->assertEquals(1.0, $result['stats']['total_qty_retur']);
        $this->assertEquals(4.0, $result['stats']['total_qty_net']);
        $this->assertEquals(50000.0, $result['stats']['total_nominal_beli']);
        $this->assertEquals(10000.0, $result['stats']['total_nominal_retur']);

        $this->assertCount(1, $result['summary']);
        $row = $result['summary'][0];
        $this->assertSame('Budi Santoso', $row['patient_name']);
        $this->assertSame('Paracetamol 500mg', $row['medicine_name']);
        $this->assertSame(2, $row['frekuensi_beli']);
        $this->assertSame(1, $row['frekuensi_retur']);
        $this->assertEquals(5.0, $row['qty_beli']);
        $this->assertEquals(1.0, $row['qty_retur']);
        $this->assertEquals(4.0, $row['qty_net']);
        $this->assertSame('17/11/2026 09:00', $row['first_buy_at']);
        $this->assertSame('17/11/2026 13:00', $row['last_buy_at']);
        $this->assertSame('17/11/2026 15:00', $row['last_retur_at']);

        // Timeline newest-first: Retur (saldo 4), Beli ke-2 (saldo 5), Beli ke-1 (saldo 2)
        $timeline = $result['timeline'];
        $this->assertCount(3, $timeline);
        $this->assertSame('RETUR', $timeline[0]['event_type']);
        $this->assertSame('RT-001', $timeline[0]['transaction_code']);
        $this->assertSame('TRX-001', $timeline[0]['reference_code']);
        $this->assertEquals(4.0, $timeline[0]['akumulasi_qty']);

        $this->assertSame('BELI', $timeline[1]['event_type']);
        $this->assertSame(2, $timeline[1]['frekuensi_ke']);
        $this->assertEquals(5.0, $timeline[1]['akumulasi_qty']);

        $this->assertSame('BELI', $timeline[2]['event_type']);
        $this->assertSame(1, $timeline[2]['frekuensi_ke']);
        $this->assertEquals(2.0, $timeline[2]['akumulasi_qty']);
    }

    public function test_service_filters_by_patient_medicine_and_all_branches(): void
    {
        $service = new PatientMedicineCardService();

        $allBranches = $service->build([
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'pharmacy_id' => 'all',
        ]);
        $this->assertSame(2, $allBranches['stats']['total_patients']);

        $byMedicine = $service->build([
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'pharmacy_id' => 'all',
            'medicine_filter' => 'Amoxicillin',
        ]);
        $this->assertCount(1, $byMedicine['summary']);
        $this->assertSame('Siti Aminah', $byMedicine['summary'][0]['patient_name']);

        $byPatientId = $service->build([
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'pharmacy_id' => 'all',
            'patient_filter' => '10',
        ]);
        $this->assertCount(1, $byPatientId['summary']);
        $this->assertSame('Budi Santoso', $byPatientId['summary'][0]['patient_name']);
    }

    public function test_excel_export_builds_summary_and_timeline_sheets(): void
    {
        $service = new PatientMedicineCardService();
        $data = $service->build([
            'start_date' => '2026-11-17',
            'end_date' => '2026-11-17',
            'pharmacy_id' => 2,
        ]);

        $export = new PatientMedicineCardExport($data, ['pharmacy_id' => 2]);
        $sheets = $export->sheets();
        $this->assertCount(2, $sheets);

        $summaryRows = $sheets[0]->array();
        $timelineRows = $sheets[1]->array();

        $this->assertSame('Rekap Frekuensi Obat', $sheets[0]->title());
        $this->assertSame('Riwayat Kronologis', $sheets[1]->title());
        $this->assertSame('Budi Santoso', $summaryRows[6][2]);
        $this->assertSame(2, $summaryRows[6][7]);
        $this->assertSame('TOTAL KESELURUHAN', $summaryRows[7][0]);
        $this->assertSame('RETUR', $timelineRows[6][7]);
        $this->assertSame('TOTAL BERSIH', $timelineRows[9][0]);
    }

    public function test_route_access_control(): void
    {
        $this->get('/master/patient-medicine-cards')->assertRedirect('/login');

        $kasir = new User;
        $kasir->name = 'Kasir';
        $kasir->setRelation('roles', collect([new Role(['name' => 'Kasir', 'guard_name' => 'web'])]));
        $req = Request::create('/master/patient-medicine-cards');
        $req->setUserResolver(fn () => $kasir);
        $res = (new CheckRole)->handle($req, fn () => response('allowed'), 'HO|operator|Operator|administrator|Koordinator|koordinator|General Manager');
        $this->assertSame(route('home'), $res->getTargetUrl());

        $ho = new User;
        $ho->name = 'HO';
        $ho->setRelation('roles', collect([new Role(['name' => 'HO', 'guard_name' => 'web'])]));
        $reqHo = Request::create('/master/patient-medicine-cards');
        $reqHo->setUserResolver(fn () => $ho);
        $resHo = (new CheckRole)->handle($reqHo, fn () => response('allowed'), 'HO|operator|Operator|administrator|Koordinator|koordinator|General Manager');
        $this->assertSame('allowed', $resHo->getContent());
    }
}
