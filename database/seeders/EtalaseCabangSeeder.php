<?php

namespace Database\Seeders;

use App\Models\Etalases;
use App\Models\Pharmacies;
use Illuminate\Database\Seeder;

class EtalaseCabangSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branches = [
            // SAHABAT MULAWARMAN (id: 2)
            [
                'name_match' => 'MULAWARMAN',
                'id_fallback' => 2,
                'items' => [
                    'Backdrop', 'Backdrop 1', 'Backdrop 2', 'Backdrop 3', 'Backdrop 4', 'Backdrop 5', 'Backdrop 6',
                    'Etalase', 'Etalase 1', 'Etalase 2', 'Etalase 3', 'Etalase 4', 'Etalase 5', 'Etalase 6',
                    'Kulkas', 'Kulkas 1', 'Lemari OOT', 'NAPZA', 'NAPZA 1', 'NAPZA 2', 'Narkotika', 'OOT', 'PSIKO',
                    'Prekursor', 'Psikotropika', 'Rak', 'Rak 1', 'Rak 2', 'Rak 3', 'Rak 4', 'Rak 5', 'Rak 6',
                    'Rak 7', 'Rak 8', 'Rak 9', 'Rak 10', 'Rak 11', 'Rak 12', 'Rak 13',
                ],
            ],

            // SAHABAT MIM (id: 3)
            [
                'name_match' => 'MIM',
                'id_fallback' => 3,
                'items' => [
                    'Backdrop 1', 'Backdrop 2', 'Backdrop 3',
                    'Etalase 1', 'Etalase 2', 'Etalase 3', 'Etalase 4', 'Etalase 5', 'Etalase 6',
                    'Kulkas', 'Kulkas Dalam', 'NAPZA',
                    'Rak 1', 'Rak 2', 'Rak 3', 'Rak 4', 'Rak 5', 'Rak 6', 'Rak Dokter',
                ],
            ],

            // SAHABAT ANTASARI (id: 5)
            [
                'name_match' => 'ANTASARI',
                'id_fallback' => 5,
                'items' => [
                    'Backdrop 1', 'Backdrop 2', 'Backdrop 3', 'Backdrop 4', 'Backdrop 5', 'Backdrop 6', 'Backdrop 7', 'Backdrop 8',
                    'Etalase 1', 'Etalase 2', 'Etalase 3',
                    'Kulkas', 'Minuman', 'NAPZA',
                    'Rak 1', 'Rak 2', 'Rak 3', 'Rak 4', 'Rak 5', 'Rak 6', 'Rak 7',
                ],
            ],

            // SAHABAT PMI (id: 1)
            [
                'name_match' => 'PMI',
                'id_fallback' => 1,
                'items' => [
                    'Alkes', 'Alkes 1', 'Alkes 2', 'Alkes 3', 'Alkes 4', 'Alkes 5', 'Alkes 6',
                    'BHMP', 'BMHP', 'Generik', 'Gudang', 'Injeksi', 'Kater K', 'KB', 'Khusus',
                    'Konsi', 'Konsinasi', 'Kulkas', 'Minuman', 'Narkotika', 'OOT', 'OTC', 'OTC 1',
                    'OTC 2', 'OTC 3', 'OTC 4', 'OTC 5', 'OTC 6', 'OTC 7', 'Paten', 'Paten 1',
                    'Paten 2', 'Paten 3', 'Paten 4', 'Paten 5', 'Paten K', 'Prekursor',
                    'Psikotropika', 'Racikan', 'Salep', 'Sirup', 'Susu', 'Tetes Mata Telinga',
                ],
            ],
        ];

        foreach ($branches as $branch) {
            $pharmacy = Pharmacies::where('name', 'like', "%{$branch['name_match']}%")
                ->where('name', 'not like', '%GUDANG%')
                ->first();
            $pharmacyId = $pharmacy ? $pharmacy->id : $branch['id_fallback'];

            foreach ($branch['items'] as $item) {
                $trimmed = trim($item);

                // Cek apakah sudah ada etalase dengan nama yang sama persis (case-insensitive)
                $existing = Etalases::where('pharmacy_id', $pharmacyId)
                    ->whereRaw('LOWER(name) = ?', [strtolower($trimmed)])
                    ->first();

                if (!$existing) {
                    // Cek apakah ada record lama dengan pharmacy_id NULL
                    $legacy = Etalases::whereNull('pharmacy_id')
                        ->whereRaw('LOWER(name) = ?', [strtolower($trimmed)])
                        ->first();

                    if ($legacy && $branch['id_fallback'] === 1) {
                        // Khusus PMI, update record lama agar relasi transaksi yang sudah ada tetap terjaga
                        $legacy->update(['pharmacy_id' => $pharmacyId]);
                    } else {
                        Etalases::create([
                            'name' => $trimmed,
                            'pharmacy_id' => $pharmacyId,
                            'status' => 0,
                        ]);
                    }
                }
            }
        }
    }
}
