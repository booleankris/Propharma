<?php

namespace Tests\Feature;

use App\Models\MedicineTransactions;
use App\Models\TransactionService;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TransactionServiceEndpointTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->timestamps();
        });

        Schema::create('doctor_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('transaction_type');
            $table->string('service_invoice_type')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        Schema::create('transaction_services', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('medicine_transaction_id');
            $table->unsignedBigInteger('doctor_service_id')->nullable();
            $table->string('service_name');
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
            $table->unique('medicine_transaction_id');
        });

        Schema::create('medicine_cart', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('transaction_id')->nullable();
            $table->unsignedBigInteger('medicine_id');
            $table->string('quantity')->default('1');
            $table->string('discount')->default('0');
            $table->string('embalase')->nullable();
            $table->string('cart_type')->nullable();
            $table->string('total_price')->default('0');
            $table->string('final_price')->default('0');
            $table->string('service_fee')->nullable();
            $table->string('recipe_status')->nullable();
            $table->string('recipe_number')->nullable();
            $table->integer('status')->default(0);
            $table->timestamps();
        });

        $this->withoutMiddleware();

        $this->user = new User();
        $this->user->id = 1;
        $this->user->name = 'Kasir Test';
        $this->user->pharmacy_id = 1;
        $this->user->setRelation('roles', collect());

        $this->actingAs($this->user);
    }

    private function makeTransaction(string $type = 'JASA', ?string $invoiceType = null): MedicineTransactions
    {
        return MedicineTransactions::create([
            'pharmacy_id' => 1,
            'user_id' => 1,
            'transaction_type' => $type,
            'service_invoice_type' => $invoiceType,
            'status' => 0,
        ]);
    }

    private function makeService(string $name = 'Jasa Konsultasi Dokter', float $price = 30000): int
    {
        return (int) DB::table('doctor_services')->insertGetId([
            'name' => $name,
            'price' => $price,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_service_cannot_be_selected_on_non_jasa_transaction(): void
    {
        $trx = $this->makeTransaction('UPDS');
        $serviceId = $this->makeService();

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson('/transaction/service', [
                'transaction_id' => $trx->id,
                'doctor_service_id' => $serviceId,
            ])
            ->assertStatus(422)
            ->assertJsonPath('success', false);

        $this->assertDatabaseMissing('transaction_services', ['medicine_transaction_id' => $trx->id]);
    }

    public function test_save_service_snapshots_master_price_and_defaults_to_upds(): void
    {
        $trx = $this->makeTransaction('JASA');
        $serviceId = $this->makeService('Jasa Konsultasi Dokter', 30000);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson('/transaction/service', [
                'transaction_id' => $trx->id,
                'doctor_service_id' => $serviceId,
            ])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('service_amount', 30000)
            ->assertJsonPath('service.name', 'Jasa Konsultasi Dokter')
            ->assertJsonPath('total_transaction', 30000);

        $this->assertDatabaseHas('transaction_services', [
            'medicine_transaction_id' => $trx->id,
            'doctor_service_id' => $serviceId,
            'service_name' => 'Jasa Konsultasi Dokter',
            'price' => 30000,
        ]);

        $this->assertSame('UPDS', $trx->fresh()->service_invoice_type);
    }

    public function test_selecting_another_service_replaces_the_existing_one(): void
    {
        $trx = $this->makeTransaction('JASA', 'UPDS');
        $first = $this->makeService('Jasa Pertama', 10000);
        $second = $this->makeService('Jasa Kedua', 45000);

        $headers = ['X-Requested-With' => 'XMLHttpRequest'];

        $this->withHeaders($headers)->postJson('/transaction/service', [
            'transaction_id' => $trx->id,
            'doctor_service_id' => $first,
        ])->assertOk();

        $this->withHeaders($headers)->postJson('/transaction/service', [
            'transaction_id' => $trx->id,
            'doctor_service_id' => $second,
        ])->assertOk()->assertJsonPath('service_amount', 45000);

        $this->assertSame(1, TransactionService::where('medicine_transaction_id', $trx->id)->count());
        $this->assertDatabaseHas('transaction_services', [
            'medicine_transaction_id' => $trx->id,
            'service_name' => 'Jasa Kedua',
            'price' => 45000,
        ]);
    }

    public function test_changing_invoice_type_clears_medicine_cart(): void
    {
        $trx = $this->makeTransaction('JASA', 'UPDS');

        DB::table('medicine_cart')->insert([
            'transaction_id' => $trx->id,
            'medicine_id' => 10,
            'quantity' => '2',
            'final_price' => '50000',
            'status' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->postJson('/transaction/service/invoice-type', [
                'transaction_id' => $trx->id,
                'invoice_type' => 'RESEP TUNAI',
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('RESEP TUNAI', $trx->fresh()->service_invoice_type);
        $this->assertSame(0, DB::table('medicine_cart')->where('transaction_id', $trx->id)->count());
    }

    public function test_delete_service_removes_selection(): void
    {
        $trx = $this->makeTransaction('JASA', 'UPDS');
        $serviceId = $this->makeService();

        TransactionService::create([
            'medicine_transaction_id' => $trx->id,
            'doctor_service_id' => $serviceId,
            'service_name' => 'Jasa Konsultasi Dokter',
            'price' => 30000,
        ]);

        $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
            ->deleteJson('/transaction/service', ['transaction_id' => $trx->id])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('service_amount', 0);

        $this->assertSame(0, TransactionService::where('medicine_transaction_id', $trx->id)->count());
    }
}
