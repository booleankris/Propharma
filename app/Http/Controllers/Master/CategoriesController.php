<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\MedicineCategory;
use Illuminate\Http\Request;
use DataTables;
use Form;

class CategoriesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if ($request->ajax()) {
            $categories = MedicineCategory::select('id', 'code', 'name', 'status', 'created_at');

            if (!$request->has('order')) {
                $categories = $categories->orderBy('created_at', 'ASC');
            }

            return DataTables::of($categories)
                ->addIndexColumn()
                ->addColumn('status_label', function ($category) {
                    return $category->status == 0
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($category) {
                    $btn = '<div class="btn-toolbar" role="toolbar">
                                <div class="btn-group m-1 mr-2" role="group">';

                    $btn .= '<a class="btn btn-primary btn-sm" href="' . route('categories.edit', $category->id) . '">Edit</a>';

                    $btn .= '</div><div class="btn-group m-1" role="group">';

                    $btn .= Form::button('Delete', [
                        'id'         => 'button_delete_' . $category->id,
                        'class'      => 'btn btn-danger btn-sm',
                        'data-route' => route('categories.destroy', $category->id),
                        'onclick'    => 'delete_data(' . $category->id . ')'
                    ]);

                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        return view('master.categories.index');
    }

    public function create()
    {
        return view('master.categories.create');
    }

    /**
     * Generate dynamic category code
     * Starts with 2 digits (00), but expands if needed.
     */
    private function generateCategoryCode()
    {
        $last = MedicineCategory::orderBy('id', 'desc')->first();

        // No previous data → return "00"
        if (!$last || !$last->code) {
            return '00';
        }

        $number = (int) $last->code;
        $next   = $number + 1;

        // Determine padding length (minimum 2 digits)
        $length = max(strlen($last->code), 2);

        return str_pad($next, $length, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        $code = $this->generateCategoryCode();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category = MedicineCategory::create([
            'code'   => $code,
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Category successfully saved.',
                'data'    => $category
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Category successfully saved.');
    }

    public function edit($id)
    {
        $category = MedicineCategory::findOrFail($id);
        return view('master.categories.edit', compact('category'));
    }

    public function update(Request $request, MedicineCategory $category)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
        ]);

        $category->update([
            'name'   => $validated['name'],
            'status' => $request->status ?? 0,
        ]);

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Category successfully updated.',
                'data'    => $category
            ]);
        }

        return redirect()->route('categories.index')
            ->with('success', 'Category successfully updated.');
    }
    public function select(Request $request)
    {
        $search = $request->q;

        $result = MedicineCategory::where('name', 'like', '%' . $search . '%')
            ->select('id', 'name')
            ->limit(20)
            ->get();

        return response()->json($result);
    }
    public function destroy($id)
    {
        $category = MedicineCategory::findOrFail($id);
        $category->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Category successfully deleted!'
        ]);
    }
}
