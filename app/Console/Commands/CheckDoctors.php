<?php

namespace App\Console\Commands;

class CheckDoctors extends AuditDoctors
{
    protected $signature = 'doctors:check
        {--duplicates-only : Hanya tampilkan dokter yang terindikasi masih memiliki duplikat}
        {--search= : Filter nama atau kode dokter}
        {--limit=50 : Jumlah maksimum baris yang ditampilkan di terminal (0 untuk tampilkan semua)}
        {--export : Ekspor laporan audit lengkap ke file CSV di storage/app}';

    protected $description = 'Cek semua data dokter & lihat apakah masih ada duplikat yang tersisa (Alias untuk doctors:audit)';
}
