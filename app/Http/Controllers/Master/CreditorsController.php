<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;

use App\Models\Creditor;
use Illuminate\Http\Request;
use DataTables;
use Form;
use Image;
use Carbon\Carbon;

class CreditorsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $creditors = Creditor::select('id', 'code', 'name', 'address', 'city', 'phone', 'email', 'status', 'created_at');

            if (!$request->has('order')) {
                $creditors = $creditors->orderBy('created_at', 'ASC');
            }

            return DataTables::of($creditors)
                ->addIndexColumn()
                ->addColumn('status_label', function ($creditor) {
                    if ($creditor->status == 1) {
                        return '<span class="badge bg-success">Active</span>';
                    }
                    return '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($creditor) {
                    $button = '<div class="btn-toolbar" role="toolbar">
                                <div class="btn-group m-1 mr-2" role="group">';
                    $button .= '<a class="btn btn-primary btn-sm" href="' . route('creditors.edit', $creditor->id) . '">Edit</a>';
                    $button .= '</div>
                                <div class="btn-group m-1" role="group">';
                    $button .= Form::button('Delete', [
                        'id' => 'button-delete-' . $creditor->id,
                        'class' => 'btn btn-danger btn-sm',
                        'data-route' => route('creditors.destroy', $creditor->id),
                        'onclick' => 'delete_data(' . $creditor->id . ')'
                    ]);
                    $button .= '</div></div>';
                    return $button;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.creditors.index');
    }

    public function create()
    {
        return view('master.creditors.create');
    }

    private function generateCreditorCode()
    {
        $last = Creditor::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return 'CR0001';
        }

        // Extract number part from code (e.g., CR0007 → 7)
        $number = (int) substr($last->code, 2);
        $nextNumber = $number + 1;

        return 'CR' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
    }
    public function store(Request $request)
    {
        $nextCode = $this->generateCreditorCode();

        $this->validate($request, [
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
            'contact' => 'nullable|string|max:100',
            'email'   => 'nullable|email|max:255',
        ]);

        $creditor = Creditor::create([
            'code'    => $nextCode,
            'name'    => $request->name,
            'address' => $request->address,
            'city'    => $request->city,
            'phone'   => $request->phone,
            'contact' => $request->contact,
            'email'   => $request->email,
            'status'  => $request->status ?? 0,
        ]);

        // ✅ Always return JSON when it's an AJAX request
        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data kreditur berhasil disimpan.',
                'data' => $creditor
            ]);
        }

        // fallback for normal form submission
        return redirect()->route('creditors.index')
            ->with('success', 'Data kreditur berhasil disimpan.');
    }



    public function show($id)
    {
        abort(404);
    }

    public function edit($id)
    {
        $creditor = Creditor::findOrFail($id);
        return view('master.creditors.edit', compact('creditor'));
    }

    public function update(Request $request, Creditor $creditor)
    {
        $validated = $request->validate([
            'name'    => 'required|string|max:255',
            'address' => 'nullable|string|max:255',
            'city'    => 'nullable|string|max:100',
            'phone'   => 'nullable|string|max:50',
            'contact' => 'nullable|string|max:100',
            'email'   => 'nullable|email|max:255',
        ]);

        $creditor->update([
            'name'    => $validated['name'],
            'address' => $validated['address'] ?? null,
            'city'    => $validated['city'] ?? null,
            'phone'   => $validated['phone'] ?? null,
            'contact' => $validated['contact'] ?? null,
            'email'   => $validated['email'] ?? null,
            'status'  => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Data kreditur berhasil diperbarui.',
                'data' => $creditor
            ]);
        }

        return redirect()->route('creditors.index')
            ->with('success', 'Data kreditur berhasil diperbarui.');
    }
    public function select(Request $request)
    {
        $search = $request->q;

        return Creditor::when($search, function ($query) use ($search) {
            $query->where('name', 'like', "%{$search}%");
        })
            ->orderBy('name', 'ASC')
            ->limit(20)
            ->get(['id', 'name']);
    }

    public function destroy($id)
    {
        $creditor = Creditor::findOrFail($id);
        $creditor->delete();

        return response()->json(['status' => true, 'message' => 'Creditor successfully deleted!']);
    }
}
