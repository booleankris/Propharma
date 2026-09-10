<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateStocks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'stock:update-stocks {--dry-run: Simulasi}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $isDryRun = $this->option('dry-run');
        $pharmacy_mim_id = 3;
        $pharmacy_asa_id = 5;
        $pharmacy_pmi_id = 1;
        $pharmacy_gudang_id = 9;
        $path_asa_mim = storage_path('app/STOK_MIMASA.xlsx');
        $path_gudang_asm = storage_path('app/STOK_ASMPMIGUDANG.xlsx');

        if ($isDryRun) {
            $this->warn('Dijalankan dalam mode simulasi - tidak ada perubahan yang akan disimpan ke database');
        } elseif (App::environment('production')) {
            if (!$this->confirm('⚠️ YOU ARE IN PRODUCTION! This will update medicine components based on code. Are you sure?')) {
                $this->info('Operation cancelled.');
                return 0;
            }
        }

        $this->info('Reading Excel file... (this may take a moment)');
        if (!file_exists($path_asa_mim)) {
            $this->error('File tidak ditemukan: ' . $path_asa_mim);
            return 1;
        }
        if (!file_exists($path_gudang_asm)) {
            $this->error('File tidak ditemukan: ' . $path_gudang_asm);
            return 1;
        }

        ini_set('memory_limit', '-1');
    }
}
