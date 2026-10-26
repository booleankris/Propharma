<?php

namespace Tests\Unit;

use App\Exports\Report\RecipeExport;
use App\Exports\Report\RecipeDetailSheet;
use App\Exports\Report\RecipeMonthlySummarySheet;
use Carbon\Carbon;
use PHPUnit\Framework\TestCase;

class RecipeExportReturTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        class_exists(RecipeExport::class);
    }

    public function test_recipe_detail_sheet_hides_fully_returned_and_shows_remaining_on_partial_return(): void
    {
        $sheet = new RecipeDetailSheet(1, Carbon::parse('2026-10-26'), Carbon::parse('2026-10-26'), null, 'semua');

        // Trx 1: Fully returned (2 items, both returned)
        $trxFullReturn = (object) [
            'id' => 101,
            'transaction_code' => 'RSP-FULL',
            'transaction_type' => 'RESEP TUNAI',
            'discount' => 5000,
            'created_at' => '2026-10-26 10:00:00',
            'updated_at' => '2026-10-26 10:00:00',
            'doctors' => (object) ['name' => 'dr. John'],
            'patients' => (object) ['name' => 'Jane'],
            'shift_logs' => (object) ['shift' => (object) ['name' => 'Pagi']],
            'transactions' => collect([
                (object) [
                    'medicine_id' => 10,
                    'cart_type' => 'UM',
                    'qty' => 2,
                    'final_price' => 50000,
                ],
            ]),
            'returs' => collect([
                (object) [
                    'medicine_id' => 10,
                    'qty_retur' => 2,
                    'total_retur' => 50000,
                    'status' => 1,
                ],
            ]),
        ];

        // Trx 2: Partially returned (Item 1 returned Rp 388,687, Item 2 remains Rp 25,000)
        $trxPartialReturn = (object) [
            'id' => 102,
            'transaction_code' => '26104000704',
            'transaction_type' => 'RESEP TUNAI',
            'discount' => 0,
            'created_at' => '2026-10-26 18:24:38',
            'updated_at' => '2026-10-26 18:24:38',
            'doctors' => (object) ['name' => 'dr. dio damara'],
            'patients' => (object) ['name' => 'MEILINDA'],
            'shift_logs' => (object) ['shift' => (object) ['name' => 'Sore']],
            'transactions' => collect([
                (object) [
                    'medicine_id' => 839,
                    'cart_type' => 'UM',
                    'qty' => 10,
                    'final_price' => 25000,
                ],
                (object) [
                    'medicine_id' => 3021,
                    'cart_type' => 'UM',
                    'qty' => 20,
                    'final_price' => 388687.6,
                ],
            ]),
            'returs' => collect([
                (object) [
                    'medicine_id' => 3021,
                    'qty_retur' => 20,
                    'total_retur' => 388687.6,
                    'status' => 1,
                ],
            ]),
        ];

        // Trx 3: Normal unreturned transaction (Rp 100,000)
        $trxNormal = (object) [
            'id' => 103,
            'transaction_code' => 'RSP-NORMAL',
            'transaction_type' => 'KREDIT',
            'discount' => 0,
            'created_at' => '2026-10-26 14:00:00',
            'updated_at' => '2026-10-26 14:00:00',
            'doctors' => (object) ['name' => 'dr. Smith'],
            'patients' => (object) ['name' => 'Alice'],
            'shift_logs' => (object) ['shift' => (object) ['name' => 'Siang']],
            'transactions' => collect([
                (object) [
                    'medicine_id' => 50,
                    'cart_type' => 'UK',
                    'qty' => 1,
                    'final_price' => 100000,
                ],
            ]),
            'returs' => collect(),
        ];

        $rows = $sheet->buildRows(collect([$trxFullReturn, $trxPartialReturn, $trxNormal]));

        // Header is row 0
        $this->assertEquals(['No.', 'Tanggal', 'No. Resep', 'Layanan', 'Dokter', 'Pasien', 'Netto', 'Shift'], $rows[0]);

        // Trx 1 (fully returned) MUST NOT be present!
        $noResepCol = array_column($rows, 2);
        $this->assertNotContains('RSP-FULL', $noResepCol, 'Fully returned prescription should NOT be displayed');

        // Trx 2 (partial return) MUST be present with remaining nominal only (Rp 25,000)
        $this->assertEquals('26104000704', $rows[1][2]);
        $this->assertEquals(25000, $rows[1][6], 'Partial return should only show remaining nominal of 25,000');

        // Trx 3 (normal) MUST be present with Rp 100,000
        $this->assertEquals('RSP-NORMAL', $rows[2][2]);
        $this->assertEquals(100000, $rows[2][6]);

        // Total row: 25000 + 100000 = 125000
        $this->assertEquals('TOTAL', $rows[3][5]);
        $this->assertEquals(125000, $rows[3][6]);
    }

    public function test_classification_with_active_items(): void
    {
        $narkotik = (object) ['type' => 'NARKOTIKA', 'category' => (object) ['name' => 'NARKOTIKA']];
        $umum = (object) ['type' => 'REGULER', 'category' => (object) ['name' => 'OBAT BEBAS']];

        $trx = (object) [
            'transactions' => [
                (object) ['medicine' => $narkotik],
                (object) ['medicine' => $umum],
            ],
        ];

        // If both items active -> NARKOTIK
        $this->assertEquals('NARKOTIK', RecipeMonthlySummarySheet::classifyPrescription($trx));

        // If Narkotik item was returned and only umum is active -> UMUM
        $activeItems = [
            (object) ['medicine' => $umum],
        ];
        $this->assertEquals('UMUM', RecipeMonthlySummarySheet::classifyPrescription($trx, $activeItems));
    }
}
