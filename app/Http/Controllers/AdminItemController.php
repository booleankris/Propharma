<?php

namespace App\Http\Controllers;

use App\Models\Item;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Form;
use Image;
use DataTables;

class AdminItemController extends Controller
{
    public function index(Request $request)
    {
        if ($request->ajax()) {
            $items = Item::with('user')->select(['id', 'user_id', 'item_photo', 'item_name', 'item_desc', 'item_price']);

            if (!$request->has('order')) {
                $items = $items->orderBy('item_name', 'ASC');
            }

            return DataTables::of($items)
                ->addIndexColumn()
                ->filter(function ($query) use ($request) {
                    if ($request->has('item_name')) {
                        $query->where('item_name', 'like', "%{$request->get('item_name')}%");
                    }

                    if ($request->has('item_price')) {
                        $query->where('item_price', $request->get('item_price'));
                    }
                })
                ->addColumn('users', function ($item) {
                    return optional($item->user)->name ?? '-';
                })
                ->addColumn('item_photo', function ($item) {
                    return '<img src="' . asset('uploads/items/' . $item->item_photo) . '" alt="item" class="img-thumbnail" width="60">';
                })
                ->addColumn('action', function ($item) {
                    $button = '<div class="btn-toolbar" role="toolbar">
                            <div class="btn-group m-1 mr-2" role="group">
                            <a class="btn btn-outline-primary" href="' . route('adminitems.show', $item->id) . '">Detail</a>';

                    if (Auth::user()->hasRole('administrator')) {
                        $button .= '<a class="btn btn-primary" href="' . route('adminitems.edit', $item->id) . '">Edit</a>';
                        $button .= '<div class="btn-group m-1">';
                        $button .= Form::button('Delete', [
                            'id' => 'button-delete-' . $item->id,
                            'class' => 'btn btn-danger',
                            'data-route' => route('adminitems.destroy', $item->id),
                            'onclick' => 'delete_data(' . $item->id . ')'
                        ]);
                        $button .= '</div>';
                    }

                    $button .= '</div>';
                    return $button;
                })
                ->rawColumns(['item_photo', 'action'])
                ->toJson();
        }

        return view('admin.adminitems.index');
    }
    public function create()
    {
        $user_id = User::role('UMKM')->get();
        return view('admin.adminitems.create', compact('user_id'));
    }
    public function store(Request $request)
    {
        $this->validate($request, [
            'user_id'      => 'required',
            'item_name'    => 'required',
            'item_desc'    => 'required',
            'image'        => 'image|mimes:jpg,jpeg,png|max:5024',
            'item_price'   => 'required',
        ]);
        $fileName = null;
        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'items' . '-' . time() . Str::random(4) . '.' . $image->extension();
            $image->move(public_path('uploads/items/'), $fileName); // Move the actual file
        } else {
            $fileName = 'default.png';
        }
        try {
            DB::beginTransaction();
            $item = Item::create([
                'user_id'      => $request->user_id,
                'item_name'    => $request->item_name,
                'item_desc'    => $request->item_desc,
                'item_photo'   => $fileName,
                'item_price'   => $request->item_price,
                'item_status'  => 0,
            ]);
            DB::commit();

            return redirect()->route('adminitems.index')
                ->with('success', 'Produk berhasil ditambahkan');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menambahkan Produk " . $e->getMessage());
        }
    }
    public function edit($id)
    {
        $item = Item::findOrFail($id);
        $user_id = User::role('UMKM')->get();

        return view('admin.adminitems.edit', compact('item', 'user_id'));
    }
    public function update(Request $request, $id)
    {
        $request->validate([
            'user_id' => 'required',
            'item_name' => 'required|string|max:255',
            'item_desc' => 'required|string',
            'item_price' => 'required|numeric|min:0',
            'item_photo' => 'nullable|image|max:2048',
        ]);

        $item = Item::findOrFail($id);
        $item->user_id = $request->user_id;
        $item->item_name = $request->item_name;
        $item->item_desc = $request->item_desc;
        $item->item_price = $request->item_price;
        $item->item_status = $request->item_status;

        if ($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'items-' . time() . Str::random(4) . '.' . $image->extension();

            // Delete old image if it exists
            $oldImagePath = public_path('uploads/items/' . $item->item_photo);
            if (File::exists($oldImagePath)) {
                File::delete($oldImagePath);
            }

            // Save new image
            $image->move(public_path('uploads/items/'), $fileName);

            // Update image path
            $item->item_photo = $fileName;
        } else {
            $fileName = 'default.png';
            $item->item_photo = $fileName;
        }

        $item->save();

        return redirect()->route('adminitems.index')->with('success', 'Item updated successfully.');
    }

    public function destroy($id)
    {
        $item = Item::find($id);
        if (!$item) {
            return response()->json(['status' => false, 'message' => 'Produk tidak ditemukan!']);
        }

        try {
            // Optionally delete file:
            if ($item->item_photo !== 'default.png') {
                $filePath = public_path('uploads/items/' . $item->item_photo);
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $item->delete();

            return response()->json(['status' => true, 'message' => 'Produk berhasil dihapus!']);
        } catch (\Exception $e) {
            return response()->json(['status' => false, 'message' => 'Gagal menghapus produk!']);
        }
    }
}
