<?php

namespace Tests\Unit;

use App\Exports\Report\BankSalesExport;
use App\Exports\Report\BankSalesSheetExport;
use PHPUnit\Framework\TestCase;

class BankSalesReconciliationTest extends TestCase
{
    public function test_recap_and_detail_sheets_reconcile_with_transaction_discounts(): void
    {
        // Mock transactions for QRIS BNI
        $item1 = (object) [
            'id' => 1,
            'quantity' => 10,
            'item_price' => 7453.44, // raw HNA
            'total_price' => 100000,
            'discount' => 10000,
            'final_price' => 90000, // 90,000 net after item discount
            'status' => 1,
            'cart_type' => null,
            'medicine' => (object) ['code' => 'MED001', 'name' => 'OBAT A'],
        ];

        $item2 = (object) [
            'id' => 2,
            'quantity' => 5,
            'item_price' => 20000,
            'total_price' => 120000,
            'discount' => 0,
            'final_price' => 120000,
            'status' => 1,
            'cart_type' => null,
            'medicine' => (object) ['code' => 'MED002', 'name' => 'OBAT B'],
        ];

        // Trx 1: Subtotal items = 90k + 120k = 210k. Trx discount = 15k. Net = 195k.
        $trx1 = (object) [
            'id' => 101,
            'pharmacy_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'transaction_code' => 'TRX001',
            'transaction_type' => 'UPDS',
            'payment_method' => 'qris',
            'transfer_bank_name' => 'BNI',
            'subtotal' => 210000,
            'discount' => 15000,
            'paid' => 195000,
            'patients' => (object) ['name' => 'Pasien 1'],
            'doctors' => (object) ['name' => 'Dokter 1'],
            'user' => (object) ['name' => 'Kasir 1'],
            'shift_logs' => (object) ['shift_name' => 'Pagi'],
            'shift_logs_id' => 1,
            'transactions' => collect([$item1, $item2]),
        ];

        $item3 = (object) [
            'id' => 3,
            'quantity' => 2,
            'item_price' => 25000,
            'total_price' => 60000,
            'discount' => 5000,
            'final_price' => 55000,
            'status' => 1,
            'cart_type' => null,
            'medicine' => (object) ['code' => 'MED003', 'name' => 'OBAT C'],
        ];

        // Trx 2: Subtotal items = 55k. Trx discount = 0. Net = 55k.
        $trx2 = (object) [
            'id' => 102,
            'pharmacy_id' => 1,
            'status' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'transaction_code' => 'TRX002',
            'transaction_type' => 'HV/OTC',
            'payment_method' => 'qris',
            'transfer_bank_name' => 'BNI',
            'subtotal' => 55000,
            'discount' => 0,
            'paid' => 55000,
            'patients' => (object) ['name' => 'Pasien 2'],
            'doctors' => (object) ['name' => 'Dokter 2'],
            'user' => (object) ['name' => 'Kasir 1'],
            'shift_logs' => (object) ['shift_name' => 'Pagi'],
            'shift_logs_id' => 1,
            'transactions' => collect([$item3]),
        ];

        $transactions = collect([$trx1, $trx2]);

        // 1. Test summarizeTransactions (used by Recap Sheet)
        $refMethod = new \ReflectionMethod(BankSalesExport::class, 'summarizeTransactions');
        $refMethod->setAccessible(true);
        $summary = $refMethod->invoke(null, $transactions);

        // Expected figures:
        // Item totals: 90,000 + 120,000 + 55,000 = 265,000
        // Trx discounts: 15,000 + 0 = 15,000
        // Net: 265,000 - 15,000 = 250,000
        // Total Qty: 10 + 5 + 2 = 17
        $this->assertSame(2, $summary['struk_count']);
        $this->assertEquals(17, $summary['qty_sum']);
        $this->assertSame(265000, $summary['gross_sum']);
        $this->assertSame(15000, $summary['discount_sum']);
        $this->assertSame(250000, $summary['net_sum']);
        $this->assertSame($summary['gross_sum'] - $summary['discount_sum'], $summary['net_sum']);

        // 2. Test Detail Sheet buildDetailRows
        $detailSheet = new BankSalesSheetExport(
            1,
            '2026-10-16',
            '2026-10-16',
            'SAHABAT PMI',
            '',
            null,
            'semua',
            'QRIS BNI',
            false,
            [],
            $transactions
        );

        $buildMethod = new \ReflectionMethod(BankSalesSheetExport::class, 'buildDetailRows');
        $buildMethod->setAccessible(true);
        $detailRows = $buildMethod->invoke($detailSheet);

        // Header: row 0
        // Trx1 items: row 1 (OBAT A), row 2 (OBAT B)
        // Trx1 discount: row 3 ([DISKON TRANSAKSI])
        // Trx2 items: row 4 (OBAT C)
        // Summary row 1: row 5 (SUBTOTAL ITEM QRIS BNI)
        // Summary row 2: row 6 (TOTAL DISKON TRANSAKSI)
        // Summary row 3: row 7 (TOTAL NETTO QRIS BNI)

        $this->assertCount(8, $detailRows);

        // Check [DISKON TRANSAKSI] row
        $discRow = $detailRows[3];
        $this->assertSame('[DISKON TRANSAKSI]', $discRow[5]);
        $this->assertSame(0, $discRow[6]); // Qty
        $this->assertSame(-15000, $discRow[9]); // Total Item

        // Check footer rows
        $subtotalItemRow = $detailRows[5];
        $this->assertSame('SUBTOTAL ITEM QRIS BNI', $subtotalItemRow[1]);
        $this->assertEquals(17, $subtotalItemRow[6]); // Qty
        $this->assertSame(15000, $subtotalItemRow[8]); // Item discounts (10k + 5k)
        $this->assertSame(265000, $subtotalItemRow[9]); // Matches summary gross_sum!

        $totalTrxDiscRow = $detailRows[6];
        $this->assertSame('TOTAL DISKON TRANSAKSI', $totalTrxDiscRow[1]);
        $this->assertSame(-15000, $totalTrxDiscRow[9]); // Matches summary discount_sum!

        $totalNettoRow = $detailRows[7];
        $this->assertSame('TOTAL NETTO QRIS BNI', $totalNettoRow[1]);
        $this->assertEquals(17, $totalNettoRow[6]); // Qty
        $this->assertSame(30000, $totalNettoRow[8]); // Total discounts (15k item + 15k trx)
        $this->assertSame(250000, $totalNettoRow[9]); // Matches summary net_sum!

        // Assert exact reconciliation between recap and detail:
        $this->assertSame($summary['gross_sum'], $subtotalItemRow[9]);
        $this->assertSame($summary['discount_sum'], abs($totalTrxDiscRow[9]));
        $this->assertSame($summary['net_sum'], $totalNettoRow[9]);
    }

    public function test_recap_sheet_includes_total_kas_total_bank_and_total_keseluruhan(): void
    {
        $allBanksData = [
            'CASH' => [
                'struk_count' => 50,
                'qty_sum' => 120,
                'gross_sum' => 1500000,
                'discount_sum' => 50000,
                'net_sum' => 1450000,
            ],
            'KREDIT' => [
                'struk_count' => 10,
                'qty_sum' => 30,
                'gross_sum' => 500000,
                'discount_sum' => 0,
                'net_sum' => 500000,
            ],
            'TRANSFER BCA' => [
                'struk_count' => 5,
                'qty_sum' => 15,
                'gross_sum' => 350000,
                'discount_sum' => 10000,
                'net_sum' => 340000,
            ],
            'QRIS MANDIRI' => [
                'struk_count' => 8,
                'qty_sum' => 20,
                'gross_sum' => 200000,
                'discount_sum' => 5000,
                'net_sum' => 195000,
            ],
            'DEBIT BRI' => [
                'struk_count' => 2,
                'qty_sum' => 5,
                'gross_sum' => 100000,
                'discount_sum' => 0,
                'net_sum' => 100000,
            ],
            'BELUM BAYAR' => [
                'struk_count' => 1,
                'qty_sum' => 2,
                'gross_sum' => 25000,
                'discount_sum' => 0,
                'net_sum' => 25000,
            ],
        ];

        $sheet = new BankSalesSheetExport(
            1,
            '2026-10-16',
            '2026-10-16',
            'SAHABAT PMI',
            '',
            null,
            'semua',
            null,
            true, // isRecap
            $allBanksData,
            []
        );

        $buildMethod = new \ReflectionMethod(BankSalesSheetExport::class, 'buildRecapRows');
        $buildMethod->setAccessible(true);
        $rows = $buildMethod->invoke($sheet);

        // Header: row 0
        // Data: row 1 (CASH), row 2 (KREDIT), row 3 (TRANSFER BCA), row 4 (QRIS MANDIRI), row 5 (DEBIT BRI), row 6 (BELUM BAYAR)
        // Summary row 1: row 7 (TOTAL KAS)
        // Summary row 2: row 8 (TOTAL BANK)
        // Summary row 3: row 9 (TOTAL KESELURUHAN)
        $this->assertCount(10, $rows);

        $totalKasRow = $rows[7];
        $this->assertSame('', $totalKasRow[0]);
        $this->assertSame('TOTAL KAS', $totalKasRow[1]);
        $this->assertSame(50, $totalKasRow[2]); // Struk
        $this->assertEquals(120, $totalKasRow[3]); // Qty
        $this->assertSame(1500000, $totalKasRow[4]); // Gross
        $this->assertSame(50000, $totalKasRow[5]); // Discount
        $this->assertSame(1450000, $totalKasRow[6]); // Net
        // 100% same as CASH data
        $this->assertSame($allBanksData['CASH']['struk_count'], $totalKasRow[2]);
        $this->assertSame($allBanksData['CASH']['net_sum'], $totalKasRow[6]);

        $totalBankRow = $rows[8];
        $this->assertSame('', $totalBankRow[0]);
        $this->assertSame('TOTAL BANK', $totalBankRow[1]);
        // Bank = TRANSFER BCA (5) + QRIS MANDIRI (8) + DEBIT BRI (2) = 15 struk
        $this->assertSame(15, $totalBankRow[2]);
        $this->assertEquals(40, $totalBankRow[3]); // Qty: 15 + 20 + 5 = 40
        $this->assertSame(650000, $totalBankRow[4]); // Gross: 350k + 200k + 100k
        $this->assertSame(15000, $totalBankRow[5]); // Discount: 10k + 5k + 0
        $this->assertSame(635000, $totalBankRow[6]); // Net: 340k + 195k + 100k

        $totalKeseluruhanRow = $rows[9];
        $this->assertSame('', $totalKeseluruhanRow[0]);
        $this->assertSame('TOTAL KESELURUHAN', $totalKeseluruhanRow[1]);
        // Total Kas + Total Bank = Total Keseluruhan
        $this->assertSame($totalKasRow[2] + $totalBankRow[2], $totalKeseluruhanRow[2]);
        $this->assertEquals($totalKasRow[3] + $totalBankRow[3], $totalKeseluruhanRow[3]);
        $this->assertSame($totalKasRow[4] + $totalBankRow[4], $totalKeseluruhanRow[4]);
        $this->assertSame($totalKasRow[5] + $totalBankRow[5], $totalKeseluruhanRow[5]);
        $this->assertSame($totalKasRow[6] + $totalBankRow[6], $totalKeseluruhanRow[6]);
        $this->assertSame(2085000, $totalKeseluruhanRow[6]); // 1,450,000 + 635,000
    }
}
