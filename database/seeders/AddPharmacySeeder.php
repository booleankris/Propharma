<?php

namespace Database\Seeders;

use App\Models\Pharmacies;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

use App\Models\User;

class AddPharmacySeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $pharmacy = Pharmacies::create([
            'name'              => 'Home',
            'address'           => 'Jl. Samarinda',
            'phone'             => '085349130854',
            'city'              => 'Samarinda',
            'npwp'              => '00.000.000.0-000.000',
            'permit'            => 'Izin Apotek 123/XYZ/2025',
            'pharmacist_permit' => 'Izin Apoteker 456/ABC/2025',
            'pharmacist'        => 'Dr. Dany',
            'footnote1'         => 'Terima kasih telah berbelanja di apotek kami.',
            'footnote2'         => 'Obat sesuai resep dokter.',
            'status'            => 1,
        ]);
    }
}
