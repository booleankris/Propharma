<?php

namespace Tests\Unit;

use App\Exports\Stocks\StorageStockHistoryExport;
use App\Exports\Stocks\StorageStockHistorySheetExport;
use App\Models\Batches;
use App\Models\Factories;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\Pharmacies;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class StorageStockHistoryExportTest extends TestCase
{
    public function test_sheet_export_structure_and_delta_calculation()
    {
        $log1 = new ItemsLog([
            'id' => 1,
            'date' => '2026-11-05 10:00:00',
            'transaction_code' => 'TRX001',
            'status' => 2, // Pembelian (+qty)
            'qty' => 50,
            'qty_before' => 10,
            'qty_after' => 60,
        ]);
        $log1->setRelation('medicines', new Medicines(['code' => 'MED01', 'name' => 'Paracetamol']));
        $log1->setRelation('batches', new Batches(['name' => 'BATCH01', 'expired_date' => '2027-01-01']));
        $log1->setRelation('users', new User(['name' => 'Petugas Gudang']));
        $log1->setRelation('receiving', null);

        $log2 = new ItemsLog([
            'id' => 2,
            'date' => '2026-11-05 11:00:00',
            'transaction_code' => 'TRX002',
            'status' => 4, // Retur Beli (-qty)
            'qty' => 5,
            'qty_before' => 60,
            'qty_after' => 55,
        ]);
        $log2->setRelation('medicines', new Medicines(['code' => 'MED01', 'name' => 'Paracetamol']));
        $log2->setRelation('batches', new Batches(['name' => 'BATCH01', 'expired_date' => '2027-01-01']));
        $log2->setRelation('users', new User(['name' => 'Petugas Gudang']));
        $log2->setRelation('receiving', null);

        $log3 = new ItemsLog([
            'id' => 3,
            'date' => '2026-11-05 12:00:00',
            'transaction_code' => 'TRX003',
            'status' => 7, // Mutasi Stok (after - before)
            'qty' => 10,
            'qty_before' => 55,
            'qty_after' => 45,
        ]);
        $log3->setRelation('medicines', new Medicines(['code' => 'MED01', 'name' => 'Paracetamol']));
        $log3->setRelation('batches', new Batches(['name' => 'BATCH01', 'expired_date' => '2027-01-01']));
        $log3->setRelation('users', new User(['name' => 'Petugas Gudang']));
        $log3->setRelation('receiving', null);

        $sheetExport = new StorageStockHistorySheetExport(
            'Sanbe Farma',
            'PT Sanbe Farma',
            collect([$log1, $log2, $log3]),
            new Request(),
            new Pharmacies(['name' => 'Gudang Pusat'])
        );

        $this->assertEquals('Sanbe Farma', $sheetExport->title());
        $rows = $sheetExport->array();

        // Row 0: APOTEK PROPHARMA — GUDANG PUSAT
        $this->assertStringContainsString('GUDANG PUSAT', $rows[0][0]);
        // Row 1: RIWAYAT PERGERAKAN STOK GUDANG
        $this->assertEquals('RIWAYAT PERGERAKAN STOK GUDANG', $rows[1][0]);
        // Row 4: Headings
        $this->assertEquals(['NO', 'TANGGAL', 'NO. TRANSAKSI', 'KODE OBAT', 'NAMA OBAT', 'TIPE', 'BATCH', 'EXPIRED DATE', 'SALDO AWAL', 'MUTASI', 'SALDO AKHIR', 'PBF / PETUGAS'], $rows[4]);

        // Data Row 1 (log 1): Pembelian delta +50
        $this->assertEquals(1, $rows[5][0]);
        $this->assertEquals('TRX001', $rows[5][2]);
        $this->assertEquals('Pembelian', $rows[5][5]);
        $this->assertEquals(10, $rows[5][8]);
        $this->assertEquals(50, $rows[5][9]);
        $this->assertEquals(60, $rows[5][10]);

        // Data Row 2 (log 2): Retur Beli delta -5
        $this->assertEquals(2, $rows[6][0]);
        $this->assertEquals('TRX002', $rows[6][2]);
        $this->assertEquals('Retur Beli', $rows[6][5]);
        $this->assertEquals(60, $rows[6][8]);
        $this->assertEquals(-5, $rows[6][9]);
        $this->assertEquals(55, $rows[6][10]);

        // Data Row 3 (log 3): Mutasi Stok delta -10
        $this->assertEquals(3, $rows[7][0]);
        $this->assertEquals('TRX003', $rows[7][2]);
        $this->assertEquals('Mutasi Stok', $rows[7][5]);
        $this->assertEquals(55, $rows[7][8]);
        $this->assertEquals(-10, $rows[7][9]);
        $this->assertEquals(45, $rows[7][10]);
    }

    public function test_sheet_title_length_and_sanitization()
    {
        $longNameWithSpecialChars = 'PT. Farma / Kimia [Bagus] : Test * Long Name Exceeding Thirty-One Characters';
        $cleanTitle = trim(preg_replace('/[\\\\\/\\?\\*:\\[\\]]/', ' ', $longNameWithSpecialChars)) ?: 'Pabrik';
        $baseTitle = mb_substr($cleanTitle, 0, 31);

        $this->assertLessThanOrEqual(31, mb_strlen($baseTitle));
        $this->assertDoesNotMatchRegularExpression('/[\\\\\/\\?\\*:\\[\\]]/', $baseTitle);
    }
}
