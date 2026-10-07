<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use App\Models\Doctors;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use DataTables;
use Form;

class DoctorsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        // AJAX: DataTables
        if ($request->ajax()) {
            $doctors = Doctors::leftJoin('pharmacies', 'doctors.pharmacy_id', '=', 'pharmacies.id')
                ->select(
                    'doctors.id',
                    'doctors.pharmacy_id',
                    'doctors.code',
                    'doctors.name',
                    'doctors.specialist',
                    'doctors.address',
                    'doctors.city',
                    'doctors.phone',
                    'doctors.status',
                    'doctors.created_at',
                    'pharmacies.name as pharmacy_name'
                );

            if ($request->filled('pharmacy_id')) {
                $doctors->where('doctors.pharmacy_id', $request->pharmacy_id);
            }

            if (!$request->has('order')) {
                $doctors->orderBy('doctors.created_at', 'ASC');
            }

            return DataTables::of($doctors)
                ->addIndexColumn()
                ->filterColumn('DT_RowIndex', function ($query, $keyword) {
                    // Do nothing
                })
                ->addColumn('pharmacy_name', function ($doctor) {
                    return $doctor->pharmacy_name ?? '-';
                })
                ->addColumn('status_label', function ($doctor) {
                    return $doctor->status == 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-secondary">Inactive</span>';
                })
                ->addColumn('action', function ($doctor) {
                    $btn  = '<div class="btn-toolbar" role="toolbar">';
                    $btn .= '<div class="btn-group m-1 mr-2" role="group">';
                    $btn .= '<button class="btn btn-primary btn-sm" onclick="editData(' . $doctor->id . ')">Edit</button>';
                    $btn .= '</div>';

                    $btn .= '<div class="btn-group m-1" role="group">';
                    $btn .= Form::button("Delete", [
                        "id" => "button_delete_" . $doctor->id,
                        "class" => "btn btn-danger btn-sm",
                        "data-route" => route("doctors.destroy", $doctor->id),
                        "onclick" => "delete_data(" . $doctor->id . ")"
                    ]);
                    $btn .= '</div></div>';

                    return $btn;
                })
                ->escapeColumns([])
                ->toJson();
        }

        // Page render (table + form)
        $pharmacies = Pharmacies::orderBy('name')->get();

        return view('master.doctors.index', compact('pharmacies'));
    }

    private function generateDoctorCode()
    {
        $last = Doctors::orderBy('id', 'desc')->first();

        if (!$last || !$last->code) {
            return 'DR0001';
        }

        $number = (int) substr($last->code, 2);
        return 'DR' . str_pad($number + 1, 4, '0', STR_PAD_LEFT);
    }

    public function store(Request $request)
    {
        $code = $this->generateDoctorCode();

        $validated = $request->validate([
            'pharmacy_id' => 'required|',
            'name'        => 'required|string|max:255',
            'specialist'  => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:50',
        ]);

        $this->ensureNotDuplicate($validated['name']);

        $doctor = Doctors::create([
            'code'        => $code,
            'pharmacy_id' => $validated['pharmacy_id'],
            'name'        => $validated['name'],
            'specialist'  => $validated['specialist'] ?? null,
            'address'     => $validated['address'] ?? null,
            'city'        => $validated['city'] ?? null,
            'phone'       => $validated['phone'] ?? null,
            'status'      => $request->status ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data doctor berhasil disimpan.',
            'data'    => $doctor
        ]);
    }

    public function show(Doctors $doctor)
    {
        return response()->json($doctor);
    }

    public function update(Request $request, Doctors $doctor)
    {
        $validated = $request->validate([
            'pharmacy_id' => 'required|',
            'name'        => 'required|string|max:255',
            'specialist'  => 'nullable|string|max:255',
            'address'     => 'nullable|string|max:255',
            'city'        => 'nullable|string|max:100',
            'phone'       => 'nullable|string|max:50',
        ]);

        $this->ensureNotDuplicate($validated['name'], $doctor->id);

        $doctor->update([
            'pharmacy_id' => $validated['pharmacy_id'],
            'name'        => $validated['name'],
            'specialist'  => $validated['specialist'] ?? null,
            'address'     => $validated['address'] ?? null,
            'city'        => $validated['city'] ?? null,
            'phone'       => $validated['phone'] ?? null,
            'status'      => $request->status ?? 0,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Data doctor berhasil diperbarui.',
            'data'    => $doctor
        ]);
    }

    public function destroy($id)
    {
        $doctor = Doctors::findOrFail($id);

        // FK medicine_transactions.doctor_id = ON DELETE CASCADE:
        // menghapus dokter yang dipakai transaksi akan ikut menghapus transaksinya.
        $used = \Illuminate\Support\Facades\DB::table('medicine_transactions')->where('doctor_id', $doctor->id)->count();
        if ($used > 0) {
            return response()->json([
                'status'  => false,
                'message' => "Dokter dipakai di {$used} transaksi, tidak bisa dihapus.",
            ], 422);
        }

        $doctor->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Doctor successfully deleted!'
        ]);
    }

    private function ensureNotDuplicate(string $name, ?int $ignoreId = null): void
    {
        $key = \App\Support\DoctorNameMatcher::strictKey($name);

        $existing = Doctors::select(['id', 'code', 'name'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->get()
            ->first(fn ($d) => \App\Support\DoctorNameMatcher::strictKey($d->name) === $key);

        if ($existing) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'name' => "Dokter \"{$existing->name}\" ({$existing->code}) sudah terdaftar.",
            ]);
        }
    }

    public function getDuplicates()
    {
        $txCounts = DB::table('medicine_transactions')
            ->whereNotNull('doctor_id')
            ->selectRaw('doctor_id, COUNT(*) as c')
            ->groupBy('doctor_id')
            ->pluck('c', 'doctor_id')
            ->map(fn ($v) => (int) $v)
            ->all();

        $doctors = Doctors::orderBy('id')->get()
            ->each(fn ($d) => $d->tx_count = $txCounts[$d->id] ?? 0)
            ->reject(fn ($d) => \App\Support\DoctorNameMatcher::isPlaceholder($d->name));

        $buckets = [];
        foreach ($doctors as $d) {
            $buckets[\App\Support\DoctorNameMatcher::strictKey($d->name)][] = $d;
        }
        $keys = array_keys($buckets);

        $parent = array_combine($keys, $keys);
        $find = function ($k) use (&$parent, &$find) {
            return $parent[$k] === $k ? $k : ($parent[$k] = $find($parent[$k]));
        };

        $parsed = [];
        foreach ($keys as $k) {
            $parsed[$k] = \App\Support\DoctorNameMatcher::parse($buckets[$k][0]->name);
        }

        $n = count($keys);
        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                if (\App\Support\DoctorNameMatcher::isFuzzyMatch($parsed[$keys[$i]], $parsed[$keys[$j]])) {
                    $parent[$find($keys[$i])] = $find($keys[$j]);
                }
            }
        }

        $clusters = [];
        foreach ($keys as $k) {
            $clusters[$find($k)][] = $k;
        }

        $groups = [];
        foreach ($clusters as $bucketKeys) {
            $members = array_merge(...array_map(fn ($k) => $buckets[$k], $bucketKeys));
            if (count($members) < 2) {
                continue;
            }

            usort($members, fn ($a, $b) => [$b->tx_count, $a->id] <=> [$a->tx_count, $b->id]);
            $type = count($bucketKeys) > 1 ? 'FUZZY' : 'EXACT';

            $groups[] = [
                'type'      => $type,
                'canonical' => $members[0],
                'members'   => $members,
            ];
        }

        usort($groups, fn ($a, $b) => count($b['members']) <=> count($a['members']));

        return response()->json([
            'success' => true,
            'groups'  => $groups,
        ]);
    }

    public function mergeDuplicates(Request $request)
    {
        $validated = $request->validate([
            'canonical_id'    => 'required|integer|exists:doctors,id',
            'duplicate_ids'   => 'required|array|min:1',
            'duplicate_ids.*' => 'integer|exists:doctors,id',
        ]);

        $canonicalId = $validated['canonical_id'];
        $dupIds = array_values(array_filter($validated['duplicate_ids'], fn ($id) => (int) $id !== $canonicalId));

        if (empty($dupIds)) {
            return response()->json(['success' => false, 'message' => 'Tidak ada ID duplikat yang valid.'], 422);
        }

        DB::transaction(function () use ($canonicalId, $dupIds) {
            // Remap transactions
            DB::table('medicine_transactions')
                ->whereIn('doctor_id', $dupIds)
                ->update(['doctor_id' => $canonicalId]);

            // Fill missing fields on canonical from duplicates
            $canonical = Doctors::find($canonicalId);
            $duplicates = Doctors::whereIn('id', $dupIds)->get();

            $fill = [];
            $fields = ['specialist', 'address', 'city', 'phone'];
            foreach ($fields as $f) {
                if (empty($canonical->$f)) {
                    foreach ($duplicates as $d) {
                        if (!empty($d->$f)) {
                            $fill[$f] = $d->$f;
                            break;
                        }
                    }
                }
            }

            if (!empty($fill)) {
                $canonical->update($fill);
            }

            // Delete duplicate doctors
            Doctors::whereIn('id', $dupIds)->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Dokter duplikat berhasil digabungkan.',
        ]);
    }
}
