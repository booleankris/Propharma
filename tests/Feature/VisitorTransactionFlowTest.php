<?php

namespace Tests\Feature;

use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Medicines;
use App\Models\Patients;
use App\Models\PaymentParameters;
use App\Models\Pharmacies;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class VisitorTransactionFlowTest extends TestCase
{
    protected $pharmacy;
    protected $kasir1;
    protected $kasir2;
    protected $medicine;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureActiveShift::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckRole::class);
        $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);
        $this->withoutExceptionHandling();

        \Illuminate\Support\Facades\Cache::put('sidebar_creditors', collect());
        \Illuminate\Support\Facades\Cache::put('sidebar_shifts', collect());
        \Illuminate\Support\Facades\Cache::put('sidebar_factories', collect());
        \Illuminate\Support\Facades\Cache::put('sidebar_doctors', collect());

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

        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('clock_out')->nullable();
            $table->timestamps();
        });

        Schema::create('creditors', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('phone')->nullable();
            $table->date('birth')->nullable();
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->string('packaging')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('patient_id')->nullable();
            $table->string('transaction_type');
            $table->string('transaction_code')->nullable();
            $table->decimal('subtotal', 15, 2)->nullable();
            $table->decimal('discount', 15, 2)->nullable();
            $table->decimal('paid', 15, 2)->nullable();
            $table->decimal('changes', 15, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->string('transfer_bank_name')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamp('kwitansi_printed_at')->nullable();
            $table->boolean('is_submitted')->default(false);
            $table->unsignedBigInteger('shift_logs_id')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('transaction_id');
            $table->decimal('quantity', 12, 2)->default(1);
            $table->decimal('discount', 15, 2)->default(0);
            $table->decimal('embalase', 15, 2)->default(0);
            $table->string('cart_type')->nullable();
            $table->string('package')->nullable();
            $table->string('dosage_r')->nullable();
            $table->decimal('item_price', 15, 2)->default(0);
            $table->decimal('raw_total', 15, 2)->default(0);
            $table->decimal('total_price', 15, 2)->default(0);
            $table->decimal('final_price', 15, 2)->default(0);
            $table->string('status')->default('0');
            $table->string('recipe_status')->nullable();
            $table->string('recipe_number')->nullable();
            $table->string('medicine_type')->nullable();
            $table->decimal('service_fee', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('transaction_parameter', function (Blueprint $table) {
            $table->id();
            $table->string('otc')->default('0');
            $table->string('pdu')->default('0');
            $table->string('receipt')->default('0');
            $table->string('credit')->default('0');
            $table->string('embalas')->default('0');
            $table->string('rounding')->default('0');
            $table->timestamps();
        });

        PaymentParameters::create([
            'id' => 1,
            'otc' => '0',
            'pdu' => '0',
            'receipt' => '0',
            'credit' => '0',
            'embalas' => '0',
            'rounding' => '0',
        ]);

        $this->pharmacy = Pharmacies::create(['id' => 1, 'name' => 'Apotek Utama']);
        $this->kasir1 = User::create([
            'id' => 1,
            'pharmacy_id' => 1,
            'name' => 'Kasir Satu',
            'username' => 'kasir1',
            'password' => 'secret',
        ]);
        $this->kasir2 = User::create([
            'id' => 2,
            'pharmacy_id' => 1,
            'name' => 'Kasir Dua',
            'username' => 'kasir2',
            'password' => 'secret',
        ]);

        $this->medicine = Medicines::create(['id' => 10, 'name' => 'Paracetamol 500mg']);
    }

    public function test_mark_visitor_creates_or_links_patient_and_returns_clean_redirect(): void
    {
        $this->actingAs($this->kasir1);

        $transaction = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->kasir1->id,
            'transaction_type' => 'HV/OTC',
            'status' => 0,
        ]);

        MedicineCart::create([
            'user_id' => $this->kasir1->id,
            'medicine_id' => $this->medicine->id,
            'transaction_id' => $transaction->id,
            'quantity' => 2,
            'item_price' => 15000,
            'total_price' => 30000,
            'final_price' => 30000,
            'status' => '0',
        ]);

        $response = $this->postJson(route('transaction.markVisitor'), [
            'transaction_id' => $transaction->id,
            'visitor_name' => 'Bpk. Budi Santoso',
        ]);

        $response->assertOk()
            ->assertJson([
                'success' => true,
                'redirect_url' => route('transaction', ['type' => 'hv']),
            ]);

        $transaction->refresh();
        $this->assertSame(0, (int) $transaction->status);
        $this->assertNotNull($transaction->patient_id);
        $this->assertSame('Bpk. Budi Santoso', $transaction->patients->name);
        $this->assertEquals(30000, (float) $transaction->subtotal);
    }

    public function test_resuming_pending_transaction_by_another_cashier_updates_ownership_cleanly(): void
    {
        // Kasir 1 creates transaction
        $transaction = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->kasir1->id,
            'transaction_type' => 'HV/OTC',
            'status' => 0,
        ]);

        $cart = MedicineCart::create([
            'user_id' => $this->kasir1->id,
            'medicine_id' => $this->medicine->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'item_price' => 20000,
            'total_price' => 20000,
            'final_price' => 20000,
            'status' => '0',
        ]);

        // Kasir 2 logs in and resumes the pending transaction
        $this->actingAs($this->kasir2);

        $response = $this->get(route('transaction', ['type' => 'hv', 'id' => $transaction->id]));
        $response->assertOk();

        // Ownership should now transfer to Kasir 2
        $transaction->refresh();
        $cart->refresh();

        $this->assertSame($this->kasir2->id, $transaction->user_id);
        $this->assertSame($this->kasir2->id, $cart->user_id);
    }

    public function test_resuming_transaction_with_mismatched_url_type_redirects_safely_without_deleting_cart(): void
    {
        $this->actingAs($this->kasir1);

        // Transaction created as HV/OTC
        $transaction = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->kasir1->id,
            'transaction_type' => 'HV/OTC',
            'status' => 0,
        ]);

        MedicineCart::create([
            'user_id' => $this->kasir1->id,
            'medicine_id' => $this->medicine->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'item_price' => 25000,
            'total_price' => 25000,
            'final_price' => 25000,
            'status' => '0',
        ]);

        // Access via 'resep' slug instead of 'hv'
        $response = $this->get(route('transaction', ['type' => 'resep', 'id' => $transaction->id]));

        // It should redirect to correct type 'hv' and keep cart items intact!
        $response->assertRedirect(route('transaction', ['type' => 'hv', 'id' => $transaction->id]));

        $this->assertSame(1, MedicineCart::where('transaction_id', $transaction->id)->count());
    }

    public function test_pending_sales_data_includes_type_slug(): void
    {
        $this->actingAs($this->kasir1);

        $transaction = MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => $this->kasir1->id,
            'transaction_type' => 'HV/OTC',
            'status' => 0,
        ]);

        MedicineCart::create([
            'user_id' => $this->kasir1->id,
            'medicine_id' => $this->medicine->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'item_price' => 10000,
            'total_price' => 10000,
            'final_price' => 10000,
            'status' => '0',
        ]);

        $response = $this->getJson(route('salesdata.pending'), [
            'X-Requested-With' => 'XMLHttpRequest',
        ]);
        $response->assertOk();

        $data = $response->json('data');
        $this->assertNotEmpty($data);
        $this->assertSame('hv', $data[0]['type_slug']);
    }
}
