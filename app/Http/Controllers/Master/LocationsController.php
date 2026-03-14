<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Locations;
use Illuminate\Http\Request;
use Form;
use DataTables;

class LocationsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $locations = Locations::select('id', 'name', 'status', 'created_at');

            if (!$request->has('order')) {
                $locations = $locations->orderBy('created_at', 'ASC');
            }

            return DataTables::of($locations)
                ->addIndexColumn()
                ->addColumn('action', function ($location) {

                    $btn = '<div style="display:flex; gap:6px;">';
                    $btn .= '<a href="' . route('locations.edit', $location->id) . '"
                                title="Edit"
                                style="
                                    display:inline-flex;
                                    align-items:center;
                                    justify-content:center;
                                    width:32px;
                                    height:32px;
                                    background:#3b82f6;
                                    border-radius:6px;
                                    text-decoration:none;
                                ">
                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="white" viewBox="0 0 24 24">
                                    <path d="M3 17.25V21h3.75L17.81 9.94l-3.75-3.75L3 17.25zm2.92 2.33H5v-.92l9.06-9.06.92.92L5.92 19.58zM20.71 7.04a1.003 1.003 0 000-1.42L18.37 3.29a1.003 1.003 0 00-1.42 0l-1.83 1.83 3.75 3.75 1.84-1.83z"/>
                                </svg>
                            </a>';

                    $btn .= '<button
                            onclick="delete_data(' . $location->id . ')"
                            data-route="' . route('locations.destroy', $location->id) . '"
                            title="Delete"
                            style="
                                display:inline-flex;
                                align-items:center;
                                justify-content:center;
                                width:32px;
                                height:32px;
                                background:#ef4444;
                                border:none;
                                border-radius:6px;
                                cursor:pointer;
                            ">
                            <svg width="25px" height="25px" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2.75C11.0215 2.75 10.1871 3.37503 9.87787 4.24993C9.73983 4.64047 9.31134 4.84517 8.9208 4.70713C8.53026 4.56909 8.32557 4.1406 8.46361 3.75007C8.97804 2.29459 10.3661 1.25 12 1.25C13.634 1.25 15.022 2.29459 15.5365 3.75007C15.6745 4.1406 15.4698 4.56909 15.0793 4.70713C14.6887 4.84517 14.2602 4.64047 14.1222 4.24993C13.813 3.37503 12.9785 2.75 12 2.75Z" fill="#ffffff"></path>
                                <path d="M2.75 6C2.75 5.58579 3.08579 5.25 3.5 5.25H20.5001C20.9143 5.25 21.2501 5.58579 21.2501 6C21.2501 6.41421 20.9143 6.75 20.5001 6.75H3.5C3.08579 6.75 2.75 6.41421 2.75 6Z" fill="#ffffff"></path>
                                <path d="M5.91508 8.45011C5.88753 8.03681 5.53015 7.72411 5.11686 7.75166C4.70356 7.77921 4.39085 8.13659 4.41841 8.54989L4.88186 15.5016C4.96735 16.7844 5.03641 17.8205 5.19838 18.6336C5.36678 19.4789 5.6532 20.185 6.2448 20.7384C6.83639 21.2919 7.55994 21.5307 8.41459 21.6425C9.23663 21.75 10.2751 21.75 11.5607 21.75H12.4395C13.7251 21.75 14.7635 21.75 15.5856 21.6425C16.4402 21.5307 17.1638 21.2919 17.7554 20.7384C18.347 20.185 18.6334 19.4789 18.8018 18.6336C18.9637 17.8205 19.0328 16.7844 19.1183 15.5016L19.5818 8.54989C19.6093 8.13659 19.2966 7.77921 18.8833 7.75166C18.47 7.72411 18.1126 8.03681 18.0851 8.45011L17.6251 15.3492C17.5353 16.6971 17.4712 17.6349 17.3307 18.3405C17.1943 19.025 17.004 19.3873 16.7306 19.6431C16.4572 19.8988 16.083 20.0647 15.391 20.1552C14.6776 20.2485 13.7376 20.25 12.3868 20.25H11.6134C10.2626 20.25 9.32255 20.2485 8.60915 20.1552C7.91715 20.0647 7.54299 19.8988 7.26957 19.6431C6.99616 19.3873 6.80583 19.025 6.66948 18.3405C6.52891 17.6349 6.46488 16.6971 6.37503 15.3492L5.91508 8.45011Z" fill="#ffffff"></path>
                                <path d="M9.42546 10.2537C9.83762 10.2125 10.2051 10.5132 10.2464 10.9254L10.7464 15.9254C10.7876 16.3375 10.4869 16.7051 10.0747 16.7463C9.66256 16.7875 9.29502 16.4868 9.25381 16.0746L8.75381 11.0746C8.71259 10.6625 9.0133 10.2949 9.42546 10.2537Z" fill="#ffffff"></path>
                                <path d="M15.2464 11.0746C15.2876 10.6625 14.9869 10.2949 14.5747 10.2537C14.1626 10.2125 13.795 10.5132 13.7538 10.9254L13.2538 15.9254C13.2126 16.3375 13.5133 16.7051 13.9255 16.7463C14.3376 16.7875 14.7051 16.4868 14.7464 16.0746L15.2464 11.0746Z" fill="#ffffff"></path>
                            </svg>
                             </button>';

                    $btn .= '</div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.locations.index');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $location = Locations::create([
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Lokasi Berhasil Ditambahkan.',
                'data'    => $location
            ]);
        }

        return redirect()->route('locations.index')
            ->with('success', 'Lokasi Berhasil Ditambahkan.');
    }

    public function edit($id)
    {
        $location = Locations::findOrFail($id);
        return view('master.locations.edit', compact('location'));
    }

    public function update(Request $request, Locations $location)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $location->update([
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Lokasi Berhasil Diubah.',
                'data'    => $location
            ]);
        }

        return redirect()->route('locations.index')
            ->with('success', 'Lokasi Berhasil Diubah.');
    }

    public function select(Request $request)
    {
        $search = $request->q;

        $result = Locations::where('name', 'like', '%' . $search . '%')
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json($result);
    }

    public function destroy($id)
    {
        $location = Locations::findOrFail($id);
        $location->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Lokasi Berhasil Dihapus'
        ]);
    }
}