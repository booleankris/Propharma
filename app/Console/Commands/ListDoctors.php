<?php

namespace App\Console\Commands;

class ListDoctors extends AuditDoctors
{
    protected $signature = 'doctors:list
        {--duplicates-only : Hanya tampilkan dokter yang terindikasi masih memiliki duplikat}
        {--search= : Filter nama atau kode dokter}
        {--limit=50 : Jumlah maksimum baris yang ditampilkan di terminal (0 untuk tampilkan semua)}
        {--export : Ekspor laporan audit lengkap ke file CSV di storage/app}';

    protected $description = 'Lihat daftar semua data dokter beserta status duplikasi (Alias untuk doctors:audit)';
}
