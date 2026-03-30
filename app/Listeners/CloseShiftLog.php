<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Logout;
use App\Models\ShiftLog;
use App\Models\ShiftLogs;

class CloseShiftLog
{
    public function handle(Logout $event)
    {
        $shiftLogId = session('active_shift_log_id');

        if ($shiftLogId) {
            ShiftLogs::where('id', $shiftLogId)->update([
                'clock_out' => now(),
                'status' => 'finished',
            ]);
        }
        session()->forget('active_shift_log_id');
    }
}
