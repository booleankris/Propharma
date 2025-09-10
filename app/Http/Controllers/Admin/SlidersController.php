<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

use App\Models\Slider;

use Form;
use Image;
use DataTables;
use Carbon\Carbon;

class SlidersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index(Request $request)
    {
        if($request->ajax()) {
            $sliders = Slider::select('id','image', 'alt', 'link', 'created_at');
            if($request->has('order') == false){
                $sliders = $sliders->orderBy('created_at', 'ASC');
            }

            return DataTables::of($sliders)
                            ->addIndexColumn()
                            ->addColumn('preview', function($slider) {
                                $preview = '<img src="'. asset('uploads/sliders/'. $slider->image) .'" class="img-fluid" width="350px" alt="">';
                                return $preview;
                            })
                            ->addColumn('ket', function($slider) {
                                $ket = '<strong> Alt : </strong>'. $slider->alt .'<br>';
                                $ket .= '<strong> Link : </strong>';
                                $ket .=  ($slider->link) ? $slider->link : "tidak ada";
                                return $ket;
                            })
                            ->addColumn('action', function($slider) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">';
                                            // if(Auth::user()->can('sliders-edit')){
                                                    $button .= '<a class="btn btn-primary" href="'. route('sliders.edit', $slider->id) .'">Edit</a>';
                                            // }
                                $button .= '</div>';
                                // if(Auth::user()->can('sliders-delete')):
                                    $button .= '<div class="btn-group m-1">';
                                        $button .= Form::button('Hapus', ['id' => 'button-delete-'. $slider->id, 'class' => 'btn btn-danger', 'data-route' => route('sliders.destroy', $slider->id) , 'onclick' => 'delete_data('. $slider->id .')']);
                                    $button .= '</div>';
                                // endif;

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['preview, ket, action'])
                            ->toJson();
        }

        return view('admin.sliders.index');
    }

    public function create()
    {
        return view('admin.sliders.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'image'   => 'required|image|max:1024',
        ]);

        $fileName = null;
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'Sliders' . '-' . time() . Str::random(4) .'.'. $image->extension();
            $request->image->move(public_path('uploads/sliders/'), $fileName);
        }

        Slider::create([
            'image' => $fileName, 
            'link'  => $request->link,
            'alt'   => $request->alt, 
        ]);

        return redirect()->route('sliders.index')
                        ->with('success','Slider berhasil ditambah');
    }

    public function show(Slider $slider)
    {
        //
    }

    public function edit(Slider $slider)
    {
        return view('admin.sliders.edit', compact('slider'));
    }

    public function update(Request $request, Slider $slider)
    {
        $fileName = $slider->image;
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'Sliders' . '-' . time() . Str::random(4) .'.'. $image->extension();
            $request->image->move(public_path('uploads/sliders/'), $fileName);
            if (is_file(public_path('uploads/sliders/'.$slider->image))) {
                unlink(public_path('uploads/sliders/'.$slider->image));
            }
        }

        $slider->update([
            'image' => $fileName, 
            'link'  => $request->link,
            'alt'   => $request->alt, 
        ]);

        return redirect()->route('sliders.index')
                        ->with('success','Slider berhasil diupdate');
    }

    public function destroy(Slider $slider)
    {
        if($slider->image){
            if (is_file(public_path('uploads/sliders/'.$slider->image))) {
                unlink(public_path('uploads/sliders/'.$slider->image));
            }
        }
        $slider->delete();
        return response()->json(['status' => true, 'message' => 'Artikel berhasil dihapus!']);
    }
}
