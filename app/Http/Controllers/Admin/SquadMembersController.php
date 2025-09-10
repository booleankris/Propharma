<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

use App\Models\SquadOfficial;
use App\Models\SquadMember;
use App\Models\Team;

use Form;
use Image;
use DataTables;
use Carbon\Carbon;

class SquadMembersController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);

        if($request->ajax()) {
            $officials = SquadMember::select('id', 'team_id','photo', 'name', 'position', 'number')
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
                                                    $button .= '<a class="btn btn-primary" href="'. route('teams.squadmembers.edit',  [$data->team_id, $data->id ]) .'">Edit</a>';
                                $button .= '</div>';
                                $button .= '<div class="btn-group m-1">';
                                    $button .= Form::button('Hapus', ['id' => 'button-delete-'. $data->id, 'class' => 'btn btn-danger', 'data-route' => route('teams.squadmembers.destroy', [$data->team_id, $data->id]) , 'onclick' => 'delete_data('. $data->id .')']);
                                $button .= '</div>';

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['preview, action'])
                            ->toJson();
        }

        return view('admin.squadmembers.index', compact('team'));
    }

    public function create($teamId)
    {
        $team = Team::findOrFail($teamId);

        return view('admin.squadmembers.create', compact('team'));
    }

    public function store(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);

        $this->validate($request, [
            'image'       => 'required|image|max:1024',
            'name'        => 'required',
            'position'    => 'required',
            'number'      => 'required',
            'dateofbirth' => 'required',
            'file'        => 'required|image|max:1024',
        ]);

        try {
            DB::beginTransaction();

            $fileName = null;
            if($request->hasFile('image')) {
                $image        = $request->file('image');
                $fileName     = 'Squad' . '-' . time() . Str::random(4) .'.'. $image->extension();
                $request->image->move(public_path('uploads/squad/'), $fileName);
            }

            $identityCard     = null;
            if($request->hasFile('file')) {
                $image        = $request->file('file');
                $identityCard = 'Identity' . '-' . time() . Str::random(4) .'.'. $image->extension();
                $request->file->move(public_path('uploads/squad/identitycard/'), $identityCard);
            }

            SquadMember::create([
                'team_id'       => $team->id,
                'name'          => $request->name,
                'photo'         => $fileName,
                'number'        => $request->number, 
                'position'      => $request->position,
                'dateofbirth'   => $request->dateofbirth,
                'identity_card' => $identityCard
            ]);

            DB::commit();

            return redirect()->route('teams.squadmembers.index', [$team->id])
                        ->with('success','Data Pemain berhasil ditambah');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }
    }

    public function show(SquadMember $squadMember)
    {
        //
    }

    public function edit($teamId, $memberId)
    {
        $team   = Team::findOrFail($teamId);
        $member = SquadMember::FindOrFail($memberId);

        if($member->team_id == $team->id) {
            return view('admin.squadmembers.edit', compact('team','member'));
        }
        abort(404);
    }

    public function update(Request $request, $teamId, $memberId)
    {
        $team = Team::findOrFail($teamId);

        $this->validate($request, [
            'image'       => 'nullable|image|max:1024',
            'name'        => 'required',
            'position'    => 'required',
            'number'      => 'required',
            'dateofbirth' => 'required',
            'file'        => 'nullable|image|max:1024',
        ]);

        $member = SquadMember::FindOrFail($memberId);

        if($member->team_id == $team->id) {
            
            try {
                DB::beginTransaction();
    
                $fileName = $member->photo;
                if($request->hasFile('image')) {
                    $image        = $request->file('image');
                    $fileName     = 'Squad' . '-' . time() . Str::random(4) .'.'. $image->extension();
                    $request->image->move(public_path('uploads/squad/'), $fileName);
    
                    if (is_file(public_path('uploads/squad/'.$member->photo))) {
                        unlink(public_path('uploads/squad/'.$member->photo));
                    }
                }
    
                $identityCard     = $member->identity_card;
                if($request->hasFile('file')) {
                    $image        = $request->file('file');
                    $identityCard = 'Identity' . '-' . time() . Str::random(4) .'.'. $image->extension();
                    $request->file->move(public_path('uploads/squad/identitycard/'), $identityCard);
    
                    if (is_file(public_path('uploads/squad/identitycard/'.$member->identity_card))) {
                        unlink(public_path('uploads/squad/identitycard/'.$member->identity_card));
                    }
                }
    
                $member->update([
                    'name'          => $request->name,
                    'photo'         => $fileName,
                    'number'        => $request->number, 
                    'position'      => $request->position,
                    'dateofbirth'   => $request->dateofbirth,
                    'identity_card' => $identityCard
                ]);
    
                DB::commit();
    
                return redirect()->route('teams.squadmembers.index', [$team->id])
                            ->with('success','Data Pemain berhasil diedit');
    
            } catch (\Exception $e) {
                DB::rollBack();
                return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
            }
        }
    }

    public function destroy(Request $request, $teamId, $memberId)
    {
        $team = Team::findOrFail($teamId);
        $member = SquadMember::FindOrFail($memberId);
        
        if($member->team_id == $team->id) {

            try {
                DB::beginTransaction();

                if (is_file(public_path('uploads/squad/'.$member->photo))) {
                    unlink(public_path('uploads/squad/'.$member->photo));
                }

                if (is_file(public_path('uploads/squad/identitycard/'.$member->identity_card))) {
                    unlink(public_path('uploads/squad/identitycard/'.$member->identity_card));
                }
                
                $member->delete();
               
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
