<?php

namespace Tests\Unit;

use App\Exports\Report\MedicineExport;
use PHPUnit\Framework\TestCase;

class TestableMedicineExport extends MedicineExport
{
    public array $mockStockMap = [];

    public function testBuildDetail($transactions, array $returns = []): array
    {
        return $this->buildDetail($transactions, $returns);
    }

    public function testBuildRecap($transactions, array $returns = []): array
    {
        return $this->buildRecap($transactions, $returns);
    }

    protected function resolveStockMap(array $medicineIds): array
    {
        return $this->mockStockMap;
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

    public function test_rekap_export_includes_sisa_stok_column(): void
    {
        $export = new TestableMedicineExport(1, '2026-10-16', '2026-10-17', null, 'semua', 'rekap');
        $export->mockStockMap = [
            101 => 25,
            102 => 40,
        ];

        $fakeTransactions = collect([
            (object) [
                'transaction_code' => 'TRX001',
                'transaction_type' => 'UPDS',
                'discount' => 5000,
                'transactions' => [
                    (object) [
                        'medicine' => (object) [
                            'id' => 101,
                            'name' => 'PARACETAMOL 500 MG',
                            'factory' => (object) ['name' => 'KIMIA FARMA'],
                        ],
                        'quantity' => 2,
                        'final_price' => 20000,
                    ],
                    (object) [
                        'medicine' => (object) [
                            'id' => 102,
                            'name' => 'AMOXICILLIN 500 MG',
                            'factory' => (object) ['name' => 'KALBE'],
                        ],
                        'quantity' => 5,
                        'final_price' => 50000,
                    ],
                ],
            ],
        ]);

        $returns = [
            [
                'key'         => 101,
                'medicine_id' => 101,
                'name'        => 'PARACETAMOL 500 MG',
                'factory'     => 'KIMIA FARMA',
                'qty'         => 1,
                'total'       => 10000,
            ],
        ];

        $rows = $export->testBuildRecap($fakeTransactions, $returns);

        // 1. Headers (Row index 0)
        $expectedHeaders = ['No', 'Nama Obat', 'Pabrik', 'Qty Jual', 'Nilai Jual', 'Qty Retur', 'Nilai Retur', 'Qty Netto', 'Nilai Netto', 'Sisa Stok'];
        $this->assertEquals($expectedHeaders, $rows[0]);
        $this->assertCount(10, $rows[0]);

        // 2. Data rows: AMOXICILLIN (sorted A-Z)
        $this->assertEquals(1, $rows[1][0]);
        $this->assertEquals('AMOXICILLIN 500 MG', $rows[1][1]);
        $this->assertEquals('KALBE', $rows[1][2]);
        $this->assertEquals(5.0, $rows[1][3]);
        $this->assertEquals(50000, $rows[1][4]);
        $this->assertEquals(0, $rows[1][5]);
        $this->assertEquals(0, $rows[1][6]);
        $this->assertEquals(5.0, $rows[1][7]);
        $this->assertEquals(50000, $rows[1][8]);
        $this->assertEquals(40, $rows[1][9]); // Sisa Stok from mock

        // 3. Data rows: PARACETAMOL (has return)
        $this->assertEquals(2, $rows[2][0]);
        $this->assertEquals('PARACETAMOL 500 MG', $rows[2][1]);
        $this->assertEquals('KIMIA FARMA', $rows[2][2]);
        $this->assertEquals(2.0, $rows[2][3]);
        $this->assertEquals(20000, $rows[2][4]);
        $this->assertEquals(-1.0, $rows[2][5]);
        $this->assertEquals(-10000, $rows[2][6]);
        $this->assertEquals(1.0, $rows[2][7]);
        $this->assertEquals(10000, $rows[2][8]);
        $this->assertEquals(25, $rows[2][9]); // Sisa Stok from mock

        // 4. Summary: TOTAL (Row index 3)
        $this->assertEquals('TOTAL', $rows[3][1]);
        $this->assertEquals(7.0, $rows[3][3]);
        $this->assertEquals(70000, $rows[3][4]);
        $this->assertEquals(-1.0, $rows[3][5]);
        $this->assertEquals(-10000, $rows[3][6]);
        $this->assertEquals(6.0, $rows[3][7]);
        $this->assertEquals(60000, $rows[3][8]);
        $this->assertEquals(65, $rows[3][9]); // Grand total stock (40 + 25)

        // 5. Summary: POTONGAN TRANSAKSI (Row index 4)
        $this->assertEquals('POTONGAN TRANSAKSI (DISKON NOTA)', $rows[4][1]);
        $this->assertEquals(-5000, $rows[4][8]);
        $this->assertEquals('', $rows[4][9]);

        // 6. Summary: TOTAL NETTO AKHIR (Row index 5)
        $this->assertEquals('TOTAL NETTO AKHIR', $rows[5][1]);
        $this->assertEquals(6.0, $rows[5][7]);
        $this->assertEquals(55000, $rows[5][8]);
        $this->assertEquals(65, $rows[5][9]); // Grand total stock

        // 7. Column Widths for rekap
        $widths = $export->columnWidths();
        $this->assertCount(10, $widths);
        $this->assertEquals(14, $widths['J']);
    }
}
