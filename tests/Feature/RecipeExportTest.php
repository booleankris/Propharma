<?php

namespace Tests\Feature;

use App\Exports\Report\RecipeExport;
use App\Exports\Report\RecipeMonthlySummarySheet;
use App\Models\MedicineCategory;
use App\Models\Medicines;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class RecipeExportTest extends TestCase
{
    public function test_recipe_export_has_two_sheets_including_rekap_lembar_resep()
    {
        $export = new RecipeExport(1, '2026-01-01', '2026-09-08');
        $sheets = $export->sheets();

        $this->assertCount(2, $sheets);
        $this->assertEquals('DAFTAR RESEP', $sheets[0]->title());
        $this->assertEquals('REKAP LEMBAR RESEP', $sheets[1]->title());
    }

    public function test_recipe_monthly_summary_sheet_structure()
    {
        $export = new RecipeExport(1, '2026-01-01', '2026-09-08');
        $summarySheet = $export->sheets()[1];
        $rows = $summarySheet->array();

        // Check header title
        $this->assertEquals('REKAPITULASI LEMBAR RESEP PER GOLONGAN OBAT', $rows[3][0]);
        $this->assertEquals('Tahun : 2026', $rows[4][0]);

        // Check table column headers (row index 6)
        $expectedCols = ['No.', 'Golongan', 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des', 'Total'];
        $this->assertEquals($expectedCols, $rows[6]);

        // Check categories
        $this->assertEquals('NARKOTIK', $rows[7][1]);
        $this->assertEquals('PSIKOTROPIKA', $rows[8][1]);
        $this->assertEquals('PREKURSOR', $rows[9][1]);
        $this->assertEquals('OOT', $rows[10][1]);
        $this->assertEquals('UMUM', $rows[11][1]);
        $this->assertEquals('TOTAL', $rows[12][1]);

        // Verify mathematical consistency:
        // Sum of categories total = grand total
        $sumCategoriesTotal = $rows[7][14] + $rows[8][14] + $rows[9][14] + $rows[10][14] + $rows[11][14];
        $this->assertEquals($sumCategoriesTotal, $rows[12][14]);

        // Sum of month totals = grand total
        $sumMonths = 0;
        for ($m = 2; $m <= 13; $m++) {
            $sumMonths += $rows[12][$m];
        }
        $this->assertEquals($sumMonths, $rows[12][14]);
    }

    public function test_classification_logic()
    {
        // Dummy medicine models
        $narkotik = new Medicines(['type' => 'NARKOTIKA']);
        $psiko = new Medicines(['type' => 'PSIKOTROPIKA']);
        $prekursor = new Medicines(['type' => 'PREKURSOR']);
        $oot = new Medicines(['type' => 'OBAT-OBAT TERTENTU (OOT)']);
        $umum = new Medicines(['type' => 'REGULER']);

        $this->assertEquals('NARKOTIK', RecipeMonthlySummarySheet::classifyMedicine($narkotik));
        $this->assertEquals('PSIKOTROPIKA', RecipeMonthlySummarySheet::classifyMedicine($psiko));
        $this->assertEquals('PREKURSOR', RecipeMonthlySummarySheet::classifyMedicine($prekursor));
        $this->assertEquals('OOT', RecipeMonthlySummarySheet::classifyMedicine($oot));
        $this->assertEquals('UMUM', RecipeMonthlySummarySheet::classifyMedicine($umum));
    }

    public function test_reports_controller_preview_includes_both_recipe_sheets()
    {
        $user = User::first();
        $this->actingAs($user);

        $request = new Request([
            'selectedReport' => 'Daftar Resep',
            'mode'           => 'preview',
            'start_date'     => '2026-08-01',
            'end_date'       => '2026-09-08',
            'shift'          => '',
            'shiftType'      => 'semua',
        ]);

        $ctrl = app()->make(\App\Http\Controllers\ReportsController::class);
        $res = $ctrl->reports($request);

        $this->assertInstanceOf(\Illuminate\View\View::class, $res);
        $sheets = $res->getData()['sheets'] ?? [];

        $this->assertArrayHasKey('DAFTAR RESEP', $sheets);
        $this->assertArrayHasKey('REKAP LEMBAR RESEP', $sheets);

        $html = $res->render();
        $this->assertStringContainsString('DAFTAR RESEP', $html);
        $this->assertStringContainsString('REKAP LEMBAR RESEP', $html);
        $this->assertStringContainsString('NARKOTIK', $html);
        $this->assertStringContainsString('PSIKOTROPIKA', $html);
    }
}
