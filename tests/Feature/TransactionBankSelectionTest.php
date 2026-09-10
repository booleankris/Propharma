<?php

namespace Tests\Feature;

use App\Exports\Report\BankSalesExport;
use App\Models\MedicineCart;
use App\Models\MedicineTransactions;
use App\Models\Medicines;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransactionBankSelectionTest extends TestCase
{
    protected function getAuthenticatedUser(): User
    {
        return User::first();
    }

    public function test_transaction_page_renders_only_5_allowed_banks()
    {
        $user = $this->getAuthenticatedUser();

        foreach (['hv', 'resep', 'upds'] as $type) {
            $response = $this->actingAs($user)->followingRedirects()->get("/transaction/{$type}");
            $response->assertStatus(200);

            $allowedBanks = ['Mandiri', 'BNI', 'BCA', 'BRI', 'BTN'];
            foreach ($allowedBanks as $bank) {
                $response->assertSee('data-bank="' . $bank . '"', false);
                $response->assertSee('<option value="' . $bank . '">', false);
            }
        }
    }

    public function test_transaction_page_does_not_contain_removed_banks_or_wallets()
    {
        $user = $this->getAuthenticatedUser();

        $response = $this->actingAs($user)->followingRedirects()->get('/transaction/hv');
        $response->assertStatus(200);

        $disallowed = [
            '<option value="BSI">',
            'data-bank="BSI"',
            '<option value="Bank Kaltimtara">',
            '<option value="CIMB Niaga">',
            '<option value="Permata">',
            '<option value="Danamon">',
            '<option value="ShopeePay">',
            '<option value="GoPay">',
            '<option value="OVO">',
            '<option value="DANA">',
            '<option value="Lainnya">',
        ];

        foreach ($disallowed as $removedItem) {
            $response->assertDontSee($removedItem, false);
        }
    }

    public function test_transaction_checkout_saves_selected_bank_properly()
    {
        $user = $this->getAuthenticatedUser();

        // Create a test pending transaction and cart item
        DB::beginTransaction();
        try {
            $med = Medicines::where('pharmacy_id', $user->pharmacy_id)->first();
            $this->assertNotNull($med, 'At least one medicine must exist for testing');

            $allowedBanks = ['Mandiri', 'BNI', 'BCA', 'BRI', 'BTN'];

            foreach ($allowedBanks as $bank) {
                $trx = MedicineTransactions::create([
                    'pharmacy_id' => $user->pharmacy_id,
                    'user_id' => $user->id,
                    'transaction_type' => 'HV/OTC',
                    'status' => 0,
                ]);

                $cart = MedicineCart::create([
                    'transaction_id' => $trx->id,
                    'medicine_id' => $med->id,
                    'user_id' => $user->id,
                    'cart_type' => 'HV/OTC',
                    'quantity' => 1,
                    'item_price' => 10000,
                    'discount' => 0,
                    'total_price' => 10000,
                    'final_price' => 10000,
                    'status' => 0,
                ]);

                // Test each bank as payment option
                $checkoutPayload = [
                    'transaction_id' => $trx->id,
                    'paid' => 10000,
                    'changes' => 0,
                    'totaltransaction' => 10000,
                    'discounsubtotalvalue' => 0,
                    'paymentType' => 'TRANSFER',
                    'bank_name' => $bank,
                    'user_id' => $user->id,
                ];

                $response = $this->actingAs($user)
                    ->postJson(route('transaction.checkout'), $checkoutPayload);

                $response->assertStatus(200);
                $this->assertDatabaseHas('medicine_transactions', [
                    'id' => $trx->id,
                    'transfer_bank_name' => $bank,
                    'payment_method' => 'TRANSFER',
                    'status' => 1,
                ]);
            }
        } finally {
            DB::rollBack();
        }
    }

    public function test_bank_sales_export_sheets_include_only_standard_banks()
    {
        $user = $this->getAuthenticatedUser();

        $export = new BankSalesExport($user->pharmacy_id, now()->subDays(7)->toDateString(), now()->toDateString());
        $sheets = $export->sheets();

        $sheetTitles = array_map(fn($s) => $s->title(), $sheets);

        $expectedBanks = BankSalesExport::STANDARD_BANKS;
        foreach ($expectedBanks as $bank) {
            $this->assertContains($bank, $sheetTitles);
        }

        // Assert BSI is NOT in STANDARD_BANKS unless historical data exists
        $this->assertNotContains('BSI', BankSalesExport::STANDARD_BANKS);
    }

    public function test_preview_table_preserves_raw_codes_and_leading_zeros()
    {
        $mockRows = [
            ['SAHABAT PMI'],
            ['LAPORAN PENJUALAN : BRI'],
            ['No', 'Tanggal & Waktu', 'No. Struk', 'Jenis Transaksi', 'Kode Obat', 'Nama Obat', 'Qty', 'Harga Satuan', 'Diskon Item', 'Total Item'],
            [1, '08/09/2026 07:24', '26092000801', 'UPDS', '000100012', 'BETASERC TAB 24MG', 1, 17496, 0, 17000],
            ['', 'TOTAL BRI', '', '', '', '', 1, '', 0, 17000],
        ];

        $html = view('reports._preview_table', ['rows' => $mockRows])->render();

        // Must display raw codes correctly
        $this->assertStringContainsString('26092000801', $html);
        $this->assertStringContainsString('000100012', $html);

        // Must NOT format codes as numbers with dots or strip leading zeroes
        $this->assertStringNotContainsString('26.092.000.801', $html);
        $this->assertStringNotContainsString('100.012', $html);

        // Currency/numeric amounts MUST be formatted properly
        $this->assertStringContainsString('17.496', $html);
        $this->assertStringContainsString('17.000', $html);
    }

    public function test_preview_table_formats_billions_and_trillions_correctly()
    {
        $mockRows = [
            ['SAHABAT PMI'],
            ['LAPORAN PENJUALAN : BCA'],
            ['No', 'Tanggal & Waktu', 'No. Struk', 'Jenis Transaksi', 'Kode Obat', 'Nama Obat', 'Qty', 'Harga Satuan', 'Diskon Item', 'Total Item'],
            [1, '08/09/2026 08:00', '26092000801', 'UPDS', '000100012', 'Mesin CT Scan', 2, 1500000000, 0, 3000000000],
            [2, '08/09/2026 08:30', '26092000802', 'Bebas', '000999888', 'Alat Radiologi RS', 1, 1500000000000, 0, 1500000000000],
            ['', 'TOTAL BCA', '', '', '', '', 3, '', 0, 1503000000000],
        ];

        $html = view('reports._preview_table', ['rows' => $mockRows])->render();

        // Must display raw codes correctly
        $this->assertStringContainsString('26092000801', $html);
        $this->assertStringContainsString('26092000802', $html);
        $this->assertStringContainsString('000100012', $html);
        $this->assertStringContainsString('000999888', $html);

        // Must format billions (1.500.000.000 & 3.000.000.000)
        $this->assertStringContainsString('1.500.000.000', $html);
        $this->assertStringContainsString('3.000.000.000', $html);

        // Must format trillions (1.500.000.000.000 & 1.503.000.000.000)
        $this->assertStringContainsString('1.500.000.000.000', $html);
        $this->assertStringContainsString('1.503.000.000.000', $html);
    }
}
