<?php

namespace Tests\Feature;

use App\Http\Controllers\PrintController;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class KwitansiPrintTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pharmacy_id');
            $table->timestamp('kwitansi_printed_at')->nullable();
            $table->timestamps();
        });
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 1;
        $user->pharmacy_id = 1;
        $user->shouldReceive('hasRole')->andReturn(false);
        $this->actingAs($user);
        DB::table('medicine_transactions')->insert(['id' => 1, 'pharmacy_id' => 1]);
    }

    public function test_kwitansi_renders_locked_identity_and_blank_signature(): void
    {
        $html = view('sales.kwitansi', [
            'transaction' => (object) ['id' => 1, 'transaction_code' => 'TRX-TEST', 'updated_at' => '2026-10-01'],
            'pharmacy' => (object) ['name' => 'Apotek Test', 'address' => 'Alamat', 'phone' => '123', 'pharmacist' => null, 'city' => 'Samarinda'],
            'patient' => null, 'terbilang' => 'Seribu Rupiah', 'totalPrice' => 1000,
            'paymentFor' => 'Pembelian obat', 'operator' => 'Petugas Transaksi',
        ])->render();
        $this->assertStringContainsString('KWITANSI NO. : <b>TRX-TEST</b>', $html);
        $this->assertStringContainsString('data-fit-min="12">Petugas Transaksi</div>', $html);
        $this->assertStringContainsString('<div class="wet-signature"></div>', $html);
        $this->assertStringNotContainsString('sig-image', $html);
        $this->assertStringContainsString('id="back-button"', $html);
        $this->assertStringContainsString('id="cancel-button"', $html);
        $this->assertStringContainsString('size: 105mm 220mm', $html);
        $this->assertStringContainsString("--detail-font: 'Charm'", $html);
    }

    public function test_print_claim_is_consumed_once_and_rejected_on_retry(): void
    {
        DB::table('medicine_transactions')->where('id', 1)->update(['updated_at' => '2026-09-01 10:00:00']);
        $controller = new PrintController;
        $this->assertSame(200, $controller->claimKwitansiPrint(Request::create('/'), 1)->status());
        $this->assertNotNull(DB::table('medicine_transactions')->value('kwitansi_printed_at'));
        $this->assertSame('2026-09-01 10:00:00', DB::table('medicine_transactions')->value('updated_at'));
        $this->expectException(HttpException::class);
        $this->expectExceptionMessage('Kwitansi sudah pernah dicetak.');
        $controller->claimKwitansiPrint(Request::create('/'), 1);
    }

    public function test_other_pharmacy_cannot_consume_print_claim(): void
    {
        DB::table('medicine_transactions')->where('id', 1)->update(['pharmacy_id' => 2]);
        try {
            (new PrintController)->claimKwitansiPrint(Request::create('/'), 1);
            $this->fail('Cross-pharmacy access must fail.');
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $exception) {
            $this->assertNull(DB::table('medicine_transactions')->value('kwitansi_printed_at'));
        }
    }
}
