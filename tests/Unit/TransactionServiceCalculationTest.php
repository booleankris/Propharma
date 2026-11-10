<?php

namespace Tests\Unit;

use App\Exports\Report\BankSalesExport;
use App\Exports\Report\BankSalesSheetExport;
use PHPUnit\Framework\TestCase;

class TransactionServiceCalculationTest extends TestCase
{
    private function serviceOnlyTransaction(): object
    {
        return (object) [
            'id' => 901,
            'pharmacy_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'transaction_code' => '2611JASA000101',
            'transaction_type' => 'JASA',
            'payment_method' => 'CASH',
            'transfer_bank_name' => null,
            'subtotal' => 30000,
            'discount' => 0,
            'paid' => 30000,
            'patient_id' => null,
            'doctor_id' => null,
            'user_id' => 1,
            'shift_logs_id' => null,
            'patients' => (object) ['name' => 'Pasien Jasa'],
            'doctors' => (object) ['name' => '-'],
            'user' => (object) ['name' => 'Kasir'],
            'shift_logs' => (object) ['shift_name' => 'Pagi'],
            'transactions' => collect([]),
            'service' => (object) [
                'id' => 1,
                'service_name' => 'Jasa Konsultasi Dokter',
                'price' => 30000,
            ],
        ];
    }

    private function serviceTransactionWithMedicine(): object
    {
        $trx = $this->serviceOnlyTransaction();
        $trx->subtotal = 80000;
        $trx->transactions = collect([
            (object) [
                'id' => 1,
                'medicine_id' => 10,
                'quantity' => 2,
                'item_price' => 25000,
                'discount' => 0,
                'final_price' => 50000,
                'total_price' => 50000,
                'status' => 1,
                'cart_type' => 'UP',
                'medicine' => (object) ['code' => 'MED010', 'name' => 'OBAT A'],
            ],
        ]);

        return $trx;
    }

    public function test_calculate_transaction_amount_includes_service_price(): void
    {
        $this->assertSame(30000.0, BankSalesExport::calculateTransactionAmount($this->serviceOnlyTransaction()));
    }

    public function test_calculate_transaction_amount_adds_service_on_top_of_medicine(): void
    {
        // 50.000 obat + 30.000 jasa = 80.000
        $this->assertSame(80000.0, BankSalesExport::calculateTransactionAmount($this->serviceTransactionWithMedicine()));
    }

    public function test_summarize_includes_service_without_adding_medicine_qty(): void
    {
        $method = new \ReflectionMethod(BankSalesExport::class, 'summarizeTransactions');
        $method->setAccessible(true);

        $summary = $method->invoke(null, collect([$this->serviceOnlyTransaction()]));

        $this->assertSame(1, $summary['struk_count']);
        $this->assertSame(0, $summary['qty_sum'], 'Jasa tidak boleh menambah qty obat');
        $this->assertSame(30000, $summary['gross_sum']);
        $this->assertSame(30000, $summary['net_sum']);
    }

    public function test_detail_sheet_prints_jasa_row_and_reconciles_total(): void
    {
        $sheet = new BankSalesSheetExport(
            1,
            '2026-11-10',
            '2026-11-10',
            'APOTEK SAHABAT',
            'Alamat',
            null,
            'semua',
            'CASH',
            false,
            [],
            collect([$this->serviceTransactionWithMedicine()])
        );

        $method = new \ReflectionMethod(BankSalesSheetExport::class, 'buildDetailRows');
        $method->setAccessible(true);
        $rows = $method->invoke($sheet);

        $jasaRows = array_values(array_filter($rows, function ($row) {
            return is_array($row) && ($row[5] ?? '') === '[JASA] Jasa Konsultasi Dokter';
        }));

        $this->assertCount(1, $jasaRows);
        $this->assertSame(0, $jasaRows[0][6], 'Baris jasa tidak menambah qty obat');
        $this->assertSame(30000, $jasaRows[0][9]);

        $totalRow = null;
        foreach ($rows as $row) {
            if (is_array($row) && str_starts_with((string) ($row[1] ?? ''), 'TOTAL ')) {
                $totalRow = $row;
            }
        }

        $this->assertNotNull($totalRow);
        $this->assertEquals(2, $totalRow[6], 'Qty total tetap qty obat');
        $this->assertSame(80000, $totalRow[9], 'Total item = obat + jasa');
    }

    public function test_detail_sheet_counts_service_only_transaction_without_qty(): void
    {
        $sheet = new BankSalesSheetExport(
            1,
            '2026-11-10',
            '2026-11-10',
            'APOTEK SAHABAT',
            'Alamat',
            null,
            'semua',
            'CASH',
            false,
            [],
            collect([$this->serviceOnlyTransaction()])
        );

        $method = new \ReflectionMethod(BankSalesSheetExport::class, 'buildDetailRows');
        $method->setAccessible(true);
        $rows = $method->invoke($sheet);

        $totalRow = null;
        foreach ($rows as $row) {
            if (is_array($row) && str_starts_with((string) ($row[1] ?? ''), 'TOTAL ')) {
                $totalRow = $row;
            }
        }

        $this->assertNotNull($totalRow);
        $this->assertSame(0, $totalRow[6], 'Transaksi jasa tanpa obat tidak menambah qty');
        $this->assertSame(30000, $totalRow[9]);
    }
}
