<?php

namespace App\Exports\Finance;

use App\Models\FinanceAccount;
use App\Models\FinancePayment;
use App\Models\Pharmacies;
use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class KasBankExport implements FromArray, WithStyles, WithColumnWidths, WithTitle
{
    protected array $targetPharmacyIds;
    protected array $filters;
    protected ?Pharmacies $activePharmacy;
    protected int $activePharmacyId;
    protected int $dataRowCount = 0;
    protected int $dataStartRow = 6;
    protected ?int $totalRow = null;
    protected ?int $netRow = null;

    public function __construct(array $targetPharmacyIds, array $filters = [], ?Pharmacies $activePharmacy = null)
    {
        $this->targetPharmacyIds = $targetPharmacyIds;
        $this->filters = $filters;
        $this->activePharmacy = $activePharmacy;
        $this->activePharmacyId = (int) ($activePharmacy?->id ?? (function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1));
        if ($this->activePharmacyId <= 0) {
            $this->activePharmacyId = 1;
        }
    }

    public function title(): string
    {
        return 'Mutasi Kas & Bank';
    }

    public function columnWidths(): array
    {
        return [
            'A' => 6,  // No
            'B' => 14,  // Tanggal
            'C' => 20,  // No. Dokumen
            'D' => 26,  // Rekening Kas/Bank
            'E' => 25,  // Kategori Transaksi
            'F' => 26,  // Pihak Terkait
            'G' => 44,  // Keterangan / Catatan
            'H' => 20,  // Masuk / Penerimaan (Rp)
            'I' => 20,  // Keluar / Pengeluaran (Rp)
            'J' => 16,  // Petugas / Kasir
        ];
    }

    public function array(): array
    {
        $branchName = $this->activePharmacy ? $this->activePharmacy->name : 'Semua Cabang';

        // 1. Label Periode Tanggal
        $startDate = $this->filters['start_date'] ?? null;
        $endDate = $this->filters['end_date'] ?? null;
        $filterDateStr = 'Semua Periode';
        if ($startDate && $endDate) {
            $filterDateStr = Carbon::parse($startDate)->format('d/m/Y') . ' s/d ' . Carbon::parse($endDate)->format('d/m/Y');
        } elseif ($startDate) {
            $filterDateStr = 'Mulai ' . Carbon::parse($startDate)->format('d/m/Y');
        } elseif ($endDate) {
            $filterDateStr = 'Sampai ' . Carbon::parse($endDate)->format('d/m/Y');
        }

        // 2. Label Akun Kas/Bank
        $filterAccountId = $this->filters['account_id'] ?? 'all';
        $filterAccountName = 'Semua Rekening Kas & Bank';
        if ($filterAccountId && $filterAccountId !== 'all') {
            $acc = is_numeric($filterAccountId)
                ? FinanceAccount::find($filterAccountId)
                : FinanceAccount::where('name', 'LIKE', "%{$filterAccountId}%")->first();
            if ($acc) {
                $filterAccountName = ($acc->code ? "{$acc->code} - " : '') . $acc->name;
            }
        }

        // 3. Label Arah Transaksi
        $filterDirection = strtoupper((string) ($this->filters['direction'] ?? 'ALL'));
        $filterDirectionName = match ($filterDirection) {
            'IN' => 'Penerimaan / Masuk Saja (Debit)',
            'OUT' => 'Pengeluaran / Keluar Saja (Kredit)',
            default => 'Semua Transaksi (Masuk & Keluar)',
        };

        // Header Title Block (Row 1-5)
        $rows = [
            ["APOTEK SAHABAT - {$branchName}"],
            ['BUKU MUTASI KAS & BANK'],
            ["Periode: {$filterDateStr} | Rekening: {$filterAccountName} | Aliran: {$filterDirectionName} | Dicetak: " . Carbon::now()->format('d/m/Y H:i')],
            array_fill(0, 10, ''),  // Row 4 Blank
            [  // Row 5 Table Headers
                'No',
                'Tanggal',
                'No. Dokumen / Bukti',
                'Rekening Kas / Bank',
                'Kategori Transaksi',
                'Pihak Terkait',
                'Keterangan / Catatan',
                'Masuk (Debit) (Rp)',
                'Keluar (Kredit) (Rp)',
                'Petugas / Kasir',
            ],
        ];

        // 4. Query Data Mutasi
        $query = FinancePayment::with([
            'account',
            'expenseAccount',
            'incomeAccount',
            'transferAccount',
            'creator',
            'pharmacy',
            'receivingDetail.creditor',
            'transaction.debtors',
            'transaction.patients',
        ])
            ->whereHas('account', function ($q) {
                $q
                    ->where('category', 'Kas & Bank')
                    ->where('pharmacy_id', $this->activePharmacyId);
            })
            ->where(function ($q) {
                $q
                    ->whereHas('receiving', function ($sub) {
                        $sub->whereIn('pharmacy_id', $this->targetPharmacyIds);
                    })
                    ->orWhereHas('transaction', function ($sub) {
                        $sub->whereIn('pharmacy_id', $this->targetPharmacyIds);
                    })
                    ->orWhere(function ($sub) {
                        $sub
                            ->where('payment_type', 'BIAYA')
                            ->where(function ($sub2) {
                                $sub2
                                    ->whereIn('pharmacy_id', $this->targetPharmacyIds)
                                    ->orWhereNull('pharmacy_id');
                            });
                    })
                    ->orWhere(function ($sub) {
                        $sub->where('payment_type', 'PENDAPATAN')->whereIn('pharmacy_id', $this->targetPharmacyIds);
                    })
                    ->orWhere(function ($sub) {
                        $sub->where('payment_type', 'SETOR')->whereIn('pharmacy_id', $this->targetPharmacyIds);
                    })
                    ->orWhere(function ($sub) {
                        $sub->where('payment_type', 'TARIK TUNAI')->whereIn('pharmacy_id', $this->targetPharmacyIds);
                    })
                    ->orWhere(function ($sub) {
                        $sub
                            ->whereIn('payment_type', ['PENJUALAN TUNAI', 'PENJUALAN NON TUNAI', 'RETUR PENJUALAN'])
                            ->whereIn('pharmacy_id', $this->targetPharmacyIds);
                    })
                    ->orWhere('payment_type', 'SALDO AWAL')
                    ->orWhere('payment_type', 'PENYESUAIAN SALDO');
            });

        // Date Filter
        if ($startDate && $endDate) {
            $query->whereBetween('payment_date', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('payment_date', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('payment_date', '<=', $endDate);
        }

        // Account Filter
        if ($filterAccountId && $filterAccountId !== 'all') {
            $query->where(function ($q) use ($filterAccountId) {
                $q
                    ->where('account_id', $filterAccountId)
                    ->orWhere(function ($sub) use ($filterAccountId) {
                        $sub
                            ->where('transfer_account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
            });
        }

        // Direction Filter
        if ($filterDirection === 'IN') {
            $query->where(function ($q) use ($filterAccountId) {
                $q
                    ->whereIn('payment_type', ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'])
                    ->orWhere(function ($sub) {
                        $sub
                            ->where('payment_type', 'PENYESUAIAN SALDO')
                            ->where('reference_number', '!=', 'OPNAME-OUT');
                    });
                if ($filterAccountId && $filterAccountId !== 'all') {
                    $q->orWhere(function ($sub) use ($filterAccountId) {
                        $sub
                            ->where('transfer_account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
                }
            });
        } elseif ($filterDirection === 'OUT') {
            $query->where(function ($q) use ($filterAccountId) {
                $q
                    ->whereIn('payment_type', ['KREDIT', 'CASH', 'KONSINYASI', 'BIAYA', 'RETUR PENJUALAN'])
                    ->orWhere(function ($sub) {
                        $sub
                            ->where('payment_type', 'PENYESUAIAN SALDO')
                            ->where('reference_number', '=', 'OPNAME-OUT');
                    });
                if ($filterAccountId && $filterAccountId !== 'all') {
                    $q->orWhere(function ($sub) use ($filterAccountId) {
                        $sub
                            ->where('account_id', $filterAccountId)
                            ->whereIn('payment_type', ['SETOR', 'TARIK TUNAI']);
                    });
                }
            });
        }

        // Search Filter
        if (!empty($this->filters['search'])) {
            $s = trim($this->filters['search']);
            $query->where(function ($q) use ($s) {
                $q
                    ->where('reference_number', 'like', "%{$s}%")
                    ->orWhere('notes', 'like', "%{$s}%")
                    ->orWhere('recipient', 'like', "%{$s}%")
                    ->orWhereHas('transaction', function ($sub) use ($s) {
                        $sub
                            ->where('transaction_code', 'like', "%{$s}%")
                            ->orWhere('invoice_number', 'like', "%{$s}%")
                            ->orWhereHas('debtors', fn($d) => $d->where('name', 'like', "%{$s}%"))
                            ->orWhereHas('patients', fn($p) => $p->where('name', 'like', "%{$s}%"));
                    })
                    ->orWhereHas('receivingDetail', function ($sub) use ($s) {
                        $sub
                            ->where('invoice_number', 'like', "%{$s}%")
                            ->orWhereHas('creditor', fn($c) => $c->where('name', 'like', "%{$s}%"));
                    })
                    ->orWhereHas('account', function ($sub) use ($s) {
                        $sub
                            ->where('name', 'like', "%{$s}%")
                            ->orWhere('code', 'like', "%{$s}%");
                    });
            });
        }

        // Sort ascending by date and id for chronological ledger export
        $payments = $query->orderBy('payment_date', 'asc')->orderBy('id', 'asc')->get();

        // 5. Format Data Rows
        $expandedRows = [];
        $targetAccId = ($filterAccountId && $filterAccountId !== 'all') ? $filterAccountId : null;

        foreach ($payments as $p) {
            $isSetor = $p->payment_type === 'SETOR';
            $isTarikTunai = $p->payment_type === 'TARIK TUNAI';

            if (!$isSetor && !$isTarikTunai) {
                $expandedRows[] = $this->formatRowData($p, $targetAccId);
            } else {
                if ($targetAccId) {
                    $expandedRows[] = $this->formatRowData($p, $targetAccId);
                } else {
                    // Tampilkan dua sisi mutasi transfer antar akun agar buku kas & bank balance
                    $expandedRows[] = $this->formatRowData($p, null);
                    $expandedRows[] = $this->formatRowData($p, $p->transfer_account_id);
                }
            }
        }

        $this->dataRowCount = count($expandedRows);

        if ($this->dataRowCount === 0) {
            $rows[] = [
                'Tidak ada catatan transaksi mutasi kas & bank yang sesuai dengan filter yang dipilih.',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ];
            return $rows;
        }

        $no = 1;
        $totalMasuk = 0.0;
        $totalKeluar = 0.0;

        foreach ($expandedRows as $item) {
            $masuk = $item['direction'] === 'IN' ? (float) $item['amount'] : 0.0;
            $keluar = $item['direction'] === 'OUT' ? (float) $item['amount'] : 0.0;
            $totalMasuk += $masuk;
            $totalKeluar += $keluar;

            $rows[] = [
                $no++,
                $item['date'],
                $item['document_no'],
                $item['account_name'],
                $item['category_label'],
                $item['party'],
                $item['description'],
                $masuk > 0 ? $masuk : 0,
                $keluar > 0 ? $keluar : 0,
                $item['user'],
            ];
        }

        // Summary Rows
        $this->totalRow = $this->dataStartRow + $this->dataRowCount;
        $this->netRow = $this->totalRow + 1;

        $rows[] = [
            'TOTAL MUTASI',
            '',
            '',
            '',
            '',
            '',
            '',
            $totalMasuk,
            $totalKeluar,
            '',
        ];

        $netCashflow = $totalMasuk - $totalKeluar;
        $rows[] = [
            'ARUS KAS BERSIH (NET CASHFLOW)',
            '',
            '',
            '',
            '',
            '',
            '',
            $netCashflow,
            '',
            $netCashflow >= 0 ? 'Surplus (+)' : 'Defisit (-)',
        ];

        return $rows;
    }

    protected function formatRowData($p, $forcedAccountId = null): array
    {
        $isMasuk = in_array($p->payment_type, ['PIUTANG', 'PENDAPATAN', 'PENJUALAN TUNAI', 'PENJUALAN NON TUNAI'], true);
        $isSaldoAwal = $p->payment_type === 'SALDO AWAL';
        $isSetor = $p->payment_type === 'SETOR';
        $isTarikTunai = $p->payment_type === 'TARIK TUNAI';
        $isPenyesuaian = $p->payment_type === 'PENYESUAIAN SALDO';
        $isOpnameOut = $isPenyesuaian && $p->reference_number === 'OPNAME-OUT';

        if ($isSaldoAwal) {
            $direction = 'OPENING';
            $catLabel = 'Saldo Awal';
        } elseif ($isPenyesuaian) {
            $direction = $isOpnameOut ? 'OUT' : 'IN';
            $catLabel = $isOpnameOut ? 'Penyesuaian Saldo Riil (-)' : 'Penyesuaian Saldo Riil (+)';
        } elseif ($isSetor) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $direction = 'IN';
                $catLabel = 'Setoran Masuk (Bank)';
            } else {
                $direction = 'OUT';
                $catLabel = 'Setor Kas ke Bank';
            }
        } elseif ($isTarikTunai) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $direction = 'IN';
                $catLabel = 'Penerimaan Tarik Tunai (Kas)';
            } else {
                $direction = 'OUT';
                $catLabel = 'Tarik Tunai dari Bank';
            }
        } elseif ($p->payment_type === 'RETUR PENJUALAN') {
            $direction = 'OUT';
            $catLabel = 'Retur Jual (Refund Kasir)';
        } else {
            $direction = $isMasuk ? 'IN' : 'OUT';
            $catLabel = match ($p->payment_type) {
                'PENJUALAN TUNAI' => 'Penjualan Tunai',
                'PENJUALAN NON TUNAI' => 'Penjualan Non-Tunai',
                'PIUTANG' => 'Penerimaan Piutang',
                'KREDIT' => 'Pelunasan Hutang (PBF)',
                'CASH' => 'Pembelian Cash',
                'KONSINYASI' => 'Pembayaran Konsinyasi',
                'BIAYA' => 'Biaya Operasional',
                'PENDAPATAN' => 'Pendapatan Lain-lain',
                default => $p->payment_type,
            };
        }

        $pihakTerkait = '-';
        $noDokumen = $p->reference_number ?: ('MUT-' . str_pad($p->id, 5, '0', STR_PAD_LEFT));
        $deskripsi = $p->notes;

        if ($p->payment_type === 'PENJUALAN TUNAI') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->transaction_code ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = 'Penerimaan penjualan tunai kasir';
        } elseif ($p->payment_type === 'PENJUALAN NON TUNAI') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->transaction_code ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi) {
                $method = $p->transaction->payment_method ?? 'NON-TUNAI';
                $bank = $p->transaction->transfer_bank_name ?? ($p->account->name ?? 'Bank');
                $deskripsi = "Penerimaan penjualan {$method} melalui {$bank}";
            }
        } elseif ($p->payment_type === 'PIUTANG') {
            $pihakTerkait = $p->transaction->debtors->name ?? ($p->transaction->patients->name ?? 'Pelanggan Umum');
            $doc = $p->transaction->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Penerimaan piutang penjualan dari {$pihakTerkait}";
        } elseif ($p->payment_type === 'KREDIT') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Pelunasan hutang pembelian ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'CASH') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Pembelian tunai (cash) ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'KONSINYASI') {
            $pihakTerkait = $p->receivingDetail->creditor->name ?? ($p->receivingDetail->creditor_code ?? 'PBF / Vendor');
            $doc = $p->receivingDetail->invoice_number ?? $p->reference_number;
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = "Pembayaran barang konsinyasi terjual ke {$pihakTerkait}";
        } elseif ($p->payment_type === 'BIAYA') {
            $pihakTerkait = $p->recipient ?: ($p->expenseAccount->name ?? 'Biaya Operasional');
            $doc = $p->reference_number ?: ('BY-' . str_pad($p->id, 5, '0', STR_PAD_LEFT));
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = 'Pengeluaran biaya: ' . ($p->expenseAccount->name ?? 'Operasional');
        } elseif ($p->payment_type === 'PENDAPATAN') {
            $pihakTerkait = $p->recipient ?: ($p->incomeAccount->name ?? 'Pendapatan Lain-lain');
            if (!$p->reference_number)
                $noDokumen = 'PL-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            if (!$deskripsi)
                $deskripsi = 'Pendapatan lain-lain: ' . ($p->incomeAccount->name ?? 'Pendapatan');
        } elseif ($p->payment_type === 'RETUR PENJUALAN') {
            $pihakTerkait = $p->transaction->patients->name ?? ($p->transaction->debtors->name ?? 'Pelanggan Umum');
            $doc = $p->reference_number ?: ('RT-' . $p->id);
            if ($doc)
                $noDokumen = $doc;
            if (!$deskripsi)
                $deskripsi = 'Pengembalian dana retur penjualan tunai kasir';
        } elseif ($isSaldoAwal) {
            $pihakTerkait = $p->account->name ?? 'Kas / Bank';
            $noDokumen = 'SA-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            $deskripsi = $p->notes ?: 'Saldo awal akun Kas & Bank';
        } elseif ($isSetor) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $pihakTerkait = $p->account->name ?? 'Kas Apotek / Drawer';
                $deskripsi = $p->notes ?: 'Setoran masuk dari kas';
            } else {
                $pihakTerkait = $p->transferAccount->name ?? 'Rekening tujuan';
                $deskripsi = $p->notes ?: 'Setoran kas ke rekening';
            }
        } elseif ($isTarikTunai) {
            if ($forcedAccountId && $p->transfer_account_id == $forcedAccountId) {
                $pihakTerkait = $p->account->name ?? 'Rekening Bank';
                $deskripsi = $p->notes ?: 'Penerimaan tarik tunai dari rekening bank ke kas';
            } else {
                $pihakTerkait = $p->transferAccount->name ?? 'Kas Apotek / Drawer';
                $deskripsi = $p->notes ?: 'Tarik tunai dari rekening bank ke kas';
            }
        } elseif ($isPenyesuaian) {
            $pihakTerkait = $p->account->name ?? 'Kas / Bank';
            $noDokumen = 'OPN-' . str_pad($p->id, 5, '0', STR_PAD_LEFT);
            $deskripsi = $p->notes ?: 'Penyesuaian Saldo Riil (Opname)';
        }

        $accountToShow = ($forcedAccountId && $p->transfer_account_id == $forcedAccountId)
            ? $p->transferAccount
            : $p->account;

        return [
            'account_name' => $accountToShow?->name ?? 'Akun Kas',
            'date' => Carbon::parse($p->payment_date)->format('d/m/Y'),
            'amount' => (float) $p->amount,
            'direction' => $direction,
            'category_label' => $catLabel,
            'document_no' => $noDokumen,
            'party' => $pihakTerkait,
            'description' => $deskripsi ?: 'Transaksi Keuangan',
            'user' => $p->creator->name ?? 'Admin',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        // 1. Title Block Styling
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '0F172A']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['bold' => true, 'size' => 12, 'color' => ['rgb' => '1E40AF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(2)->setRowHeight(20);

        $sheet->mergeCells('A3:J3');
        $sheet->getStyle('A3')->applyFromArray([
            'font' => ['italic' => true, 'size' => 9, 'color' => ['rgb' => '64748B']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT, 'vertical' => Alignment::VERTICAL_CENTER],
        ]);
        $sheet->getRowDimension(3)->setRowHeight(18);

        // 2. Table Headers (Row 5)
        $sheet->getStyle('A5:J5')->applyFromArray([
            'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E293B'],  // Dark Slate
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
                'wrapText' => true,
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '0F172A']],
            ],
        ]);
        $sheet->getRowDimension(5)->setRowHeight(28);

        // 3. Data Rows Styling
        if ($this->dataRowCount > 0) {
            $start = $this->dataStartRow;
            $end = $start + $this->dataRowCount - 1;

            for ($row = $start; $row <= $end; $row++) {
                $sheet->getRowDimension($row)->setRowHeight(20);

                // Zebra striping
                if ($row % 2 === 1) {
                    $sheet
                        ->getStyle("A{$row}:J{$row}")
                        ->getFill()
                        ->setFillType(Fill::FILL_SOLID)
                        ->getStartColor()
                        ->setRGB('F8FAFC');
                }

                $sheet->getStyle("A{$row}:J{$row}")->applyFromArray([
                    'borders' => [
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                    ],
                    'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // Alignment per kolom
                foreach (['A', 'B', 'C', 'J'] as $col) {
                    $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                foreach (['D', 'E', 'F', 'G'] as $col) {
                    $sheet->getStyle("{$col}{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                }
                $sheet->getStyle("C{$row}")->getFont()->setBold(true);

                // Format mata uang & warna
                $sheet->getStyle("H{$row}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'numberFormat' => ['formatCode' => '#,##0'],
                    'font' => ['color' => ['rgb' => '059669'], 'bold' => true],  // Emerald
                ]);
                $sheet->getStyle("I{$row}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'numberFormat' => ['formatCode' => '#,##0'],
                    'font' => ['color' => ['rgb' => 'DC2626'], 'bold' => true],  // Red/Rose
                ]);
            }

            // 4. Total Row Styling
            if ($this->totalRow !== null) {
                $tRow = $this->totalRow;
                $sheet->mergeCells("A{$tRow}:G{$tRow}");
                $sheet->getStyle("A{$tRow}:G{$tRow}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E293B']],
                ]);
                $sheet->getStyle("A{$tRow}:J{$tRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'F1F5F9']],
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                        'bottom' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '94A3B8']],
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'CBD5E1']],
                    ],
                    'font' => ['bold' => true, 'size' => 10],
                ]);
                $sheet->getStyle("H{$tRow}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'numberFormat' => ['formatCode' => '#,##0'],
                    'font' => ['bold' => true, 'color' => ['rgb' => '059669']],
                ]);
                $sheet->getStyle("I{$tRow}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'numberFormat' => ['formatCode' => '#,##0'],
                    'font' => ['bold' => true, 'color' => ['rgb' => 'DC2626']],
                ]);
                $sheet->getRowDimension($tRow)->setRowHeight(24);
            }

            // 5. Net Cashflow Row Styling
            if ($this->netRow !== null) {
                $nRow = $this->netRow;
                $sheet->mergeCells("A{$nRow}:G{$nRow}");
                $sheet->mergeCells("H{$nRow}:I{$nRow}");
                $sheet->getStyle("A{$nRow}:G{$nRow}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT, 'vertical' => Alignment::VERTICAL_CENTER],
                    'font' => ['bold' => true, 'size' => 10, 'color' => ['rgb' => '1E3A8A']],
                ]);
                $sheet->getStyle("A{$nRow}:J{$nRow}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],  // Soft blue
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '3B82F6']],
                        'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['rgb' => '1D4ED8']],
                        'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'BFDBFE']],
                    ],
                    'font' => ['bold' => true, 'size' => 10],
                ]);
                $sheet->getStyle("H{$nRow}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_RIGHT],
                    'numberFormat' => ['formatCode' => '#,##0'],
                    'font' => ['bold' => true, 'color' => ['rgb' => '1D4ED8']],
                ]);
                $sheet->getStyle("J{$nRow}")->applyFromArray([
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                    'font' => ['bold' => true, 'size' => 9, 'color' => ['rgb' => '1D4ED8']],
                ]);
                $sheet->getRowDimension($nRow)->setRowHeight(24);
            }
        } else {
            // Empty state row styling
            $sheet->mergeCells('A6:J6');
            $sheet->getStyle('A6:J6')->applyFromArray([
                'font' => ['italic' => true, 'color' => ['rgb' => '64748B'], 'size' => 10],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders' => [
                    'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'E2E8F0']],
                ],
            ]);
            $sheet->getRowDimension(6)->setRowHeight(32);
        }

        return [];
    }
}
