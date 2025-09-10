<?php

namespace Database\Seeders;

use App\Models\MedicineCategory;
use App\Models\Medicines;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MedicineCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $user = MedicineCategory::insert([
            [
                'code' => '01',
                'name' => 'OBAT BEBAS/OTC (B)',
            ],
            [
                'code' => '02',
                'name' => 'OBAT BEBAS TERBATAS (W)',
            ],
            [
                'code' => '03',
                'name' => 'OBAT KERAS (G)',
            ],
            [
                'code' => '04',
                'name' => 'OBAT NARKOTIKA (O)',
            ],
            [
                'code' => '05',
                'name' => 'ALAT KESEHATAN (ALKES)',
            ],
            [
                'code' => '06',
                'name' => 'OBAT GOLONGAN PSIKOTROPIKA (P)',
            ],
        ]);
    }
}
