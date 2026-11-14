<?php

namespace App\Exports\Stocks;

use App\Models\ItemsLog;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class StorageStockHistoryExport implements WithMultipleSheets
{
    protected ?Request $request;
    protected int $warehouseId;

    public function __construct($request = null, $warehouseId = null)
    {
        $this->request = $request instanceof Request ? $request : (is_array($request) ? new Request($request) : request());
        $this->warehouseId = (int) ($warehouseId ?: getWarehousePharmacyId());
    }

    public function sheets(): array
    {
        $warehouseId = $this->warehouseId;
        $pharmacy = Pharmacies::find($warehouseId);

        $effectiveDateSql = "CASE WHEN (LENGTH(items_log.date) <= 10 OR TIME(items_log.date) = '00:00:00') AND items_log.created_at IS NOT NULL THEN CONCAT(DATE(items_log.date), ' ', TIME(items_log.created_at)) ELSE items_log.date END";

        $query = ItemsLog::query()
            ->select([
                'items_log.id',
                'items_log.date',
                'items_log.created_at',
                'items_log.transaction_code',
                'items_log.code',
                'items_log.status',
                'items_log.type',
                'items_log.qty',
                'items_log.qty_before',
                'items_log.qty_after',
                'items_log.total',
                'items_log.medicine_id',
                'items_log.batches_id',
                'items_log.users_id',
                'items_log.receiving_id',
            ])
            ->join('batches', 'batches.id', '=', 'items_log.batches_id')
            ->where('batches.pharmacy_id', $warehouseId)
            ->whereIn('items_log.status', [2, 4, 5, 6, 7, 8, 9]);

        if ($this->request && $this->request->filled('start_date')) {
            $query->whereDate('items_log.date', '>=', $this->request->start_date);
        }
        if ($this->request && $this->request->filled('end_date')) {
            $query->whereDate('items_log.date', '<=', $this->request->end_date);
        }
        if ($this->request && $this->request->filled('medicine_id')) {
            $query->where('items_log.medicine_id', $this->request->medicine_id);
        }

        $logs = $query->orderByRaw($effectiveDateSql . ' DESC')
            ->orderBy('items_log.id', 'desc')
            ->with([
                'medicines:id,factory_id,code,name,unit',
                'medicines.factory:id,name',
                'batches:id,name,expired_date',
                'users:id,name',
                'receiving.receiving_details.creditor:id,code,name',
                'receiving.receiving_details.receiving_items:id,receiving_details_id,order_items_id,batches_id,qty_received,stock_unit_factor',
                'receiving.receiving_details.receiving_items.order_items:id,medicine_id,creditor_code',
                'receiving.receiving_details.receiving_items.order_items.creditors:id,code,name',
                'purchaseReturn.creditor:id,code,name',
            ])
            ->get();

        if ($logs->isEmpty()) {
            return [
                new StorageStockHistorySheetExport('Riwayat Stok Gudang', 'Semua Pabrik', collect(), $this->request, $pharmacy)
            ];
        }

        // Group by medicines.factory_id
        $grouped = $logs->groupBy(function ($log) {
            return (int) ($log->medicines?->factory_id ?? 0);
        });

        // Resolve factory names and sort
        $factoryData = [];
        foreach ($grouped as $factoryId => $groupLogs) {
            if ($factoryId === 0) {
                $factoryName = 'Tanpa Pabrik';
            } else {
                $factoryName = $groupLogs->first()->medicines?->factory?->name ?: ('Pabrik #' . $factoryId);
            }
            $factoryData[] = [
                'factory_id' => $factoryId,
                'factory_name' => $factoryName,
                'logs' => $groupLogs,
            ];
        }

        usort($factoryData, function ($a, $b) {
            if ($a['factory_id'] === 0) {
                return 1;
            }
            if ($b['factory_id'] === 0) {
                return -1;
            }
            return strcasecmp($a['factory_name'], $b['factory_name']);
        });

        $sheets = [];
        $usedTitles = [];

        foreach ($factoryData as $item) {
            $cleanTitle = trim(preg_replace('/[\\\\\/\\?\\*:\\[\\]]/', ' ', $item['factory_name'])) ?: 'Pabrik';
            $baseTitle = mb_substr($cleanTitle, 0, 31);
            $sheetTitle = $baseTitle;
            $suffix = 2;
            while (isset($usedTitles[mb_strtolower($sheetTitle)])) {
                $ending = ' (' . $suffix++ . ')';
                $sheetTitle = mb_substr($baseTitle, 0, 31 - mb_strlen($ending)) . $ending;
            }
            $usedTitles[mb_strtolower($sheetTitle)] = true;

            $sheets[] = new StorageStockHistorySheetExport(
                $sheetTitle,
                $item['factory_name'],
                $item['logs'],
                $this->request,
                $pharmacy
            );
        }

        return $sheets;
    }
}

