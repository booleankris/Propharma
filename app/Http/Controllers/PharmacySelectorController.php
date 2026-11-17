<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PharmacySelectorController extends Controller
{
    public function switch(Request $request)
    {
        if (!hasFeatureAccess(['branch.switch_and_filter', 'ho.analytics'], ['HO', 'administrator', 'General Manager', 'Koordinator', 'koordinator'])) {
            abort(403, 'Unauthorized role.');
        }

        $request->validate([
            'pharmacy_id' => 'required|exists:pharmacies,id'
        ]);

        session(['ho_pharmacy_id' => $request->pharmacy_id]);

        return back()->with('success', 'Cabang berhasil diubah');
    }
}
