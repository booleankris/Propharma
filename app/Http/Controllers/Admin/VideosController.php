<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

use App\Models\Video;

use Form;
use Image;
use DataTables;
use Carbon\Carbon;

class VideosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index(Request $request)
    {
        if($request->ajax()) {
            $videos = Video::select('id','title', 'link', 'created_at');
            if($request->has('order') == false){
                $videos = $videos->orderBy('created_at', 'ASC');
            }

            return DataTables::of($videos)
                            ->addIndexColumn()
                            ->addColumn('action', function($video) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">';
                                            // if(Auth::user()->can('videos-edit')){
                                                    $button .= '<a class="btn btn-primary" href="'. route('videos.edit', $video->id) .'">Edit</a>';
                                            // }
                                $button .= '</div>';
                                // if(Auth::user()->can('videos-delete')):
                                    $button .= '<div class="btn-group m-1">';
                                        $button .= Form::button('Hapus', ['id' => 'button-delete-'. $video->id, 'class' => 'btn btn-danger', 'data-route' => route('videos.destroy', $video->id) , 'onclick' => 'delete_data('. $video->id .')']);
                                    $button .= '</div>';
                                // endif;

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['preview, ket, action'])
                            ->toJson();
        }

        return view('admin.videos.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.videos.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate($request, [
            'title' => 'required',
            'link'  => 'required|url',
        ]);

        $embedLink    = "https://www.youtube.com/embed/";
        $linkRaw      = Str::replaceFirst('https://youtu.be/','', $request->link);
        $embedLink   .= $linkRaw;

        Video::create([
            'title' => $request->title,
            'link'  => $embedLink
        ]);

        return redirect()->route('videos.index')
                        ->with('success','Video berhasil ditambah');
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Models\Video  $video
     * @return \Illuminate\Http\Response
     */
    public function show(Video $video)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Models\Video  $video
     * @return \Illuminate\Http\Response
     */
    public function edit(Video $video)
    {
        return view('admin.videos.edit', compact('video'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Video  $video
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, Video $video)
    {
        $this->validate($request, [
            'title'   => 'required',
            'link'   => 'required',
        ]);

        $embedLink    = "https://www.youtube.com/embed/";
        $linkRaw      = Str::replaceFirst('https://youtu.be/','', $request->link);
        $linkRaw      = Str::replaceFirst('https://www.youtube.com/embed/','', $linkRaw);
        $embedLink   .= $linkRaw;

        $video->update([
            'title' => $request->title,
            'link'  => $embedLink
        ]);

        return redirect()->route('videos.index')
                        ->with('success','Video berhasil ditambah');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Models\Video  $video
     * @return \Illuminate\Http\Response
     */
    public function destroy(Video $video)
    {
        $video->delete();
        return response()->json(['status' => true, 'message' => 'Video berhasil dihapus!']);
    }
}
