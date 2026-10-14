<?php

namespace Tests\Feature;

use App\Exports\Report\LiphExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiphTransactionCompletenessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Keep this data-integrity test isolated from any local .env database.
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');

        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('shift_id')->nullable();
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
        });
        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('pharmacy_id');
            $table->unsignedBigInteger('user_id')->default(1);
            $table->unsignedBigInteger('shift_logs_id')->nullable();
            $table->string('transaction_type');
            $table->string('transaction_code')->nullable();
            $table->string('payment_method')->nullable();
            $table->integer('status');
            $table->integer('subtotal')->default(0);
            $table->integer('discount')->default(0);
            $table->timestamps();
        });
        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('transaction_id');
            $table->unsignedBigInteger('user_id')->default(1);
            $table->string('cart_type')->nullable();
            $table->integer('status');
            $table->integer('final_price');
            $table->integer('discount')->default(0);
            $table->integer('service_fee')->default(0);
            $table->integer('embalase')->default(0);
        });
    }

    private function sale(int $id, string $type, int $amount, array $attributes = [], bool $cart = true): void
    {
        DB::table('medicine_transactions')->insert(array_merge([
            'id' => $id,
            'pharmacy_id' => 1,
            'transaction_type' => $type,
            'transaction_code' => 'CASE-' . $id,
            'payment_method' => 'CASH',
            'status' => 1,
            'subtotal' => $amount,
            'discount' => 0,
            'created_at' => '2026-10-13 12:00:00',
            'updated_at' => '2026-10-13 12:00:00',
        ], $attributes));

        if ($cart) {
            DB::table('medicine_cart')->insert([
                'transaction_id' => $id,
                'status' => 1,
                'final_price' => $amount,
                'discount' => 0,
                'service_fee' => 0,
                'embalase' => 0,
            ]);
        }
    }

    private function row(array $rows, string $label): array
    {
        return collect($rows)->first(fn ($row) => ($row[1] ?? null) === $label) ?? [];
    }

    public function test_all_finalized_sales_and_returns_are_in_liph_exactly_once_and_cancelled_or_other_branch_records_are_not(): void
    {
        $this->sale(1, 'KREDIT', 10000);
        $this->sale(2, 'RESEP KREDIT', 20000);
        $this->sale(3, 'RETUR KREDIT', 3000);
        $this->sale(4, 'HV/OTC', 4000);
        $this->sale(5, 'RETUR JUAL', 500);
        $this->sale(6, 'RESEP TUNAI', 6000);
        $this->sale(7, 'UPDS', 7000);
        $this->sale(8, 'ONLINE', 8000);

        // Finalized cart + BELUM BAYAR is a sale and must appear in LIPH.
        $this->sale(9, 'KREDIT', 9000, [
            'status' => 0,
            'payment_method' => 'BELUM BAYAR',
        ]);
        // ReturnController records returns as a separate RT header and copies
        // the source sale status, so a return from unpaid credit is status 0.
        $this->sale(14, 'RETUR KREDIT', 1000, [
            'status' => 0,
            'transaction_code' => 'RT',
            'payment_method' => null,
        ], false);
        // A pending cart, a non-finalized paid sale, another branch and an
        // unrecognized type must never leak into this report.
        $this->sale(10, 'HV/OTC', 100000, ['status' => 0]);
        $this->sale(11, 'RESEP TUNAI', 110000, ['pharmacy_id' => 2]);
        $this->sale(12, 'UNKNOWN', 120000);
        $this->sale(13, 'UPDS', 130000, [], false);
        DB::table('medicine_cart')->where('transaction_id', 12)->update(['status' => 0]);

        $rows = (new LiphExport(1, '2026-10-13', '2026-10-13', 'Apotek Test'))->array();

        $this->assertSame(39000, $this->row($rows, 'Resep Kredit')[7]);
        $this->assertSame(-4000, $this->row($rows, 'Retur Kredit')[7]);
        $this->assertSame(12000, $this->row($rows, 'Obat Bebas')[7]);
        $this->assertSame(-500, $this->row($rows, 'Retur Tunai')[7]);
        $this->assertSame(6000, $this->row($rows, 'Resep Tunai')[7]);
        // A completed legacy header with no cart lines falls back to subtotal.
        $this->assertSame(137000, $this->row($rows, 'UPDS')[7]);
        $this->assertSame(189500, $this->row($rows, 'Grand Total')[7]);
        $this->assertSame(189500, $this->row($rows, 'Grand Total')[9]);
    }

    public function test_transaction_and_item_discounts_reconcile_to_netto_akhir(): void
    {
        $this->sale(1, 'RESEP TUNAI', 10000, ['discount' => 500]);
        DB::table('medicine_cart')->where('transaction_id', 1)->update(['discount' => 1000]);

        $rows = (new LiphExport(1, '2026-10-13', '2026-10-13'))->array();
        $sale = $this->row($rows, 'Resep Tunai');
        $grand = $this->row($rows, 'Grand Total');

        $this->assertSame(10000, $sale[7]);
        $this->assertSame(500, $sale[8]);
        $this->assertSame(9500, $sale[9]);
        $this->assertSame($grand[7] - $grand[8], $grand[9]);
    }
}
