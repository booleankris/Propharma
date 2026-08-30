<?php

namespace App\Http\Controllers;

use App\Exports\Export\ParetoExport;
use App\Exports\Orders\InvoiceExport;
use App\Exports\Orders\OrdersExport;
use App\Exports\Report\CategoryExport;
use App\Exports\Report\DoctorExport;
use App\Exports\Report\FactoryExport;
use App\Exports\Report\LiphExport;
use App\Exports\Report\LiphOnlineExport;
use App\Exports\Report\MedicineExport;
use App\Exports\Report\RecipeExport;
use App\Exports\Report\ReturExport;
use App\Jobs\ProcessMedicinesExport;
use App\Jobs\ProcessPatientsExport;
use App\Jobs\TransactionExportJob;
use App\Models\ExportJob;
use App\Models\MedicineTransactions;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    // Report Data
    public function reports(Request $request)
    {
        $activeId = getActivePharmacyId();
        $pharmacy = Pharmacies::findOrFail($activeId);
        $report = $request->selectedReport;

        [$export, $filename] = $this->resolveReportExport($report, $request, $pharmacy);

        if (!$export) {
            return response()->json(['status' => 'success']);
        }

        // mode=preview -> render HTML table, mode anything else (or absent) -> download
        if ($request->mode === 'preview') {
            // Multi-sheet export (e.g. LiphOnlineExport): build tabs data
            if ($export instanceof WithMultipleSheets) {
                $sheets = [];
                foreach ($export->sheets() as $sheet) {
                    $title = method_exists($sheet, 'title') ? $sheet->title() : 'Sheet';
                    $sheets[$title] = $sheet->array();
                }

                return view('reports.preview', [
                    'sheets'      => $sheets,
                    'rows'        => [],
                    'reportTitle' => $report,
                    'queryParams' => $request->except('mode'),
                ]);
            }

            if (!method_exists($export, 'array')) {
                return response()->json(['status' => 'error', 'message' => 'Preview not supported for this report'], 422);
            }

            return view('reports.preview', [
                'rows' => $export->array(),
                'reportTitle' => method_exists($export, 'title') ? $export->title() : $report,
                'queryParams' => $request->except('mode'),
            ]);
        }

        return Excel::download($export, $filename);
    }

    private function resolveReportExport(string $report, Request $request, Pharmacies $pharmacy): array
    {
        $request->validate([
            'start_date' => 'required_if:selectedReport,LIPH,Obat,Golongan,Pabrik,Dokter,Daftar Resep,Retur Jual|date',
            'end_date' => 'required_if:selectedReport,LIPH,Obat,Golongan,Pabrik,Dokter,Daftar Resep,Retur Jual|date|after_or_equal:start_date',
        ]);

        return match ($report) {
            'LIPH' => $request->shiftType === 'online'
                ? [
                    new LiphOnlineExport(
                        $pharmacy->id,
                        $request->start_date,
                        $request->end_date,
                        $pharmacy->name,
                        $pharmacy->address,
                        $request->shift,
                    ),
                    'LIPH_Online_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
                ]
                : [
                    new LiphExport(
                        $pharmacy->id,
                        $request->start_date,
                        $request->end_date,
                        $pharmacy->name,
                        $pharmacy->address,
                        $request->shift,
                        $request->shiftType,
                    ),
                    'LIPH_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
                ],
            'Obat' => [
                new MedicineExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                ),
                'DATA_OBAT_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Golongan' => [
                new CategoryExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                ),
                'GOLONGAN_OBAT_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Pabrik' => [
                new FactoryExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                    $request->factory,
                ),
                'PABRIK_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Dokter' => [
                new DoctorExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                    $request->doctor,
                ),
                'DOKTER_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Daftar Resep' => [
                new RecipeExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                ),
                'RESEP_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Retur Jual' => [
                new ReturExport($pharmacy->id, $request->start_date, $request->end_date),
                'RETUR_JUAL_' . $pharmacy->name . '_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Laporan Pembelian' => [
                new OrdersExport($pharmacy->id, $request->start_date, $request->end_date),
                'DATA_PEMBELIAN_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            'Faktur Pembelian' => [
                new InvoiceExport($pharmacy->id, $request->start_date, $request->end_date, $request->selectedType),
                'DATA_FAKTUR_' . $request->start_date . '_sd_' . $request->end_date . '.xlsx',
            ],
            default => [null, null],
        };
    }

    // Sales Export Function (Generate)



    // ================================ ================== ===================================

    public function transactions()
    {
        return view('report.transactions');
    }

    public function medicines()
    {
        return view('report.medicines');
    }

    public function patients()
    {
        return view('report.patients');
    }

    public function doctors()
    {
        return view('report.transactions');
    }

    // Patient Export
    public function exportPatients(Request $request)
    {
        $filters = [
            'start_date' => $request->start_date ?? null,
            'end_date' => $request->end_date ?? null
        ];

        $job = ExportJob::create([
            'type' => 'patients',
            'status' => 'pending',
            'progress' => 0
        ]);

        dispatch(new ProcessPatientsExport($job->id, $filters));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export started.'
        ]);
    }

    public function exportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'file' => $job->file_path ? asset('storage/' . $job->file_path) : null
        ]);
    }

    // Transaction Export Function + Jobns
    public function exportTransactions(Request $request)
    {
        $start = $request->start ?? null;
        $end = $request->end ?? null;

        $job = ExportJob::create([
            'type' => 'transactions',
            'status' => 'queued',
            'progress' => 0,
            'file_path' => null
        ]);

        dispatch(new TransactionExportJob($job, $start, $end));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export started'
        ]);
    }

    public function transactionExportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status' => $job->status,
            'progress' => $job->progress,
            'file' => $job->file_path
                ? route('reports.export.transactions.download', $id)
                : null
        ]);
    }

    public function transactionExportDownload($id)
    {
        $job = ExportJob::findOrFail($id);

        if (!$job->file_path || !Storage::disk('public')->exists($job->file_path)) {
            abort(404, 'File not found.');
        }

        return Storage::disk('public')->download($job->file_path);
    }

    public function exportMedicines()
    {
        $job = ExportJob::create([
            'type' => 'medicines',
            'status' => 'queued',
            'progress' => 0,
        ]);

        dispatch(new ProcessMedicinesExport($job->id));

        return response()->json([
            'job_id' => $job->id,
            'message' => 'Export started'
        ]);
    }

    public function exportMedicinesStatus($id)
    {
        return ExportJob::findOrFail($id);
    }

    public function exportMedicinesDownload($id)
    {
        $job = ExportJob::findOrFail($id);

        if (!$job->file_path) {
            abort(404, "File not ready");
        }

        return response()->download(storage_path("app/public/" . $job->file_path));
    }

    public function exportSpecialMedicines(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $job = ExportJob::create([
            'type'     => 'special_medicines',
            'status'   => 'queued',
            'progress' => 0,
        ]);

        dispatch(new \App\Jobs\ProcessSpecialMedicinesExport($job->id, $pharmacyId, $startDate, $endDate));

        return response()->json([
            'job_id'  => $job->id,
            'message' => 'Export antrean dimulai'
        ]);
    }

    public function exportSalesRetur(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $job = ExportJob::create([
            'type'     => 'sales_retur',
            'status'   => 'queued',
            'progress' => 0,
        ]);

        dispatch(new \App\Jobs\ProcessSalesReturExport($job->id, $pharmacyId, $startDate, $endDate));

        return response()->json([
            'job_id'  => $job->id,
            'message' => 'Export antrean dimulai'
        ]);
    }

    public function exportPurchaseRetur(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $job = ExportJob::create([
            'type'     => 'purchase_retur',
            'status'   => 'queued',
            'progress' => 0,
        ]);

        dispatch(new \App\Jobs\ProcessPurchaseReturExport($job->id, $pharmacyId, $startDate, $endDate));

        return response()->json([
            'job_id'  => $job->id,
            'message' => 'Export antrean dimulai'
        ]);
    }

    public function exportExpiryDates(Request $request)
    {
        $pharmacyId = getActivePharmacyId();

        $job = ExportJob::create([
            'type'     => 'expiry_dates',
            'status'   => 'queued',
            'progress' => 0,
        ]);

        dispatch(new \App\Jobs\ProcessExpiryDateExport($job->id, $pharmacyId));

        return response()->json([
            'job_id'  => $job->id,
            'message' => 'Export antrean dimulai'
        ]);
    }

    public function exportRejects(Request $request)
    {
        $pharmacyId = getActivePharmacyId();
        $startDate = $request->start_date ?? now()->startOfMonth()->toDateString();
        $endDate = $request->end_date ?? now()->toDateString();

        $job = ExportJob::create([
            'type'     => 'rejects',
            'status'   => 'queued',
            'progress' => 0,
        ]);

        dispatch(new \App\Jobs\ProcessRejectSalesExport($job->id, $pharmacyId, $startDate, $endDate));

        return response()->json([
            'job_id'  => $job->id,
            'message' => 'Export antrean dimulai'
        ]);
    }
}
