<?php

namespace Tests\Feature;

use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Medicines;
use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Tests\TestCase;

class CashRecipeCartProtectionTest extends TestCase
{
    private User $user;
    private Medicines $medicine;
    private MedicineTransactions $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        DB::purge('sqlite');
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->withoutMiddleware(\App\Http\Middleware\EnsureActiveShift::class);
        $this->withoutMiddleware(\App\Http\Middleware\HandleInertiaRequests::class);
        $this->withoutMiddleware(\App\Http\Middleware\CheckRole::class);

        foreach (['medicine_cart', 'medicines', 'medicine_transactions', 'users'] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->string('name')->nullable();
            $table->string('username')->nullable();
            $table->string('password')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->nullable();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('debtor_id')->nullable();
            $table->string('transaction_type');
            $table->unsignedTinyInteger('status')->default(0);
            $table->timestamps();
        });

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('unit')->nullable();
            $table->string('packaging')->nullable();
            $table->string('net_price')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('transaction_id');
            $table->string('quantity');
            $table->string('discount')->default('0');
            $table->string('embalase')->nullable();
            $table->string('cart_type')->nullable();
            $table->string('package')->nullable();
            $table->string('dosage_r')->nullable();
            $table->string('item_price')->nullable();
            $table->string('raw_total')->nullable();
            $table->string('total_price');
            $table->string('final_price')->nullable();
            $table->unsignedTinyInteger('status')->default(0);
            $table->string('recipe_status')->nullable();
            $table->string('recipe_number')->nullable();
            $table->string('medicine_type')->nullable();
            $table->string('service_fee')->nullable();
            $table->timestamps();
        });

        $this->user = User::create([
            'pharmacy_id' => 1,
            'name' => 'Kasir Test',
            'username' => 'kasir-test',
            'password' => 'test',
        ]);
        $this->medicine = Medicines::create([
            'name' => 'Obat Test',
            'unit' => 'TAB',
            'packaging' => 'BOX',
            'net_price' => 1000,
        ]);
        $this->transaction = $this->makeTransaction($this->user, 'RESEP TUNAI');
        $this->actingAs($this->user);
    }

    public function test_recipe_items_join_current_open_group_and_finish_only_that_transaction_group(): void
    {
        $first = $this->addItem(true);
        $second = $this->addItem(true);
        $otherTransaction = $this->makeTransaction($this->user, 'RESEP TUNAI');
        $unrelated = $this->makeCartItem($otherTransaction, $this->user, 1, 0);

        $this->assertSame('1', $first->recipe_number);
        $this->assertSame('1', $second->recipe_number);

        $this->postJson(route('sales.sendembalase'), [
            'transaction_id' => $this->transaction->id,
            'jasaValue' => 5000,
        ])->assertOk()->assertJsonPath('finalprice', '6000');

        $this->assertDatabaseHas('medicine_cart', [
            'id' => $first->id,
            'recipe_status' => '1',
            'embalase' => '0',
        ]);
        $this->assertDatabaseHas('medicine_cart', [
            'id' => $second->id,
            'recipe_status' => '1',
            'embalase' => '5000',
            'final_price' => '6000',
        ]);
        $this->assertDatabaseHas('medicine_cart', [
            'id' => $unrelated->id,
            'recipe_status' => '0',
            'embalase' => null,
        ]);
    }

    public function test_completed_recipe_is_not_reopened_and_next_recipe_gets_next_number(): void
    {
        $first = $this->addItem(true);
        $this->completeRecipe(1);
        $ordinary = $this->addItem(false);
        $next = $this->addItem(true);

        $this->assertSame('1', $first->fresh()->recipe_number);
        $this->assertNull($ordinary->recipe_number);
        $this->assertSame('2', $next->recipe_number);
        $this->assertSame('0', $next->recipe_status);
    }

    public function test_stale_checked_tab_cannot_silently_start_a_new_recipe_after_completion(): void
    {
        $this->addItem(true);
        $this->completeRecipe(1);

        $this->postJson(route('transaction.addToCart'), $this->addPayload($this->transaction, true, false))
            ->assertStatus(409);

        $this->assertSame(1, MedicineCart::count());
    }

    public function test_embalase_cannot_finish_a_recipe_when_no_open_recipe_exists(): void
    {
        $this->postJson(route('sales.sendembalase'), [
            'transaction_id' => $this->transaction->id,
            'jasaValue' => 5000,
        ])->assertStatus(422);

        $this->assertSame(0, MedicineCart::count());
    }

    public function test_embalase_targets_the_recipe_even_if_a_non_recipe_line_was_added_after_it(): void
    {
        $recipe = $this->addItem(true);
        $ordinary = $this->addItem(false);

        $this->postJson(route('sales.sendembalase'), [
            'transaction_id' => $this->transaction->id,
            'jasaValue' => 2500,
        ])->assertOk();

        $this->assertDatabaseHas('medicine_cart', [
            'id' => $recipe->id,
            'recipe_status' => '1',
            'embalase' => '2500',
        ]);
        $this->assertDatabaseHas('medicine_cart', [
            'id' => $ordinary->id,
            'recipe_status' => null,
            'embalase' => '0',
        ]);
    }

    public function test_closed_or_foreign_transaction_cannot_be_modified(): void
    {
        $this->transaction->update(['status' => 1]);
        $this->postJson(route('sales.sendembalase'), [
            'transaction_id' => $this->transaction->id,
            'jasaValue' => 5000,
        ])->assertStatus(404);

        $foreignUser = User::create([
            'pharmacy_id' => 1,
            'name' => 'Kasir Lain',
            'username' => 'kasir-lain',
            'password' => 'test',
        ]);
        $foreignTransaction = $this->makeTransaction($foreignUser, 'RESEP TUNAI');
        $this->postJson(route('transaction.addToCart'), $this->addPayload($foreignTransaction, true))
            ->assertStatus(404);

        $this->assertSame(0, MedicineCart::count());
    }

    public function test_recipe_mode_is_rejected_for_non_prescription_transaction(): void
    {
        $this->transaction->update(['transaction_type' => 'UPDS']);

        $this->postJson(route('transaction.addToCart'), $this->addPayload($this->transaction, true))
            ->assertStatus(422);

        $this->assertSame(0, MedicineCart::count());
    }

    public function test_credit_recipe_items_remain_credit_and_keep_their_recipe_group(): void
    {
        $this->transaction->update(['transaction_type' => 'KREDIT']);
        $payload = $this->addPayload($this->transaction, true);
        $payload['cart_type'] = 'UK';

        $this->postJson(route('transaction.addToCart'), $payload)->assertOk();

        $this->assertDatabaseHas('medicine_cart', [
            'transaction_id' => $this->transaction->id,
            'cart_type' => 'UK',
            'medicine_type' => 'KREDIT',
            'recipe_number' => '1',
            'recipe_status' => '0',
        ]);
    }

    public function test_invalid_embalase_amount_is_rejected_without_closing_recipe(): void
    {
        $recipe = $this->addItem(true);

        $this->postJson(route('sales.sendembalase'), [
            'transaction_id' => $this->transaction->id,
            'jasaValue' => -1,
        ])->assertStatus(422);

        $this->assertDatabaseHas('medicine_cart', [
            'id' => $recipe->id,
            'recipe_status' => '0',
            'embalase' => '0',
        ]);
    }

    private function addItem(bool $asRecipe, ?bool $startNewRecipe = null): MedicineCart
    {
        $this->postJson(route('transaction.addToCart'), $this->addPayload($this->transaction, $asRecipe, $startNewRecipe))
            ->assertOk();

        return MedicineCart::latest('id')->firstOrFail();
    }

    private function addPayload(MedicineTransactions $transaction, bool $asRecipe, ?bool $startNewRecipe = null): array
    {
        return [
            'transaction_id' => $transaction->id,
            'medicine_id' => $this->medicine->id,
            'quantity' => 1,
            'discount' => 0,
            'embalase' => 0,
            'cart_type' => 'UM',
            'package' => 1,
            'dosage_r' => 1,
            'price2' => 1000,
            'raw_total' => 1000,
            'final_price' => 1000,
            'total_price' => 1000,
            'racikstatus' => $asRecipe ? 1 : 0,
            'start_new_recipe' => $startNewRecipe ?? $asRecipe,
            'medicine_type' => $transaction->transaction_type,
            'service' => 0,
        ];
    }

    private function makeTransaction(User $user, string $type): MedicineTransactions
    {
        return MedicineTransactions::create([
            'pharmacy_id' => $user->pharmacy_id,
            'user_id' => $user->id,
            'transaction_type' => $type,
            'status' => 0,
        ]);
    }

    private function makeCartItem(MedicineTransactions $transaction, User $user, int $recipeNumber, int $recipeStatus): MedicineCart
    {
        return MedicineCart::create([
            'user_id' => $user->id,
            'medicine_id' => $this->medicine->id,
            'transaction_id' => $transaction->id,
            'quantity' => 1,
            'discount' => 0,
            'cart_type' => 'UM',
            'total_price' => 0,
            'final_price' => 0,
            'status' => 0,
            'recipe_number' => $recipeNumber,
            'recipe_status' => $recipeStatus,
        ]);
    }

    private function completeRecipe(int $recipeNumber): void
    {
        MedicineCart::where('transaction_id', $this->transaction->id)
            ->where('user_id', $this->user->id)
            ->where('recipe_number', $recipeNumber)
            ->update(['recipe_status' => 1]);
    }
}
