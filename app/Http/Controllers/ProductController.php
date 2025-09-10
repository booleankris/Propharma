<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {

        if ($request->ajax()) {

            $tickets = Ticket::with('matchDay')
                ->select('id', 'match_id', 'match_day_id', 'match_name', 'quota', 'created_at');
            // dd($tickets);
            if ($request->has('order') == false) {
                $tickets = $tickets->orderBy('created_at', 'ASC');
            }

            return DataTables::of($tickets)
                ->addIndexColumn()
                // ->addColumn('preview', function($data) {
                //     $preview = '<img src="'. $data->image_url .'" class="img-fluid" width="150px" alt="">';
                //     return $preview;
                // })
                ->addColumn('action', function ($data) {
                    $button = '<div class="btn-toolbar" role="toolbar">
                                            <div class="btn-group m-1 mr-2" role="group">';
                    $button .= '<a class="btn btn-primary" href="' . route('tickets.edit', $data->id) . '">Edit</a>';
                    $button .= '</div>';
                    $button .= '<div class="btn-group m-1">';
                    $button .= Form::button('Hapus', ['id' => 'button-delete-' . $data->id, 'class' => 'btn btn-danger', 'data-route' => route('tickets.destroy', $data->id), 'onclick' => 'delete_data(' . $data->id . ')']);
                    $button .= '</div>';

                    $button .= '</div>';
                    return $button;
                })
                ->escapeColumns(['preview, action'])
                ->toJson();
        }

        return view('admin.tickets.index');
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
}
