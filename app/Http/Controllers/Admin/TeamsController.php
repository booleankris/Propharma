<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use App\Http\Controllers\Controller;

use App\Models\Team;
use App\Models\SquadMember;
use App\Models\SquadOfficial;
use App\Models\TeamPayment;
use Barryvdh\DomPDF\Facade\Pdf; 
use App\Mail\DownPaymentTeam;
use App\Mail\InformationTeams;

use Form;
use Image;
use DataTables;
use Carbon\Carbon;

class TeamsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        if($request->ajax()) {
            $teams = Team::with(['downpayment','repayment'])
                    ->select('id','name', 'status', 'manager_name', 'manager_email', 'manager_phone', 'created_at');
            if($request->has('order') == false){
                $teams = $teams->orderBy('created_at', 'ASC');
            }

            return DataTables::of($teams)
                            ->addIndexColumn()
                            ->filter(function ($query) use ($request) {
                                if ($request->has('name')) {
                                    $query->where('name', 'like', "%{$request->get('name')}%");
                                }
                                if ($request->has('manager')) {
                                    $query->where('manager_name', 'like', "%{$request->get('manager')}%")
                                        ->orWhere('manager_email', 'like', "%{$request->get('manager')}%");
                                }
                            })
                            ->addColumn('manager', function($data) {
                                $manager = '<strong> Nama : </strong>'. $data->manager_name .'<br>';
                                $manager .= 'Email : '. $data->manager_email .'<br>';
                                $manager .= 'No HP : '. $data->manager_phone .'<br>';
                                return $manager;
                            })
                            ->addColumn('info', function($data) {
                                $info = '';
                                if($data->downpayment){
                                    if($data->downpayment->status == "SETTLEMENT") {
                                        $info .= '<p class="badge badge-success">DP <i class="fas fa-check"></i></p>';
                                        if($data->repayment) {
                                            if($data->repayment->status == "SETTLEMENT") {
                                                $info .= ' <br/> <p class="badge badge-success">Pelunasan <i class="fas fa-check"></i></p>';
                                            } else {
                                                $info .= ' <br/> <p class="badge badge-warning">Pelunasan <i class="fas fa-times text-danger"></i></p>';
                                            }
                                        } else {
                                            $info .= ' <br/> <p class="badge badge-info">Pelunasan <i class="fas fa-hourglass-half"></i></p>';
                                        }
                                    } else {
                                        $info .= '<p class="badge badge-warning">DP <i class="fas fa-times text-danger"></i></p>';
                                    }
                                } else {
                                    $info .= ' <br/> <p class="badge badge-info">DP <i class="fas fa-hourglass-half"></i></p>';
                                }
                                return $info;
                            })
                            ->addColumn('action', function($data) {
                                $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">
                                             <a class="btn btn-outline-primary" href="'. route('teams.show', $data->id) .'">Detail</a>';
                                    $button .= '<a class="btn btn-primary" href="'. route('teams.edit', $data->id) .'">Edit</a>';
                                $button .= '</div>';

                                $button .= '<div class="btn-group m-1">';
                                $button .= '<a class="btn btn-info" href="'. route('teams.squadofficials.index', $data->id) .'">Data Official</a>';
                                $button .= '</div>';

                                $button .= '<div class="btn-group m-1">';
                                $button .= '<a class="btn btn-success" href="'. route('teams.squadmembers.index', $data->id) .'">Data Pemain</a>';
                                $button .= '</div>';

                                $button .= '<div class="btn-group m-1">';
                                    $button .= Form::button('Email Manajer', ['id' => 'button-delete-'. $data->id, 'class' => 'btn btn-warning', 'data-route' => route('teams.destroy', $data->id) , 'onclick' => 'delete_data('. $data->id .')']);
                                $button .= '</div>';

                                $button .= '<div class="btn-group m-1">';
                                    $button .= Form::button('Delete', ['id' => 'button-delete-real-'. $data->id, 'class' => 'btn btn-danger', 'data-route' => route('teams.delete', $data->id) , 'onclick' => 'delete_team('. $data->id .')']);
                                $button .= '</div>';

                                $button .= '</div>';
                                return $button;
                            })
                            ->escapeColumns(['manager, info, action'])
                            ->toJson();
        }

        return view('admin.teams.index');
    }

    
    public function create()
    {
        return view('admin.teams.create');
    }

    public function store(Request $request)
    {
        $this->validate($request, [
            'image'         => 'image|max:1024',
            'name'          => 'required',
            'manager_name'  => 'required',
            'manager_email' => 'required',
            'manager_phone' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $fileName = null;
            if($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = 'Teams' . '-' . time() . Str::random(4) .'.'. $image->extension();
                $request->image->move(public_path('uploads/teams/'), $fileName);
            }

            $team = Team::create([
                'name'          => $request->name,
                'slug'          => Str::slug($request->name),
                'code'          => Str::upper(Str::random(4)),
                'logo'          => $fileName, 
                'manager_name'  => $request->manager_name,
                'manager_email' => $request->manager_email,
                'manager_phone' => $request->manager_phone,
            ]);

            $code   = 'WKC25-'. rand(1000, 9999);
            $amount = 1000000;

            $payment = TeamPayment::create([
                'team_id'        => $team->id,
                'type'           => 'DP',
                'payment_code'   => $code,
                'total_price'    => $amount,
                'status'         => 'PENDING',
                'status_payment' => 'pending',
            ]);

            $payload = [
                'transaction_details' => [
                    'order_id'      => $code,
                    'gross_amount'  => $amount,
                ],
                'item_details' => [
                    [
                        'id'       => 'team-registration',
                        'price'    => $amount,
                        'quantity' => 1,
                        'name'     => 'Pendaftaran Tim Basket'
                    ]
                ],
                'customer_details' =>
                [
                    'first_name'    => $team->name,
                    'email'         => $team->manager_email,
                    'phone'         => $team->manager_phone
                ],
                "custom_expiry" => [
                    "expiry_duration" => 8,
                    "unit" => "hours"
                ],
                "expiry" => [
                    "duration" => 8,
                    "unit" => "hours"
                ],
                "callbacks" => [
                    "finish" => URL::to('team/'.$team->slug.'/'.$team->code)
                ]
            ];

            $midtransServerKey   = config('services.midtrans.serverKey');
            $midtransEndpoint    = config('services.midtrans.endpoint');
            $midtransFeedbackUrl = config('services.midtrans.feedbackUrl');

            $response = Http::withHeaders([
                'Accept'                    => 'application/json',
                'Content-Type'              => 'application/json',
                'Authorization'             => 'Basic ' . base64_encode($midtransServerKey) . ":",
                'X-Override-Notification'   => $midtransFeedbackUrl,
            ])
            ->post($midtransEndpoint, $payload);

            $resultData = json_decode($response->body());
                
            $payment->update([
                'ref'         => $resultData->token,
                'payment_url' => $resultData->redirect_url,
            ]);

            DB::commit();

            // Mail::to($team->manager_email)->send(new InformationTeams($team));
            Mail::to($team->manager_email)->send(new DownPaymentTeam($team));

            return redirect()->route('teams.index')
                        ->with('success','Data Tim berhasil ditambah & Email telah dikirim ke manajer');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }
    }

    public function show(Team $team)
    {
        $team = Team::with(['downpayment','repayment'])->findOrFail($team->id);
        return view('admin.teams.show', compact('team'));
    }

    public function edit(Team $team)
    {
        return view('admin.teams.edit', compact('team'));
    }

    public function update(Request $request, Team $team)
    {
        $this->validate($request, [
            'image'         => 'image|max:1024',
            'name'          => 'required',
            'manager_name'  => 'required',
            'manager_email' => 'required',
            'manager_phone' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $fileName = $team->logo;
            if($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = 'Teams' . '-' . time() . Str::random(4) .'.'. $image->extension();
                $request->image->move(public_path('uploads/teams/'), $fileName);

                if (is_file(public_path('uploads/teams/'.$team->logo))) {
                    unlink(public_path('uploads/teams/'.$team->logo));
                }
            }

            $team->update([
                'name'          => $request->name,
                'slug'          => Str::slug($request->name),
                'logo'          => $fileName, 
                'manager_name'  => $request->manager_name,
                'manager_email' => $request->manager_email,
                'manager_phone' => $request->manager_phone,
            ]);

            DB::commit();

            return redirect()->route('teams.index')
                        ->with('success','Data Tim berhasil diupdate');

        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }
    }

    public function destroy(Team $team)
    {
        $team = Team::with(['downpayment','repayment'])->findOrFail($team->id);

        if($team->repayment) {
            Mail::to($team->manager_email)->send(new InformationTeams($team));
        } else {
            Mail::to($team->manager_email)->send(new DownPaymentTeam($team));
        }
        

        return response()->json(['status' => true, 'message' => 'Data Tim berhasil dikirim ke manajer!']);
    }

    public function delete(Request $request, $teamId)
    {
        $team = Team::findOrFail($teamId);


        if($team) {

            try {
                DB::beginTransaction();

                if (is_file(public_path('uploads/teams/'.$team->logo))) {
                    unlink(public_path('uploads/teams/'.$team->logo));
                }
                
                $squadMembers = SquadMember::where('team_id', $teamId)->get();
                foreach ($squadMembers as $member) {
                    if ($member->photo && is_file(public_path('uploads/squad/' . $member->photo))) {
                        unlink(public_path('uploads/squad/' . $member->photo));
                    }
                    if ($member->identity_card && is_file(public_path('uploads/squad/identitycard/' . $member->identity_card))) {
                        unlink(public_path('uploads/squad/identitycard/' . $member->identity_card));
                    }
                }

                $squadOfficials = SquadOfficial::where('team_id', $teamId)->get();
                foreach ($squadOfficials as $official) {
                    if ($official->photo && is_file(public_path('uploads/official/' . $official->photo))) {
                        unlink(public_path('uploads/official/' . $official->photo));
                    }
                }

                SquadMember::where('team_id', $teamId)->delete();      
                SquadOfficial::where('team_id', $teamId)->delete();


                $team->delete(); 
               
                DB::commit();
    
                return response()->json(['status' => true, 'message' => 'Data Tim berhasil dihapus!']);
    
            } catch (\Exception $e) {
                DB::rollBack();
                return response()->json(['status' => false, 'message' => $e->getMessage()]);

            }
            
        }

        return response()->json(['status' => false, 'message' => 'Gagal!']);
    }
    public function downloadteam() {
        $team = Team::whereHas('repayment')->get();
        $pdf = PDF::loadView('pdf.ticket', ['teams' => $team]);
        return $pdf->download('WK2025_Teams.pdf');

    }

}
