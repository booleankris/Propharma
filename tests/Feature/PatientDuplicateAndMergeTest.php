<?php

namespace Tests\Feature;

use App\Models\MedicineTransactions;
use App\Models\Patients;
use App\Models\User;
use App\Support\PatientMatcher;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PatientDuplicateAndMergeTest extends TestCase
{
    use DatabaseTransactions;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(\App\Http\Middleware\VerifyCsrfToken::class);
        $this->user = User::first() ?? User::factory()->create();
    }

    public function test_patient_matcher_detects_exact_phone_and_fuzzy_names()
    {
        $uniquePhone = '089999888877';

        $p1 = Patients::create([
            'code'    => 'PT9001',
            'name'    => 'TN. BUDI SANTOSO',
            'phone'   => $uniquePhone,
            'address' => 'Jl. Mawar No. 1',
            'city'    => 'Samarinda',
        ]);

        $p2 = Patients::create([
            'code'    => 'PT9002',
            'name'    => 'NY. SITI AMINAH',
            'phone'   => '081112223334',
            'address' => 'Jl. Melati No. 2',
            'city'    => 'Samarinda',
        ]);

        $list = [$p1, $p2];

        // 1. Exact match by name
        $resName = PatientMatcher::findMatches('Tn. Budi Santoso', null, $list);
        $this->assertNotNull($resName['exact']);
        $this->assertEquals($p1->id, $resName['exact']->id);

        // 2. Exact match by phone
        $resPhone = PatientMatcher::findMatches('Budi S', $uniquePhone, $list);
        $this->assertNotNull($resPhone['exact']);
        $this->assertTrue($resPhone['is_phone_match']);
        $this->assertEquals($p1->id, $resPhone['exact']->id);

        // 3. Similar name (typo: Budi Santoso -> Budi Santosa)
        $resFuzzy = PatientMatcher::findMatches('Budi Santosa', null, $list);
        $this->assertNull($resFuzzy['exact']);
        $this->assertNotEmpty($resFuzzy['similar']);
        $this->assertEquals($p1->id, $resFuzzy['similar'][0]->id);
    }

    public function test_add_patient_endpoint_returns_confirmation_for_exact_and_similar_patients()
    {
        $uniquePhone = '088877776666';

        $existing = Patients::create([
            'code'    => 'PT9010',
            'name'    => 'TN. HENDRA WIJAYA',
            'phone'   => $uniquePhone,
            'address' => 'Jl. Pahlawan No. 10',
            'city'    => 'Samarinda',
        ]);

        // Request with exact same name
        $responseExact = $this->actingAs($this->user)
            ->postJson(route('transaction.addPatient'), [
                'name'    => 'hendra wijaya',
                'phone'   => '-',
                'address' => 'Jl. Baru',
            ]);

        $responseExact->assertOk();
        $responseExact->assertJson([
            'success'            => false,
            'needs_confirmation' => true,
            'is_exact'           => true,
        ]);
        $this->assertEquals($existing->id, $responseExact->json('existing_patient.id'));

        // Request with same phone number
        $responsePhone = $this->actingAs($this->user)
            ->postJson(route('transaction.addPatient'), [
                'name'    => 'Hendra W',
                'phone'   => $uniquePhone,
                'address' => 'Jl. Baru',
            ]);

        $responsePhone->assertOk();
        $responsePhone->assertJson([
            'success'            => false,
            'needs_confirmation' => true,
            'is_phone_match'     => true,
        ]);

        // Request with similar name (typo: Hendra Wijaya -> Hendra Wijoyo)
        $responseSimilar = $this->actingAs($this->user)
            ->postJson(route('transaction.addPatient'), [
                'name'    => 'Hendra Wijoyo',
                'phone'   => '-',
                'address' => 'Jl. Baru',
            ]);

        $responseSimilar->assertOk();
        $responseSimilar->assertJson([
            'success'            => false,
            'needs_confirmation' => true,
            'is_exact'           => false,
        ]);
        $this->assertNotEmpty($responseSimilar->json('suggestions'));

        // Request with force=1 creates the patient
        $responseForce = $this->actingAs($this->user)
            ->postJson(route('transaction.addPatient'), [
                'name'    => 'Hendra Wijaya',
                'phone'   => $uniquePhone,
                'address' => 'Jl. Berbeda',
                'force'   => '1',
            ]);

        $responseForce->assertOk();
        $responseForce->assertJson([
            'success' => true,
        ]);
        $this->assertNotEquals($existing->id, $responseForce->json('patient.id'));
    }

    public function test_merge_duplicate_patients_command_merges_records_and_remaps_transactions()
    {
        $uniquePhone = '081999888123';
        $testName = 'TEST PASIEN DUPLIKAT MERGE';

        $canonical = Patients::create([
            'code'    => 'PT9991',
            'name'    => $testName,
            'phone'   => $uniquePhone,
            'address' => 'Alamat Asli',
            'city'    => 'Samarinda',
        ]);

        $duplicate = Patients::create([
            'code'    => 'PT9992',
            'name'    => $testName,
            'phone'   => $uniquePhone,
            'address' => 'Alamat Duplikat',
            'city'    => 'Samarinda',
        ]);

        // Buat dummy transaksi merujuk ke pasien duplikat
        $tx = MedicineTransactions::create([
            'transaction_code' => 'TEST-TX-' . uniqid(),
            'total_price'      => 50000,
            'profit'           => 10000,
            'status'           => 1,
            'payment_type'     => 'CASH',
            'patient_id'       => $duplicate->id,
            'user_id'          => $this->user->id,
            'pharmacy_id'      => 1,
            'total_real'       => 50000,
            'shift'            => 'Pagi',
            'date_transaction' => now()->toDateString(),
            'transaction_type' => 'HV/OTC',
        ]);

        // Berikan 2 transaksi ke canonical agar dipilih sebagai pasien utama
        MedicineTransactions::create([
            'transaction_code' => 'TEST-TX-CANON-1-' . uniqid(),
            'total_price'      => 50000,
            'profit'           => 10000,
            'status'           => 1,
            'payment_type'     => 'CASH',
            'patient_id'       => $canonical->id,
            'user_id'          => $this->user->id,
            'pharmacy_id'      => 1,
            'total_real'       => 50000,
            'shift'            => 'Pagi',
            'date_transaction' => now()->toDateString(),
            'transaction_type' => 'HV/OTC',
        ]);
        MedicineTransactions::create([
            'transaction_code' => 'TEST-TX-CANON-2-' . uniqid(),
            'total_price'      => 50000,
            'profit'           => 10000,
            'status'           => 1,
            'payment_type'     => 'CASH',
            'patient_id'       => $canonical->id,
            'user_id'          => $this->user->id,
            'pharmacy_id'      => 1,
            'total_real'       => 50000,
            'shift'            => 'Pagi',
            'date_transaction' => now()->toDateString(),
            'transaction_type' => 'HV/OTC',
        ]);

        $this->assertEquals($duplicate->id, $tx->fresh()->patient_id);

        // Jalankan artisan patients:merge-duplicates --execute --force
        $exitCode = Artisan::call('patients:merge-duplicates', [
            '--execute' => true,
            '--force'   => true,
        ]);

        $this->assertEquals(0, $exitCode);

        // Verifikasi transaksi telah dipindahkan ke canonical
        $this->assertEquals($canonical->id, $tx->fresh()->patient_id);

        // Verifikasi pasien duplikat telah dihapus
        $this->assertNull(Patients::find($duplicate->id));
        $this->assertNotNull(Patients::find($canonical->id));
    }
}
