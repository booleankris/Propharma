<?php

namespace App\Support;

/**
 * Normalisasi & pencocokan data pasien (nama dan nomor telepon).
 */
class PatientMatcher
{
    /** Gelar / panggilan umum pasien di Indonesia */
    private const TITLES = [
        'TN' => 'TN', 'TUAN' => 'TN',
        'NY' => 'NY', 'NYONYA' => 'NY',
        'NN' => 'NN', 'NONA' => 'NN',
        'AN' => 'AN', 'ANAK' => 'AN',
        'BY' => 'BY', 'BAYI' => 'BY',
        'SDR' => 'SDR', 'SAUDARA' => 'SDR',
        'SDRI' => 'SDRI', 'SAUDARI' => 'SDRI',
        'OL' => 'OL', 'ONLINE' => 'OL', 'OP' => 'OL',
        'MR' => 'TN', 'MRS' => 'NY', 'MS' => 'NN',
    ];

    /** Nama yang terlalu umum / placeholder */
    private const PLACEHOLDERS = ['', '-', '0', 'UMUM', 'PASIEN', 'NONE', 'TIDAKADA', 'XXX', 'PELANGGAN'];

    /**
     * Normalisasi nama untuk pencocokan ketat (strict).
     * Huruf besar, hanya alphanumeric tanpa spasi & tanda baca.
     */
    public static function strictNameKey(?string $name): string
    {
        return preg_replace('/[^A-Z0-9]/', '', mb_strtoupper((string) $name));
    }

    /**
     * Normalisasi nomor telepon: hanya digit, konversi +62 / 62 menjadi 0.
     */
    public static function normalizePhone(?string $phone): string
    {
        $digits = preg_replace('/[^0-9]/', '', (string) $phone);
        if ($digits === '') {
            return '';
        }
        if (str_starts_with($digits, '62')) {
            $digits = '0' . substr($digits, 2);
        }
        return $digits;
    }

    /**
     * Cek apakah nomor telepon merupakan nomor valid (bukan dummy/placeholder).
     */
    public static function isValidPhone(?string $phone): bool
    {
        $clean = self::normalizePhone($phone);
        if (strlen($clean) < 6) {
            return false;
        }
        // Tolak jika semua angka sama (misal 000000, 111111) atau hanya angka 0 berulang
        if (preg_match('/^(\d)\1+$/', $clean)) {
            return false;
        }
        return true;
    }

    public static function isPlaceholderName(?string $name): bool
    {
        $key = self::strictNameKey($name);
        return in_array($key, self::PLACEHOLDERS, true) || strlen($key) < 2;
    }

    /**
     * Pecah nama menjadi honorific (title), core (nama inti tanpa honorific), dan coreSorted.
     */
    public static function parseName(?string $name): array
    {
        $s = mb_strtoupper((string) $name);
        $s = preg_replace('/[^A-Z0-9]+/u', ' ', $s);
        $tokens = array_values(array_filter(explode(' ', $s), fn ($t) => $t !== ''));

        $title = '';
        $core = [];

        foreach ($tokens as $idx => $t) {
            if ($idx === 0 && isset(self::TITLES[$t])) {
                $title = self::TITLES[$t];
                continue;
            }
            $core[] = $t;
        }

        // Jika semua token terambil sebagai title (misal nama cuma "TN"), kembalikan ke core
        if (empty($core) && $title !== '') {
            $core = [$title];
            $title = '';
        }

        $sorted = $core;
        sort($sorted);

        return [
            'title'      => $title,
            'core'       => implode('', $core),
            'coreWords'  => $core,
            'coreSorted' => implode('', $sorted),
        ];
    }

    /**
     * Evaluasi kemiripan nama secara fuzzy.
     */
    public static function isFuzzyNameMatch(array $a, array $b): bool
    {
        if ($a['core'] === '' || $b['core'] === '') {
            return false;
        }

        // Nama inti sama persis atau urutan kata sama
        if ($a['core'] === $b['core'] || $a['coreSorted'] === $b['coreSorted']) {
            return true;
        }

        $lenA = strlen($a['core']);
        $lenB = strlen($b['core']);
        $minLen = min($lenA, $lenB);

        // Jangan fuzzy nama yang terlalu pendek
        if ($minLen < 4) {
            return false;
        }

        $allowedDist = ($minLen >= 12) ? 2 : (($minLen >= 6) ? 1 : 0);
        if ($allowedDist > 0 && levenshtein($a['core'], $b['core']) <= $allowedDist) {
            return true;
        }

        // Mirip teks > 85% untuk nama panjang
        if ($minLen >= 7) {
            similar_text($a['core'], $b['core'], $percent);
            if ($percent >= 85.0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cari pasien yang sudah ada berdasarkan nama dan/atau telepon.
     *
     * @param  string  $name
     * @param  string|null  $phone
     * @param  iterable  $patients  koleksi objek {id, code, name, phone, address, city, ...}
     * @param  int  $limit
     * @return array{exact: mixed|null, match_reason: string|null, is_phone_match: bool, similar: array}
     */
    public static function findMatches(string $name, ?string $phone, iterable $patients, int $limit = 5): array
    {
        $strictKey = self::strictNameKey($name);
        $normPhone = self::normalizePhone($phone);
        $phoneValid = self::isValidPhone($normPhone);
        $parsed = self::parseName($name);

        $exact = null;
        $matchReason = null;
        $isPhoneMatch = false;
        $similar = [];
        $seenIds = [];

        foreach ($patients as $pat) {
            if (self::isPlaceholderName($pat->name)) {
                continue;
            }

            $patStrictKey = self::strictNameKey($pat->name);
            $patNormPhone = self::normalizePhone($pat->phone ?? '');
            $patPhoneValid = self::isValidPhone($patNormPhone);
            $patParsed = self::parseName($pat->name);

            $sameNameStrict = ($strictKey === $patStrictKey);
            $samePhone = ($phoneValid && $patPhoneValid && $normPhone === $patNormPhone);
            $sameCoreName = ($parsed['core'] !== '' && $parsed['core'] === $patParsed['core']);

            // 1. EXACT MATCH
            if ($sameNameStrict && $samePhone) {
                if (!$exact) {
                    $exact = $pat;
                    $matchReason = 'Nama dan Nomor Telepon Sama Persis';
                    $isPhoneMatch = true;
                    $seenIds[$pat->id] = true;
                    continue;
                }
            } elseif ($samePhone) {
                if (!$exact) {
                    $exact = $pat;
                    $matchReason = 'Nomor Telepon Sama Persis (' . $pat->phone . ')';
                    $isPhoneMatch = true;
                    $seenIds[$pat->id] = true;
                    continue;
                }
            } elseif ($sameNameStrict) {
                if (!$exact) {
                    $exact = $pat;
                    $matchReason = 'Nama Sama Persis';
                    $seenIds[$pat->id] = true;
                    continue;
                }
            } elseif ($sameCoreName) {
                // Misal "TN. AMINUDIN" vs "AMINUDIN"
                if (!$exact && ($phoneValid === false || $patPhoneValid === false || $samePhone)) {
                    $exact = $pat;
                    $matchReason = 'Nama Pasien Identik (hanya beda sebutan Tn/Ny)';
                    $seenIds[$pat->id] = true;
                    continue;
                }
            }

            // 2. SIMILAR (FUZZY) MATCH
            if (isset($seenIds[$pat->id])) {
                continue;
            }

            $isSimilar = false;
            if ($samePhone) {
                $isSimilar = true;
            } elseif ($sameNameStrict || $sameCoreName) {
                $isSimilar = true;
            } elseif (self::isFuzzyNameMatch($parsed, $patParsed)) {
                $isSimilar = true;
            }

            if ($isSimilar && count($similar) < $limit) {
                $similar[] = $pat;
                $seenIds[$pat->id] = true;
            }
        }

        return [
            'exact'          => $exact,
            'match_reason'   => $matchReason,
            'is_phone_match' => $isPhoneMatch,
            'similar'        => $similar,
        ];
    }
}
