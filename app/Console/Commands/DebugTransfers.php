<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MedicineTransfers;

class DebugTransfers extends Command
{
    protected $signature = 'debug:transfers';
    protected $description = 'Debug transfers count and data';

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
            
        $count = $deniedQuery->count();
        $this->info("Total denied: $count");
        
        $first = $deniedQuery->first();
        if ($first) {
            $this->info("First denied ID: " . $first->id);
            $this->info("First denied status: " . $first->status);
            $this->info("First denied items count: " . $first->items->count());
        }
    }
}
