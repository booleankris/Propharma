<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FinanceAccount extends Model
{
    use HasFactory;

    protected $table = 'finance_accounts';

    protected $fillable = [
        'code',
        'pharmacy_id',
        'name',
        'name_en',
        'category',
        'account_type',
        'account_number',
        'balance',
        'is_active',
    ];

    protected $casts = [
        'balance' => 'float',
        'is_active' => 'boolean',
    ];

    public const DEFAULT_BRANCH_BANKS = [
        'BCA' => [
            'code' => '1-10002',
            'name' => 'Rekening BCA',
            'name_en' => 'BCA Bank Account',
            'short_name' => 'BCA',
        ],
        'MANDIRI' => [
            'code' => '1-10003',
            'name' => 'Rekening Mandiri',
            'name_en' => 'Mandiri Bank Account',
            'short_name' => 'Mandiri',
        ],
        'BNI' => [
            'code' => '1-10004',
            'name' => 'Rekening BNI',
            'name_en' => 'BNI Bank Account',
            'short_name' => 'BNI',
        ],
        'BTN' => [
            'code' => '1-10005',
            'name' => 'Rekening BTN',
            'name_en' => 'BTN Bank Account',
            'short_name' => 'BTN',
        ],
        'BRI' => [
            'code' => '1-10006',
            'name' => 'Rekening BRI',
            'name_en' => 'BRI Bank Account',
            'short_name' => 'BRI',
        ],
    ];

    public function payments()
    {
        return $this->hasMany(FinancePayment::class, 'account_id');
    }

    public function pharmacy()
    {
        return $this->belongsTo(Pharmacies::class, 'pharmacy_id');
    }

    /**
     * Normalisasi nama bank (menghapus prefix metode bayar atau kata "Rekening"/"Bank")
     * menjadi kunci alfanumerik huruf besar, contoh: "QRIS Bank BRI" -> "BRI".
     */
    public static function normalizeBankKey(?string $value): string
    {
        $cleaned = strtoupper(trim((string) $value));
        if ($cleaned === '') {
            return '';
        }

        // Hapus kata umum di awal/akhir agar pencocokan nama bank akurat per cabang
        $cleaned = preg_replace('/\b(TRANSFER|DEBIT|QRIS|EDC|REKENING|BANK|KAS|APOTEK|PT|TBK)\b/i', ' ', $cleaned);
        return preg_replace('/[^A-Z0-9]/', '', (string) $cleaned);
    }

    /**
     * Ambil nama pendek bank untuk tampilan UI/dropdown (contoh: "Rekening Mandiri" -> "Mandiri").
     */
    public static function extractShortBankName(?string $value): string
    {
        $trimmed = trim((string) $value);
        $short = trim((string) preg_replace('/^(TRANSFER|DEBIT|QRIS|EDC|Rekening|Bank)\s+/i', '', $trimmed));
        return $short !== '' ? $short : $trimmed;
    }

    /**
     * Buat kode akun Kas & Bank berikutnya untuk cabang tertentu (1-100XX).
     */
    public static function nextBranchCashBankCode(int $pharmacyId): string
    {
        $prefix = '1-100';
        $existingCodes = self::where('pharmacy_id', $pharmacyId)
            ->where('code', 'LIKE', $prefix . '%')
            ->pluck('code')
            ->all();

        $maxNum = 1;
        foreach ($existingCodes as $code) {
            if (preg_match('/^1-100(\d+)$/', (string) $code, $matches)) {
                $num = (int) $matches[1];
                if ($num > $maxNum) {
                    $maxNum = $num;
                }
            }
        }

        return $prefix . str_pad((string) ($maxNum + 1), 2, '0', STR_PAD_LEFT);
    }

    /**
     * Pastikan cabang memiliki akun Kas Apotek (1-10001) dan rekening bank standar (BCA, Mandiri, BNI, BTN, BRI).
     */
    public static function ensureBranchDefaultAccounts(int $pharmacyId): void
    {
        if ($pharmacyId <= 0) {
            return;
        }

        $branchAccounts = self::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->get();

        // 1. Pastikan Kas Apotek / Drawer tersedia
        $cashAccount = $branchAccounts->firstWhere('account_type', 'kas_apotek')
            ?? $branchAccounts->firstWhere('code', '1-10001');

        if (!$cashAccount) {
            self::firstOrCreate(
                ['pharmacy_id' => $pharmacyId, 'code' => '1-10001'],
                [
                    'name' => 'Kas Apotek / Drawer',
                    'name_en' => 'Cash Drawer',
                    'category' => 'Kas & Bank',
                    'account_type' => 'kas_apotek',
                    'account_number' => null,
                    'balance' => 0,
                    'is_active' => true,
                ]
            );
        } elseif (!$cashAccount->is_active || $cashAccount->account_type !== 'kas_apotek') {
            $cashAccount->update([
                'account_type' => 'kas_apotek',
                'is_active' => true,
            ]);
        }

        // 2. Pastikan Rekening Bank standar (BCA, Mandiri, BNI, BTN, BRI) tersedia di cabang ini
        $existingBanks = self::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->where('account_type', 'bank')
            ->get();

        foreach (self::DEFAULT_BRANCH_BANKS as $bankKey => $meta) {
            $matched = $existingBanks->first(function (self $acc) use ($bankKey) {
                $accKey = self::normalizeBankKey($acc->name);
                return $accKey === $bankKey || ($accKey !== '' && str_contains($accKey, $bankKey));
            });

            if (!$matched) {
                $codeInUse = self::where('pharmacy_id', $pharmacyId)
                    ->where('code', $meta['code'])
                    ->exists();
                $code = $codeInUse ? self::nextBranchCashBankCode($pharmacyId) : $meta['code'];

                $created = self::create([
                    'pharmacy_id' => $pharmacyId,
                    'code' => $code,
                    'name' => $meta['name'],
                    'name_en' => $meta['name_en'],
                    'category' => 'Kas & Bank',
                    'account_type' => 'bank',
                    'account_number' => null,
                    'balance' => 0,
                    'is_active' => true,
                ]);
                $existingBanks->push($created);
            }
        }
    }

    /**
     * Resolusi akun Kas Apotek aktif untuk cabang tertentu.
     */
    public static function resolveBranchCashAccount(int $pharmacyId, bool $lockForUpdate = false): ?self
    {
        if ($pharmacyId <= 0) {
            return null;
        }

        self::ensureBranchDefaultAccounts($pharmacyId);

        $query = self::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->where('account_type', 'kas_apotek')
            ->where('is_active', true)
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /**
     * Resolusi akun Rekening Bank untuk cabang dan nama bank tertentu.
     * Jika belum ada di cabang tersebut dan $autoCreate = true, otomatis dibuatkan untuk cabang itu.
     */
    public static function resolveBranchBankAccount(
        int $pharmacyId,
        ?string $bankName,
        bool $lockForUpdate = false,
        bool $autoCreate = true
    ): ?self {
        if ($pharmacyId <= 0) {
            return null;
        }

        $bankKey = self::normalizeBankKey($bankName);
        if ($bankKey === '') {
            return null;
        }

        self::ensureBranchDefaultAccounts($pharmacyId);

        $query = self::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->where('account_type', 'bank')
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $accounts = $query->get();
        $activeAccounts = $accounts->where('is_active', true);

        // Prioritas 1: Exact match pada bank key di akun aktif
        $exactActive = $activeAccounts->first(fn (self $acc) => self::normalizeBankKey($acc->name) === $bankKey);
        if ($exactActive) {
            return $exactActive;
        }

        // Prioritas 2: Substring match pada akun aktif
        $partialActive = $activeAccounts->first(function (self $acc) use ($bankKey) {
            $accKey = self::normalizeBankKey($acc->name);
            return $accKey !== '' && (str_contains($accKey, $bankKey) || str_contains($bankKey, $accKey));
        });
        if ($partialActive) {
            return $partialActive;
        }

        if (!$autoCreate) {
            return null;
        }

        // Prioritas 3: Jika ada akun bank nonaktif dengan nama yang cocok di cabang ini, aktifkan kembali
        $inactiveMatch = $accounts->where('is_active', false)->first(function (self $acc) use ($bankKey) {
            $accKey = self::normalizeBankKey($acc->name);
            return $accKey !== '' && ($accKey === $bankKey || str_contains($accKey, $bankKey));
        });
        if ($inactiveMatch) {
            $inactiveMatch->update(['is_active' => true]);
            return $inactiveMatch;
        }

        // Prioritas 4: Buat akun bank baru khusus untuk cabang ini
        $defaultMeta = self::DEFAULT_BRANCH_BANKS[$bankKey] ?? null;
        $shortName = $defaultMeta['short_name'] ?? self::extractShortBankName($bankName);
        $accountName = $defaultMeta['name'] ?? ('Rekening ' . $shortName);
        $accountNameEn = $defaultMeta['name_en'] ?? ($shortName . ' Bank Account');

        $preferredCode = $defaultMeta['code'] ?? null;
        $code = ($preferredCode && !self::where('pharmacy_id', $pharmacyId)->where('code', $preferredCode)->exists())
            ? $preferredCode
            : self::nextBranchCashBankCode($pharmacyId);

        $created = self::create([
            'pharmacy_id' => $pharmacyId,
            'code' => $code,
            'name' => $accountName,
            'name_en' => $accountNameEn,
            'category' => 'Kas & Bank',
            'account_type' => 'bank',
            'account_number' => null,
            'balance' => 0,
            'is_active' => true,
        ]);

        if ($lockForUpdate) {
            return self::whereKey($created->id)->lockForUpdate()->first();
        }

        return $created;
    }

    /**
     * Ambil daftar opsi rekening bank aktif milik cabang untuk dropdown Kasir & Data Penjualan.
     */
    public static function getBranchBankOptions(int $pharmacyId)
    {
        if ($pharmacyId > 0) {
            self::ensureBranchDefaultAccounts($pharmacyId);
        }

        $preferredOrder = ['MANDIRI', 'BNI', 'BCA', 'BRI', 'BTN'];

        return self::where('pharmacy_id', $pharmacyId)
            ->where('category', 'Kas & Bank')
            ->where('account_type', 'bank')
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'account_number'])
            ->map(function (self $acc) {
                $bankKey = self::normalizeBankKey($acc->name);
                $defaultMeta = self::DEFAULT_BRANCH_BANKS[$bankKey] ?? null;
                $shortName = $defaultMeta['short_name'] ?? self::extractShortBankName($acc->name);

                return [
                    'id' => $acc->id,
                    'code' => $acc->code,
                    'name' => $acc->name,
                    'bank_name' => $shortName,
                    'bank_key' => $bankKey,
                    'account_number' => $acc->account_number,
                ];
            })
            ->sortBy(function (array $item) use ($preferredOrder) {
                $idx = array_search($item['bank_key'], $preferredOrder, true);
                return $idx !== false ? sprintf('0_%02d', $idx) : '1_' . $item['name'];
            })
            ->values();
    }
}
