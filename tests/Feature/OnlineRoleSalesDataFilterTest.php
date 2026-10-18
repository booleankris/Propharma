<?php

namespace Tests\Feature;

use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class OnlineRoleSalesDataFilterTest extends TestCase
{
    protected $pharmacy;
    protected $shopeeUser;
    protected $grabUser;
    protected $waUser;
    protected $digitalUser;
    protected $adminUser;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $pdo = DB::connection()->getPdo();
        if (method_exists($pdo, 'sqliteCreateFunction')) {
            $pdo->sqliteCreateFunction('FIELD', function (...$args) {
                if (empty($args)) return 0;
                $needle = array_shift($args);
                $pos = array_search($needle, $args);
                return $pos === false ? 0 : $pos + 1;
            });
        }

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

        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('username')->unique();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
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
            $table->unsignedBigInteger('shift_id')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('status')->nullable();
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->string('transaction_type')->default('HV/OTC');
            $table->string('transaction_code')->nullable();
            $table->double('paid')->default(0);
            $table->double('changes')->default(0);
            $table->double('subtotal')->default(0);
            $table->double('discount')->default(0);
            $table->integer('status')->default(1);
            $table->string('payment_method')->nullable();
            $table->string('transfer_bank_name')->nullable();
            $table->unsignedBigInteger('shift_logs_id')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->dateTime('kwitansi_printed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->integer('qty')->default(1);
            $table->double('item_price')->default(10000);
            $table->double('discount')->default(0);
            $table->double('total_price')->default(10000);
            $table->double('final_price')->default(10000);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        $this->pharmacy = Pharmacies::create(['id' => 1, 'name' => 'SAHABAT PMI', 'address' => 'Jl. PMI']);

        $roleShopee  = Role::create(['name' => 'Online Shopee', 'guard_name' => 'web']);
        $roleGrab    = Role::create(['name' => 'Online Grab', 'guard_name' => 'web']);
        $roleWA      = Role::create(['name' => 'Online', 'guard_name' => 'web']);
        $roleDigital = Role::create(['name' => 'Digital', 'guard_name' => 'web']);
        $roleAdmin   = Role::create(['name' => 'administrator', 'guard_name' => 'web']);

        $this->shopeeUser = User::create(['name' => 'Shopee Staff', 'username' => 'shopee_pmi', 'pharmacy_id' => 1]);
        $this->shopeeUser->assignRole($roleShopee);

        $this->grabUser = User::create(['name' => 'Grab Staff', 'username' => 'grab_pmi', 'pharmacy_id' => 1]);
        $this->grabUser->assignRole($roleGrab);

        $this->waUser = User::create(['name' => 'WA Online Staff', 'username' => 'wa_pmi', 'pharmacy_id' => 1]);
        $this->waUser->assignRole($roleWA);

        $this->digitalUser = User::create(['name' => 'Digital Staff', 'username' => 'digital_pmi', 'pharmacy_id' => 1]);
        $this->digitalUser->assignRole($roleDigital);

        $this->adminUser = User::create(['name' => 'Admin User', 'username' => 'admin_pmi', 'pharmacy_id' => 1]);
        $this->adminUser->assignRole($roleAdmin);

        // Buat 1 transaksi Shopee
        $txShopee = MedicineTransactions::create([
            'id' => 101,
            'pharmacy_id' => 1,
            'user_id' => $this->shopeeUser->id,
            'transaction_code' => 'TRX-SHOPEE-001',
            'status' => 1,
            'subtotal' => 50000,
        ]);
        MedicineCart::create([
            'transaction_id' => $txShopee->id,
            'user_id' => $this->shopeeUser->id,
            'final_price' => 50000,
            'total_price' => 50000,
            'status' => 1,
        ]);

        // Buat 1 transaksi Grab
        $txGrab = MedicineTransactions::create([
            'id' => 102,
            'pharmacy_id' => 1,
            'user_id' => $this->grabUser->id,
            'transaction_code' => 'TRX-GRAB-001',
            'status' => 1,
            'subtotal' => 60000,
        ]);
        MedicineCart::create([
            'transaction_id' => $txGrab->id,
            'user_id' => $this->grabUser->id,
            'final_price' => 60000,
            'total_price' => 60000,
            'status' => 1,
        ]);

        // Buat 1 transaksi Online WA
        $txWA = MedicineTransactions::create([
            'id' => 103,
            'pharmacy_id' => 1,
            'user_id' => $this->waUser->id,
            'transaction_code' => 'TRX-WA-001',
            'status' => 1,
            'subtotal' => 70000,
        ]);
        MedicineCart::create([
            'transaction_id' => $txWA->id,
            'user_id' => $this->waUser->id,
            'final_price' => 70000,
            'total_price' => 70000,
            'status' => 1,
        ]);

        // Buat 1 transaksi Digital
        $txDigital = MedicineTransactions::create([
            'id' => 104,
            'pharmacy_id' => 1,
            'user_id' => $this->digitalUser->id,
            'transaction_code' => 'TRX-DIGITAL-001',
            'status' => 1,
            'subtotal' => 80000,
        ]);
        MedicineCart::create([
            'transaction_id' => $txDigital->id,
            'user_id' => $this->digitalUser->id,
            'final_price' => 80000,
            'total_price' => 80000,
            'status' => 1,
        ]);
    }

    public function test_helpers_identify_restricted_online_roles(): void
    {
        $this->assertEquals('Online Shopee', getRestrictedOnlineRole($this->shopeeUser));
        $this->assertEquals('shopee', getRestrictedOnlineChannelKey($this->shopeeUser));

        $this->assertEquals('Online Grab', getRestrictedOnlineRole($this->grabUser));
        $this->assertEquals('grab', getRestrictedOnlineChannelKey($this->grabUser));

        $this->assertEquals('Online', getRestrictedOnlineRole($this->waUser));
        $this->assertEquals('online', getRestrictedOnlineChannelKey($this->waUser));

        $this->assertEquals('Digital', getRestrictedOnlineRole($this->digitalUser));
        $this->assertEquals('digital', getRestrictedOnlineChannelKey($this->digitalUser));

        $this->assertNull(getRestrictedOnlineRole($this->adminUser));
        $this->assertNull(getRestrictedOnlineChannelKey($this->adminUser));
    }

    public function test_shopee_user_only_sees_shopee_sales_data(): void
    {
        $this->withoutExceptionHandling();
        $response = $this->actingAs($this->shopeeUser)
            ->getJson(route('salesdata.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('TRX-SHOPEE-001', $data[0]['code']);
    }

    public function test_grab_user_only_sees_grab_sales_data(): void
    {
        $response = $this->actingAs($this->grabUser)
            ->getJson(route('salesdata.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('TRX-GRAB-001', $data[0]['code']);
    }

    public function test_online_wa_user_only_sees_online_sales_data(): void
    {
        $response = $this->actingAs($this->waUser)
            ->getJson(route('salesdata.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('TRX-WA-001', $data[0]['code']);
    }

    public function test_digital_user_only_sees_digital_sales_data(): void
    {
        $response = $this->actingAs($this->digitalUser)
            ->getJson(route('salesdata.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('TRX-DIGITAL-001', $data[0]['code']);
    }

    public function test_shopee_user_cannot_bypass_filter_via_query_param(): void
    {
        // Mencoba request dengan channel grab padahal role Online Shopee
        $response = $this->actingAs($this->shopeeUser)
            ->getJson(route('salesdata.index', ['channel' => 'grab']), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');

        // Tetap hanya shopee yang muncul
        $this->assertCount(1, $data);
        $this->assertEquals('TRX-SHOPEE-001', $data[0]['code']);
    }

    public function test_shopee_user_only_sees_shopee_pending_sales(): void
    {
        // Buat pending transaction shopee & grab (status = 0)
        $pendingShopee = MedicineTransactions::create([
            'id' => 201,
            'pharmacy_id' => 1,
            'user_id' => $this->shopeeUser->id,
            'transaction_code' => 'TRX-PENDING-SHOPEE',
            'status' => 0,
            'subtotal' => 25000,
        ]);
        MedicineCart::create([
            'transaction_id' => $pendingShopee->id,
            'user_id' => $this->shopeeUser->id,
            'final_price' => 25000,
            'total_price' => 25000,
            'status' => 0,
        ]);

        $pendingGrab = MedicineTransactions::create([
            'id' => 202,
            'pharmacy_id' => 1,
            'user_id' => $this->grabUser->id,
            'transaction_code' => 'TRX-PENDING-GRAB',
            'status' => 0,
            'subtotal' => 35000,
        ]);
        MedicineCart::create([
            'transaction_id' => $pendingGrab->id,
            'user_id' => $this->grabUser->id,
            'final_price' => 35000,
            'total_price' => 35000,
            'status' => 0,
        ]);

        $response = $this->actingAs($this->shopeeUser)
            ->getJson(route('salesdata.pending'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');

        $this->assertCount(1, $data);
        $this->assertEquals('TRX-PENDING-SHOPEE', $data[0]['code']);
    }

    public function test_regular_cashier_can_see_all_transactions(): void
    {
        $roleKasir = Role::firstOrCreate(['name' => 'Kasir', 'guard_name' => 'web']);
        $cashierUser = User::create(['name' => 'Kasir Reguler', 'username' => 'kasir_reguler', 'pharmacy_id' => 1]);
        $cashierUser->assignRole($roleKasir);

        // Pastikan helper menganggap kasir biasa tidak dibatasi
        $this->assertNull(getRestrictedOnlineRole($cashierUser));
        $this->assertNull(getRestrictedOnlineChannelKey($cashierUser));

        // Kasir biasa mengakses Data Penjualan tanpa filter channel -> bisa melihat seluruh 4 transaksi
        $response = $this->actingAs($cashierUser)
            ->getJson(route('salesdata.index'), ['X-Requested-With' => 'XMLHttpRequest']);

        $response->assertStatus(200);
        $data = $response->json('data');
        $this->assertCount(4, $data);

        // Controller meneruskan restrictedChannel = null sehingga dropdown tidak dikunci
        $controller = app(\App\Http\Controllers\Sales\SalesDataController::class);
        $req = \Illuminate\Http\Request::create('/data/sales', 'GET');
        auth()->setUser($cashierUser);
        $view = $controller->index($req);
        $this->assertNull($view->getData()['restrictedChannel']);
        $this->assertNull($view->getData()['restrictedRole']);
    }

    public function test_online_roles_cannot_access_dashboard_and_redirected_to_home(): void
    {
        $onlineUsers = [
            $this->shopeeUser,
            $this->grabUser,
            $this->waUser,
            $this->digitalUser,
        ];

        foreach ($onlineUsers as $user) {
            $response = $this->actingAs($user)->get(route('dashboard'));
            $response->assertRedirect(route('home'));
        }
    }
}




