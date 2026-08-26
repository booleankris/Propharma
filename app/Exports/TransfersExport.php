<?php

namespace App\Exports;

use App\Models\MedicineTransferItems;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class TransfersExport implements FromQuery, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $pharmacyId;
    protected $startDate;
    protected $endDate;
    protected $search;
    protected $type;

    public function __construct($pharmacyId, $startDate, $endDate, $search, $type)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->type = $type;
    }

    public function query()
    {
        $query = MedicineTransferItems::query()
            ->select('medicine_transfer_items.*')
            ->join('medicine_transfers', 'medicine_transfer_items.medicine_transfer_id', '=', 'medicine_transfers.id')
            ->join('users', 'medicine_transfers.user_id', '=', 'users.id')
            ->join('batches', 'medicine_transfer_items.batches_id', '=', 'batches.id');

        if ($this->search) {
            $query->join('medicines', 'batches.medicine_id', '=', 'medicines.id');
        }

        $query->with([
            'transfer.users.pharmacy',
            'batches.medicines',
            'batches.pharmacy',
            'etalases'
        ]);

        if ($this->startDate && $this->endDate) {
            $query->whereBetween('medicine_transfers.created_at', [
                $this->startDate . ' 00:00:00',
                $this->endDate . ' 23:59:59'
            ]);
        }

        if ($this->search) {
            $query->where('medicines.name', 'like', "%{$this->search}%");
        }

        $pharmacyId = $this->pharmacyId;
        
        $query->where(function ($q) use ($pharmacyId) {
            $q->where('users.pharmacy_id', $pharmacyId)
              ->orWhere('batches.pharmacy_id', $pharmacyId);
        });

        // Optimize memory and query speed by ordering by id
        return $query->orderBy('id', 'desc');
    }

    public function headings(): array
    {
        return [
            'ID Transaksi',
            'Tipe Mutasi',
            'Tanggal',
            'Pengirim',
            'Penerima',
            'Kode Obat',
            'Nama Obat',
            'Batch',
            'Etalase (Penerima)',
            'Qty',
            'Status Item'
        ];
    }

    public function map($item): array
    {
        $statusMap = [
            0 => 'Pending',
            1 => 'Diterima',
            2 => 'Ditolak',
        ];

        $senderId = $item->transfer->users->pharmacy_id ?? null;
        $receiverId = $item->batches->pharmacy_id ?? null;

        if ($senderId == $this->pharmacyId && $receiverId == $this->pharmacyId) {
            $tipe = 'Internal';
        } elseif ($senderId == $this->pharmacyId) {
            $tipe = 'Keluar';
        } elseif ($receiverId == $this->pharmacyId) {
            $tipe = 'Masuk';
        } else {
            $tipe = '-';
        }

        return [
            $item->transfer->code ?? '-',
            $tipe,
            $item->transfer->created_at ? $item->transfer->created_at->format('Y-m-d H:i') : '-',
            $item->transfer->users->pharmacy->name ?? '-',
            $item->batches->pharmacy->name ?? '-',
            $item->batches->medicines->code ?? '-',
            $item->batches->medicines->name ?? '-',
            $item->batches->name ?? '-',
            $item->etalases->name ?? '-',
            $item->qty,
            $statusMap[$item->status] ?? '-',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
