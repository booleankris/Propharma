<?php

namespace App\Http\Controllers\Master;

use App\Exports\Report\PatientMedicineCardExport;
use App\Http\Controllers\Controller;
use App\Models\Medicines;
use App\Models\Patients;
use App\Models\Pharmacies;
use App\Services\PatientMedicineCardService;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PatientMedicineCardController extends Controller
{
    public function __construct(protected PatientMedicineCardService $service)
    {
        $this->middleware('auth');
        $this->middleware(function ($request, $next) {
            $user = auth()->user();
            if (!$user || !hasFeatureAccess('patients.manage', ['HO', 'operator', 'Operator', 'administrator', 'Koordinator', 'koordinator', 'General Manager'], $user)) {
                abort(403, 'Akses ditolak.');
            }
            return $next($request);
        });
    }

    public function index()
    {
        $activePharmacyId = getActivePharmacyId();
        $canSwitchBranch = $this->canSwitchBranch();
        $pharmacies = Pharmacies::where('status', 1)->orderBy('name')->get(['id', 'name']);
        $activePharmacy = $pharmacies->firstWhere('id', $activePharmacyId);
        $today = now()->toDateString();

        return view('master.patient-medicine-cards.index', compact(
            'pharmacies',
            'activePharmacyId',
            'activePharmacy',
            'canSwitchBranch',
            'today'
        ));
    }

    public function searchPatients(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Patients::query()
            ->select(['id', 'code', 'name', 'phone', 'city'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(10);

        return response()->json($query);
    }

    public function searchMedicines(Request $request)
    {
        $search = trim((string) $request->input('search', ''));

        $query = Medicines::query()
            ->where('status', 1)
            ->select(['id', 'code', 'name', 'unit'])
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->orderBy('name', 'asc')
            ->paginate(10);

        return response()->json($query);
    }

    public function data(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $result = $this->service->build($filters);

        return response()->json([
            'status' => 'success',
            'period' => $result['period'],
            'stats' => $result['stats'],
            'summary' => $result['summary'],
            'timeline' => $result['timeline'],
        ]);
    }

    public function export(Request $request)
    {
        $filters = $this->resolveFilters($request);
        $data = $this->service->build($filters);

        $filename = 'Kartu_Obat_Pasien_' . date('Ymd_His') . '.xlsx';

        return Excel::download(new PatientMedicineCardExport($data, $filters), $filename);
    }

    private function canSwitchBranch(): bool
    {
        $user = auth()->user();
        return $user !== null && hasFeatureAccess(
            'branch.switch_and_filter',
            ['HO', 'administrator', 'General Manager', 'Koordinator', 'koordinator'],
            $user
        );
    }

    private function resolveFilters(Request $request): array
    {
        $activePharmacyId = getActivePharmacyId();
        $pharmacyId = $activePharmacyId;

        if ($this->canSwitchBranch() && $request->filled('pharmacy_id')) {
            $requestedPharmacy = trim((string) $request->input('pharmacy_id'));
            $pharmacyId = $requestedPharmacy === 'all' ? 'all' : (int) $requestedPharmacy;
        }

        return [
            'start_date' => $request->input('start_date') ?: now()->toDateString(),
            'end_date' => $request->input('end_date') ?: now()->toDateString(),
            'pharmacy_id' => $pharmacyId,
            'patient_filter' => trim((string) $request->input('patient_filter', '')),
            'medicine_filter' => trim((string) $request->input('medicine_filter', '')),
        ];
    }
}
