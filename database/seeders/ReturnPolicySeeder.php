<?php

namespace Database\Seeders;

use App\Models\Creditor;
use App\Models\Factories;
use App\Models\ReturnPolicy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ReturnPolicySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $filePath = storage_path('app/ketentuan retur.xlsx');
        if (!file_exists($filePath)) {
            $this->command->error("File {$filePath} tidak ditemukan.");
            return;
        }

        $spreadsheet = IOFactory::load($filePath);
        $rows = $spreadsheet->getActiveSheet()->toArray();
        array_shift($rows); // Remove header row

        $factories = DB::table('factories')->pluck('name', 'id')->toArray();
        $creditors = DB::table('creditors')->select('code', 'name')->get();

        // PBF Mapping from Excel exact names to DB Creditor Code
        $pbfMap = [];
        foreach ($rows as $r) {
            $pbfRaw = trim($r[2] ?? '');
            if (!$pbfRaw || isset($pbfMap[$pbfRaw])) continue;

            foreach ($creditors as $c) {
                if (strcasecmp($c->name, $pbfRaw) === 0 || stripos($c->name, $pbfRaw) !== false || stripos($pbfRaw, $c->name) !== false) {
                    $pbfMap[$pbfRaw] = $c->code;
                    break;
                }
            }
        }

        // Factory manual aliases for discrepancies in spelling/formatting
        $factoryAliases = [
            'ACTAVIS INDONESIA' => 65,
            'AMAROX PHARMA GLOBAL' => 465,
            'ASTELLAS PHARMA INDONESI' => 177,
            'ASTRA ZENECA' => 30,
            'B. BRAUN MEDICAL INDONESIA' => 696,
            'B. BRAUN PHARMACEUTICAL' => 473,
            'BAYER INDONESIA(BYR)' => 884,
            'BESINS' => 573,
            'BINTANG TOEDJO' => 54,
            'BUFAZENITH' => 416,
            'CAPRI' => 14,
            'CONVATEC (CVT)' => 764,
            'CORONET CROWN  (CORONET)' => 3,
            'CORSA INDUSTRIES' => 10,
            'DELTO MED' => 574,
            'DEXA MEDICA (GENERIK)' => 15,
            'DKT. INDONESIA' => 572,
            'ELI LILY' => 81,
            'ETANA BIOTECHNOLOGIES INDONESIA' => 596,
            'ETHICA INDUSTRI FARMAS' => 41,
            'FERRING PRAMACEUTICALS (FRI)' => 541,
            'FERRON PAR PHARMACEUTICALS (FPP)' => 32,
            'FRESENIUS KABI INDONESIA' => 269,
            'GALDERMA INDONESIA HEALTHCARE' => 406,
            'GENERO' => 346,
            'GLAXO SMITH KLEIN' => 46,
            'GLOBAL HELTH PHARMACEUTICAL' => 52,
            'GRACIA' => 175,
            'GUARDIAN' => 34,
            'HARSEN LABIOTAONES' => 213,
            'HUFA OTC (HUFAGRIF)' => 325,
            'IKONG PHARMACEUTICAL' => 189,
            'IMEDCO DJAJA' => 433,
            'INFION (GENERIK)' => 408,
            'INFION (PATEN)' => 408,
            'ISMUT FITOMEDIKA' => 518,
            'MAHAKAM ETH' => 85,
            'MEAD AND JONHSON' => 203,
            'MECOSIN' => 193,
            'MEDIKON UTAMA PHARMALAB' => 185,
            "MEGA INTER DISTRINDO /\nPYRIDAM" => 39,
            'MEGA LIFESCIENCES PTY LTD' => 248,
            'MEPRO' => 20,
            'MERCK (MRK)' => 5,
            'MERCK SHARP & DOHME INDONESIA' => 71,
            'MESTIKA FARMA' => 25,
            'MITRA PHARMA INDONESIA' => 70,
            'MITSUBISHI TANABE PHARMA INDONESIA (TAN)' => 70,
            'MOLEX AYU' => 163,
            'MULIA FARMA SUCI' => 505,
            'MULIA PUTRA MANDIRI (DELTOMED)' => 574,
            'NATURE' => 391,
            'NESTLE HEALTH SCIENCE (NHS)' => 209,
            'NICHOLAS LAB IND **' => 100,
            'NOVELL / & ETERCON OGB' => 424,
            'NOVELL&ETERCON' => 424,
            'OMRON HEALTHCARE INDONESIA' => 265,
            'ORGANON PHARMA INDONESIA TBK' => 643,
            'OTSUKA INDONESIA' => 33,
            'PFIZER INDONESIA' => 13,
            'PIM' => 279,
            'PRIMA MEDIKA' => 554,
            'PROMEDRAHARDJO' => 21,
            'PROSWEAL' => 395,
            'PT. ABBOTT INDONESIA' => 64,
            'PYRIDAM FARMA' => 39,
            'PYRIDAM OTC' => 39,
            'RAMA EMERALD' => 210,
            'ROCHE/PYRIDAM PHARMA' => 78,
            'ROHTO DERMA / ETHICAL' => 251,
            'SAMPHARINDO PUTRA TRADING' => 349,
            'SANTHI SEPURI' => 181,
            'SATYA ABADI PHARMA' => 705,
            'SEKAWAN KOSMETIK WASANTARA' => 591,
            'SELES GLOBAL PHARMA' => 219,
            'SHAMPARINDO' => 349,
            'SIMEX PHARMACEUTICAL' => 171,
            'SINTESA' => 331,
            'SOHO OTC' => 73,
            'SOHO PP' => 73,
            'SUNTHI' => 181,
            'SURYA DERMANTO MEDICA (SDM)' => 657,
            'TEGUHSINDO' => 92,
            'TEMPO SCAN PASIFIC' => 19,
            'TRIFA' => 366,
            'TROPICA MAS' => 189,
            'TUNGGAL IDAMAN' => 22,
            'UNICHARM TRADING INDONESIA' => 626,
            'YARINDO FARMATAMA (GENERIK)' => 281,
            'YARINDO FARMATAMA (PATEN)' => 281,
        ];

        ReturnPolicy::truncate();

        $inserted = 0;
        foreach ($rows as $r) {
            $factoryRaw = trim($r[1] ?? '');
            $pbfRaw = trim($r[2] ?? '');
            $hRaw = trim($r[3] ?? '');
            $ketRaw = trim($r[4] ?? '');

            if (!$factoryRaw || !$pbfRaw) continue;

            $creditorCode = $pbfMap[$pbfRaw] ?? null;
            if (!$creditorCode) continue;

            // Resolve Factory ID
            $factoryId = null;
            if (isset($factoryAliases[$factoryRaw])) {
                $factoryId = $factoryAliases[$factoryRaw];
            } else {
                // Exact search
                foreach ($factories as $id => $name) {
                    if (strcasecmp($name, $factoryRaw) === 0) {
                        $factoryId = $id;
                        break;
                    }
                }
                // Partial search if not found
                if (!$factoryId) {
                    $cleanRaw = preg_replace('/[^A-Za-z0-9]/', '', str_replace(['PT.', 'PT', 'CV.', 'CV', 'TBK.', 'TBK'], '', $factoryRaw));
                    foreach ($factories as $id => $name) {
                        $cleanName = preg_replace('/[^A-Za-z0-9]/', '', str_replace(['PT.', 'PT', 'CV.', 'CV', 'TBK.', 'TBK'], '', $name));
                        if ($cleanRaw !== '' && $cleanName !== '' && (strcasecmp($cleanRaw, $cleanName) === 0 || str_starts_with($cleanName, $cleanRaw))) {
                            $factoryId = $id;
                            break;
                        }
                    }
                }
            }

            // Parsing return rules
            $isReturnable = (strtoupper($hRaw) !== 'X');
            $monthsBeforeEd = null;
            $monthsAfterEd = 0;
            $allowOnEdMonth = false;

            if ($isReturnable) {
                $monthsBeforeEd = is_numeric($hRaw) ? (int)$hRaw : 0;
                $upperKet = strtoupper($ketRaw);

                if (preg_match('/(\d+)\s*BULAN\s*SESUDAH\s*ED/', $upperKet, $m)) {
                    $monthsAfterEd = (int)$m[1];
                }

                if (
                    $monthsBeforeEd === 0 ||
                    $monthsAfterEd > 0 ||
                    str_contains($upperKet, 'SAAT BULAN ED') ||
                    str_contains($upperKet, 'SAAT BULAN BERJALAN') ||
                    trim($upperKet) === 'BULAN ED'
                ) {
                    $allowOnEdMonth = true;
                }
            }

            ReturnPolicy::create([
                'factory_id' => $factoryId,
                'factory_name' => $factoryRaw,
                'creditor_code' => $creditorCode,
                'is_returnable' => $isReturnable,
                'months_before_ed' => $monthsBeforeEd,
                'months_after_ed' => $monthsAfterEd,
                'allow_on_ed_month' => $allowOnEdMonth,
                'raw_rule' => $hRaw,
                'notes' => $ketRaw,
            ]);
            $inserted++;
        }

        $this->command->info("Seeding Return Policies selesai. Total data diimpor: {$inserted} baris.");
    }
}
