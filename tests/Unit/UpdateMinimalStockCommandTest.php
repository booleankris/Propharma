<?php

namespace Tests\Unit;

use App\Models\Medicines;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class UpdateMinimalStockCommandTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('medicines', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->integer('minimal_stock')->default(0);
            $table->integer('stock')->default(0);
            $table->timestamps();
        });

        $this->tempFile = sys_get_temp_dir() . '/test_stok_min_' . uniqid('', true) . '.xlsx';
    }

    protected function tearDown(): void
    {
        if (isset($this->tempFile) && is_file($this->tempFile)) {
            @unlink($this->tempFile);
        }

        parent::tearDown();
    }

    private function createSampleWorkbook(): void
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('PMI');

        $sheet->fromArray([
            ['KODE LAMA', 'KODE BARU', 'Nama Obat', 'Qty', 'QTY/BULAN', 'ISI/BOX', 'QTY/BOX', 'STOK MIN'],
            ['018800029', '#N/A', 'OBAT NA', 36, 3.6, 1, 3.6, 0],
            ['042600171', '042600171', 'BONEVELL PFS', 358, 35.8, 1, 35.8, 35.8],
            ['016700043', '016700043', 'NEUROAID II', 5085, 508.5, 60, 8.475, 8.475],
            ['000100001', '100001', 'OBAT PADDED', 2, 0.2, 1, 0.2, 0.2],
            ['006400012', '006400012', 'DUPLIKAT PERTAMA', 33, 3.3, 1, 3.3, 3.3],
            ['006400012', '006400012', 'DUPLIKAT KEDUA', 7, 0.7, 1, 0.7, 0.7],
        ]);

        // Pastikan kode tersimpan sebagai teks agar leading zero tidak hilang
        foreach (['B3' => '042600171', 'B4' => '016700043', 'B5' => '100001', 'B6' => '006400012', 'B7' => '006400012'] as $ref => $val) {
            $sheet->setCellValueExplicit($ref, $val, DataType::TYPE_STRING);
        }

        (new Xlsx($spreadsheet))->save($this->tempFile);
        $spreadsheet->disconnectWorksheets();
    }

    public function test_dry_run_does_not_modify_database(): void
    {
        $this->createSampleWorkbook();

        DB::table('medicines')->insert([
            ['code' => '042600171', 'name' => 'BONEVELL PFS', 'minimal_stock' => 5, 'stock' => 10],
            ['code' => '016700043', 'name' => 'NEUROAID II', 'minimal_stock' => 2, 'stock' => 10],
        ]);

        $this->artisan('medicines:update-minimal-stock', [
            '--file' => $this->tempFile,
            '--sheet' => 'PMI',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertSame(5, (int) Medicines::where('code', '042600171')->value('minimal_stock'));
        $this->assertSame(2, (int) Medicines::where('code', '016700043')->value('minimal_stock'));
    }

    public function test_updates_minimal_stock_with_excel_round_and_skips_na_and_duplicates(): void
    {
        $this->createSampleWorkbook();

        DB::table('medicines')->insert([
            ['code' => '042600171', 'name' => 'BONEVELL PFS', 'minimal_stock' => 0, 'stock' => 99],
            ['code' => '016700043', 'name' => 'NEUROAID II', 'minimal_stock' => 99, 'stock' => 88],
            ['code' => '000100001', 'name' => 'OBAT PADDED', 'minimal_stock' => 5, 'stock' => 77],
            ['code' => '006400012', 'name' => 'DUPLIKAT PERTAMA', 'minimal_stock' => 1, 'stock' => 66],
        ]);

        $this->artisan('medicines:update-minimal-stock', [
            '--file' => $this->tempFile,
            '--sheet' => 'PMI',
            '--force' => true,
        ])->assertExitCode(0);

        // 35.8 -> 36, 8.475 -> 8, 0.2 -> 0, 3.3 -> 3 (baris duplikat kedua 0.7 diabaikan)
        $this->assertSame(36, (int) Medicines::where('code', '042600171')->value('minimal_stock'));
        $this->assertSame(8, (int) Medicines::where('code', '016700043')->value('minimal_stock'));
        $this->assertSame(0, (int) Medicines::where('code', '000100001')->value('minimal_stock'));
        $this->assertSame(3, (int) Medicines::where('code', '006400012')->value('minimal_stock'));

        // Kolom lain seperti stock tidak berubah
        $this->assertSame(99, (int) Medicines::where('code', '042600171')->value('stock'));
    }
}
