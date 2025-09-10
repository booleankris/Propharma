<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Models\Team;
use App\Models\SquadOfficial;

use Form;
use Image;
use DataTables;
use Carbon\Carbon;


class SquadOffcicialController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);

        if($request->ajax()) {
            $officials = SquadOfficial::select('id', 'team_id','photo', 'name', 'details')
                                ->where('team_id', $team->id);
            if($request->has('order') == false){
                $officials = $officials->orderBy('created_at', 'ASC');
            }

            return DataTables::of($officials)
                            ->addIndexColumn()
                            ->addColumn('preview', function($data) {
                                $preview = '<img src="'. $data->image_url .'" class="img-fluid" width="150px" alt="">';
                                return $preview;
                            })
                            ->addColumn('action', function($data) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">';
                                                    $button .= '<a class="btn btn-primary" href="'. route('teams.squadofficials.edit',  [$data->team_id, $data->id ]) .'">Edit</a>';
                                $button .= '</div>';
                                $button .= '<div class="btn-group m-1">';
                                    $button .= Form::button('Hapus', ['id' => 'button-delete-'. $data->id, 'class' => 'btn btn-danger', 'data-route' => route('teams.squadofficials.destroy', [$data->team_id, $data->id]) , 'onclick' => 'delete_data('. $data->id .')']);
                                $button .= '</div>';

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['preview, action'])
                            ->toJson();
        }

        return view('admin.squadofficials.index', compact('team'));
    }

    public function create($teamId)
    {
        $team = Team::findOrFail($teamId);

        return view('admin.squadofficials.create', compact('team'));
    }

    public function store(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);

        $this->validate($request, [
            'image'       => 'required|image|max:1024',
            'name'        => 'required',
            'information' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $fileName = null;
            if($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = 'Official' . '-' . time() . Str::random(4) .'.'. $image->extension();
                $request->image->move(public_path('uploads/official/'), $fileName);
            }

            $official = SquadOfficial::create([
                'team_id'          => $team->id,
                'name'             => $request->name,
                'photo'            => $fileName,
                'details'          => $request->information,
            ]);

            DB::commit();

            return redirect()->route('teams.squadofficials.index', [$team->id])
                        ->with('success','Data Official berhasil ditambah');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }
    }

    public function show(SquadOfficial $squadOfficial)
    {
        abort(404);
    }

    public function edit($teamId, $officialId)
    {
        $team      = Team::findOrFail($teamId);
        $official = SquadOfficial::FindOrFail($officialId);

        if($official->team_id == $team->id) {
            return view('admin.squadofficials.edit', compact('team','official'));
        }
        abort(404);
    }

    public function update(Request $request, $teamId, $officialId)
    {
        $team      = Team::findOrFail($teamId);
        $official = SquadOfficial::FindOrFail($officialId);

        if($official->team_id == $team->id) {

            $this->validate($request, [
                'image'   => 'nullable|image|max:1024',
                'name'    => 'required',
                'details' => 'required',
            ]);


            try {
                DB::beginTransaction();
    
                $fileName = $official->photo;
                if($request->hasFile('image')) {
                    $image = $request->file('image');
                    $fileName = 'Official' . '-' . time() . Str::random(4) .'.'. $image->extension();
                    $request->image->move(public_path('uploads/official/'), $fileName);

                    if (is_file(public_path('uploads/official/'.$official->photo))) {
                        unlink(public_path('uploads/official/'.$official->photo));
                    }
                }
                
                $official->update([
                    'name'             => $request->name,
                    'photo'            => $fileName,
                    'details'          => $request->details,
                ]);

                DB::commit();
    
                return redirect()->route('teams.squadofficials.index', [$team->id])
                            ->with('success','Data Official berhasil diupdate');
    
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
            }
        }
        abort(404);
    }

    public function destroy(Request $request, $teamId, $officialId)
    {
        $team      = Team::findOrFail($teamId);
        $official = SquadOfficial::FindOrFail($officialId);

        if($official->team_id == $team->id) {

            try {
                DB::beginTransaction();

                if (is_file(public_path('uploads/official/'.$official->photo))) {
                    unlink(public_path('uploads/official/'.$official->photo));
                }
                
                $official->delete();
               
                DB::commit();
    
                return response()->json(['status' => true, 'message' => 'Data Tim berhasil dihapus!']);
    
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => 'Gagal!']);

            }
            
        }

        return response()->json(['status' => false, 'message' => 'Gagal!']);
    }
}
