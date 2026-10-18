<?php

namespace Tests\Feature;

use App\Exports\Export\ParetoExport;
use App\Exports\Export\ParetoSalesSheet;
use App\Http\Controllers\ParetoController;
use App\Models\Pharmacies;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SalesParetoAccuracyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password');
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('doctors', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('factories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedBigInteger('user_id')->default(1);
            $table->string('transaction_type')->default('HV/OTC');
            $table->string('transaction_code')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('user_id')->default(1);
            $table->string('quantity');
            $table->string('final_price');
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id');
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->unsignedBigInteger('medicine_id');
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_items_id');
            $table->unsignedBigInteger('receiving_details_id')->default(1);
            $table->string('qty');
            $table->string('total');
            $table->timestamps();
        });

        Role::create(['name' => 'Finance', 'guard_name' => 'web']);
        Role::create(['name' => 'General Manager', 'guard_name' => 'web']);
        Role::create(['name' => 'administrator', 'guard_name' => 'web']);
        Role::create(['name' => 'Koordinator', 'guard_name' => 'web']);
        Role::create(['name' => 'Kasir', 'guard_name' => 'web']);

        DB::table('pharmacies')->insert([
            ['id' => 1, 'name' => 'Apotek Propharma 1', 'address' => 'Jl. Propharma 1'],
            ['id' => 2, 'name' => 'Apotek Propharma 2', 'address' => 'Jl. Propharma 2'],
        ]);

        $user = User::create([
            'id' => 1,
            'name' => 'Finance User',
            'username' => 'finance_user',
            'password' => bcrypt('secret'),
            'pharmacy_id' => 1,
        ]);
        $user->assignRole('Finance');

        $this->actingAs($user);
    }

    public function test_sales_pareto_filters_by_transaction_completion_date_not_cart_date()
    {
        // Medicine 1 & 2
        DB::table('medicines')->insert([
            ['id' => 1, 'code' => 'MED-001', 'name' => 'Paracetamol 500mg', 'unit' => 'TAB'],
            ['id' => 2, 'code' => 'MED-002', 'name' => 'Amoxicillin 500mg', 'unit' => 'KAP'],
        ]);

        // Cart item was created on 2026-10-01, but transaction completed on 2026-10-05
        DB::table('medicine_transactions')->insert([
            'id' => 100,
            'pharmacy_id' => 1,
            'status' => 1,
            'created_at' => '2026-10-05 14:30:00',
            'updated_at' => '2026-10-05 14:30:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 1,
            'transaction_id' => 100,
            'medicine_id' => 1,
            'quantity' => '10',
            'final_price' => '50000',
            'status' => 1,
            'created_at' => '2026-10-01 10:00:00', // earlier date!
            'updated_at' => '2026-10-05 14:30:00',
        ]);

        // Another transaction completed on 2026-10-06
        DB::table('medicine_transactions')->insert([
            'id' => 101,
            'pharmacy_id' => 1,
            'status' => 1,
            'created_at' => '2026-10-06 09:00:00',
            'updated_at' => '2026-10-06 09:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 2,
            'transaction_id' => 101,
            'medicine_id' => 2,
            'quantity' => '5',
            'final_price' => '25000',
            'status' => 1,
            'created_at' => '2026-10-06 09:00:00',
            'updated_at' => '2026-10-06 09:00:00',
        ]);

        $ctrl = app(ParetoController::class);

        // 1. Query on 2026-10-01: cart created_at was on Oct 1st, but transaction was NOT on Oct 1st.
        // It must NOT appear on Oct 1st!
        $reqOct1 = Request::create('/pareto/sales', 'GET', [
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-01',
        ]);
        request()->merge($reqOct1->all());
        $resOct1 = json_decode($ctrl->salesPareto($reqOct1)->getContent(), true);
        $this->assertEquals(0, $resOct1['recordsTotal'], 'Transaction completed on Oct 5 must NOT leak into Oct 1');

        // 2. Query on 2026-10-05: transaction was completed on Oct 5th.
        // It MUST appear on Oct 5th!
        $reqOct5 = Request::create('/pareto/sales', 'GET', [
            'start_date' => '2026-10-05',
            'end_date' => '2026-10-05',
        ]);
        request()->merge($reqOct5->all());
        $resOct5 = json_decode($ctrl->salesPareto($reqOct5)->getContent(), true);
        $this->assertEquals(1, $resOct5['recordsTotal'], 'Transaction completed on Oct 5 must be found on Oct 5');
        $this->assertEquals('Paracetamol 500mg', $resOct5['data'][0]['medicine_name']);
        $this->assertEquals(1, $resOct5['data'][0]['rank']);
        $this->assertEquals(100.0, (float) $resOct5['data'][0]['persen']);
        $this->assertEquals(100.0, (float) $resOct5['data'][0]['kumulatif']);
    }

    public function test_date_range_boundary_coverage_from_midnight_to_23_59_59()
    {
        DB::table('medicines')->insert([
            ['id' => 1, 'code' => 'MED-001', 'name' => 'Medicine A', 'unit' => 'TAB'],
            ['id' => 2, 'code' => 'MED-002', 'name' => 'Medicine B', 'unit' => 'TAB'],
            ['id' => 3, 'code' => 'MED-003', 'name' => 'Medicine Outside', 'unit' => 'TAB'],
        ]);

        // Start boundary: 2026-10-10 00:00:00
        DB::table('medicine_transactions')->insert([
            'id' => 201, 'pharmacy_id' => 1, 'status' => 1,
            'created_at' => '2026-10-10 00:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 201, 'transaction_id' => 201, 'medicine_id' => 1,
            'quantity' => '10', 'final_price' => '100000', 'status' => 1,
            'created_at' => '2026-10-10 00:00:00',
        ]);

        // End boundary: 2026-10-12 23:59:59
        DB::table('medicine_transactions')->insert([
            'id' => 202, 'pharmacy_id' => 1, 'status' => 1,
            'created_at' => '2026-10-12 23:59:59',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 202, 'transaction_id' => 202, 'medicine_id' => 2,
            'quantity' => '5', 'final_price' => '50000', 'status' => 1,
            'created_at' => '2026-10-12 23:59:59',
        ]);

        // Outside boundary: 2026-10-13 00:00:00
        DB::table('medicine_transactions')->insert([
            'id' => 203, 'pharmacy_id' => 1, 'status' => 1,
            'created_at' => '2026-10-13 00:00:00',
        ]);
        DB::table('medicine_cart')->insert([
            'id' => 203, 'transaction_id' => 203, 'medicine_id' => 3,
            'quantity' => '1', 'final_price' => '10000', 'status' => 1,
            'created_at' => '2026-10-13 00:00:00',
        ]);

        $ctrl = app(ParetoController::class);
        $req = Request::create('/pareto/sales', 'GET', [
            'start_date' => '2026-10-10',
            'end_date' => '2026-10-12',
        ]);
        request()->merge($req->all());
        $res = json_decode($ctrl->salesPareto($req)->getContent(), true);

        $this->assertEquals(2, $res['recordsTotal']);
        $names = array_column($res['data'], 'medicine_name');
        $this->assertContains('Medicine A', $names);
        $this->assertContains('Medicine B', $names);
        $this->assertNotContains('Medicine Outside', $names);
    }

    public function test_pareto_cumulative_and_search_retention()
    {
        DB::table('medicines')->insert([
            ['id' => 1, 'code' => 'A01', 'name' => 'High Value Drug', 'unit' => 'BTL'],
            ['id' => 2, 'code' => 'B02', 'name' => 'Medium Value Drug', 'unit' => 'BOX'],
            ['id' => 3, 'code' => 'C03', 'name' => 'Low Value Drug', 'unit' => 'STRIP'],
        ]);

        // Total: 700,000 + 200,000 + 100,000 = 1,000,000
        // A01: 70%, cum 70% (Category A)
        // B02: 20%, cum 90% (Category B)
        // C03: 10%, cum 100% (Category C)
        DB::table('medicine_transactions')->insert([
            ['id' => 301, 'pharmacy_id' => 1, 'status' => 1, 'created_at' => '2026-10-15 10:00:00'],
            ['id' => 302, 'pharmacy_id' => 1, 'status' => 1, 'created_at' => '2026-10-15 11:00:00'],
            ['id' => 303, 'pharmacy_id' => 1, 'status' => 1, 'created_at' => '2026-10-15 12:00:00'],
        ]);
        DB::table('medicine_cart')->insert([
            ['id' => 301, 'transaction_id' => 301, 'medicine_id' => 1, 'quantity' => '7', 'final_price' => '700000', 'status' => 1, 'created_at' => '2026-10-15 10:00:00'],
            ['id' => 302, 'transaction_id' => 302, 'medicine_id' => 2, 'quantity' => '2', 'final_price' => '200000', 'status' => 1, 'created_at' => '2026-10-15 11:00:00'],
            ['id' => 303, 'transaction_id' => 303, 'medicine_id' => 3, 'quantity' => '1', 'final_price' => '100000', 'status' => 1, 'created_at' => '2026-10-15 12:00:00'],
        ]);

        $ctrl = app(ParetoController::class);

        // 1. Unfiltered request
        $reqAll = Request::create('/pareto/sales', 'GET', [
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-15',
        ]);
        request()->merge($reqAll->all());
        $resAll = json_decode($ctrl->salesPareto($reqAll)->getContent(), true);

        $this->assertEquals(3, $resAll['recordsTotal']);
        $this->assertEquals(1, $resAll['data'][0]['rank']);
        $this->assertEquals(70.0, (float) $resAll['data'][0]['persen']);
        $this->assertEquals(70.0, (float) $resAll['data'][0]['kumulatif']);

        $this->assertEquals(2, $resAll['data'][1]['rank']);
        $this->assertEquals(20.0, (float) $resAll['data'][1]['persen']);
        $this->assertEquals(90.0, (float) $resAll['data'][1]['kumulatif']);

        $this->assertEquals(3, $resAll['data'][2]['rank']);
        $this->assertEquals(10.0, (float) $resAll['data'][2]['persen']);
        $this->assertEquals(100.0, (float) $resAll['data'][2]['kumulatif']);

        // 2. Search request for Medium Value Drug
        $reqSearch = Request::create('/pareto/sales', 'GET', [
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-15',
            'search_medicine' => 'Medium',
        ]);
        request()->merge($reqSearch->all());
        $resSearch = json_decode($ctrl->salesPareto($reqSearch)->getContent(), true);

        $this->assertEquals(1, $resSearch['recordsFiltered']);
        $item = $resSearch['data'][0];
        $this->assertEquals('Medium Value Drug', $item['medicine_name']);
        // Must retain its true Pareto metrics, NOT distort to 100%
        $this->assertEquals(2, $item['rank'], 'Rank must be retained as #2');
        $this->assertEquals(20.0, (float) $item['persen'], 'Percentage must be retained as 20%');
        $this->assertEquals(90.0, (float) $item['kumulatif'], 'Cumulative must be retained as 90%');

        // 3. Verify Excel export sheet matches
        new ParetoExport(1);
        $sheet = new ParetoSalesSheet(1, '2026-10-15', '2026-10-15', 'Medium');
        $exportData = $sheet->array();
        $row = $exportData[7]; // First data row after header

        $this->assertEquals(2, $row[0], 'Excel export rank must be 2');
        $this->assertEquals('B02', $row[1]);
        $this->assertEquals('Medium Value Drug', $row[2]);
        $this->assertEquals(200000, $row[4]);
        $this->assertEquals(20.0, (float) $row[5]);
        $this->assertEquals(90.0, (float) $row[6]);
    }

    public function test_orders_pareto_accuracy_and_search_retention()
    {
        DB::table('medicines')->insert([
            ['id' => 10, 'code' => 'PUR-01', 'name' => 'Antibiotic Premium', 'unit' => 'BOX'],
            ['id' => 20, 'code' => 'PUR-02', 'name' => 'Vitamin Standard', 'unit' => 'BTL'],
        ]);

        DB::table('orders')->insert([
            ['id' => 501, 'pharmacy_id' => 1, 'created_at' => '2026-10-16 08:00:00'],
            ['id' => 502, 'pharmacy_id' => 1, 'created_at' => '2026-10-16 09:00:00'],
        ]);

        DB::table('order_items')->insert([
            ['id' => 601, 'order_id' => 501, 'medicine_id' => 10, 'created_at' => '2026-10-16 08:00:00'],
            ['id' => 602, 'order_id' => 502, 'medicine_id' => 20, 'created_at' => '2026-10-16 09:00:00'],
        ]);

        DB::table('receiving_items')->insert([
            ['id' => 701, 'order_items_id' => 601, 'receiving_details_id' => 1, 'qty' => '10', 'total' => '800000', 'created_at' => '2026-10-16 10:00:00'],
            ['id' => 702, 'order_items_id' => 602, 'receiving_details_id' => 1, 'qty' => '5', 'total' => '200000', 'created_at' => '2026-10-16 11:00:00'],
        ]);

        $ctrl = app(ParetoController::class);

        $req = Request::create('/pareto/orders', 'GET', [
            'start_date' => '2026-10-16',
            'end_date' => '2026-10-16',
        ]);
        request()->merge($req->all());
        $res = json_decode($ctrl->ordersPareto($req)->getContent(), true);

        $this->assertEquals(2, $res['recordsTotal']);
        $this->assertEquals(1, $res['data'][0]['rank']);
        $this->assertEquals('Antibiotic Premium', $res['data'][0]['medicine_name']);
        $this->assertEquals(80.0, (float) $res['data'][0]['persen']);
        $this->assertEquals(80.0, (float) $res['data'][0]['kumulatif']);

        $this->assertEquals(2, $res['data'][1]['rank']);
        $this->assertEquals('Vitamin Standard', $res['data'][1]['medicine_name']);
        $this->assertEquals(20.0, (float) $res['data'][1]['persen']);
        $this->assertEquals(100.0, (float) $res['data'][1]['kumulatif']);
    }

    public function test_koordinator_role_can_access_pareto()
    {
        $koordinator = User::create([
            'id' => 99,
            'name' => 'Koordinator User',
            'username' => 'koordinator_user',
            'password' => bcrypt('secret'),
            'pharmacy_id' => 1,
        ]);
        $koordinator->assignRole('Koordinator');

        DB::table('shift_logs')->insert([
            'user_id' => $koordinator->id,
            'clock_in' => now(),
            'clock_out' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($koordinator);

        $responseIndex = $this->get(route('pareto.index'));
        $responseIndex->assertStatus(200);

        $responseSales = $this->get(route('pareto.get'));
        $responseSales->assertStatus(200);

        $responseOrders = $this->get(route('pareto.orders.get'));
        $responseOrders->assertStatus(200);
    }

    public function test_unauthorized_role_cannot_access_pareto()
    {
        $kasir = User::create([
            'id' => 100,
            'name' => 'Kasir User',
            'username' => 'kasir_user',
            'password' => bcrypt('secret'),
            'pharmacy_id' => 1,
        ]);
        $kasir->assignRole('Kasir');

        DB::table('shift_logs')->insert([
            'user_id' => $kasir->id,
            'clock_in' => now(),
            'clock_out' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->actingAs($kasir);

        $response = $this->get(route('pareto.index'));
        $response->assertStatus(403);
    }
}
