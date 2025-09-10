<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Matches;
use App\Models\MatchDay;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use DataTables;
use Form;

class MatchController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request, $matchDayId)
    {
        $matchDay = MatchDay::findOrFail($matchDayId);

        if($request->ajax()) {
            
            $matches = Matches::with(['homeTeam', 'awayTeam'])
                                ->select('id', 'team_home','team_away', 'time', 'created_at', 'match_day_id')
                                ->where('match_day_id', $matchDay->id);
            // dd($matches);
            if($request->has('order') == false){
                $matches = $matches->orderBy('created_at', 'ASC');
            }

            return DataTables::of($matches)
                            ->addIndexColumn()
                            // ->addColumn('preview', function($data) {
                            //     $preview = '<img src="'. $data->image_url .'" class="img-fluid" width="150px" alt="">';
                            //     return $preview;
                            // })
                            ->addColumn('action', function($data) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">';
                                                    $button .= '<a class="btn btn-primary" href="'. route('matchday.matches.edit',  [$data->match_day_id, $data->id ]) .'">Edit</a>';
                                $button .= '</div>';
                                $button .= '<div class="btn-group m-1">';
                                    $button .= Form::button('Hapus', ['id' => 'button-delete-'. $data->id, 'class' => 'btn btn-danger', 'data-route' => route('matchday.matches.destroy', [$data->match_day_id, $data->id]) , 'onclick' => 'delete_data('. $data->id .')']);
                                $button .= '</div>';

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['preview, action'])
                            ->toJson();
        }

        return view('admin.matches.index', compact('matchDay'));
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create($matchDayId)
    {
        $teams = Team::pluck('name', 'id');
        $matchDay = MatchDay::findOrFail($matchDayId);
        return view('admin.matches.create', compact('matchDay', 'teams'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request, $matchDayId)
    {
        $matchDay = MatchDay::findOrFail($matchDayId);

        $this->validate($request, [
            'team_home'    => 'required|exists:teams,id',
            'team_away'    => 'required|exists:teams,id|different:team_home',
            'time'         => 'required|date_format:H:i',
        ]);

        try {
            DB::beginTransaction();


            Matches::create([
                'match_day_id'  => $matchDay->id,
                'team_home'     => $request->team_home,
                'team_away'     => $request->team_away, 
                'time'          => $request->time
            ]);

            DB::commit();

            return redirect()->route('matchday.matches.index', [$matchDay->id])
                        ->with('success','Match berhasil ditambahkan');

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
    public function edit($matchDayId, $matchId)
    {
        $matchDay   = MatchDay::findOrFail($matchDayId);
        $match      = Matches::FindOrFail($matchId);
        $teams = Team::pluck('name', 'id');

        if($match->match_day_id == $matchDay->id) {
            return view('admin.matches.edit', compact('matchDay','match', 'teams'));
        }
        abort(404);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $matchDayId, $id)
    {
        $matchDay = MatchDay::findOrFail($matchDayId);
        $match = Matches::findOrFail($id);
        

        $this->validate($request, [
            'team_home'    => 'required|exists:teams,id',
            'team_away'    => 'required|exists:teams,id',
            // 'time'         => 'required|date_format:H:i',
        ]);

        try {
            DB::beginTransaction();


            Matches::findOrFail($id)->update([
                'match_day_id' => $matchDay->id,
                'team_home'  => $request->team_home,
                'team_away' => $request->team_away,
                'time' => $request->time
            ]);

            DB::commit();

            return redirect()->route('matchday.matches.index', [$matchDay->id])
                        ->with('success','Match berhasil diubah');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }

        
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($matchDayId, $id)
    {
        Matches::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Match berhasil dihapus!']);
    }
}
