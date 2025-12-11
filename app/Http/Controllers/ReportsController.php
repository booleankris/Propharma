<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessMedicinesExport;
use App\Jobs\ProcessPatientsExport;
use App\Jobs\TransactionExportJob;
use App\Models\ExportJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ReportsController extends Controller
{
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

    // Transaction Export
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
