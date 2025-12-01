<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Composition;
use Illuminate\Http\Request;
use DataTables;
use Form;

class CompositionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $compositions = Composition::select('id', 'code', 'name', 'status', 'created_at');

            if (!$request->has('order')) {
                $compositions = $compositions->orderBy('created_at', 'ASC');
            }

            return DataTables::of($compositions)
                ->addIndexColumn()
                ->addColumn('status_label', function ($composition) {
                    return $composition->status == 0
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($composition) {
                    $btn = '<div class="btn-toolbar" role="toolbar">
                                <div class="btn-group m-1 mr-2" role="group">';

                    $btn .= '<a class="btn btn-primary btn-sm" href="' . route('compositions.edit', $composition->id) . '">Edit</a>';

                    $btn .= '</div><div class="btn-group m-1" role="group">';

                    $btn .= Form::button('Delete', [
                        'id'         => 'button_delete_' . $composition->id,
                        'class'      => 'btn btn-danger btn-sm',
                        'data-route' => route('compositions.destroy', $composition->id),
                        'onclick'    => 'delete_data(' . $composition->id . ')'
                    ]);

                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.compositions.index');
    }

    public function create()
    {
        return view('master.compositions.create');
    }

    private function generateCompositionCode()
    {
        $last = Composition::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return '0001';
        }

        // Convert last code into an integer
        $number = (int) $last->code;

        // Increment
        $next = $number + 1;

        // Return padded code (always 4 digits)
        return str_pad($next, 4, '0', STR_PAD_LEFT);
    }
    public function store(Request $request)
    {
        $code = $this->generateCompositionCode();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $composition = Composition::create([
            'code'   => $code,
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Composition successfully saved.',
                'data'    => $composition
            ]);
        }

        return redirect()->route('compositions.index')
            ->with('success', 'Composition successfully saved.');
    }

    public function edit($id)
    {
        $composition = Composition::findOrFail($id);
        return view('master.compositions.edit', compact('composition'));
    }

    public function update(Request $request, Composition $composition)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $composition->update([
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Composition successfully updated.',
                'data'    => $composition
            ]);
        }

        return redirect()->route('compositions.index')
            ->with('success', 'Composition successfully updated.');
    }
    public function select(Request $request)
    {
        $search = $request->q;

        $result = Composition::where('name', 'like', '%' . $search . '%')
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json($result);
    }
    public function destroy($id)
    {
        $composition = Composition::findOrFail($id);
        $composition->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Composition successfully deleted!'
        ]);
    }
}
