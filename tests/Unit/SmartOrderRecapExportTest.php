<?php

namespace Tests\Unit;

use App\Exports\Orders\SmartOrderExport;
use App\Exports\Orders\SmartOrderRecapExport;
use Tests\TestCase;

class SmartOrderRecapExportTest extends TestCase
{
    public function test_smart_order_daily_export_restored_structure()
    {
        $items = [
            [
                'code' => 'MED001',
                'name' => 'Amoxicillin 500mg',
                'packaging' => 'Box',
                'raw_price' => 25000,
                'stocks' => 15,
                'min_stock' => 20,
                'total_sold' => 45,
                'sold_1_month' => 30,
                'sold_3_months' => 70,
            ],
        ];

        $filters = [
            'date_from' => '2026-11-01',
            'date_to' => '2026-11-05',
            'pharmacy_name' => 'Apotek PMI',
            'order_code' => 'ORD-123',
        ];

        $export = new SmartOrderExport($items, $filters);
        $this->assertEquals('Smart Order', $export->title());
        $rows = $export->array();

        // Header check
        $this->assertEquals('SMART ORDER - REKOMENDASI PESANAN OBAT', $rows[0][0]);
        $this->assertEquals([
            'No.',
            'Kode Obat',
            'Nama Obat',
            'Kemasan',
            'Harga Beli (HNA)',
            'Sisa Stok',
            'Min. Stok',
            'Status Stok',
            'Jual (Rentang)',
            'Jual 1 Bln (30 Hari)',
            'Jual 3 Bln (90 Hari)',
        ], $rows[5]);

        // Item check
        $this->assertEquals(1, $rows[6][0]);
        $this->assertEquals('MED001', $rows[6][1]);
        $this->assertEquals('Amoxicillin 500mg', $rows[6][2]);
        $this->assertEquals('DI BAWAH MIN', $rows[6][7]);
        $this->assertEquals(45, $rows[6][8]);
        $this->assertEquals(30, $rows[6][9]);
        $this->assertEquals(70, $rows[6][10]);

        // Total row check
        $this->assertEquals('TOTAL', $rows[7][2]);
        $this->assertEquals(45, $rows[7][8]);
        $this->assertEquals(30, $rows[7][9]);
        $this->assertEquals(70, $rows[7][10]);
    }

    public function test_smart_order_recap_export_structure()
    {
        $items = [
            [
                'name' => 'Paracetamol 500mg',
                'code' => 'MED002',
                'factory_name' => 'Kalbe Farma',
                'sold_1_month' => 50,
                'sold_3_months' => 120,
                'stocks' => 85,
            ],
            [
                'name' => 'Cefixime 100mg',
                'code' => 'MED003',
                'factory_name' => 'Kimia Farma',
                'sold_1_month' => 20,
                'sold_3_months' => 45,
                'stocks' => 30,
            ],
        ];

        $filters = [
            'pharmacy_name' => 'Sahabat PMI',
            'order_code' => 'SP-001',
            'stock_scope_label' => 'Gabungan Etalase & Gudang PMI',
        ];

        $export = new SmartOrderRecapExport($items, $filters);
        $this->assertEquals('Rekap Smart Order', $export->title());
        $rows = $export->array();

        // Header check
        $this->assertEquals('SMART ORDER - REKAPITULASI PENJUALAN & STOK PER PABRIK', $rows[0][0]);
        $this->assertStringContainsString('Gabungan Etalase & Gudang PMI', $rows[1][0]);
        $this->assertEquals([
            'No.',
            'Nama Obat',
            'Kode Obat',
            'Nama Pabrik',
            'Terjual 1 Bulan',
            'Terjual 3 Bulan',
            'Stok Sekarang',
        ], $rows[5]);

        // Item 1
        $this->assertEquals(1, $rows[6][0]);
        $this->assertEquals('Paracetamol 500mg', $rows[6][1]);
        $this->assertEquals('MED002', $rows[6][2]);
        $this->assertEquals('Kalbe Farma', $rows[6][3]);
        $this->assertEquals(50, $rows[6][4]);
        $this->assertEquals(120, $rows[6][5]);
        $this->assertEquals(85, $rows[6][6]);

        // Item 2
        $this->assertEquals(2, $rows[7][0]);
        $this->assertEquals('Cefixime 100mg', $rows[7][1]);
        $this->assertEquals('MED003', $rows[7][2]);
        $this->assertEquals('Kimia Farma', $rows[7][3]);
        $this->assertEquals(20, $rows[7][4]);
        $this->assertEquals(45, $rows[7][5]);
        $this->assertEquals(30, $rows[7][6]);

        // Summary row
        $this->assertEquals('TOTAL', $rows[8][1]);
        $this->assertEquals(70, $rows[8][4]); // 50 + 20
        $this->assertEquals(165, $rows[8][5]); // 120 + 45
        $this->assertEquals(115, $rows[8][6]); // 85 + 30
    }
}

