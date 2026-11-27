<?php

namespace Tests\Unit;

use App\Http\Controllers\FinanceController;
use App\Models\User;
use App\Services\Finance\NeracaReportService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NeracaPersediaanTest extends TestCase
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

        Schema::create('items_log', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_code')->nullable();
            $table->string('code')->nullable();
            $table->string('type')->nullable();
            $table->unsignedBigInteger('medicine_id')->nullable();
            $table->double('qty')->default(0);
            $table->double('qty_before')->default(0);
            $table->double('qty_after')->default(0);
            $table->string('total')->nullable();
            $table->string('date')->nullable();
            $table->integer('status')->default(1);
            $table->unsignedBigInteger('batches_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();
        });

        Schema::create('medicine_price_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('medicine_id');
            $table->unsignedBigInteger('source_receiving_item_id')->nullable();
            $table->string('old_price')->nullable();
            $table->string('old_het_price')->nullable();
            $table->string('new_price')->nullable();
            $table->string('new_het_price')->nullable();
            $table->timestamps();
        });
    }

    public function test_neraca_persediaan_matches_nilai_stok_with_vat_and_preserves_opening_hna(): void
    {
        Carbon::setTestNow('2026-11-27 14:30:00');

        $user = new User(['name' => 'Staff Branch 2', 'pharmacy_id' => 2]);
        $user->id = 1;
        $user->save();
        $this->be($user);
        Cache::flush();

        // Active medicine: on 2026-10-31 had 10 pcs at HNA 1,000; on Nov bought +5 pcs, sold -2 pcs => current 13 pcs.
        // HNA changed on Nov 10 from 1,000 -> 1,500, and on Nov 20 from 1,500 -> 2,000.
        $medActive = DB::table('medicines')->insertGetId([
            'code' => 'M1',
            'name' => 'Med Active',
            'raw_price' => '2000',
            'pharmacy_net_price' => '2000',
            'status' => 1,
        ]);

        // Inactive medicine (status 0) must be excluded from both AWAL & AKHIR
        $medInactive = DB::table('medicines')->insertGetId([
            'code' => 'M0',
            'name' => 'Med Inactive',
            'raw_price' => '5000',
            'pharmacy_net_price' => '5000',
            'status' => 0,
        ]);

        $b1 = DB::table('batches')->insertGetId([
            'medicine_id' => $medActive,
            'pharmacy_id' => 2,
            'name' => 'B1',
            'stock' => '13',
        ]);
        $b0 = DB::table('batches')->insertGetId([
            'medicine_id' => $medInactive,
            'pharmacy_id' => 2,
            'name' => 'B0',
            'stock' => '10',
        ]);

        DB::table('medicine_transfer_items')->insert([
            ['batches_id' => $b1, 'qty' => 13, 'status' => 1, 'source_type' => null],
            ['batches_id' => $b1, 'qty' => -5, 'status' => 1, 'source_type' => null], // negative MTI row ignored
            ['batches_id' => $b0, 'qty' => 10, 'status' => 1, 'source_type' => null],
        ]);

        // Movements after 2026-10-31: +5 (status 7 incoming) and -2 (status 1 sale with batches_id = null)
        DB::table('items_log')->insert([
            [
                'medicine_id' => $medActive,
                'batches_id' => $b1,
                'type' => 'MU',
                'qty' => 5,
                'qty_before' => 10,
                'qty_after' => 15,
                'total' => '0',
                'date' => '2026-11-05 10:00:00',
                'status' => 7,
                'user_id' => 1,
            ],
            [
                'medicine_id' => $medActive,
                'batches_id' => null, // untracked sale resolved via users.pharmacy_id
                'type' => 'OBAT BEBAS',
                'qty' => 2,
                'qty_before' => 15,
                'qty_after' => 13,
                'total' => '5000',
                'date' => '2026-11-15 11:00:00',
                'status' => 1,
                'user_id' => 1,
            ],
            [
                // SO-SALDO-AWAL marker must NOT affect delta
                'medicine_id' => $medActive,
                'batches_id' => $b1,
                'type' => 'SO-SALDO-AWAL',
                'qty' => 0,
                'qty_before' => 10,
                'qty_after' => 10,
                'total' => '0',
                'date' => '2026-11-01 00:00:00',
                'status' => 5,
                'user_id' => 1,
            ],
            [
                // Inactive medicine log must be ignored
                'medicine_id' => $medInactive,
                'batches_id' => $b0,
                'type' => 'MU',
                'qty' => 4,
                'qty_before' => 6,
                'qty_after' => 10,
                'total' => '0',
                'date' => '2026-11-08 09:00:00',
                'status' => 7,
                'user_id' => 1,
            ],
        ]);

        // Price changes in Nov: earliest after 2026-10-31 had old_price = 1000
        DB::table('medicine_price_history')->insert([
            [
                'user_id' => 1,
                'medicine_id' => $medActive,
                'old_price' => '1000',
                'new_price' => '1500',
                'created_at' => '2026-11-10 08:00:00',
                'updated_at' => '2026-11-10 08:00:00',
            ],
            [
                'user_id' => 1,
                'medicine_id' => $medActive,
                'old_price' => '1500',
                'new_price' => '2000',
                'created_at' => '2026-11-20 08:00:00',
                'updated_at' => '2026-11-20 08:00:00',
            ],
        ]);

        $financeController = new class extends FinanceController {
            public function __construct()
            {
            }

            public function valuation(): array
            {
                return $this->getStockValuation();
            }
        };

        $neracaService = app(NeracaReportService::class);

        $nilaiStokToday = $financeController->valuation()['total']; // 13 * 2000 * 1.11 = 28860
        $neracaAkhir = $neracaService->inventoryValuationAtDate([2], '2026-11-27'); // 28860
        $neracaAwal = $neracaService->inventoryValuationAtDate([2], '2026-10-31'); // (13 - (5 - 2)) * 1000 * 1.11 = 10 * 1110 = 11100

        $this->assertSame(28860.0, $nilaiStokToday);
        $this->assertSame($nilaiStokToday, $neracaAkhir);
        $this->assertSame(11100.0, $neracaAwal);

        Carbon::setTestNow();
    }
}
