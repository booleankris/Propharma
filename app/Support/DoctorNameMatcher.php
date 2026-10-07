<?php

namespace App\Support;

/**
 * Normalisasi & pencocokan nama dokter.
 *
 * Dua level pencocokan:
 *  - strictKey : huruf besar, semua tanda baca & spasi dibuang.
 *                "dr.Rizky  Sp. PD" == "DR RIZKY SPPD"  → dianggap PASTI sama.
 *  - fuzzy     : nama inti (tanpa gelar / spesialis / gelar akademik) mirip
 *                (typo 1–2 huruf, urutan kata tertukar), dengan syarat gelar
 *                & spesialis tidak bertentangan. Hasil fuzzy WAJIB direview.
 */
class DoctorNameMatcher
{
    /** Gelar depan → bentuk kanonik */
    private const TITLES = [
        'PROF' => 'PROF', 'DR' => 'DR', 'DOKTER' => 'DR', 'DRG' => 'DRG',
        'DRH' => 'DRH', 'DRS' => 'DRS', 'DRA' => 'DRA', 'IR' => 'IR',
        'H' => 'H', 'HJ' => 'HJ', 'BIDAN' => 'BIDAN', 'BD' => 'BIDAN',
    ];

    /** Kode spesialis umum Indonesia (urut panjang → pendek saat dipakai di regex) */
    private const SPECIALIST_CODES = [
        'THTKL', 'PERIO', 'BTKV', 'PROS', 'THT', 'KFR', 'KGA', 'DVE', 'ORT', 'RAD',
        'AK', 'AN', 'BS', 'BP', 'BU', 'BM', 'DV', 'EM', 'FK', 'FM', 'GK', 'JP', 'JK',
        'KJ', 'KK', 'KG', 'KN', 'KO', 'KP', 'MK', 'OG', 'OT', 'OK', 'OF', 'PA', 'PD',
        'PK', 'PM', 'PR', 'PRS', 'RM', 'RO', 'A', 'B', 'F', 'M', 'N', 'P', 'S', 'U',
    ];

    /** Gelar akademik belakang yang diabaikan untuk nama inti */
    private const DEGREES = [
        'MKES', 'MKED', 'MSC', 'MBIOMED', 'MM', 'MH', 'MARS', 'MPH', 'PHD', 'SKED',
        'SFARM', 'APT', 'SKEP', 'NS', 'AMD', 'AMDKEP', 'SST', 'STR', 'SPD', 'SH', 'SE',
    ];

    /** Nama yang terlalu generik untuk digabung otomatis */
    private const PLACEHOLDERS = ['', '-', '0', 'UMUM', 'NONE', 'TIDAKADA', 'XXX'];

    public static function strictKey(?string $name): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $name));
    }

    public static function isPlaceholder(?string $name): bool
    {
        $key = self::strictKey($name);
        return in_array($key, self::PLACEHOLDERS, true) || strlen($key) < 3;
    }

    /**
     * Pecah nama menjadi: titles[], spec, core (string compact), coreSorted.
     */
    public static function parse(?string $name): array
    {
        $s = mb_strtoupper((string) $name);

        // Spesialis: "SP.PD", "SP. PD", "SPPD", "Sp.OG(K)"
        $spec = '';
        $codes = self::SPECIALIST_CODES;
        usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
        $codeRe = implode('|', $codes);
        if (preg_match('/\bSP\s*\.?\s*(' . $codeRe . ')\b(\s*\(\s*K\s*\))?/u', $s, $m)) {
            $spec = $m[1] . (!empty($m[2]) ? 'K' : '');
            $s = str_replace($m[0], ' ', $s);
        }

        // Ganti tanda baca dengan spasi, lalu tokenisasi
        $s = preg_replace('/[^A-Z0-9]+/u', ' ', $s);
        $tokens = array_values(array_filter(explode(' ', $s), fn ($t) => $t !== ''));

        // Gabungkan token gelar akademik yang terpisah titik, mis. "M KES" → "MKES"
        $merged = [];
        for ($i = 0; $i < count($tokens); $i++) {
            $pair = $tokens[$i] . ($tokens[$i + 1] ?? '');
            if (isset($tokens[$i + 1]) && in_array($pair, self::DEGREES, true)) {
                $merged[] = $pair;
                $i++;
                continue;
            }
            $merged[] = $tokens[$i];
        }

        $titles = [];
        $core = [];
        foreach ($merged as $idx => $t) {
            // Gelar hanya dikenali di depan nama (sebelum token nama pertama)
            if (empty($core) && isset(self::TITLES[$t])) {
                $titles[self::TITLES[$t]] = true;
                continue;
            }
            if (in_array($t, self::DEGREES, true)) {
                continue;
            }
            $core[] = $t;
        }

        $titles = array_keys($titles);
        sort($titles);
        $sorted = $core;
        sort($sorted);

        return [
            'titles'     => implode('+', $titles),
            'spec'       => $spec,
            'core'       => implode('', $core),
            'coreSorted' => implode('', $sorted),
        ];
    }

    /**
     * Apakah dua nama kemungkinan orang yang sama (untuk review)?
     */
    public static function isFuzzyMatch(array $a, array $b): bool
    {
        if ($a['core'] === '' || $b['core'] === '') {
            return false;
        }
        // Gelar & spesialis tidak boleh bertentangan (boleh salah satu kosong)
        if ($a['titles'] !== '' && $b['titles'] !== '' && $a['titles'] !== $b['titles']) {
            return false;
        }
        if ($a['spec'] !== '' && $b['spec'] !== '' && $a['spec'] !== $b['spec']) {
            return false;
        }

        if ($a['core'] === $b['core'] || $a['coreSorted'] === $b['coreSorted']) {
            return true;
        }

        $len = min(strlen($a['core']), strlen($b['core']));
        $allowed = $len >= 12 ? 2 : ($len >= 6 ? 1 : 0);
        if ($allowed === 0) {
            return false;
        }

        return levenshtein($a['core'], $b['core']) <= $allowed;
    }

    /**
     * Cari dokter yang sudah ada.
     *
     * @param  iterable  $doctors  koleksi objek {id, name, ...}
     * @return array{exact: mixed|null, similar: array}
     */
    public static function findMatches(string $name, iterable $doctors, int $limit = 3): array
    {
        $key = self::strictKey($name);
        $parsed = self::parse($name);
        $exact = null;
        $similar = [];

        foreach ($doctors as $doc) {
            if (self::strictKey($doc->name) === $key) {
                $exact = $exact ?? $doc;
                continue;
            }
            if (count($similar) < $limit && self::isFuzzyMatch($parsed, self::parse($doc->name))) {
                $similar[] = $doc;
            }
        }

        return ['exact' => $exact, 'similar' => $similar];
    }
}
