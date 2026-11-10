<?php

namespace Tests\Feature;

use App\Models\DoctorService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DoctorServiceMasterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        Schema::create('doctor_services', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 150);
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();
        });

        $this->withoutMiddleware();
    }

    private function ajax(): self
    {
        return $this->withHeaders(['X-Requested-With' => 'XMLHttpRequest']);
    }

    public function test_store_accepts_free_service_with_zero_price(): void
    {
        $this->ajax()->postJson('/doctor-services', ['name' => 'Jasa Cek Tekanan Darah', 'price' => 0])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('Jasa Cek Tekanan Darah', DoctorService::first()->name);
        $this->assertSame('0.00', DoctorService::first()->price);
    }

    public function test_store_defaults_price_to_zero_when_empty(): void
    {
        $this->ajax()->postJson('/doctor-services', ['name' => 'Ongkir Gratis'])
            ->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame('0.00', DoctorService::first()->price);
    }

    public function test_store_requires_name_and_rejects_negative_price(): void
    {
        $this->ajax()->postJson('/doctor-services', ['price' => 10000])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');

        $this->ajax()->postJson('/doctor-services', ['name' => 'Jasa Jahit Luka', 'price' => -1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price');
    }

    public function test_update_and_destroy_service(): void
    {
        $service = DoctorService::create(['name' => 'Jasa Nebulizer', 'price' => 50000]);

        $this->ajax()->putJson('/doctor-services/' . $service->id, [
            'name'  => 'Jasa Nebulizer Dewasa',
            'price' => 75000,
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame('Jasa Nebulizer Dewasa', $service->fresh()->name);
        $this->assertSame('75000.00', $service->fresh()->price);

        $this->ajax()->deleteJson('/doctor-services/' . $service->id)
            ->assertOk()
            ->assertJsonPath('status', true);

        $this->assertDatabaseMissing('doctor_services', ['id' => $service->id]);
    }

    public function test_datatable_lists_price_in_rupiah_and_marks_free_service(): void
    {
        DoctorService::create(['name' => 'Jasa Suntik', 'price' => 25000]);
        DoctorService::create(['name' => 'Jasa Timbang Bayi', 'price' => 0]);

        $response = $this->ajax()->getJson('/doctor-services');

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['name' => 'Jasa Suntik', 'price_label' => 'Rp 25.000'])
            ->assertJsonFragment(['name' => 'Jasa Timbang Bayi', 'price_label' => 'Rp 0 (Gratis)']);
    }

    public function test_master_export_config_is_registered(): void
    {
        $this->assertArrayHasKey('doctor-services', config('master_exports'));
        $this->assertSame('doctor_services', config('master_exports.doctor-services.table'));
    }
}
