<?php

namespace Tests\Feature;

use App\Http\Controllers\Master\MasterDataExportController;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

class MasterDataExportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::dropIfExists('export_test_rows');
        Schema::create('export_test_rows', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        config()->set('master_exports.test', [
            'filename' => 'master-test',
            'table' => 'export_test_rows',
            'columns' => [
                'ID' => 'export_test_rows.id',
                'Nama' => 'export_test_rows.name',
            ],
        ]);
    }

    public function test_it_streams_every_chunk_as_utf8_csv(): void
    {
        foreach (array_chunk(range(1, 2105), 500) as $ids) {
            DB::table('export_test_rows')->insert(array_map(
                static fn (int $id): array => [
                    'id' => $id,
                    'name' => $id === 1 ? '=HYPERLINK("https://example.test")' : "Baris {$id}",
                ],
                $ids
            ));
        }

        $response = app(MasterDataExportController::class)('test');

        ob_start();
        $response->sendContent();
        $csv = ob_get_clean();

        $this->assertStringStartsWith("\xEF\xBB\xBFID,Nama\n", $csv);
        $this->assertStringContainsString("1,\"'=HYPERLINK(\"\"https://example.test\"\")\"", $csv);
        $this->assertStringContainsString('2105,"Baris 2105"', $csv);
        $this->assertSame(2106, substr_count($csv, "\n"));
        $this->assertSame('text/csv; charset=UTF-8', $response->headers->get('Content-Type'));
    }

    public function test_unknown_export_type_is_rejected(): void
    {
        $this->expectException(NotFoundHttpException::class);

        app(MasterDataExportController::class)('not-registered');
    }

    public function test_medicines_export_returns_binary_excel_with_cookie_token(): void
    {
        $request = \Illuminate\Http\Request::create('/master-data/medicines/export', 'GET', [
            'download_token' => 'my_test_token_123',
            'status' => '1',
        ]);

        $response = app(MasterDataExportController::class)('medicines', $request);

        $this->assertInstanceOf(\Symfony\Component\HttpFoundation\BinaryFileResponse::class, $response);
        $this->assertStringContainsString('.xlsx', $response->getFile()->getFilename());

        $cookies = $response->headers->getCookies();
        $tokenCookie = null;
        foreach ($cookies as $cookie) {
            if ($cookie->getName() === 'download_token') {
                $tokenCookie = $cookie;
                break;
            }
        }

        $this->assertNotNull($tokenCookie);
        $this->assertSame('my_test_token_123', $tokenCookie->getValue());
        $this->assertFalse($tokenCookie->isHttpOnly());
    }

    public function test_import_service_resolves_dynamic_export_column_mapping(): void
    {
        $export = new \App\Exports\Master\MasterMedicinesExport('1');
        $tempPath = tempnam(sys_get_temp_dir(), 'test_export_') . '.xlsx';
        \Maatwebsite\Excel\Facades\Excel::store($export, basename($tempPath), 'local');
        $fullPath = storage_path('app/' . basename($tempPath));

        $service = new \App\Services\MedicineImportService();
        $preview = $service->preview($fullPath);

        $this->assertIsArray($preview);
        $this->assertArrayHasKey('total_rows', $preview);
        $this->assertGreaterThan(0, $preview['total_rows']);
        $this->assertSame(0, $preview['skipped_count']);

        if (file_exists($fullPath)) {
            unlink($fullPath);
        }
    }

    protected function tearDown(): void
    {
        Schema::dropIfExists('export_test_rows');
        parent::tearDown();
    }
}
