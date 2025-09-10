<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;

use App\Models\User;
use App\Models\Article;

use Form;
use Image;
use DataTables;
use Carbon\Carbon;

class ArticlesController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }
    
    public function index(Request $request)
    {
        if($request->ajax()) {
            $articles = Article::select(['id', 'title', 'created_at']);
            if($request->has('order') == false){
                $articles = $articles->orderBy('title', 'ASC');
            }

            return DataTables::of($articles)
                            ->addIndexColumn()
                            ->filter(function ($query) use ($request) {
                                if ($request->has('name')) {
                                    $query->where('title', 'like', "%{$request->get('name')}%");
                                }
                            })
                            ->addColumn('date', function($article) {
                                $date = Carbon::parse($article->created_at)->translatedFormat('l, d F Y');
                                return $date;
                            })
                            ->addColumn('action', function($article) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">
                                            <a class="btn btn-outline-primary" href="'. route('articles.show', $article->id) .'">Detail</a>';
                                            // if(Auth::user()->can('articles-edit')){
                                                    $button .= '<a class="btn btn-primary" href="'. route('articles.edit', $article->id) .'">Edit</a>';
                                            // }
                                $button .= '</div>';
                                // if(Auth::user()->can('articles-delete')):
                                    $button .= '<div class="btn-group m-1">';
                                        $button .= Form::button('Hapus', ['id' => 'button-delete-'. $article->id, 'class' => 'btn btn-danger', 'data-route' => route('articles.destroy', $article->id) , 'onclick' => 'delete_data('. $article->id .')']);
                                    $button .= '</div>';
                                // endif;

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['date, action'])
                            ->toJson();
        }
        return view('admin.articles.index');
    }

    
    public function create()
    {
        return view('admin.articles.create');
    }

   
    public function store(Request $request)
    {
        $this->validate($request, [
            'title'   => 'required',
            'image'   => 'required|image|max:1024',
            'content' => 'required'
        ]);

        $fileName = null;
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'Thumbnail' . '-' . time() . Str::random(4) .'.'. $image->extension();
            $request->image->move(public_path('uploads/articles/'), $fileName);
        }

        Article::create([
            'title'          => $request->title, 
            'slug'           => Str::slug($request->title),
            'thumbnail'      => $fileName, 
            'thumbnail_info' => $request->image_info, 
            'content'        => $request->content
        ]);

        return redirect()->route('articles.index')
                        ->with('success','Artikel berhasil ditambah');
    }

    
    public function show($id)
    {
        $article = Article::findOrFail($id);
        return view('admin.articles.show', compact('article'));
    }

    
    public function edit($id)
    {
        $article = Article::findOrFail($id);
        return view('admin.articles.edit', compact('article'));
    }

    
    public function update(Request $request, $id)
    {
        $this->validate($request, [
            'title'   => 'required',
            'content' => 'required'
        ]);

        $article = Article::findOrFail($id);

        $fileName = $article->thumbnail;
        if($request->hasFile('image')) {
            $image = $request->file('image');
            $fileName = 'Thumbnail' . '-' . time() . Str::random(4) .'.'. $image->extension();
            $request->image->move(public_path('uploads/articles/'), $fileName);
            if (is_file(public_path('uploads/articles/'.$article->thumbnail))) {
                unlink(public_path('uploads/articles/'.$article->thumbnail));
            }
        }

        $article->update([
            'title'          => $request->title, 
            'slug'           => Str::slug($request->title),
            'thumbnail'      => $fileName, 
            'thumbnail_info' => $request->thumbnail_info, 
            'content'        => $request->content
        ]);

        return redirect()->route('articles.index')
                        ->with('success','Artikel berhasil diupdate');
    }

    
    public function destroy($id)
    {
        $article = Article::findOrFail($id);
        if($article->thumbnail){
            if (is_file(public_path('uploads/articles/'.$article->thumbnail))) {
                unlink(public_path('uploads/articles/'.$article->thumbnail));
            }
        }
        $article->delete();
        return response()->json(['status' => true, 'message' => 'Artikel berhasil dihapus!']);
    }
}
