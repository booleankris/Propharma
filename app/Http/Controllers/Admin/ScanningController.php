<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\TicketDetail;
use App\Models\Ticket;
use App\Models\TicketTransaction;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use DataTables;

class ScanningController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {

        if ($request->ajax()) {

            $tickets = TicketDetail::whereHas('transaction.payment', function ($query) {
                $query->where('status', 'SETTLEMENT');
            })->with('transaction.matchDay');
            // Apply global search
            if ($search = $request->get('search')['value']) {
                $tickets = $tickets->whereHas('transaction', function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('quantity', 'like', "%{$search}%");
                });
            }
            if ($request->has('order') == false) {
                $tickets = $tickets->orderBy('created_at', 'ASC');
            }

            return DataTables::of($tickets)
                ->addIndexColumn()
                ->addColumn('name', fn($row) => $row->transaction->name ?? '—')
                ->addColumn('quantity', fn($row) => $row->transaction->quantity ?? '—')

                ->addColumn('ticketqr', function ($data) {
                    $ticketqr = '<img src="https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=' . $data->ticket_qr . '" class="img-fluid" width="150px" alt="">';
                    return $ticketqr;
                })
                ->addColumn('day', function ($data) {

                    $matchDayId = $data->transaction->matchDay->day ?? '—'; // Safe null check
                    $day = '<p class="btn btn-primary">' . $matchDayId . ' Ticket Pass</p>';

                    return $day;
                })
                ->addColumn('status', function ($data) {
                    if ($data->status == 1) {
                        $status = '<p class="badge badge-success">Scan Berhasil <i class="fas fa-check"></i></p>';
                    } else {
                        $status = '<p class="badge badge-warning">Scan Pending<i style="margin-left:5px" class="fas fa-spinner"></i></p>';
                    }
                    return $status;
                })
                ->escapeColumns(['ticketqr, status', 'quantity'])
                ->toJson();
        }

        return view('admin.ticketing.index');
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        $matchdays = MatchDay::all()->mapWithKeys(function ($item) {
            return [$item->id => "{$item->day} | {$item->place}"];
        });
        return view('admin.tickets.create', compact('matchdays'));
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
            'match_day_id'  => 'required|exists:match_day,id',
            'match_name'    => 'required',
            'quota'         => 'required|integer'
        ]);

        // try {
        DB::beginTransaction();



        Ticket::create([
            'match_day_id' => $request->match_day_id,
            'match_name'   => $request->match_name,
            'quota'        => $request->quota
        ]);

        DB::commit();

        return redirect()->route('tickets.index')
            ->with('success', 'Data Ticket berhasil ditambah');

        // } catch (\Exception $e) {
        //     DB::rollBack();
        //     return redirect()->back()->with('message', "Gagal Menyimpan! Terdapat Kesalahan");
        // }
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
        $ticket = Ticket::with('matchDay')->findOrFail($id);
        $matchdays = MatchDay::all()->mapWithKeys(function ($item) {
            return [$item->id => "{$item->day} | {$item->place}"];
        });
        return view('admin.tickets.edit', compact('ticket', 'matchdays'));
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
        $ticket = Ticket::findOrFail($id);

        $this->validate($request, [
            // 'image'       => 'required|image|max:1024',
            'match_day_id'  => 'required|exists:match_day,id',
            'match_name'    => 'required',
            'quota'         => 'required|integer'
        ]);

        try {
            DB::beginTransaction();


            TIcket::findOrFail($id)->update([
                'match_day_id' => $request->match_day_id,
                'match_name'   => $request->match_name,
                'quota'        => $request->quota
            ]);

            DB::commit();

            return redirect()->route('tickets.index', [$matchDay->id])
                ->with('success', 'Ticket berhasil diubah');
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
    public function destroy($id)
    {
        Ticket::findOrFail($id)->delete();
        return response()->json(['status' => true, 'message' => 'Ticket berhasil dihapus!']);
    }
    public function downloadticket() {
        $tickets = TicketDetail::whereHas('transaction.payment', function ($query) {
            $query->where('status', 'SETTLEMENT');
        })->with('transaction.matchDay')->get();

        $pdf = PDF::loadView('pdf.ticket', ['tickets' => $tickets]);
        return $pdf->download('WK2025_Ticket_Transaction.pdf');

    }
}
