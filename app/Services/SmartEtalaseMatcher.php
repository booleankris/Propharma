<?php

namespace App\Services;

use App\Models\Etalases;
use Illuminate\Support\Collection;

class SmartEtalaseMatcher
{
    protected int $pharmacyId;
    protected Collection $etalases;
    protected array $lookupCache = [];

    public function __construct(int $pharmacyId)
    {
        $this->pharmacyId = $pharmacyId;
        $this->etalases = Etalases::where(function ($q) use ($pharmacyId) {
            $q->where('pharmacy_id', $pharmacyId)
              ->orWhereNull('pharmacy_id');
        })->get();

        $this->buildLookupIndex();
    }

    /**
     * Build indexed canonical mappings for fast and accurate matching.
     */
    protected function buildLookupIndex(): void
    {
        foreach ($this->etalases as $etalase) {
            $rawName = trim((string) $etalase->name);
            $canonical = self::canonicalize($rawName);

            $this->lookupCache[] = [
                'id'            => $etalase->id,
                'name'          => $rawName,
                'canonical'     => $canonical,
                'model'         => $etalase,
            ];
        }
    }

    /**
     * Transform a string into a canonical representation for flexible matching:
     * - Lowercase & trim
     * - Standardize separators (underscores, dashes, dots to spaces)
     * - Separate letters and numbers: "Rak1" -> "rak 1", "OTC6" -> "otc 6"
     * - Remove leading zeros in numbers: "01" -> "1"
     * - Collapse multiple whitespaces
     * - Strip non-alphanumeric (except spaces)
     */
    public static function canonicalize(string $input): string
    {
        $str = mb_strtolower(trim($input), 'UTF-8');

        // Replace common punctuation/separators with spaces
        $str = preg_replace('/[_\-\.\/\\\]+/', ' ', $str);

        // Separate letters from numbers: "rak1" -> "rak 1", "1rak" -> "1 rak"
        $str = preg_replace('/([a-z]+)(\d+)/u', '$1 $2', $str);
        $str = preg_replace('/(\d+)([a-z]+)/u', '$1 $2', $str);

        // Strip non-alphanumeric characters except spaces
        $str = preg_replace('/[^a-z0-9\s]/u', '', $str);

        // Remove leading zeros on stand-alone numbers: "otc 06" -> "otc 6", "rak 01" -> "rak 1"
        $str = preg_replace('/\b0+(\d+)\b/', '$1', $str);

        // Collapse multiple whitespace
        $str = preg_replace('/\s+/', ' ', $str);

        return trim($str);
    }

    /**
     * Match a raw etalase name against pharmacy etalases.
     *
     * @param string|null $rawName
     * @return array
     */
    public function match(?string $rawName): array
    {
        $original = trim((string) $rawName);

        if ($original === '') {
            return [
                'matched'       => false,
                'etalase'       => null,
                'etalases_id'   => null,
                'original'      => '',
                'matched_name'  => null,
                'is_exact'      => false,
                'confidence'    => 0,
                'was_corrected' => false,
                'suggestion'    => null,
            ];
        }

        $inputCanonical = self::canonicalize($original);

        // 1. Direct exact match on original name (case-insensitive)
        foreach ($this->lookupCache as $item) {
            if (strcasecmp($original, $item['name']) === 0) {
                return [
                    'matched'       => true,
                    'etalase'       => $item['model'],
                    'etalases_id'   => $item['id'],
                    'original'      => $original,
                    'matched_name'  => $item['name'],
                    'is_exact'      => true,
                    'confidence'    => 1.0,
                    'was_corrected' => false,
                    'suggestion'    => null,
                ];
            }
        }

        // 2. Exact match on canonical form (e.g. "rak1" vs "Rak 1", "ALKES 04" vs "ALKES 4")
        foreach ($this->lookupCache as $item) {
            if ($inputCanonical === $item['canonical']) {
                $wasCorrected = ($original !== $item['name']);
                return [
                    'matched'       => true,
                    'etalase'       => $item['model'],
                    'etalases_id'   => $item['id'],
                    'original'      => $original,
                    'matched_name'  => $item['name'],
                    'is_exact'      => false,
                    'confidence'    => 0.98,
                    'was_corrected' => $wasCorrected,
                    'suggestion'    => null,
                ];
            }
        }

        // 3. Fuzzy matching with similarity metric
        $bestMatch = null;
        $highestScore = 0.0;

        preg_match_all('/\d+/', $inputCanonical, $inputDigits);
        $inputNumbers = $inputDigits[0] ?? [];

        foreach ($this->lookupCache as $item) {
            // If both contain digits, ensure all digits match to prevent 'Rak 10' matching 'Rak 1'
            preg_match_all('/\d+/', $item['canonical'], $itemDigits);
            $targetNumbers = $itemDigits[0] ?? [];

            if (!empty($inputNumbers) || !empty($targetNumbers)) {
                if ($inputNumbers !== $targetNumbers) {
                    continue; // Skip candidates with different shelf/rack numbers
                }
            }

            similar_text($inputCanonical, $item['canonical'], $percent);
            $score = $percent / 100.0;

            // Also check levenshtein for short string nuances
            $lev = levenshtein($inputCanonical, $item['canonical']);
            $maxLen = max(strlen($inputCanonical), strlen($item['canonical']));
            if ($maxLen > 0) {
                $levScore = 1.0 - ($lev / $maxLen);
                $combinedScore = max($score, $levScore);
            } else {
                $combinedScore = $score;
            }

            if ($combinedScore > $highestScore) {
                $highestScore = $combinedScore;
                $bestMatch = $item;
            }
        }

        // Threshold for fuzzy match: >= 80% similarity
        if ($bestMatch && $highestScore >= 0.80) {
            return [
                'matched'       => true,
                'etalase'       => $bestMatch['model'],
                'etalases_id'   => $bestMatch['id'],
                'original'      => $original,
                'matched_name'  => $bestMatch['name'],
                'is_exact'      => false,
                'confidence'    => round($highestScore, 2),
                'was_corrected' => true,
                'suggestion'    => null,
            ];
        }

        // Suggestion if score between 50% and 80%
        $suggestion = ($bestMatch && $highestScore >= 0.50) ? $bestMatch['name'] : null;

        return [
            'matched'       => false,
            'etalase'       => null,
            'etalases_id'   => null,
            'original'      => $original,
            'matched_name'  => null,
            'is_exact'      => false,
            'confidence'    => round($highestScore, 2),
            'was_corrected' => false,
            'suggestion'    => $suggestion,
        ];
    }

    /**
     * Get list of all available etalases for this pharmacy.
     */
    public function getAvailableEtalases(): Collection
    {
        return $this->etalases;
    }
}
