<?php

namespace App\Http\Controllers;

use App\Mail\DownPaymentTeam;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Mail\InformationTeams;
use App\Mail\Tickets;
use App\Models\MatchDay;
use App\Models\SquadMember;
use App\Models\SquadOfficial;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

use App\Models\Team;
use App\Models\TeamPayment;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Models\TicketPayment;
use App\Models\TicketTransaction;
use Form;
use Image;
use DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\URL;

class LandingpageController extends Controller
{
    public function index()
    {
        return view('landing_page.welcome');
    }
    public function teamRegistration()
    {
        return view('landing_page.step1');
    }

    public function teamRegister(Request $request)
    {
        // Validate the image
        $this->validate($request, [
            'image'         => 'image|max:6144',
            'name'          => 'required',
            'manager_name'  => 'required',
            'manager_email' => 'required',
            'manager_phone' => 'required',
        ]);

        try {
            DB::beginTransaction();

            $fileName = null;
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = 'Teams' . '-' . time() . Str::random(4) . '.' . $image->extension();
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

            $code   = 'WKC25-' . rand(1000, 9999);
            $amount = 1;

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
                        'name'     => 'Tagihan DP Tim'
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
                    "finish" => URL::to('team/' . $team->slug . '/' . $team->code)
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

            return redirect()->route('teamregister.success')
                ->with('success', 'Data Tim berhasil ditambah & Email telah dikirim ke manajer');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }

        // Store the image


        return back()->with('error', 'Image upload failed!');
        return view('step1');
    }
    function teamRegisterSuccess()
    {
        return view('landing_page.teamregistersuccess');
    }

    public function squadRegistration($slug, $code)
    {
        $squad = Team::where('code', $code)->where('slug', $slug)->first();

        if (!$squad) {
            return redirect('/');
        }

        $downPayment = TeamPayment::where('team_id', $squad->id)->where('type', 'DP')->first();

        if (!$downPayment || $downPayment->status != 'SETTLEMENT') {
            return redirect()->route('dp.payment.squad', [$squad->slug, $squad->code]);
        }


        $member = SquadMember::where('team_id', $squad->id)->get();
        $official = SquadOfficial::where('team_id', $squad->id)->get();

        $totalmember = SquadMember::where('team_id', $squad->id)->count();
        $totalofficial = SquadOfficial::where('team_id', $squad->id)->count();

        $payment = TeamPayment::where('team_id', $squad->id)->where('type', 'FULL')->first();

        // if($squad->statement_letter == NULL){
        return view('landing_page.step2', compact('squad', 'member', 'official', 'totalmember', 'totalofficial', 'payment'));
        // }
        // else{
        //     return view('landing_page.done', compact('squad','member','official'));
        // }
    }

    public function squadRegister(Request $request)
    {
        // Validate the image
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
            if ($request->hasFile('image')) {
                $image = $request->file('image');
                $fileName = 'Teams' . '-' . time() . Str::random(4) . '.' . $image->extension();
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

            DB::commit();

            Mail::to($team->manager_email)->send(new InformationTeams($team));

            return redirect()->route('teamregister.success')
                ->with('success', 'Data Tim berhasil ditambah & Email telah dikirim ke manajer');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }

        // Store the image


        return back()->with('error', 'Image upload failed!');
        return view('step1');
    }

    function squadRegisterSuccess()
    {
        return view('landing_page.teamregistersuccess');
    }

    function dpSquadRegister(Request $request, $slug, $code)
    {
        $squad = Team::where('code', $code)->where('slug', $slug)->first();

        if (!$squad) {
            return redirect('/');
        }

        $payment = TeamPayment::where('team_id', $squad->id)->where('type', 'DP')->first();

        return view('landing_page.downpaymentsquad', compact('squad', 'payment'));
    }

    public function matches()
    {
        $matchday = MatchDay::with('match')->get();
        return view('landing_page.matches',compact('matchday'));
    }
    public function tickets()
    {
        $ticket = Ticket::all();
        return view('landing_page.tickets', compact('ticket'));
    }
    public function checkout($id)
    {
        $ticket = Ticket::where('match_day_id', $id)->first();
        return view('landing_page.checkout', compact('ticket'));
    }
    public function sendTicket($code){

    }
    public function checkTicket($code){
        return view('landing_page.ticketpaymentsuccess');
    }
    public function payTicket($id){
        $ticket = TicketPayment::where('id',$id)->first();
        return Redirect::to($ticket->payment_url);

    }
    public function buyTicket(Request $request, $id)
    {
        $this->validate($request, [
            'name'          => 'required',
            'phone'         => 'required',
            'email'         => 'required',
            'quantity'      => 'required',
        ]);
        try {
            DB::beginTransaction();

            $getid   =  TicketTransaction::count();
            $getidTicket   =  Ticket::where('id', $id)->first();
            $getidTicket->update([
                'quota' => $getidTicket->quota - $request->quantity,
            ]);
            $ticket_code   = 'WKC25-' . Str::random(4) .$getid + 1;
            $amount = 20000;

            $ticket = TicketTransaction::create([
                'match_day_id'  => $id,
                'name'          => $request->name,
                'phone'         => $request->phone,
                'ticket_code'   => $ticket_code,
                'email'         => $request->email,
                'quantity'      => $request->quantity,

            ]); 
            
            for ($i = 0; $i < $request->quantity; $i++) {
                $ticket_code   = 'WKC25-' . Str::random(4) .$getid + 1;
                $tickets = TicketDetail::create([
                    'ticket_transaction_id' => $ticket->id,
                    'ticket_qr' => $ticket_code,
                    'status' => "0",

                ]);
            }

            $payment = TicketPayment::create([
                'transaction_id' => $ticket->id,
                'payment_code'   => $ticket_code,
                'total_price'    => $amount * $request->quantity,
                'status'         => 'PENDING',
                'status_payment' => 'pending',
            ]);
            $payload = [
                'transaction_details' => [
                    'order_id'      => $ticket_code,
                    'gross_amount'  => $amount * $request->quantity,
                ],
                'item_details' => [
                    [
                        'id'       => 'ticket-payment',
                        'price'    => $amount,
                        'quantity' => $request->quantity,
                        'name'     => 'Tagihan Ticket Pertandingan'
                    ]
                ],
                'customer_details' =>
                [
                    'first_name'    => $ticket->name,
                    'email'         => $ticket->email,
                    'phone'         => $ticket->phone
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
                    "finish" => URL::to('checkticket/'. $ticket_code)
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
         
            return redirect()->route('ticket.transaction',$payment->id)
                ->with('success', 'Pembelian Berhasil Dibuat');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }
    }
    public function scanTicket(Request $request) {
        $ticket = TicketDetail::where('ticket_qr', $request->qr)->where('status', 0)->update([
            'status' => 1, 
        ]);

        if ($ticket) {
            return response()->json(['message' => 'Ticket Scanned successfully'], 200);
        } else {
            return response()->json(['error' => 'Gagal! Ticket Sudah Pernah di-Scan!'], 404);
        }
    }
}
