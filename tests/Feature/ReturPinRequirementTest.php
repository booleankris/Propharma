<?php

namespace Tests\Feature;

use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\ItemsLog;
use App\Models\MedicineCart;
use App\Models\Medicines;
use App\Models\MedicineTransactions;
use App\Models\Retur;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ReturPinRequirementTest extends TestCase
{
    private User $cashierUser;
    private User $returUser;
    private User $gmUser;
    private User $adminUser;
    private Medicines $medicine;
    private MedicineTransactions $transaction;
    private MedicineCart $cart;
    private FinanceAccount $cashAccount;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->createSchema();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $returPerm = Permission::create(['name' => 'sales.retur', 'guard_name' => 'web']);
        $kasirRole = Role::create(['name' => 'Kasir', 'guard_name' => 'web']);
        $returRole = Role::create(['name' => 'Retur', 'guard_name' => 'web']);
        $gmRole = Role::create(['name' => 'General Manager', 'guard_name' => 'web']);
        $adminRole = Role::create(['name' => 'administrator', 'guard_name' => 'web']);
        $kasirRole->givePermissionTo($returPerm);

        $this->cashierUser = User::create([
            'name' => 'KASIR CABANG 1',
            'username' => 'kasir1',
            'password' => bcrypt('secret'),
            'secret_pin' => '1111',
            'pharmacy_id' => 1,
        ]);
        $this->cashierUser->assignRole($kasirRole);

        // User Retur dari cabang berbeda (pharmacy_id = 5) untuk uji lintas cabang
        $this->returUser = User::create([
            'name' => 'RETUR APRIL',
            'username' => 'RETUR APRIL',
            'password' => bcrypt('secret'),
            'secret_pin' => '7953',
            'pharmacy_id' => 5,
        ]);
        $this->returUser->assignRole($returRole);

        $this->gmUser = User::create([
            'name' => 'GM TEST',
            'username' => 'gm_test',
            'password' => bcrypt('secret'),
            'secret_pin' => '2222',
            'pharmacy_id' => 1,
        ]);
        $this->gmUser->assignRole($gmRole);

        $this->adminUser = User::create([
            'name' => 'ADMIN TEST',
            'username' => 'admin_test',
            'password' => bcrypt('secret'),
            'secret_pin' => '3333',
            'pharmacy_id' => 1,
        ]);
        $this->adminUser->assignRole($adminRole);

        DB::table('pharmacies')->insert([
            'id' => 1,
            'name' => 'SAHABAT PMI',
            'code' => 'PMI',
        ]);

        $this->medicine = Medicines::create([
            'code' => 'OBT-001',
            'name' => 'PARACETAMOL 500MG',
            'stock' => 10,
        ]);

        $this->transaction = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->cashierUser->id,
            'transaction_type' => 'PENJUALAN',
            'transaction_code' => 'TRX-0001',
            'subtotal' => 20000,
            'status' => 1,
        ]);

        $this->cart = MedicineCart::create([
            'transaction_id' => $this->transaction->id,
            'medicine_id' => $this->medicine->id,
            'quantity' => 5,
            'final_price' => 20000,
        ]);

        $this->cashAccount = FinanceAccount::create([
            'pharmacy_id' => 1,
            'code' => 'KAS-01',
            'name' => 'Kas Apotek',
            'category' => 'Kas & Bank',
            'account_type' => 'kas_apotek',
            'balance' => 100000,
            'is_active' => true,
        ]);
    }

    private function createSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->string('secret_pin')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->index(['model_id', 'model_type']);
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
        });

        Schema::create('pharmacies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('code')->nullable();
            $table->timestamps();
        });

        Schema::create('shift_logs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->decimal('stock', 12, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('medicine_id');
            $table->string('name')->nullable();
            $table->decimal('stock', 12, 2)->default(0);
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty', 12, 2)->default(0);
            $table->integer('status')->default(1);
            $table->string('source_type')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('transaction_type');
            $table->string('transaction_code')->nullable();
            $table->string('paid')->nullable();
            $table->string('changes')->nullable();
            $table->string('subtotal')->nullable();
            $table->string('discount')->nullable();
            $table->unsignedBigInteger('shift_logs_id')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('medicine_carts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id');
            $table->decimal('quantity', 12, 2)->default(0);
            $table->decimal('final_price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('items_log', function (Blueprint $table): void {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->decimal('qty', 12, 2)->default(0);
            $table->decimal('qty_before', 12, 2)->default(0);
            $table->decimal('qty_after', 12, 2)->default(0);
            $table->decimal('total', 15, 2)->default(0);
            $table->dateTime('date')->nullable();
            $table->integer('status')->default(3);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('retur', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('medicine_id');
            $table->string('qty_retur');
            $table->string('total_retur');
            $table->integer('status')->default(0)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('finance_accounts', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('code');
            $table->string('name');
            $table->string('category');
            $table->string('account_type')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('finance_payments', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->unsignedBigInteger('medicine_transaction_id')->nullable();
            $table->unsignedBigInteger('account_id');
            $table->string('payment_type');
            $table->date('payment_date');
            $table->decimal('amount', 15, 2)->default(0);
            $table->string('reference_number')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function test_verify_retur_pin_enforces_authorized_roles_and_allows_cross_branch(): void
    {
        $this->actingAs($this->cashierUser);

        // Wrong PIN
        $this->postJson(route('returdata.verifypin'), ['pin' => '9999'])
            ->assertOk()
            ->assertJson(['success' => false]);

        // Kasir PIN rejected
        $this->postJson(route('returdata.verifypin'), ['pin' => '1111'])
            ->assertOk()
            ->assertJson([
                'success' => false,
                'message' => 'PIN tidak memiliki akses Retur (khusus role Retur, General Manager, atau administrator).',
            ]);

        // Cross-branch Retur role accepted
        $this->postJson(route('returdata.verifypin'), ['pin' => '7953'])
            ->assertOk()
            ->assertJson([
                'success' => true,
                'user' => [
                    'id' => $this->returUser->id,
                    'name' => 'RETUR APRIL',
                ],
            ]);

        // General Manager & administrator accepted
        $this->postJson(route('returdata.verifypin'), ['pin' => '2222'])
            ->assertOk()
            ->assertJson(['success' => true, 'user' => ['id' => $this->gmUser->id]]);

        $this->postJson(route('returdata.verifypin'), ['pin' => '3333'])
            ->assertOk()
            ->assertJson(['success' => true, 'user' => ['id' => $this->adminUser->id]]);
    }

    public function test_retur_item_requires_valid_authorized_pin_and_records_pin_actor(): void
    {
        $this->actingAs($this->cashierUser);

        $basePayload = [
            'transaction_id' => $this->transaction->id,
            'medicine_id' => $this->medicine->id,
            'cart_id' => $this->cart->id,
            'qty_retur' => 2,
            'total_retur' => 8000,
            'old_qty' => 5,
            'transfer_id' => 'fallback',
            'account_id' => (string) $this->cashAccount->id,
        ];

        // 1. Missing PIN -> 422
        $this->postJson(route('returdata.returItem'), $basePayload)
            ->assertStatus(422);

        // 2. Wrong PIN -> 422
        $this->postJson(route('returdata.returItem'), $basePayload + ['pin' => '9999'])
            ->assertStatus(422);

        // 3. Unauthorized role (Kasir) PIN -> 403
        $this->postJson(route('returdata.returItem'), $basePayload + ['pin' => '1111'])
            ->assertStatus(403);

        $this->assertEquals(0, Retur::count());
        $this->assertEquals(10, (float) $this->medicine->fresh()->stock);

        // 4. Valid cross-branch Retur PIN -> 200 and records $this->returUser->id everywhere
        $response = $this->postJson(route('returdata.returItem'), $basePayload + ['pin' => '7953']);
        $response->assertOk()
            ->assertJson([
                'success' => true,
                'actor' => [
                    'id' => $this->returUser->id,
                    'name' => 'RETUR APRIL',
                ],
            ]);

        $this->assertEquals(12, (float) $this->medicine->fresh()->stock);

        $returRow = Retur::first();
        $this->assertNotNull($returRow);
        $this->assertSame($this->returUser->id, (int) $returRow->user_id);

        $logRow = ItemsLog::where('status', 3)->first();
        $this->assertNotNull($logRow);
        $this->assertSame($this->returUser->id, (int) $logRow->user_id);

        $returTx = MedicineTransactions::where('transaction_code', 'RT')->first();
        $this->assertNotNull($returTx);
        $this->assertSame($this->returUser->id, (int) $returTx->user_id);

        $payment = FinancePayment::where('payment_type', 'RETUR PENJUALAN')->first();
        $this->assertNotNull($payment);
        $this->assertSame($this->returUser->id, (int) $payment->created_by);
    }
}
