<?php

namespace Tests\Unit;

use App\Exports\Orders\InvoiceSingleSheetExport;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class TestableInvoiceSingleSheetExport extends InvoiceSingleSheetExport
{
    protected $fakeItems;

    public function setFakeItems(Collection $items): void
    {
        $this->fakeItems = $items;
    }

    public function testBuildDetail(): array
    {
        return $this->buildDetail();
    }

    protected function baseQuery()
    {
        return new class($this->fakeItems) {
            protected $items;
            public function __construct($items)
            {
                $this->items = $items;
            }
            public function select($cols)
            {
                return $this;
            }
            public function orderBy($col, $dir = 'asc')
            {
                return $this;
            }
            public function get()
            {
                return $this->items ?? collect();
            }
        };
    }
}

class InvoiceExportDetailFormatTest extends TestCase
{
    public function test_detail_export_produces_supplier_grouped_invoices_with_subtotals_and_totals(): void
    {
        $export = new TestableInvoiceSingleSheetExport(1, '2026-10-16', '2026-10-17', 'Detail');

        $fakeItems = collect([
            // Supplier A - Invoice 1 (item 1)
            (object) [
                'receiving_details_id' => 101,
                'invoice_number' => '1.710.834.042',
                'invoice_date' => '2026-10-15',
                'invoice_due' => '2026-11-15',
                'invoice_times' => 30,
                'invoice_payment' => 'KREDIT',
                'invoice_ppn' => 'TANPA',
                'receiving_id' => 1,
                'receiving_details_code' => 'NT-26-10/0148',
                'receiving_updated_at' => '2026-10-16 10:00:00',
                'receiving_date' => '2026-10-16',
                'receiving_details_created_at' => '2026-10-16 10:00:00',
                'creditor_code' => 'KR0003',
                'creditor_name' => 'ANUGERAH PHARMINDO LESTARI, PT',
                'qty_received' => 2,
                'qty' => 2,
                'discount' => 0,
                'extra_discount' => 0,
                'receiving_raw_price' => 5000000,
                'order_items_price' => 5000000,
            ],
            // Supplier A - Invoice 2 (item 1)
            (object) [
                'receiving_details_id' => 102,
                'invoice_number' => '1.710.834.087',
                'invoice_date' => '2026-10-16',
                'invoice_due' => '2026-11-16',
                'invoice_times' => 30,
                'invoice_payment' => 'KREDIT',
                'invoice_ppn' => 'TANPA',
                'receiving_id' => 2,
                'receiving_details_code' => 'NT-26-10/0146',
                'receiving_updated_at' => '2026-10-16 11:00:00',
                'receiving_date' => '2026-10-16',
                'receiving_details_created_at' => '2026-10-16 11:00:00',
                'creditor_code' => 'KR0003',
                'creditor_name' => 'ANUGERAH PHARMINDO LESTARI, PT',
                'qty_received' => 1,
                'qty' => 1,
                'discount' => 0,
                'extra_discount' => 0,
                'receiving_raw_price' => 1000000,
                'order_items_price' => 1000000,
            ],
            // Supplier B - Invoice 3 (item 1)
            (object) [
                'receiving_details_id' => 103,
                'invoice_number' => '63.260.042.444',
                'invoice_date' => '2026-10-16',
                'invoice_due' => '2026-11-16',
                'invoice_times' => 30,
                'invoice_payment' => 'KREDIT',
                'invoice_ppn' => 'TANPA',
                'receiving_id' => 3,
                'receiving_details_code' => 'NT-26-10/0160',
                'receiving_updated_at' => '2026-10-16 12:00:00',
                'receiving_date' => '2026-10-16',
                'receiving_details_created_at' => '2026-10-16 12:00:00',
                'creditor_code' => 'KR0015',
                'creditor_name' => 'ANUGRAH ARGON MEDICA',
                'qty_received' => 5,
                'qty' => 5,
                'discount' => 0,
                'extra_discount' => 0,
                'receiving_raw_price' => 400000,
                'order_items_price' => 400000,
            ],
        ]);

        $export->setFakeItems($fakeItems);
        $rows = $export->testBuildDetail();

        // 1. Check Header row (Index 0)
        $expectedHeaders = [
            'NM_KREDITUR', 'KD_KREDITUR', 'NO_FAKTUR',
            'TGL_FAKTUR', 'NO_TERIMA', 'TGL_TERIMA',
            'DPP', 'PPN', 'JUMLAH', 'JTH_TEMPO', 'WKT_KREDIT', 'JNS_BAYAR'
        ];
        $this->assertEquals($expectedHeaders, $rows[0]);
        $this->assertCount(12, $rows[0]);

        // 2. Check Supplier A, Invoice 1 (Index 1)
        $this->assertEquals('ANUGERAH PHARMINDO LESTARI, PT', $rows[1][0]);
        $this->assertEquals('KR0003', $rows[1][1]);
        $this->assertEquals('1.710.834.042', $rows[1][2]);
        $this->assertEquals('15/10/2026', $rows[1][3]);
        $this->assertEquals('NT-26-10/0148', $rows[1][4]);
        $this->assertEquals(10000000.0, $rows[1][6]); // DPP
        $this->assertEquals(10000000.0, $rows[1][8]); // Jumlah

        // 3. Check Supplier A, Invoice 2 (Index 2) - Creditor Name & Code should be empty for grouping
        $this->assertEquals('', $rows[2][0]);
        $this->assertEquals('', $rows[2][1]);
        $this->assertEquals('1.710.834.087', $rows[2][2]);
        $this->assertEquals(1000000.0, $rows[2][6]); // DPP
        $this->assertEquals(1000000.0, $rows[2][8]); // Jumlah

        // 4. Check Supplier A, Subtotal Row (Index 3)
        $this->assertEquals('', $rows[3][0]);
        $this->assertEquals('SUBTOTAL - ANUGERAH PHARMINDO LESTARI, PT', $rows[3][1]);
        $this->assertEquals('', $rows[3][2]);
        $this->assertEquals(11000000.0, $rows[3][6]); // Supplier A total DPP
        $this->assertEquals(0.0, $rows[3][7]);        // Supplier A total PPN
        $this->assertEquals(11000000.0, $rows[3][8]); // Supplier A total Jumlah

        // 5. Check Supplier B, Invoice 3 (Index 4)
        $this->assertEquals('ANUGRAH ARGON MEDICA', $rows[4][0]);
        $this->assertEquals('KR0015', $rows[4][1]);
        $this->assertEquals('63.260.042.444', $rows[4][2]);
        $this->assertEquals(2000000.0, $rows[4][6]);

        // 6. Check Supplier B, Subtotal Row (Index 5)
        $this->assertEquals('', $rows[5][0]);
        $this->assertEquals('SUBTOTAL - ANUGRAH ARGON MEDICA', $rows[5][1]);
        $this->assertEquals(2000000.0, $rows[5][6]);

        // 7. Check Grand Total Row (Index 6)
        $this->assertEquals('TOTAL', $rows[6][0]);
        $this->assertEquals('', $rows[6][1]);
        $this->assertEquals(13000000.0, $rows[6][6]); // Grand total DPP
        $this->assertEquals(0.0, $rows[6][7]);        // Grand total PPN
        $this->assertEquals(13000000.0, $rows[6][8]); // Grand total Jumlah

        // 8. Check column widths count
        $widths = $export->columnWidths();
        $this->assertCount(12, $widths);
        $this->assertEquals(28, $widths['A']);
        $this->assertEquals(15, $widths['B']);
        $this->assertEquals(15, $widths['L']);
    }

    public function test_empty_invoices_returns_header_and_empty_message(): void
    {
        $export = new TestableInvoiceSingleSheetExport(1, '2026-10-16', '2026-10-17', 'Detail');
        $export->setFakeItems(collect());
        $rows = $export->testBuildDetail();

        $this->assertCount(2, $rows);
        $this->assertEquals('NM_KREDITUR', $rows[0][0]);
        $this->assertEquals('Tidak ada data untuk periode yang dipilih.', $rows[1][0]);
    }
}
