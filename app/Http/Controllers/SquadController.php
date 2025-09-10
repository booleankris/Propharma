<?php

namespace App\Http\Controllers;

use App\Mail\InformationTeams;
use App\Models\SquadMember;
use App\Models\SquadOfficial;
use App\Models\Team;
use App\Models\TeamPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SquadController extends Controller
{
    public function SquadOfficialStore(Request $request, $slug, $code)
    {
        // Validate the image

        $findTeamID = Team::where('slug', $slug)->where('code', $code)->first();

        $this->validate($request, [
            'officialname' => 'required',
            'details'      => 'required',
        ]);

        $fileName = null;
        if ($request->hasFile('imageFile3')) {
            $image = $request->file('imageFile3'); // Consistently use 'imageFile'
            $fileName = 'Official' . '-' . time() . Str::random(4) . '.' . $image->extension();
            $image->move(public_path('uploads/official/'), $fileName); // Move the actual file
        }
        

        try {
            DB::beginTransaction();

            $countofficial = SquadOfficial::where('team_id', $findTeamID->id)->count();
            if ($countofficial < 5) {

                SquadOfficial::create([
                    'team_id'          => $findTeamID->id,
                    'name'             => $request->officialname,
                    'photo'            => $fileName,
                    'details'          => $request->details,
                ]);

                DB::commit();


                return redirect()->route('squadregistration', ['code' => $findTeamID->code, 'slug' => $findTeamID->slug])
                    ->with('success', 'Data Tim berhasil ditambah & Email telah dikirim ke manajer');
            } else {
                return redirect()->back()->with('failed', 'Data Official Sudah Penuh');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }

        // Store the image
    }
    public function SquadOfficialDestroy($id)
    {
        $official = SquadOfficial::findOrFail($id);
        $official->delete();

        return redirect()->back()->with('deletesuccess', 'Data berhasil dihapus.');
    }
    public function SquadMemberStore(Request $request, $slug, $code)
    {
        // Validate the image
        $this->validate($request, [
            'membername'    => 'required',
            'number'        => 'required',
            'position'      => 'required',
            'dateofbirth'   => 'required',
        ]);

        try {
            DB::beginTransaction();

            $fileName = null;
            if ($request->hasFile('imageFile')) {
                $image = $request->file('imageFile'); // Consistently use 'imageFile'
                $fileName = 'Squad' . '-' . time() . Str::random(4) . '.' . $image->extension();
                $image->move(public_path('uploads/squad/'), $fileName); // Move the actual file
            }

            $fileName2 = null;
            if ($request->hasFile('imageFile2')) {
                $image2 = $request->file('imageFile2'); // Consistently use 'imageFile'
                $fileName2 = 'Identity' . '-' . time() . Str::random(4) . '.' . $image2->extension();
                $image2->move(public_path('uploads/squad/identitycard'), $fileName2); // Move the actual file
            }

            $findTeamID = Team::where('slug', $slug)->where('code', $code)->first();
            $countmember = SquadMember::where('team_id', $findTeamID->id)->count();
            if ($countmember < 15) {
                $findTeamID = Team::where('slug', $slug)->where('code', $code)->first();
                $team = SquadMember::create([
                    'team_id'       => $findTeamID->id,
                    'name'          => $request->membername,
                    'photo'         => $fileName,
                    'number'        => $request->number,
                    'position'      => $request->position,
                    'dateofbirth'   => $request->dateofbirth,
                    'identity_card' => $fileName2,
                ]);

                DB::commit();
                return redirect()->route('squadregistration', ['code' => $findTeamID->code, 'slug' => $findTeamID->slug])
                    ->with('success', 'Data Tim berhasil ditambah & Email telah dikirim ke manajer');
            } else {
                return redirect()->back()->with('failed', 'Data Pemain Sudah Penuh');
            }
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        }

        return back()->with('error', 'Image upload failed!');
        return view('step1');
    }
    public function SquadMemberDestroy($id)
    {
        $member = SquadMember::findOrFail($id);
        if ($member->photo && file_exists(public_path('uploads/squad/' . $member->photo))) {
            unlink(public_path('uploads/squad/' . $member->photo));
        }
        $member->delete();

        return redirect()->back()->with('deletesuccess', 'Data berhasil dihapus.');
    }
    public function download($filename): StreamedResponse
    {
        $path = 'public/' . $filename; // example: storage/app/private
        if (!Storage::exists($path)) {
            abort(404);
        }

        return Storage::download($path);
    }

    public function upload(Request $request, $slug, $code)
    {

        try {
            DB::beginTransaction();

            // Validate the uploaded PDF file
            $request->validate([
                'pdfFile' => 'required|mimes:pdf|max:5120', // Max 5MB
            ]);

            $team = Team::where('slug', $slug)->where('code', $code)->firstOrFail();

            if ($request->hasFile('pdfFile')) {
                $file = $request->file('pdfFile');

                // Generate a unique filename
                $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();

                // Define destination path
                $destination = public_path('uploads/surat-pernyataan/');

                // Ensure the directory exists
                if (!file_exists($destination)) {
                    mkdir($destination, 0755, true);
                }

                // Move file to the destination
                $file->move($destination, $filename);

                // Optional: Get public URL to show back to user
                $publicUrl = url("uploads/surat-pernyataan/{$filename}");
                $team->statement_letter = $filename;
                $team->save();
            }

            $code   = 'WKC25-'. rand(1000, 9999);
            $amount = 1000000;

            $payment = TeamPayment::create([
                'team_id'        => $team->id,
                'type'           => 'FULL',
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
                        'name'     => 'Tagihan Pelunasan Tim'
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
            return back()->with('success', 'Surat pernyataan berhasil diupload!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat mengupload file.');
        }

    }

    public function recreatePayment(Request $request)
    {

        try {
            DB::beginTransaction();

            $team = Team::where('slug', $request->slug)->where('code', $request->code)->firstOrFail();

            $code   = 'WKC25-'. rand(1000, 9999);

            $payment = TeamPayment::where('team_id', $team->id)->where('type', $request->type)->first();

            if(!$payment) {
                return back()->with('error', 'Pembayaran Tidak ditemukan.');
            }

            if($payment->status == 'SETTLEMENT' || $payment->status_payment == 'settlement') {
                return back()->with('error', 'Pembayaran Sudah berhasil dibayar.');
            }

            $typePayment = 'DP';
            if($payment->type == 'FULL'){
                $typePayment = 'Pelunasan';
            }

            $payload = [
                'transaction_details' => [
                    'order_id'      => $code,
                    'gross_amount'  => $payment->total_price,
                ],
                'item_details' => [
                    [
                        'id'       => 'team-registration',
                        'price'    => $payment->total_price,
                        'quantity' => 1,
                        'name'     => 'Tagihan Tim' . $typePayment
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
                'payment_code'   => $code,
                'ref'            => $resultData->token,
                'payment_url'    => $resultData->redirect_url,
                'status'         => 'PENDING',
                'status_payment' => 'pending',
            ]);

            DB::commit();

            return back()->with('success', 'Pembayaran Berhasil dibuat ulang!');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Terjadi kesalahan saat membuat ulang pembayaran.');
        }

    }
}
