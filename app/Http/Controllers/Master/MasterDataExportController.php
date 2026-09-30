<?php

namespace App\Http\Controllers\Master;

use App\Http\Controllers\Controller;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MasterDataExportController extends Controller
{
    private const CHUNK_SIZE = 2000;

    public function __invoke(string $type): StreamedResponse
    {
        $export = config("master_exports.{$type}");

        abort_unless(is_array($export), 404);

        $filename = sprintf('%s-%s.csv', $export['filename'], now()->format('Ymd-His'));

        return response()->streamDownload(function () use ($export): void {
            set_time_limit(0);

            $output = fopen('php://output', 'wb');

            if ($output === false) {
                return;
            }

            // UTF-8 BOM helps Excel recognize Indonesian text without mojibake.
            fwrite($output, "\xEF\xBB\xBF");
            fputcsv($output, array_keys($export['columns']));

            $query = $this->buildQuery($export);
            $aliases = array_map(
                static fn (int $index): string => "export_column_{$index}",
                array_keys(array_values($export['columns']))
            );

            foreach ($query->lazyById(self::CHUNK_SIZE, $export['table'].'.id', 'export_id') as $row) {
                $values = [];

                foreach ($aliases as $alias) {
                    $values[] = $this->sanitizeCell($row->{$alias});
                }

                fputcsv($output, $values);
            }

            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private function buildQuery(array $export): Builder
    {
        $query = DB::table($export['table']);

        foreach ($export['joins'] ?? [] as [$table, $first, $operator, $second]) {
            $query->leftJoin($table, $first, $operator, $second);
        }

        if (($export['active_pharmacy_scope'] ?? false) && getActivePharmacyId()) {
            $query->where(function (Builder $query): void {
                $query->where('etalases.pharmacy_id', getActivePharmacyId())
                    ->orWhereNull('etalases.pharmacy_id');
            });
        }

        $selects = [$export['table'].'.id as export_id'];

        foreach (array_values($export['columns']) as $index => $column) {
            $selects[] = $column." as export_column_{$index}";
        }

        return $query->select($selects);
    }

    private function sanitizeCell(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        $value = (string) $value;

        // Prevent spreadsheet programs from evaluating exported user input as a formula.
        if (! is_numeric($value) && preg_match('/^\s*[=+\-@]/u', $value) === 1) {
            return "'".$value;
        }

        return $value;
    }
}
