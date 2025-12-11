<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExportJob extends Model
{
    protected $table = 'export_jobs';

    protected $fillable = [
        'type',
        'status',
        'progress',
        'file_path',
    ];

    protected $casts = [
        'progress' => 'integer',
    ];

    // Status constants for clean code
    const STATUS_PENDING = 'pending';
    const STATUS_PROCESSING = 'processing';
    const STATUS_FINISHED = 'finished';
    const STATUS_FAILED = 'failed';

    // Helper: check if finished
    public function isFinished()
    {
        return $this->status === self::STATUS_FINISHED;
    }

    // Helper: mark as processing
    public function markProcessing()
    {
        return $this->update([
            'status' => self::STATUS_PROCESSING,
            'progress' => 0
        ]);
    }

    // Helper: update progress
    public function setProgress($percent)
    {
        return $this->update([
            'progress' => $percent
        ]);
    }

    // Helper: finish job
    public function markFinished($path)
    {
        return $this->update([
            'status' => self::STATUS_FINISHED,
            'progress' => 100,
            'file_path' => $path
        ]);
    }
}
