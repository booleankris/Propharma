<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Mail\InformationTeams;
use App\Mail\Tickets;
use App\Models\Team;
use App\Models\TeamPayment;
use App\Models\Ticket;
use App\Models\TicketDetail;
use App\Models\TicketPayment;
use App\Models\TicketTransaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Label\Label;
use Endroid\QrCode\Logo\Logo;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Writer\ValidationException;
class MidtransController extends Controller
{
    function midtransNotification(Request $request)
    {
        try {
            Log::info('Midtrans Notification Hit', $request->all());

            DB::beginTransaction();

            $input = $request->all();

            $ticketPayment = TicketPayment::where('payment_code', $input['order_id'])->first();
            if ($ticketPayment == null) {
                return response()->json([
                    "status" => false,
                    "message" => "order_id not found",
                ], 404);
            }   
            
            $midtransServerKey   = config('services.midtrans.serverKey');
            $signature_key = hash("sha512", $input['order_id'] . $input['status_code'] . $input['gross_amount'] . $midtransServerKey);
            if ($signature_key != $request->get("signature_key")) {
                return response()->json([
                    "status" => false,
                    "message" => "signature key invalid",
                ], 400);

            }
            Log::info('Generated Signature:', ['generated' => $signature_key]);
            Log::info('Received Signature:', ['received' => $request->get('signature_key')]);
            if ($input['transaction_status'] == 'capture' || $input['transaction_status'] == 'settlement') {
                Log::info('Payment status is capture or settlement, updating...');
                $ticketPayment->update([
                    "status"         => "SETTLEMENT",
                    "status_payment" => $request->get("transaction_status"),
                ]);
                $ticket = TicketTransaction::where('id',$ticketPayment->transaction_id)->first();
             
                DB::commit();
            } else if ($input['transaction_status'] == 'deny' || $input['transaction_status'] == 'cancel' || $input['transaction_status'] == 'expire') {

                $ticketPayment->update([
                    "status" => "EXPIRE",
                    "status_payment" => $request->get("transaction_status"),
                ]);
                DB::commit(); 
            }
            return response()->json([
                "status"  => true,
                "message" => "berhasil",
                "data"    => $ticketPayment
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                "status" => false,
                "message" => $e->getMessage(),
            ], 400);
        }
        // try {
        //     DB::beginTransaction();

        //     $input = $request->all();
        //     $teamPayment = TeamPayment::where('payment_code', $input['order_id'])->first();

        //     if ($teamPayment == null) {
        //         return response()->json([
        //             "status" => false,
        //             "message" => "order_id not found",
        //         ], 404);
        //     }

        //     $midtransServerKey   = config('services.midtrans.serverKey');
        //     $signature_key = hash("sha512", $teamPayment->payment_code . $request->get("status_code") . $teamPayment->total_price . '.00' . $midtransServerKey);

        //     if ($signature_key != $request->get("signature_key")) {
        //         return response()->json([
        //             "status" => false,
        //             "message" => "signature key invalid",
        //         ], 400);
        //     }

        //     if ($input['transaction_status'] == 'capture' || $input['transaction_status'] == 'settlement') {
        //         $teamPayment->update([
        //             "status"         => "SETTLEMENT",
        //             "status_payment" => $request->get("transaction_status"),
        //         ]);
        //     } else if ($input['transaction_status'] == 'deny' || $input['transaction_status'] == 'cancel' || $input['transaction_status'] == 'expire') {

        //         $teamPayment->update([
        //             "status" => "EXPIRE",
        //             "status_payment" => $request->get("transaction_status"),
        //         ]);
        //     }

        //     DB::commit();

        //     $team = Team::where('id', $teamPayment->team_id)->first();
        //     if ($teamPayment->type == 'DP' && $teamPayment->status == "SETTLEMENT") {
        //         Mail::to($team->manager_email)->send(new InformationTeams($team));
        //     }

        //     return response()->json([
        //         "status"  => true,
        //         "message" => "berhasil",
        //         "data"    => $teamPayment
        //     ]);
        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     return response()->json([
        //         "status" => false,
        //         "message" => $e->getMessage(),
        //     ], 400);
        // }
    }
    function midtransNotificationTicket(Request $request) {}
}
