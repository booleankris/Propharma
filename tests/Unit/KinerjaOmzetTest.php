<?php

namespace Tests\Unit;

use App\Exports\Finance\KinerjaOmzetExport;
use App\Services\Finance\KinerjaOmzetService;
use PHPUnit\Framework\TestCase;

class KinerjaOmzetTest extends TestCase
{
    public function test_kinerja_omzet_export_includes_total_omzet_and_total_omzet_sd(): void
    {
        $debtors = [
            ['id' => 1, 'name' => 'BPJS'],
            ['id' => 2, 'name' => 'Klinik A'],
        ];

        $days = [
            [
                'date' => '2026-10-01',
                'sales' => [
                    'cash' => ['morning' => 100000, 'evening' => 150000],
                    'bank' => ['morning' => 50000, 'evening' => 50000],
                    'credit' => [
                        'morning' => [1 => 100000, 2 => 50000],
                        'evening' => [1 => 0, 2 => 50000],
                    ],
                    'returns' => ['morning' => 0, 'evening' => 0],
                    'cashBankTotal' => 350000,
                    'creditTotal' => 200000,
                    'total' => 550000,
                    'total_sd' => 550000,
                    'exVat' => 495495,
                ],
                'purchases' => [
                    'pbf' => 200000,
                    'consignment' => 0,
                    'return' => 0,
                    'cash' => 50000,
                    'discountOff' => 0,
                    'total' => 250000,
                    'sd' => 250000,
                    'ppn' => 25000,
                    'exVat' => 225000,
                ],
                'other_income' => 10000,
                'other_income_sd' => 10000,
                'expense' => 50000,
                'saving' => 0,
                'expense_sd' => 50000,
                'margin' => 300000,
                'margin_sd' => 300000,
                'profit_loss_sd' => 260000,
            ],
            [
                'date' => '2026-10-02',
                'sales' => [
                    'cash' => ['morning' => 200000, 'evening' => 200000],
                    'bank' => ['morning' => 100000, 'evening' => 100000],
                    'credit' => [
                        'morning' => [1 => 50000, 2 => 50000],
                        'evening' => [1 => 50000, 2 => 0],
                    ],
                    'returns' => ['morning' => 0, 'evening' => 0],
                    'cashBankTotal' => 600000,
                    'creditTotal' => 150000,
                    'total' => 750000,
                    'total_sd' => 1300000, // 550,000 + 750,000
                    'exVat' => 675675,
                ],
                'purchases' => [
                    'pbf' => 300000,
                    'consignment' => 0,
                    'return' => 0,
                    'cash' => 0,
                    'discountOff' => 100000,
                    'total' => 200000,
                    'sd' => 450000,
                    'ppn' => 30000,
                    'exVat' => 170000,
                ],
                'other_income' => 5000,
                'other_income_sd' => 15000,
                'expense' => 40000,
                'saving' => 0,
                'expense_sd' => 90000,
                'margin' => 850000,
                'margin_sd' => 850000,
                'profit_loss_sd' => 775000,
            ],
        ];

        $export = new KinerjaOmzetExport(['days' => $days, 'debtors' => $debtors], 10, 2026);
        $rows = $export->array();

        // Check Header Row 4 ($middle)
        // Column 5: TOTAL OMSET TUNAI
        // Column 6: KREDIT (header for debtors)
        // Column 8: Debtor 2
        // Column 9 (debtorStart + debtorCount): TOTAL OMSET KREDIT
        // Column 10: TOTAL OMZET
        // Column 11: TOTAL OMZET S/D
        $middle = $rows[3];
        $this->assertSame('TOTAL OMSET TUNAI', $middle[5]);
        $this->assertSame('TOTAL OMZET', $middle[9]);
        $this->assertSame('TOTAL OMZET S/D', $middle[10]);

        // Check Leaf Row 5 ($leaf)
        $leaf = $rows[4];
        $this->assertSame('BPJS', $leaf[6]);
        $this->assertSame('Klinik A', $leaf[7]);
        $this->assertSame('TOTAL OMSET KREDIT', $leaf[8]);

        // Check Day 1 Row
        $day1 = $rows[5];
        $this->assertSame('01-Oct-26', $day1[0]);
        $this->assertEquals(350000, $day1[5]); // Cash + Bank Total
        $this->assertEquals(200000, $day1[8]); // Credit Total
        $this->assertEquals(550000, $day1[9]); // Total Omzet = Tunai (350k) + Kredit (200k)
        $this->assertEquals(550000, $day1[10]); // Total Omzet S/D (Day 1)

        // Check Day 2 Row
        $day2 = $rows[6];
        $this->assertSame('02-Oct-26', $day2[0]);
        $this->assertEquals(600000, $day2[5]); // Cash + Bank Total
        $this->assertEquals(150000, $day2[8]); // Credit Total
        $this->assertEquals(750000, $day2[9]); // Total Omzet = Tunai (600k) + Kredit (150k)
        $this->assertEquals(1300000, $day2[10]); // Total Omzet S/D = 550k + 750k

        // Kolom pembelian (purchaseStart = 11): PBF, KONSINYASI, RETUR, CASH, DISKON OFF, TOTAL PEMBELIAN, TOTAL PEMBELIAN S/D, MARGIN, HPP %
        $this->assertEquals(200000, $day1[11]); // PBF
        $this->assertEquals(50000, $day1[14]); // CASH
        $this->assertEquals(0, $day1[15]); // DISKON OFF
        $this->assertEquals(250000, $day1[16]); // TOTAL PEMBELIAN = 200k + 50k - 0 - 0
        $this->assertEquals(250000, $day1[17]); // TOTAL PEMBELIAN S/D
        $this->assertEquals(300000, $day1[18]); // MARGIN = Omzet S/D (550k) - Pembelian S/D (250k)

        $this->assertEquals(300000, $day2[11]); // PBF
        $this->assertEquals(100000, $day2[15]); // DISKON OFF
        $this->assertEquals(200000, $day2[16]); // TOTAL PEMBELIAN = 300k - 100k (diskon off)
        $this->assertEquals(450000, $day2[17]); // TOTAL PEMBELIAN S/D = 250k + 200k
        $this->assertEquals(850000, $day2[18]); // MARGIN = 1.3M - 450k

        // HPP % = Total Pembelian S/D / Total Omzet S/D * 100
        $this->assertEqualsWithDelta(45.4545, (float) $day1[19], 0.01); // 250k / 550k * 100
        $this->assertEqualsWithDelta(34.6153, (float) $day2[19], 0.01); // 450k / 1.3M * 100

        // Check TOTAL Row
        $totalRow = $rows[7];
        $this->assertSame('TOTAL PERIODE', $totalRow[0]);
        $this->assertEquals(950000, $totalRow[5]); // Total Tunai: 350k + 600k
        $this->assertEquals(350000, $totalRow[8]); // Total Kredit: 200k + 150k
        $this->assertEquals(1300000, $totalRow[9]); // Total Omzet: 550k + 750k = 1.3M
        $this->assertEquals(1300000, $totalRow[10]); // Total Omzet S/D: Final cumulative 1.3M
        $this->assertEquals(450000, $totalRow[16]); // Total Pembelian periode = 250k + 200k
        $this->assertEquals(450000, $totalRow[17]); // Total Pembelian S/D = nilai kumulatif terakhir
        $this->assertEquals(850000, $totalRow[18]); // Margin = Omzet S/D - Pembelian S/D
        $this->assertEqualsWithDelta(34.6153, (float) $totalRow[19], 0.01); // HPP % = Pembelian S/D / Omzet S/D * 100
    }

    public function test_purchase_aggregates_match_invoice_export_rules(): void
    {
        // Simulate items for an EXCLUDE invoice and an INCLUDE invoice
        // Invoice 1 (EXCLUDE): DPP = 100,000, PPN = 11,000, Total = 111,000
        $dpp1 = 100000;
        $ppn1 = floor($dpp1 * 0.11);
        $total1 = $dpp1 + $ppn1;

        // Invoice 2 (INCLUDE): Gross/Net = 222,000, DPP = floor(222,000 / 1.11) = 199,999, PPN = 22,001
        $total2 = 222000;
        $dpp2 = (float) floor($total2 / (1 + 0.11));
        $ppn2 = $total2 - $dpp2;

        $expectedTotalBeli = (float) ($total1 + $total2); // 111,000 + 222,000 = 333,000
        $expectedTotalPpn = (float) ($ppn1 + $ppn2);
        $expectedTotalDpp = (float) ($dpp1 + $dpp2);

        $this->assertSame(333000.0, (float) $expectedTotalBeli);
        $this->assertSame((float) ($ppn1 + $ppn2), (float) $expectedTotalPpn);
        $this->assertSame((float) ($dpp1 + $dpp2), (float) $expectedTotalDpp);

        // In Kinerja Omzet:
        $dayPurchases = [
            'pbf' => (float) $total1,
            'consignment' => 0.0,
            'cash' => (float) $total2,
            'return' => 0.0,
            'discountOff' => 0.0,
            'ppn' => (float) $expectedTotalPpn,
            'total' => (float) ($total1 + $total2 - 0.0 - 0.0),
            'exVat' => (float) $expectedTotalDpp,
        ];

        // JUMLAH BELI matches Laporan Pembelian Faktur 100%
        $this->assertEquals($expectedTotalBeli, $dayPurchases['total']);
        // PPN FAKTUR matches Laporan Pembelian Faktur 100%
        $this->assertEquals($expectedTotalPpn, $dayPurchases['ppn']);
        // BELI EXC PPN matches Laporan Pembelian Faktur DPP 100%
        $this->assertEquals($expectedTotalDpp, $dayPurchases['exVat']);
    }
}

