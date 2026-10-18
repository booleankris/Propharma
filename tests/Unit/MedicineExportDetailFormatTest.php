<?php

namespace Tests\Unit;

use App\Exports\Report\MedicineExport;
use PHPUnit\Framework\TestCase;

class TestableMedicineExport extends MedicineExport
{
    public function testBuildDetail($transactions, array $returns = []): array
    {
        return $this->buildDetail($transactions, $returns);
    }
}

class MedicineExportDetailFormatTest extends TestCase
{
    public function test_detail_export_separates_cart_type_into_jns_harga_column(): void
    {
        $export = new TestableMedicineExport(1, '2026-10-16', '2026-10-17', null, 'semua', 'detail');

        $fakeTransactions = collect([
            (object) [
                'transaction_code' => '26102051501',
                'transaction_type' => 'UPDS',
                'discount' => 0,
                'transactions' => [
                    (object) [
                        'medicine' => (object) ['name' => 'BETASERC TAB 8 MG @100'],
                        'cart_type' => 'UP',
                        'quantity' => 1,
                        'total_price' => 8000,
                        'discount' => 0,
                        'final_price' => 8000,
                    ],
                ],
            ],
            (object) [
                'transaction_code' => '26102051801',
                'transaction_type' => 'UPDS',
                'discount' => 0,
                'transactions' => [
                    (object) [
                        'medicine' => (object) ['name' => 'SPEDIFEN TAB 400 MG @30'],
                        'cart_type' => 'UP',
                        'quantity' => 10,
                        'total_price' => 112000,
                        'discount' => 0,
                        'final_price' => 112000,
                    ],
                ],
            ],
        ]);

        $rows = $export->testBuildDetail($fakeTransactions, []);

        // 1. Table Headers (Index 0)
        $expectedHeaders = ['No.', 'Nomor', 'JNS_HARGA', 'Nama Obat', 'Qty', 'Bruto', 'Disc', 'Netto'];
        $this->assertEquals($expectedHeaders, $rows[0]);
        $this->assertCount(8, $rows[0]);

        // 2. Row 1: BETASERC
        $this->assertEquals(1, $rows[1][0]);
        $this->assertEquals('26102051501', $rows[1][1]);
        $this->assertEquals('UP', $rows[1][2]);
        $this->assertEquals('BETASERC TAB 8 MG @100', $rows[1][3]);
        $this->assertEquals(1.0, $rows[1][4]);
        $this->assertEquals(8000, $rows[1][5]);
        $this->assertEquals(0, $rows[1][6]);
        $this->assertEquals(8000, $rows[1][7]);

        // 3. Row 2: SPEDIFEN
        $this->assertEquals(2, $rows[2][0]);
        $this->assertEquals('26102051801', $rows[2][1]);
        $this->assertEquals('UP', $rows[2][2]);
        $this->assertEquals('SPEDIFEN TAB 400 MG @30', $rows[2][3]);
        $this->assertEquals(10.0, $rows[2][4]);
        $this->assertEquals(112000, $rows[2][5]);
        $this->assertEquals(0, $rows[2][6]);
        $this->assertEquals(112000, $rows[2][7]);

        // 4. TOTAL row (Index 3)
        $this->assertEquals('', $rows[3][0]);
        $this->assertEquals('TOTAL', $rows[3][1]);
        $this->assertEquals('', $rows[3][2]);
        $this->assertEquals('', $rows[3][3]);
        $this->assertEquals(11.0, $rows[3][4]);
        $this->assertEquals(120000, $rows[3][5]);
        $this->assertEquals(0, $rows[3][6]);
        $this->assertEquals(120000, $rows[3][7]);

        // 5. Column Widths
        $widths = $export->columnWidths();
        $this->assertCount(8, $widths);
        $this->assertEquals(6, $widths['A']);
        $this->assertEquals(20, $widths['B']);
        $this->assertEquals(14, $widths['C']);
        $this->assertEquals(38, $widths['D']);
    }
}
