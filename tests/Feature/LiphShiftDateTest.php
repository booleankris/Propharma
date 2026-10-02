<?php

namespace Tests\Feature;

use App\Exports\Report\LiphExport;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class LiphShiftDateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('pharmacies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('address');
        });
        DB::table('pharmacies')->insert(['id' => 1, 'name' => 'Apotek Test', 'address' => 'Alamat Test']);
        Schema::create('shift', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('shift_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('shift_id');
            $table->dateTime('clock_in')->nullable();
            $table->dateTime('clock_out')->nullable();
        });
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });
        Schema::create('model_has_roles', function (Blueprint $table) {
            $table->unsignedInteger('role_id');
            $table->unsignedInteger('model_id');
            $table->string('model_type');
        });
        Schema::create('medicine_transactions', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('pharmacy_id');
            $table->unsignedInteger('user_id');
            $table->unsignedInteger('shift_logs_id')->nullable();
            $table->string('transaction_type');
            $table->integer('status');
            $table->integer('subtotal');
            $table->integer('discount')->default(0);
            $table->timestamps();
        });
        Schema::create('medicine_cart', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('transaction_id');
            $table->unsignedInteger('user_id');
            $table->string('cart_type')->nullable();
            $table->integer('status');
            $table->integer('final_price');
            $table->integer('discount')->default(0);
            $table->integer('service_fee')->default(0);
            $table->integer('embalase')->default(0);
        });
        DB::table('shift')->insert([['id' => 1, 'name' => 'PAGI'], ['id' => 2, 'name' => 'SIANG']]);
        DB::table('shift_logs')->insert([
            ['id' => 1, 'shift_id' => 2, 'clock_in' => '2026-09-30 15:00:00', 'clock_out' => '2026-10-01 01:00:00'],
            ['id' => 2, 'shift_id' => 2, 'clock_in' => '2026-10-01 15:00:00', 'clock_out' => null],
            ['id' => 3, 'shift_id' => 1, 'clock_in' => '2026-09-30 07:00:00', 'clock_out' => '2026-09-30 15:00:00'],
            ['id' => 4, 'shift_id' => 2, 'clock_in' => '2026-10-02 00:00:00', 'clock_out' => null],
            ['id' => 5, 'shift_id' => 2, 'clock_in' => null, 'clock_out' => null],
        ]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Petugas Online']);
        foreach (['Online', 'Online Grab', 'Online Shopee', 'Digital', 'Kasir'] as $index => $role) {
            DB::table('roles')->insert(['id' => $index + 1, 'name' => $role, 'guard_name' => 'web']);
        }
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_id' => 1, 'model_type' => User::class]);
        $this->transaction(1, 1, '2026-09-30 23:50:00', 100000);
        $this->transaction(2, 1, '2026-10-01 00:15:00', 200000);
        $this->transaction(3, 2, '2026-10-01 16:00:00', 400000);
        $this->transaction(4, 3, '2026-09-30 10:00:00', 50000);
        $this->transaction(5, 1, '2026-09-30 23:00:00', 900000, ['pharmacy_id' => 2]);
        $this->transaction(6, 1, '2026-09-30 23:00:00', 800000, ['status' => 0]);
        $this->transaction(7, null, '2026-10-01 00:30:00', 25000);
        // Later administrative updates must not move a sale to another shift date.
        $this->transaction(8, 1, '2026-10-02 10:00:00', 300000);
        $this->transaction(9, 4, '2026-10-02 00:00:00', 25000);
        $this->transaction(10, 5, '2026-09-30 23:00:00', 15000);
        $this->transaction(11, 1, '2026-10-01 00:20:00', 60000, ['transaction_type' => 'RESEP TUNAI']);
        DB::table('medicine_cart')->insert(['transaction_id' => 2, 'user_id' => 1, 'status' => 0, 'final_price' => 900000]);
    }

    private function transaction(int $id, ?int $shiftLog, string $updatedAt, int $amount, array $extra = []): void
    {
        DB::table('medicine_transactions')->insert(array_merge([
            'id' => $id, 'pharmacy_id' => 1, 'user_id' => 1, 'shift_logs_id' => $shiftLog,
            'transaction_type' => 'KREDIT', 'status' => 1, 'subtotal' => $amount,
            'created_at' => $updatedAt, 'updated_at' => $updatedAt,
        ], $extra));
        DB::table('medicine_cart')->insert([
            'transaction_id' => $id, 'user_id' => 1, 'status' => 1, 'final_price' => $amount,
        ]);
    }

    private function report(string $start, string $end, $shift = 2, string $mode = 'shift'): array
    {
        return (new LiphExport(1, $start, $end, 'Apotek', '', $shift, $mode, ['Online']))->array();
    }

    private function row(array $rows, string $label): array
    {
        return collect($rows)->first(fn ($row) => ($row[1] ?? null) === $label) ?? [];
    }

    public function test_midnight_sales_and_later_updates_stay_on_the_shift_start_date(): void
    {
        $rows = $this->report('2026-09-30', '2026-09-30');
        $credit = $this->row($rows, 'Resep Kredit');
        $this->assertSame(4.0, $credit[2]);
        $this->assertSame(4, $credit[3]);
        $this->assertSame(615000, $credit[9]);
        $this->assertSame(60000, $this->row($rows, 'Resep Tunai')[9]);
        $this->assertStringContainsString('Tanggal kerja : 30/09/2026', $rows[4][0]);
        $this->assertSame('2026-10-01 00:15:00', DB::table('medicine_transactions')->where('id', 2)->value('updated_at'));
    }

    public function test_next_day_and_date_range_do_not_double_count_or_include_next_midnight(): void
    {
        $first = $this->row($this->report('2026-09-30', '2026-09-30'), 'Resep Kredit');
        $next = $this->row($this->report('2026-10-01', '2026-10-01'), 'Resep Kredit');
        $range = $this->row($this->report('2026-09-30', '2026-10-01'), 'Resep Kredit');
        $this->assertSame(400000, $next[9]);
        $this->assertSame(1015000, $range[9]);
        $this->assertSame($first[9] + $next[9], $range[9]);
    }

    public function test_all_shifts_in_shift_mode_use_clock_in_with_fallback_for_missing_shift_times(): void
    {
        $credit = $this->row($this->report('2026-09-30', '2026-09-30', null), 'Resep Kredit');
        $this->assertSame(665000, $credit[9]);
    }

    public function test_online_with_selected_shift_uses_the_same_shift_date(): void
    {
        $rows = $this->report('2026-09-30', '2026-09-30', 2, 'online');
        $this->assertSame(615000, $this->row($rows, 'Resep Kredit')[9]);
        $this->assertStringContainsString('Tanggal kerja', $rows[4][0]);
    }

    public function test_all_mode_ignores_a_stale_shift_selection_in_the_title_and_totals(): void
    {
        $rows = $this->report('2026-09-30', '2026-09-30', 2, 'semua');
        $this->assertSame(665000, $this->row($rows, 'Resep Kredit')[9]);
        $this->assertStringContainsString('(Seluruh)', $rows[4][0]);
    }

    public function test_all_and_online_modes_use_the_same_working_date_as_shift_mode(): void
    {
        foreach (['semua', 'online'] as $mode) {
            $rows = $this->report('2026-10-01', '2026-10-01', null, $mode);
            $this->assertSame(425000, $this->row($rows, 'Resep Kredit')[9]);
            $this->assertStringContainsString('Tanggal kerja', $rows[4][0]);
        }
    }
    public function test_all_matches_sum_of_selected_shifts_plus_sales_without_a_shift(): void
    {
        $all = $this->row($this->report('2026-09-30', '2026-09-30', null, 'semua'), 'Resep Kredit');
        $morning = $this->row($this->report('2026-09-30', '2026-09-30', 1), 'Resep Kredit');
        $afternoon = $this->row($this->report('2026-09-30', '2026-09-30', 2), 'Resep Kredit');
        $this->assertSame($morning[9] + $afternoon[9], $all[9]);
        $nextAll = $this->row($this->report('2026-10-01', '2026-10-01', null, 'semua'), 'Resep Kredit');
        $nextShift = $this->row($this->report('2026-10-01', '2026-10-01', 2), 'Resep Kredit');
        $this->assertSame($nextShift[9] + 25000, $nextAll[9]);
        $range = $this->row($this->report('2026-09-30', '2026-10-01', null, 'semua'), 'Resep Kredit');
        $this->assertSame($all[9] + $nextAll[9], $range[9]);
    }

    public function test_unlinked_sale_keeps_original_date_after_edits_and_orphaned_logs_are_not_lost(): void
    {
        DB::table('medicine_transactions')->where('id', 7)->update(['updated_at' => '2026-10-02 10:00:00']);
        // A historical transaction may point to a deleted shift log.
        $this->transaction(12, 999, '2026-10-01 10:00:00', 10000);
        $rows = $this->report('2026-10-01', '2026-10-01', null, 'semua');
        $this->assertSame(435000, $this->row($rows, 'Resep Kredit')[9]);
        $next = $this->report('2026-10-02', '2026-10-02', null, 'semua');
        $this->assertSame(25000, $this->row($next, 'Resep Kredit')[9]);
    }

    public function test_legacy_sale_without_created_at_falls_back_to_recorded_update_time(): void
    {
        DB::table('medicine_transactions')->where('id', 7)->update(['created_at' => null]);
        $rows = $this->report('2026-10-01', '2026-10-01', null, 'semua');
        $this->assertSame(425000, $this->row($rows, 'Resep Kredit')[9]);
    }

    public function test_mixed_cart_types_keep_discounts_and_grand_total_consistent(): void
    {
        DB::table('medicine_transactions')->where('id', 1)->update(['discount' => 1000]);
        DB::table('medicine_cart')->where('transaction_id', 1)->update(['discount' => 2000]);
        DB::table('medicine_cart')->insert([
            'transaction_id' => 1, 'user_id' => 1, 'cart_type' => 'UM', 'status' => 1,
            'final_price' => 50000, 'discount' => 3000,
        ]);
        $rows = $this->report('2026-09-30', '2026-09-30', null, 'semua');
        $credit = $this->row($rows, 'Resep Kredit');
        $cash = $this->row($rows, 'Resep Tunai');
        $grand = $this->row($rows, 'Grand Total');
        $this->assertSame(664000, $credit[9]);
        $this->assertSame(110000, $cash[9]);
        $this->assertSame(774000, $grand[9]);
        $this->assertSame(5000, $grand[6]);
        $this->assertSame(1000, $grand[8]);
        $this->assertSame($grand[7] - $grand[8], $grand[9]);
        $this->assertSame($credit[2] + $cash[2], $grand[2]);
    }

    public function test_returns_reduce_total_and_empty_period_still_renders(): void
    {
        $this->transaction(12, 1, '2026-10-01 00:30:00', 20000, ['transaction_type' => 'RETUR JUAL']);
        $rows = $this->report('2026-09-30', '2026-09-30', null, 'semua');
        $this->assertSame(-20000, $this->row($rows, 'Retur Tunai')[9]);
        $this->assertSame(705000, $this->row($rows, 'Grand Total')[9]);
        $empty = $this->report('2026-10-03', '2026-10-03', null, 'semua');
        $this->assertSame(0, $this->row($empty, 'Grand Total')[9]);
    }

    public function test_online_channels_exclude_offline_and_keep_same_working_date(): void
    {
        foreach ([2 => 'Petugas Grab', 3 => 'Kasir'] as $id => $name) {
            DB::table('users')->insert(['id' => $id, 'name' => $name]);
            DB::table('model_has_roles')->insert(['role_id' => $id === 2 ? 2 : 5, 'model_id' => $id, 'model_type' => User::class]);
        }
        $this->transaction(12, 1, '2026-10-01 00:30:00', 70000, ['user_id' => 2, 'transaction_type' => 'HV/OTC']);
        $this->transaction(13, 1, '2026-10-01 00:40:00', 90000, ['user_id' => 3, 'transaction_type' => 'HV/OTC']);
        DB::table('medicine_cart')->where('transaction_id', 12)->update(['user_id' => 2]);
        DB::table('medicine_cart')->where('transaction_id', 13)->update(['user_id' => 3]);
        $sheets = (new \App\Exports\Report\LiphOnlineExport(1, '2026-09-30', '2026-09-30'))->sheets();
        $totals = [];
        foreach ($sheets as $sheet) $totals[$sheet->title()] = $this->row($sheet->array(), 'Grand Total')[9];
        $this->assertSame([
            'Semua Online' => 795000, 'Online (WA)' => 725000, 'Online Grab' => 70000,
            'Online Shopee' => 0, 'Aplikasi Digital' => 0,
        ], $totals);
        $this->assertSame(885000, $this->row($this->report('2026-09-30', '2026-09-30', null, 'semua'), 'Grand Total')[9]);
    }

    public function test_http_preview_and_download_have_identical_totals_for_all_shift_and_online(): void
    {
        $this->actingAs(User::findOrFail(1))->withSession(['active_shift_log_id' => 1]);
        foreach (['semua', 'shift', 'online'] as $mode) {
            $params = [
                'selectedReport' => 'LIPH', 'pharmacy_id' => 1,
                'start_date' => '30/09/2026', 'end_date' => '30/09/2026',
                'shiftType' => $mode, 'shift' => $mode === 'semua' ? null : 2,
            ];
            $preview = $this->post('/reports', $params + ['mode' => 'preview']);
            $preview->assertOk()->assertSee('Tanggal kerja : 30/09/2026');
            $expectedSheets = $mode === 'online' ? $preview->viewData('sheets') : ['LIPH' => $preview->viewData('rows')];
            $download = $this->post('/reports', $params + ['mode' => 'download']);
            $download->assertOk()->assertDownload();
            $path = $download->baseResponse->getFile()->getPathname();
            try {
                $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
                $this->assertSame(count($expectedSheets), $workbook->getSheetCount());
                foreach ($expectedSheets as $title => $rows) {
                    $sheet = $workbook->getSheetByName($title);
                    $this->assertNotNull($sheet);
                    foreach ($rows as $rowIndex => $row) {
                        foreach ($row as $column => $value) {
                            if ($value === '' || $value === null) continue;
                            $this->assertEquals($value, $sheet->getCell([$column + 1, $rowIndex + 1])->getValue(), "$mode / $title / row $rowIndex col $column");
                        }
                    }
                    $this->assertContains('A5:J5', $sheet->getMergeCells(), 'The working-date heading must span the sheet.');
                    $this->assertNull($sheet->getCell('C6')->getValue(), 'The spacer must not become a row of zeroes.');
                    $this->assertTrue($sheet->getStyle('C7')->getFont()->getBold(), 'The actual column heading must be styled.');
                }
                $workbook->disconnectWorksheets();
            } finally {
                if (is_file($path)) unlink($path);
            }
        }
    }

}
