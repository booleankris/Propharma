<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\MatchDay;
use Illuminate\Support\Facades\DB;
use DataTables;
use Form;

class MatchDayController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if($request->ajax()) {
            $matchDays = MatchDay::select('id','day', 'place', 'date');
            if($request->has('order') == false){
                $matchDays = $matchDays->orderBy('created_at', 'ASC');
            }

            return DataTables::of($matchDays)
                            ->addIndexColumn()
                            ->addColumn('action', function($matchDay) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">';
                                            // if(Auth::user()->can('videos-edit')){
                                                $button .= '<a class="btn btn-primary" href="'. route('matchday.matches.index', $matchDay->id) .'">Matches</a>';
                                                    
                                                    
                                            // }
                                $button .= '</div>';
                                $button .= '<div class="btn-group m-1">';
                                $button .= '<a class="btn btn-primary" href="'. route('matchday.edit', $matchDay->id) .'">Edit</a>';
                                    $button .= '</div>';
                                // if(Auth::user()->can('videos-delete')):
                                    $button .= '<div class="btn-group m-1">';
                                        $button .= Form::button('Hapus', ['id' => 'button-delete-'. $matchDay->id, 'class' => 'btn btn-danger', 'data-route' => route('matchday.destroy', $matchDay->id) , 'onclick' => 'delete_data('. $matchDay->id .')']);
                                    $button .= '</div>';
                                // endif;

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['preview, ket, action'])
                            ->toJson();
        }

        return view('admin.matchday.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.matchday.create');
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
            // 'image'       => 'required|image|max:1024',
            'day'        => 'required',
            'place'    => 'required',
            'date'      => 'required|date'
        ]);

        try {
            DB::beginTransaction();



            MatchDay::create([
                'day'       => $request->day,
                'place'          => $request->place,
                'date'        => $request->date
            ]);

            DB::commit();

            return redirect()->route('matchday.index')
                    ->with('success','Data Matchday berhasil ditambah');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $matchDay = MatchDay::findOrFail($id);

        return view('admin.matchday.edit', compact('matchDay'));
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        
        $this->validate($request, [
            'day'        => 'required',
            'place'    => 'required',
            'date'      => 'required|date'
        ]);

        MatchDay::findOrFail($id)->update([
            'day' => $request->day,
            'place'  => $request->place,
            'date' => $request->date
        ]);

        return redirect()->route('matchday.index')
                        ->with('success','Matchday berhasil diubah');
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        
        MatchDay::findOrFail($id)->delete();
        Matches::where('match_day_id', $id)->delete();
        return response()->json(['status' => true, 'message' => 'MatchDay & Match berhasil dihapus!']);
    }
}
