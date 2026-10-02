<?php

namespace Tests\Feature;

use App\Exports\Orders\InvoiceExport;
use App\Exports\Orders\OrdersExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PurchaseReportInvoiceDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $tables = [
            'pharmacies' => ['name', 'address'],
            'receiving' => ['pharmacy_id', 'updated_at'],
            'receiving_details' => ['receiving_id', 'receiving_details_code', 'invoice_number', 'invoice_date', 'invoice_due', 'invoice_times', 'invoice_payment', 'invoice_ppn', 'created_at'],
            'receiving_items' => ['receiving_details_id', 'order_items_id', 'batches_id', 'qty_received', 'qty', 'discount', 'extra_discount', 'total', 'raw_price', 'expired_date'],
            'order_items' => ['order_id', 'medicine_id', 'creditor_code', 'price', 'pack'],
            'orders' => [],
            'medicines' => ['code', 'name', 'packaging', 'unit'],
            'creditors' => ['code', 'name'],
        ];
        foreach ($tables as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) $table->string($column)->nullable();
            });
        }
        DB::table('pharmacies')->insert(['id' => 2, 'name' => 'Apotek Test']);
        DB::table('receiving')->insert([
            ['id' => 1, 'pharmacy_id' => 2, 'updated_at' => '2026-10-02 10:00:00'],
            ['id' => 2, 'pharmacy_id' => 3, 'updated_at' => '2026-10-02 10:00:00'],
        ]);
        DB::table('orders')->insert(['id' => 1]);
        DB::table('medicines')->insert(['id' => 1, 'code' => 'OBAT', 'name' => 'Obat Test', 'packaging' => 'Box', 'unit' => 'Tablet']);
        DB::table('creditors')->insert(['id' => 1, 'code' => 'SUP-1', 'name' => 'Supplier Test']);
        DB::table('order_items')->insert(['id' => 1, 'order_id' => 1, 'medicine_id' => 1, 'creditor_code' => 'SUP-1', 'price' => 10000, 'pack' => 1]);
        $this->invoice(1, '2026-09-30', '2026-10-01 00:15:00', 'KREDIT');
        $this->invoice(2, '2026-10-01', '2026-09-30 23:55:00', 'KREDIT');
        $this->invoice(3, '2026-09-30', '2026-10-01 01:00:00', 'TUNAI');
        $this->invoice(4, '2026-09-30', '2026-10-01 01:00:00', 'KONSINYASI');
        $this->invoice(5, '2026-09-30', '2026-10-01 01:00:00', 'KHUSUS');
        $this->invoice(6, '2026-09-30', '2026-10-01 01:00:00', null);
        $this->invoice(7, null, '2026-09-30 01:00:00', 'KREDIT');
        $this->invoice(8, '2026-09-29', '2026-09-30 01:00:00', 'KREDIT');
        $this->invoice(9, '2026-09-30', '2026-10-01 01:00:00', 'KREDIT', 2);
    }

    private function invoice(int $id, ?string $date, string $created, ?string $payment, int $receivingId = 1): void
    {
        DB::table('receiving_details')->insert([
            'id' => $id, 'receiving_id' => $receivingId, 'receiving_details_code' => 'RCV-'.$id,
            'invoice_number' => 'INV-'.$id, 'invoice_date' => $date, 'invoice_payment' => $payment,
            'invoice_ppn' => 'TANPA', 'created_at' => $created,
        ]);
        DB::table('receiving_items')->insert([
            'id' => $id, 'receiving_details_id' => $id, 'order_items_id' => 1, 'batches_id' => 1,
            'qty_received' => 1, 'qty' => 1, 'raw_price' => 10000, 'discount' => 0, 'extra_discount' => 0, 'total' => 10000,
        ]);
    }

    public function test_purchase_and_invoice_reports_include_late_entries_by_invoice_date(): void
    {
        foreach ([new OrdersExport(2, '2026-09-30', '2026-09-30'), new InvoiceExport(2, '2026-09-30', '2026-09-30')] as $export) {
            $rows = $export->array();
            $cells = collect($rows)->flatten();
            foreach (['INV-1', 'INV-3', 'INV-4', 'INV-5', 'INV-6'] as $invoice) $this->assertTrue($cells->containsStrict($invoice));
            foreach (['INV-2', 'INV-7', 'INV-8', 'INV-9'] as $invoice) $this->assertFalse($cells->containsStrict($invoice));
            $this->assertStringContainsString('Tanggal Faktur : 30/09/2026', $rows[4][0]);
            $this->assertTrue($cells->containsStrict('01/10/2026'), 'The actual receiving date remains visible.');
        }
    }

    public function test_tabs_use_invoice_date_for_both_discovery_and_contents(): void
    {
        foreach ([new OrdersExport(2, '2026-09-30', '2026-09-30'), new InvoiceExport(2, '2026-09-30', '2026-09-30')] as $export) {
            $sheets = collect($export->sheets())->keyBy(fn ($sheet) => $sheet->title());
            foreach (['Kredit' => 'INV-1', 'Tunai' => 'INV-3', 'Konsinyasi' => 'INV-4', 'Khusus' => 'INV-5', 'Lainnya' => 'INV-6'] as $title => $invoice) {
                $this->assertTrue($sheets->has($title));
                $cells = collect($sheets[$title]->array())->flatten();
                $this->assertTrue($cells->containsStrict($invoice));
                $this->assertFalse($cells->containsStrict('INV-2'));
            }
        }
    }

    public function test_next_day_excludes_prior_day_invoice_and_range_includes_both_boundaries(): void
    {
        foreach ([OrdersExport::class, InvoiceExport::class] as $class) {
            $next = collect((new $class(2, '2026-10-01', '2026-10-01'))->array())->flatten();
            $this->assertTrue($next->containsStrict('INV-2'));
            $this->assertFalse($next->containsStrict('INV-1'));
            $range = collect((new $class(2, '2026-09-30', '2026-10-01'))->array())->flatten();
            $this->assertTrue($range->containsStrict('INV-1'));
            $this->assertTrue($range->containsStrict('INV-2'));
        }
    }

    public function test_invoice_recap_and_supplier_filter_share_invoice_date_period(): void
    {
        $rekap = (new InvoiceExport(2, '2026-09-30', '2026-09-30', 'Rekap', 'SUP-1'))->array();
        $this->assertEquals(50000, $rekap[7][4]);
        $otherSupplier = (new InvoiceExport(2, '2026-09-30', '2026-09-30', 'Detail', 'SUP-OTHER'))->array();
        $this->assertFalse(collect($otherSupplier)->flatten()->containsStrict('INV-1'));
    }
}
