<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Creditor;
use App\Models\Factory;
use App\Models\ReturnPolicy;
use App\Models\Pharmacies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReturnPolicyController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            $allowedRoles = ['administrator', 'General Manager', 'Gudang PMI', 'Koordinator', 'koordinator'];
            $hasAccess = $user && $user->hasAnyRole($allowedRoles);
            if (!$hasAccess) {
                abort(403, 'Akses ditolak: Ketentuan Retur hanya dapat diakses oleh Administrator, General Manager, Gudang PMI, dan Koordinator.');
            }
            return $next($request);
        });
    }

    protected function getBranchContext(): array
    {
        $activePharmacyId = function_exists('getActivePharmacyId') ? getActivePharmacyId() : 1;
        $activePharmacy = Pharmacies::find($activePharmacyId);
        if (!$activePharmacy) {
            $activePharmacy = Pharmacies::first();
        }

        $user = auth()->user();
        $isHoOrAdmin = $user && ($user->hasRole('HO') || $user->hasRole('administrator') || $user->hasRole('General Manager') || $user->hasRole('Koordinator') || $user->hasRole('koordinator'));

        $branches = [];
        if ($isHoOrAdmin) {
            $branches = Pharmacies::whereIn('id', [1, 9, 2, 3, 4, 5])
                ->orderByRaw('FIELD(id, 1, 9, 2, 3, 4, 5)')
                ->get(['id', 'name']);
        }

        return [
            'activePharmacy' => $activePharmacy ? [
                'id' => $activePharmacy->id,
                'name' => $activePharmacy->name,
            ] : [
                'id' => $activePharmacyId,
                'name' => 'Apotek Cabang',
            ],
            'branches' => $branches,
            'canSwitchBranch' => $isHoOrAdmin,
        ];
    }

    public function index(Request $request)
    {
        $search = trim((string) ($request->input('search') ?? ''));
        $creditorFilter = $request->input('creditor_code');
        $returnableFilter = $request->input('is_returnable');
        $perPage = (int) $request->input('per_page', 25);

        $query = ReturnPolicy::with(['factory:id,name', 'creditor:code,name'])
            ->orderBy('id', 'asc');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('factory_name', 'LIKE', "%{$search}%")
                    ->orWhere('notes', 'LIKE', "%{$search}%")
                    ->orWhere('creditor_code', 'LIKE', "%{$search}%")
                    ->orWhereHas('factory', function ($fq) use ($search) {
                        $fq->where('name', 'LIKE', "%{$search}%");
                    })
                    ->orWhereHas('creditor', function ($cq) use ($search) {
                        $cq->where('name', 'LIKE', "%{$search}%");
                    });
            });
        }

        if (!empty($creditorFilter)) {
            $query->where('creditor_code', $creditorFilter);
        }

        if ($returnableFilter !== null && $returnableFilter !== '') {
            $query->where('is_returnable', (string)$returnableFilter === '1');
        }

        $policies = $query->paginate($perPage)->withQueryString();

        // Summary counts
        $totalCount = ReturnPolicy::count();
        $returnableCount = ReturnPolicy::where('is_returnable', true)->count();
        $nonReturnableCount = ReturnPolicy::where('is_returnable', false)->count();

        // Semua PBF (untuk modal Tambah/Edit aturan)
        $creditors = Creditor::select('code', 'name')
            ->whereNotNull('code')
            ->where('code', '!=', '')
            ->orderBy('name')
            ->get();

        // Hanya PBF yang memiliki aturan retur (untuk dropdown filter)
        $policyCounts = ReturnPolicy::select('creditor_code', DB::raw('COUNT(*) as total'))
            ->groupBy('creditor_code')
            ->pluck('total', 'creditor_code');

        $filterCreditors = $creditors
            ->filter(fn ($c) => isset($policyCounts[$c->code]))
            ->map(function ($c) use ($policyCounts) {
                $c->policy_count = (int) $policyCounts[$c->code];
                return $c;
            })
            ->values();

        $factories = Factory::select('id', 'name')
            ->orderBy('name')
            ->get();

        return view('orders.return_policies', [
            'policies' => $policies,
            'search' => $search,
            'creditorFilter' => $creditorFilter ?? '',
            'returnableFilter' => $returnableFilter !== null && $returnableFilter !== '' ? (string)$returnableFilter : '',
            'perPage' => $perPage,
            'totalCount' => $totalCount,
            'returnableCount' => $returnableCount,
            'nonReturnableCount' => $nonReturnableCount,
            'creditors' => $creditors,
            'filterCreditors' => $filterCreditors,
            'factories' => $factories,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'factory_id' => 'nullable|exists:factories,id',
            'factory_name' => 'nullable|string|max:255',
            'creditor_code' => 'required|exists:creditors,code',
            'is_returnable' => 'required|boolean',
            'months_before_ed' => 'nullable|integer|min:0|max:24',
            'months_after_ed' => 'nullable|integer|min:0|max:12',
            'allow_on_ed_month' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['factory_name']) && !empty($validated['factory_id'])) {
            $f = Factory::find($validated['factory_id']);
            $validated['factory_name'] = $f?->name;
        }

        $rawRule = $validated['is_returnable'] ? (string)($validated['months_before_ed'] ?? 0) : 'X';
        $validated['raw_rule'] = $rawRule;
        $validated['months_after_ed'] = $validated['months_after_ed'] ?? 0;

        ReturnPolicy::create($validated);

        return redirect()->back()->with('success', 'Ketentuan retur berhasil ditambahkan.');
    }

    public function update(Request $request, $id)
    {
        $policy = ReturnPolicy::findOrFail($id);

        $validated = $request->validate([
            'factory_id' => 'nullable|exists:factories,id',
            'factory_name' => 'nullable|string|max:255',
            'creditor_code' => 'required|exists:creditors,code',
            'is_returnable' => 'required|boolean',
            'months_before_ed' => 'nullable|integer|min:0|max:24',
            'months_after_ed' => 'nullable|integer|min:0|max:12',
            'allow_on_ed_month' => 'required|boolean',
            'notes' => 'nullable|string|max:1000',
        ]);

        if (empty($validated['factory_name']) && !empty($validated['factory_id'])) {
            $f = Factory::find($validated['factory_id']);
            $validated['factory_name'] = $f?->name;
        }

        $rawRule = $validated['is_returnable'] ? (string)($validated['months_before_ed'] ?? 0) : 'X';
        $validated['raw_rule'] = $rawRule;
        $validated['months_after_ed'] = $validated['months_after_ed'] ?? 0;

        $policy->update($validated);

        return redirect()->back()->with('success', 'Ketentuan retur berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $policy = ReturnPolicy::findOrFail($id);
        $policy->delete();

        return redirect()->back()->with('success', 'Ketentuan retur berhasil dihapus.');
    }

    public function export(Request $request)
    {
        $policies = ReturnPolicy::with(['factory', 'creditor'])->orderBy('id', 'asc')->get();

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Master Ketentuan Retur');

        $headers = [
            'NO', 'PABRIK / PRINCIPLE', 'PBF', 'KODE PBF', 'STATUS BISA RETUR',
            'H- BULAN SEBELUM ED', 'H+ BULAN SESUDAH ED', 'BOLEH BULAN ED', 'KETERANGAN'
        ];
        $sheet->fromArray($headers, null, 'A1');

        $rows = [];
        foreach ($policies as $idx => $p) {
            $rows[] = [
                $idx + 1,
                $p->factory ? $p->factory->name : ($p->factory_name ?? '-'),
                $p->creditor ? $p->creditor->name : $p->creditor_code,
                $p->creditor_code,
                $p->is_returnable ? 'BISA RETUR' : 'TIDAK BISA RETUR (X)',
                $p->is_returnable ? ($p->months_before_ed ?? 0) . ' Bulan' : '-',
                $p->months_after_ed > 0 ? "{$p->months_after_ed} Bulan" : '0 Bulan',
                $p->allow_on_ed_month ? 'YA' : 'TIDAK',
                $p->notes ?? '',
            ];
        }

        if (!empty($rows)) {
            $sheet->fromArray($rows, null, 'A2');
        }

        $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
        $fileName = 'Master_Ketentuan_Retur_' . date('Ymd_His') . '.xlsx';

        return new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        }, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => "attachment; filename=\"{$fileName}\"",
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
