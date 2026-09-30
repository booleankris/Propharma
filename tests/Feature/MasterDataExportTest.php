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
}
