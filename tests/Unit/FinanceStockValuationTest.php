<?php

namespace Tests\Unit;

use App\Http\Controllers\FinanceController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class FinanceStockValuationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropAllTables();

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('password')->nullable();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
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

        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name')->default('web');
            $table->timestamps();
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

        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('code')->nullable();
            $table->string('name');
            $table->string('raw_price')->nullable();
            $table->string('pharmacy_net_price')->nullable();
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->string('name');
            $table->string('stock')->default('0');
            $table->timestamps();
        });

        Schema::create('medicine_transfer_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_transfer_id')->nullable();
            $table->unsignedBigInteger('batches_id');
            $table->unsignedBigInteger('source_batches_id')->nullable();
            $table->string('source_type')->nullable();
            $table->double('qty')->default(0);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id')->default(1);
            $table->integer('status')->default(1);
            $table->timestamps();
        });

        Schema::create('receiving_details', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_id')->nullable();
            $table->string('invoice_payment')->nullable();
            $table->date('invoice_date')->nullable();
            $table->timestamps();
        });

        Schema::create('receiving_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('receiving_details_id')->nullable();
            $table->unsignedBigInteger('order_items_id')->nullable();
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('medicine_id');
            $table->timestamps();
        });
    }

    public function test_stock_valuation_excludes_inactive_medicines_and_uses_only_raw_price_times_vat(): void
    {
        $user = new User([
            'name' => 'Staff Branch 3',
            'pharmacy_id' => 3,
        ]);
        $user->id = 1;
        $this->be($user);

        Cache::flush();

        $activeWithRawPrice = DB::table('medicines')->insertGetId([
            'code' => 'M1',
            'name' => 'Active Raw',
            'raw_price' => '1000',
            'pharmacy_net_price' => '9999',
            'status' => 1,
        ]);

        $activeEmptyRawPrice = DB::table('medicines')->insertGetId([
            'code' => 'M2',
            'name' => 'Active Empty Raw',
            'raw_price' => '',
            'pharmacy_net_price' => '5000',
            'status' => 1,
        ]);

        $inactiveWithStock = DB::table('medicines')->insertGetId([
            'code' => 'M3',
            'name' => 'Inactive Med',
            'raw_price' => '2000',
            'pharmacy_net_price' => '2000',
            'status' => 0,
        ]);

        $b1 = DB::table('batches')->insertGetId([
            'medicine_id' => $activeWithRawPrice,
            'pharmacy_id' => 3,
            'name' => 'B1',
            'stock' => '10',
        ]);
        $b2 = DB::table('batches')->insertGetId([
            'medicine_id' => $activeEmptyRawPrice,
            'pharmacy_id' => 3,
            'name' => 'B2',
            'stock' => '4',
        ]);
        $b3 = DB::table('batches')->insertGetId([
            'medicine_id' => $inactiveWithStock,
            'pharmacy_id' => 3,
            'name' => 'B3',
            'stock' => '5',
        ]);

        DB::table('medicine_transfer_items')->insert([
            ['batches_id' => $b1, 'qty' => 10, 'status' => 1, 'source_type' => null],
            ['batches_id' => $b2, 'qty' => 4, 'status' => 1, 'source_type' => null],
            ['batches_id' => $b3, 'qty' => 5, 'status' => 1, 'source_type' => null],
        ]);

        $controller = new class extends FinanceController {
            public function __construct()
            {
            }

            public function valuation(): array
            {
                return $this->getStockValuation();
            }
        };

        $result = $controller->valuation();

        // Only active medicines are counted (M1 + M2 = 2 items, qty = 10 + 4 = 14)
        // M2 has empty raw_price so its valuation is 0 (no fallback to pharmacy_net_price)
        // M1 valuation = 10 * 1000 * 1.11 = 11100
        $this->assertSame(11100.0, $result['total']);
        $this->assertSame(14.0, $result['totalQty']);
        $this->assertSame(2, $result['totalItems']);
    }
}
