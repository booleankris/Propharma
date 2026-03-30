<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\ShiftLog;
use App\Models\ShiftLogs;
use App\Models\Shifts;
use Carbon\Carbon;

class CreateShiftLog
{
    public function handle(Login $event)
    {
        $user = $event->user;

        ShiftLogs::where('user_id', $user->id)
            ->whereNull('clock_out')
            ->update([
                'clock_out' => now(),
                'status' => 'auto_closed'
            ]);

        $getshift = Shifts::all();

        $shifts = $getshift->map(function ($shift) {
            return [
                'id' => $shift->id,
                'shift_in' => Carbon::parse($shift->shift_in),
                'shift_out' => Carbon::parse($shift->shift_out),
            ];
        })->toArray();


        $now = Carbon::now();
        $currentshift = "";

        foreach ($shifts as $shift) {
            $shiftStart = Carbon::parse($shift['shift_in']);
            $shiftEnd   = Carbon::parse($shift['shift_out']);
            if ($shiftEnd < $shiftStart) {
                if ($now->gte($shiftStart) || $now->lte($shiftEnd)) {
                    $currentShiftId = $shift['id'];
                }
            } else {
                if ($now->between($shiftStart, $shiftEnd)) {
                    $currentShiftId = $shift['id'];
                }
            }
        }

        $shiftLog = ShiftLogs::create([
            'user_id' => $user->id,
            'shift_id' => $currentShiftId ?? null,
            'clock_in' => now(),
            'status' => 'active',
        ]);

        session(['active_shift_log_id' => $shiftLog->id]);
    }
}
