<?php

namespace App\Jobs;

use App\Models\ExportJob;
use App\Services\StockOpnameImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessStockOpnameImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected int $exportJobId;
    protected string $token;
    protected int $pharmacyId;
    protected string $targetMode;
    protected int $userId;

    public function __construct(int $exportJobId, string $token, int $pharmacyId, string $targetMode, int $userId)
    {
        $this->exportJobId = $exportJobId;
        $this->token = $token;
        $this->pharmacyId = $pharmacyId;
        $this->targetMode = $targetMode;
        $this->userId = $userId;
    }

    public function handle(StockOpnameImportService $service): void
    {
        $job = ExportJob::find($this->exportJobId);
        if (!$job) {
            return;
        }

        $job->markProcessing();

        try {
            $service->execute(
                $this->token,
                $this->pharmacyId,
                $this->targetMode,
                $this->userId,
                $this->exportJobId
            );
        } catch (\Throwable $e) {
            Log::error("ProcessStockOpnameImport failed: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);

            $job->update([
                'status'   => ExportJob::STATUS_FAILED,
                'progress' => 0,
            ]);
        }
    }
}
