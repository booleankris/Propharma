<?php

namespace App\Http\Controllers;

use App\Exports\Report\CategoryExport;
use App\Exports\Report\DoctorExport;
use App\Exports\Report\FactoryExport;
use App\Exports\Report\LiphExport;
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
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    public function reports(Request $request)
    {
        // Export LIPH
        if ($request->selectedReport == "LIPH") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'LIPH_' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new LiphExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $pharmacy->name,
                    $pharmacy->address,
                    $request->shift,
                    $request->shiftType,
                ),
                $filename
            );
        }
        // Export Obat
        if ($request->selectedReport == "Obat") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'DATAOBAT_' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new MedicineExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                ),
                $filename
            );
        }

        // Export Category
        if ($request->selectedReport == "Golongan") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'KATEGORI_OBAT' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new CategoryExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                ),
                $filename
            );
        }

        if ($request->selectedReport == "Pabrik") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'KATEGORI_OBAT' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new FactoryExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                    $request->factory,
                ),
                $filename
            );
        }

        if ($request->selectedReport == "Dokter") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'DOKTER' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new DoctorExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                    $request->doctor,
                ),
                $filename
            );
        }

        if ($request->selectedReport == "Daftar Resep") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'RESEP' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new RecipeExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                    $request->shift,
                    $request->shiftType,
                    $request->selectedType,
                    $request->doctor,
                ),
                $filename
            );
        }

        if ($request->selectedReport == "Retur Jual") {
            $request->validate([
                'start_date'  => 'required|date',
                'end_date'    => 'required|date|after_or_equal:start_date',
            ]);
            $pharmacy = Pharmacies::findOrFail(auth()->user()->pharmacy_id);

            $filename = 'RESEP' . $pharmacy->name . '_'
                . $request->start_date . '_sd_' . $request->end_date . '.xlsx';

            return Excel::download(
                new ReturExport(
                    $pharmacy->id,
                    $request->start_date,
                    $request->end_date,
                ),
                $filename
            );
        }

        return response()->json([
            'status'   => "success",
        ]);
    }
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
            'end_date'   => $request->end_date ?? null
        ];

        $job = ExportJob::create([
            'type'     => 'patients',
            'status'   => 'pending',
            'progress' => 0
        ]);

        dispatch(new ProcessPatientsExport($job->id, $filters));

        return response()->json([
            'job_id'   => $job->id,
            'message'  => 'Export started.'
        ]);
    }

    public function exportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status'   => $job->status,
            'progress' => $job->progress,
            'file'     => $job->file_path ? asset('storage/' . $job->file_path) : null
        ]);
    }

    // Transaction Export Function + Jobns
    public function exportTransactions(Request $request)
    {
        $start = $request->start ?? null;
        $end   = $request->end ?? null;

        $job = ExportJob::create([
            'type'      => 'transactions',
            'status'    => 'queued',
            'progress'  => 0,
            'file_path' => null
        ]);

        dispatch(new TransactionExportJob($job, $start, $end));

        return response()->json([
            'job_id'  => $job->id,
            'message' => 'Export started'
        ]);
    }

    public function transactionExportStatus($id)
    {
        $job = ExportJob::findOrFail($id);

        return response()->json([
            'status'    => $job->status,
            'progress'  => $job->progress,
            'file'      => $job->file_path
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
}
