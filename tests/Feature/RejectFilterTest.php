<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class RejectFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');
        Schema::create('medicines', function (Blueprint $table) {
            $table->id();
            $table->string('name');
        });
        Schema::create('reject', function (Blueprint $table) {
            $table->id();
            $table->integer('pharmacy_id');
            $table->integer('medicine_id')->nullable();
            $table->string('medicine_name')->nullable();
            $table->date('date');
            $table->integer('quantity')->default(1);
            $table->integer('total')->default(0);
        });
        DB::table('medicines')->insert(['id' => 1, 'name' => 'Paracetamol']);
        DB::table('reject')->insert([
            ['id' => 1, 'pharmacy_id' => 0, 'medicine_id' => 1, 'medicine_name' => null, 'date' => '2026-09-01'],
            ['id' => 2, 'pharmacy_id' => 0, 'medicine_id' => null, 'medicine_name' => 'Paracetamol custom', 'date' => '2026-09-09'],
            ['id' => 3, 'pharmacy_id' => 0, 'medicine_id' => null, 'medicine_name' => 'Vitamin', 'date' => '2026-09-09'],
            ['id' => 4, 'pharmacy_id' => 1, 'medicine_id' => null, 'medicine_name' => 'Paracetamol', 'date' => '2026-09-09'],
        ]);
        $this->withoutMiddleware();
    }

    public function test_name_and_inclusive_date_filters_preserve_pharmacy_scope(): void
    {
        $this->getJson('/getreject?' . http_build_query([
            'medicine_search' => 'Paracetamol', 'start_date' => '2026-09-01', 'end_date' => '2026-09-09',
        ]))->assertOk()->assertJsonPath('recordsTotal', 3)->assertJsonPath('recordsFiltered', 2);

        $this->getJson('/getreject?' . http_build_query([
            'medicine_search' => 'Paracetamol', 'start_date' => '2026-09-09', 'end_date' => '2026-09-09',
        ]))->assertOk()->assertJsonPath('recordsFiltered', 1)->assertJsonPath('data.0.id', 2);

        $this->getJson('/getreject')->assertOk()->assertJsonPath('recordsFiltered', 3);
    }

    public function test_reversed_range_is_rejected(): void
    {
        $this->getJson('/getreject?start_date=2026-09-10&end_date=2026-09-01')
            ->assertUnprocessable()->assertJsonValidationErrors('end_date');
    }
}
