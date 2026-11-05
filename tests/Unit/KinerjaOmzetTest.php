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
                    'total' => 250000,
                    'ppn' => 25000,
                    'exVat' => 225000,
                ],
                'other_income' => 10000,
                'other_income_sd' => 10000,
                'expense' => 50000,
                'saving' => 0,
                'expense_sd' => 50000,
                'margin' => 270495,
                'margin_sd' => 270495,
                'profit_loss_sd' => 230495,
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
                    'total' => 300000,
                    'ppn' => 30000,
                    'exVat' => 270000,
                ],
                'other_income' => 5000,
                'other_income_sd' => 15000,
                'expense' => 40000,
                'saving' => 0,
                'expense_sd' => 90000,
                'margin' => 405675,
                'margin_sd' => 676170,
                'profit_loss_sd' => 601170,
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

        // Check TOTAL Row
        $totalRow = $rows[7];
        $this->assertSame('TOTAL PERIODE', $totalRow[0]);
        $this->assertEquals(950000, $totalRow[5]); // Total Tunai: 350k + 600k
        $this->assertEquals(350000, $totalRow[8]); // Total Kredit: 200k + 150k
        $this->assertEquals(1300000, $totalRow[9]); // Total Omzet: 550k + 750k = 1.3M
        $this->assertEquals(1300000, $totalRow[10]); // Total Omzet S/D: Final cumulative 1.3M
    }
}

