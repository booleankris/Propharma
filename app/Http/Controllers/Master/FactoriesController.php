<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Factory;
use Illuminate\Http\Request;
use DataTables;
use Form;

class FactoriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $factories = Factory::select('id', 'code', 'name', 'status', 'created_at');

            if (!$request->has('order')) {
                $factories = $factories->orderBy('created_at', 'ASC');
            }

            return DataTables::of($factories)
                ->addIndexColumn()
                ->addColumn('status_label', function ($factory) {
                    return $factory->status == 0
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($factory) {
                    $btn = '<div class="btn-toolbar" role="toolbar">
                                <div class="btn-group m-1 mr-2" role="group">';

                    $btn .= '<a class="btn btn-primary btn-sm" href="' . route('factories.edit', $factory->id) . '">Edit</a>';

                    $btn .= '</div><div class="btn-group m-1" role="group">';

                    $btn .= Form::button('Delete', [
                        'id'         => 'button_delete_' . $factory->id,
                        'class'      => 'btn btn-danger btn-sm',
                        'data-route' => route('factories.destroy', $factory->id),
                        'onclick'    => 'delete_data(' . $factory->id . ')'
                    ]);

                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.factories.index');
    }

    public function create()
    {
        return view('master.factories.create');
    }

    private function generateFactoryCode()
    {
        $last = Factory::orderBy('id', 'desc')->first();

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
        $code = $this->generateFactoryCode();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $factory = Factory::create([
            'code'   => $code,
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Factory successfully saved.',
                'data'    => $factory
            ]);
        }

        return redirect()->route('factories.index')
            ->with('success', 'Factory successfully saved.');
    }

    public function edit($id)
    {
        $factory = Factory::findOrFail($id);
        return view('master.factories.edit', compact('factory'));
    }

    public function update(Request $request, Factory $factory)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $factory->update([
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Factory successfully updated.',
                'data'    => $factory
            ]);
        }

        return redirect()->route('factories.index')
            ->with('success', 'Factory successfully updated.');
    }
    public function select(Request $request)
    {
        $search = $request->q;

        $result = Factory::where('name', 'like', '%' . $search . '%')
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json($result);
    }
    public function destroy($id)
    {
        $factory = Factory::findOrFail($id);
        $factory->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Factory successfully deleted!'
        ]);
    }
}
