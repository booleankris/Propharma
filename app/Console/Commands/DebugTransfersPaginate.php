<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MedicineTransfers;

class DebugTransfersPaginate extends Command
{
    protected $signature = 'debug:transfers-paginate';
    protected $description = 'Debug transfers paginate';

    public function handle()
    {
        $pharmacyId = 1; // Assuming 1 for testing
        $deniedQuery = MedicineTransfers::with([
            'items.batches.medicines',
            'items.batches.pharmacy',
            'items.sourceBatch.pharmacy',
            'items.etalases',
            'users.pharmacy',
        ])
            ->where(function ($q) {
                $q->where('status', 2)
                    ->orWhereHas('items', fn($i) => $i->where('status', 2));
            })
            ->latest();
            
        $paginator = $deniedQuery->paginate(10, ['*'], 'denied_page');
        $this->info("Total from paginator: " . $paginator->total());
        $this->info("Current page: " . $paginator->currentPage());
        $this->info("Items count: " . $paginator->count());
        $this->info("Is items empty?: " . ($paginator->isEmpty() ? "YES" : "NO"));
        
        $html = view('kasir.transfers.transfers', [
            'pending' => MedicineTransfers::where('id', 0)->paginate(10),
            'accepted' => MedicineTransfers::where('id', 0)->paginate(10),
            'denied' => $paginator
        ])->render();
        
        $this->info("HTML length: " . strlen($html));
        $this->info("Does HTML contain tab-denied?: " . (strpos($html, 'id="tab-denied"') !== false ? "YES" : "NO"));
        $this->info("Does HTML contain ERROR Rendering Transfer?: " . (strpos($html, 'ERROR Rendering Transfer') !== false ? "YES" : "NO"));
        $this->info("Does HTML contain Tidak ada transfer ditolak?: " . (strpos($html, 'Tidak ada transfer ditolak') !== false ? "YES" : "NO"));
        
        // Extract tab-denied content
        if (preg_match('/<div id="tab-denied"[^>]*>(.*?)<\/div>\s*<!-- ========================================================================= -->/s', $html, $matches)) {
            $this->info("tab-denied length: " . strlen($matches[1]));
        }
    }
}
