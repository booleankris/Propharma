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
    protected $expiredDate;
    protected $status;

    public function __construct($pharmacyId, $startDate, $endDate, $search, $type, $expiredDate = null, $status = null)
    {
        $this->pharmacyId = $pharmacyId;
        $this->startDate = $startDate;
        $this->endDate = $endDate;
        $this->search = $search;
        $this->type = $type;
        $this->expiredDate = $expiredDate;
        $this->status = $status;
    }

    public function query()
    {
        $query = MedicineTransferItems::with([
            'transfer.users.pharmacy',
            'transfer.sourcePharmacy',
            'transfer.destinationPharmacy',
            'batches.medicines',
            'batches.pharmacy',
            'etalases',
            'requestedMedicine',
            'sourceBatch.pharmacy',
        ])->whereNull('receiving_items_id');

        if ($this->startDate && $this->endDate) {
            $query->whereHas('transfer', function ($q) {
                $q->whereBetween('created_at', [$this->startDate . ' 00:00:00', $this->endDate . ' 23:59:59']);
            });
        }

        if ($this->expiredDate) {
            $query->whereHas('batches', function ($q) {
                $q->whereDate('expired_date', $this->expiredDate);
            });
        }

        if ($this->search) {
            $search = $this->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('transfer', fn($t) => $t->where('code', 'like', "%{$search}%"))
                    ->orWhereHas('batches.medicines', function ($m) use ($search) {
                        $m->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('sourceBatch.medicines', function ($m) use ($search) {
                        $m->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    })
                    ->orWhereHas('requestedMedicine', function ($m) use ($search) {
                        $m->where('name', 'like', "%{$search}%")
                            ->orWhere('code', 'like', "%{$search}%");
                    });
            });
        }

        if ($this->status === 'unreceived') {
            $query->where('status', 0)
                ->whereHas('transfer', function ($t) {
                    $t->where('status', '!=', 2)
                        ->where(function ($req) {
                            $req->where('is_request', false)
                                ->orWhereNull('is_request')
                                ->orWhere('request_status', 1);
                        });
                });
        } elseif ($this->status === 'received') {
            $query->where('status', 1);
        }

        $pharmacyId = (int) $this->pharmacyId;
        $query->where(function ($query) use ($pharmacyId) {
            $query->whereHas('transfer', function ($transfer) use ($pharmacyId) {
                $transfer->where('source_pharmacy_id', $pharmacyId)
                    ->orWhere('destination_pharmacy_id', $pharmacyId)
                    ->orWhere(function ($legacy) use ($pharmacyId) {
                        $legacy->whereNull('source_pharmacy_id')->whereHas('users', fn($user) => $user->where('pharmacy_id', $pharmacyId));
                    });
            })->orWhereHas('sourceBatch', fn($batch) => $batch->where('pharmacy_id', $pharmacyId))
                ->orWhereHas('batches', fn($batch) => $batch->where('pharmacy_id', $pharmacyId));
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

        $senderId = $item->transfer?->source_pharmacy_id ?? $item->sourceBatch?->pharmacy_id ?? $item->transfer?->users?->pharmacy_id ?? null;
        $receiverId = $item->transfer?->destination_pharmacy_id ?? $item->batches?->pharmacy_id ?? null;
        $senderName = $item->transfer?->sourcePharmacy?->name ?? $item->sourceBatch?->pharmacy?->name ?? $item->transfer?->users?->pharmacy?->name ?? '-';
        $receiverName = $item->transfer?->destinationPharmacy?->name ?? $item->batches?->pharmacy?->name ?? '-';
        $medicine = $item->batches?->medicines ?? $item->requestedMedicine;

        if ($item->transfer?->is_request && (int) $item->transfer?->request_status === 0) {
            $tipe = 'Permintaan Mutasi';
        } elseif ($senderId == $this->pharmacyId && $receiverId == $this->pharmacyId) {
            $tipe = 'Internal';
        } elseif ($senderId == $this->pharmacyId) {
            $tipe = 'Keluar';
        } elseif ($receiverId == $this->pharmacyId) {
            $tipe = 'Masuk';
        } else {
            $tipe = '-';
        }

        return [
            $item->transfer?->code ?? '-',
            $tipe,
            $item->transfer?->created_at ? $item->transfer->created_at->format('Y-m-d H:i') : '-',
            $senderName,
            $receiverName,
            $medicine?->code ?? '-',
            $medicine?->name ?? '-',
            $item->batches?->name ?? '-',
            $item->etalases?->name ?? '-',
            $item->original_qty ?? $item->qty,
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
