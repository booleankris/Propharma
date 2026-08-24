<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Pharmacies;
use Illuminate\Support\Collection;

class SuratPesananFormatter
{
    private const W = 80;

    /**
     * Build a full ESC/P text document for the Surat Pesanan (SP),
     * split per creditor/type, separated by form feeds.
     */
    public static function build(Order $order, Pharmacies $pharmacy, Collection $grouped, string $date, bool $draft = true): string
    {
        $docs = [];

        foreach ($grouped as $type => $creditors) {
            if (str_starts_with((string) $type, 'NARKOTIKA')) {
                foreach ($creditors as $items) {
                    $docs[] = self::narkotika($pharmacy, $items, $date);
                }
            } elseif ($type === 'REGULER') {
                foreach ($creditors as $items) {
                    $docs[] = self::reguler($pharmacy, $items, $date);
                }
            } elseif ($type === 'PREKURSOR') {
                foreach ($creditors as $items) {
                    $docs[] = self::prekursor($pharmacy, $items, $date);
                }
            } elseif ($type === 'Obat Tertentu' || $type === 'OBAT-OBAT TERTENTU (OOT)') {
                foreach ($creditors as $items) {
                    $docs[] = self::oot($pharmacy, $items, $date);
                }
            } elseif ($type === 'Psikotropika' || $type === 'PSIKOTROPIKA') {
                foreach ($creditors as $items) {
                    $docs[] = self::psikotropika($pharmacy, $items, $date);
                }
            }
        }

        if (empty($docs)) {
            return '';
        }

        return DotMatrixPrinter::init($draft)
            . implode(DotMatrixPrinter::FF, $docs)
            . DotMatrixPrinter::FF
            . DotMatrixPrinter::ESC . '@';
    }

    // =====================================================================
    // REGULER
    // =====================================================================
    private static function reguler(Pharmacies $pharmacy, Collection $items, string $date): string
    {
        $first = $items->first();
        $creditor = $first?->creditors;

        $lines = [];
        $lines[] = self::center(strtoupper($pharmacy->name), self::W);
        $lines[] = self::center($pharmacy->address, self::W);
        $lines[] = self::center('HP. ' . $pharmacy->phone, self::W);
        $lines[] = self::center('Apoteker : ' . $pharmacy->pharmacist, self::W);
        $lines[] = self::center('No. SIPA : ' . $pharmacy->pharmacist_permit, self::W);
        $lines[] = self::center(self::siaLabel($pharmacy), self::W);
        $lines[] = self::line();
        $lines[] = self::cell('No : ' . $first->order_items_code, 40)
            . self::rcell('Kepada Yth : ' . ($creditor->name ?? '-'), 40);
        $lines[] = self::cell('', 40)
            . self::rcell('Di- ' . ($creditor->address ?? '-'), 40);
        $lines[] = '';
        $lines[] = self::bold(self::center('SURAT PESANAN', self::W));
        $lines[] = '';
        $lines[] = self::e('Mohon dikirim obat-obatan untuk keperluan apotek :');
        $lines[] = self::line();
        $lines[] = self::cell('Jumlah', 16) . self::cell('Nama Obat', 52) . self::cell('Keterangan', 12);
        $lines[] = self::line();

        foreach ($items as $row) {
            $qty = $row->quantity . ' (' . ucfirst(terbilang((int) $row->quantity)) . ')'
                . ($row->medicines->packaging ? ' ' . $row->medicines->packaging : '');
            $lines[] = self::cell($qty, 16)
                . self::cell($row->medicines->name ?? '-', 52)
                . self::cell(self::discount($row), 12);
        }

        $lines[] = self::line();
        $lines[] = self::cell('Asli : Arsip PBF', 40) . self::rcell($pharmacy->city . ', ' . $date, 40);
        $lines[] = self::cell('Copy : Arsip Apotek', 40) . self::rcell('Penanggung Jawab,', 40);
        $lines[] = '';
        $lines[] = '';
        $lines[] = '';
        $lines[] = self::cell('', 40) . DotMatrixPrinter::bold(self::rcell($pharmacy->pharmacist, 40));
        $lines[] = self::cell('', 40) . self::rcell('SIPA : ' . $pharmacy->pharmacist_permit, 40);

        return implode(DotMatrixPrinter::CRLF, $lines);
    }

    // =====================================================================
    // PREKURSOR
    // =====================================================================
    private static function prekursor(Pharmacies $pharmacy, Collection $items, string $date): string
    {
        $first = $items->first();
        $creditor = $first?->creditors;

        $lines = [];
        $lines[] = self::bold(self::center('SURAT PESANAN OBAT MENGANDUNG PREKURSOR FARMASI', self::W));
        $lines[] = self::center('Nomor SP : ' . $first->order_items_code, self::W);
        $lines[] = '';
        $lines[] = self::e('Yang bertanda tangan dibawah ini :');
        $lines[] = self::kv('Nama Apoteker', $pharmacy->pharmacist);
        $lines[] = self::kv('Jabatan', 'Apoteker Pengelola Apotek');
        $lines[] = self::kv('No. SIPA', $pharmacy->pharmacist_permit);
        $lines[] = '';
        $lines[] = self::e('Mengajukan pesanan obat mengandung Prekursor Farmasi kepada :');
        $lines[] = self::kv('Nama PBF', $creditor->name ?? '-');
        $lines[] = self::kv('Alamat', $creditor->address ?? '-');
        $lines[] = self::kv('No. Telp.', $creditor->phone ?? '-');
        $lines[] = '';
        $lines[] = self::e('Obat mengandung Prekursor Farmasi tersebut akan digunakan untuk memenuhi kebutuhan :');
        $lines[] = self::line();
        $lines[] = self::cell('No', 4)
            . self::cell('Nama Obat Mengandung PF', 28)
            . self::cell('Zat Aktif PF', 22)
            . self::cell('Satuan', 12)
            . self::cell('Jumlah', 12)
            . self::cell('Ket', 2);
        $lines[] = self::line();

        foreach ($items as $index => $row) {
            $lines[] = self::cell($index + 1, 4)
                . self::cell($row->medicines->name ?? '-', 28)
                . self::cell($row->medicines->composition->name ?? '-', 22)
                . self::cell($row->medicines->packaging ?? '-', 12)
                . self::cell($row->quantity . ' (' . ucfirst(terbilang((int) $row->quantity)) . ')', 12)
                . self::cell('', 2);
        }
        $lines[] = self::line();
        $lines[] = '';
        $lines[] = self::e('Obat tersebut mengandung Prekursor tersebut akan digunakan untuk memenuhi kebutuhan:');
        $lines[] = self::kv('Nama Apotek', $pharmacy->name);
        $lines[] = self::kv('Alamat', $pharmacy->address);
        $lines[] = self::kv('No. SIA', $pharmacy->permit);
        $lines[] = '';

        foreach (self::signatureRight($pharmacy, $date, 'Pemesan,') as $l) {
            $lines[] = $l;
        }

        return implode(DotMatrixPrinter::CRLF, $lines);
    }

    // =====================================================================
    // OBAT-OBAT TERTENTU (OOT)
    // =====================================================================
    private static function oot(Pharmacies $pharmacy, Collection $items, string $date): string
    {
        $first = $items->first();
        $creditor = $first?->creditors;

        $lines = [];
        $lines[] = self::bold(self::center('SURAT PESANAN OBAT-OBAT TERTENTU', self::W));
        $lines[] = self::center('Nomor : ' . $first->order_items_code, self::W);
        $lines[] = '';
        $lines[] = self::e('Yang bertanda tangan dibawah ini :');
        $lines[] = self::kv('Nama', $pharmacy->pharmacist);
        $lines[] = self::kv('Jabatan', 'Apoteker Pengelola Apotek');
        $lines[] = '';
        $lines[] = self::e('Mengajukan pesanan Obat-Obat Tertentu kepada :');
        $lines[] = self::kv('Nama Distributor', $creditor->name ?? '-');
        $lines[] = self::kv('Alamat', $creditor->address ?? '-');
        $lines[] = self::kv('Telp.', $creditor->phone ?? '-');
        $lines[] = '';
        $lines[] = self::e('Dengan Obat-Obat Tertentu yang dipesan adalah :');
        $lines[] = self::line();
        $lines[] = self::cell('No', 4)
            . self::cell('NAMA OBAT', 34)
            . self::cell('KOMPONEN', 16)
            . self::cell('JUMLAH', 26);
        $lines[] = self::line();

        foreach ($items as $index => $row) {
            $lines[] = self::cell($index + 1, 4)
                . self::cell($row->medicines->name ?? '-', 34)
                . self::cell($row->medicines->composition->name ?? '-', 16)
                . self::cell($row->quantity . ' (' . ucfirst(terbilang((int) $row->quantity)) . ')', 26);
        }
        $lines[] = self::line();
        $lines[] = '';
        $lines[] = self::e('Obat-Obat Tertentu tersebut akan dipergunakan untuk :');
        $lines[] = self::kv('Nama Sarana', $pharmacy->name);
        $lines[] = self::kv('Alamat Sarana', $pharmacy->address);
        $lines[] = self::kv('No. SIA', $pharmacy->permit);
        $lines[] = '';

        foreach (self::signatureRight($pharmacy, $date, 'Pemesan,') as $l) {
            $lines[] = $l;
        }
        $lines[] = '';
        $lines[] = self::e('Catatan :');
        $lines[] = self::e('- Satu Surat pesanan hanya berlaku untuk satu jenis Obat-Obat tertentu.');
        $lines[] = self::e('- Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.');

        return implode(DotMatrixPrinter::CRLF, $lines);
    }

    // =====================================================================
    // NARKOTIKA
    // =====================================================================
    private static function narkotika(Pharmacies $pharmacy, Collection $items, string $date): string
    {
        return self::narkoPsiko($pharmacy, $items, $date, 'NARKOTIKA');
    }

    // =====================================================================
    // PSIKOTROPIKA
    // =====================================================================
    private static function psikotropika(Pharmacies $pharmacy, Collection $items, string $date): string
    {
        return self::narkoPsiko($pharmacy, $items, $date, 'PSIKOTROPIKA');
    }

    private static function narkoPsiko(Pharmacies $pharmacy, Collection $items, string $date, string $label): string
    {
        $first = $items->first();
        $creditor = $first?->creditors;
        $title = 'SURAT PESANAN ' . $label;

        $lines = [];
        $lines[] = self::bold(self::center($title, self::W));
        $lines[] = self::center('Nomor : ' . $first->order_items_code, self::W);
        $lines[] = '';
        $lines[] = self::e('Yang bertanda tangan dibawah ini :');
        $lines[] = self::kv('Nama', $pharmacy->pharmacist);
        $lines[] = self::kv('Jabatan', 'Apoteker Pengelola Apotek');
        $lines[] = '';
        $lines[] = self::e('Mengajukan pesanan ' . $label . ' kepada :');
        $lines[] = self::kv('Nama Distributor', $creditor->name ?? '-');
        $lines[] = self::kv('Alamat', $creditor->address ?? '-');
        $lines[] = self::kv('Telp.', $creditor->phone ?? '-');
        $lines[] = '';
        $lines[] = self::e('Dengan ' . $label . ' yang dipesan adalah :');
        $lines[] = self::line();
        $lines[] = self::cell('No', 4)
            . self::cell('NAMA OBAT', 28)
            . self::cell('BENTUK SEDIAAN', 18)
            . self::cell('KEKUATAN SEDIAAN', 14)
            . self::cell('JUMLAH', 16);
        $lines[] = self::line();

        foreach ($items as $index => $row) {
            $lines[] = self::cell($index + 1, 4)
                . self::cell($row->medicines->name ?? '-', 28)
                . self::cell($row->medicines->composition->name ?? '-', 18)
                . self::cell($row->medicines->dosage ?? '-', 14)
                . self::cell($row->quantity . ' (' . ucfirst(terbilang((int) $row->quantity)) . ')', 16);
        }
        $lines[] = self::line();
        $lines[] = '';
        $lines[] = self::e($label . ' tersebut akan dipergunakan untuk :');
        $lines[] = self::kv('Nama Sarana', $pharmacy->name);
        $lines[] = self::kv('Alamat Sarana', $pharmacy->address);
        $lines[] = self::kv('No. SIA', $pharmacy->permit);
        $lines[] = '';

        foreach (self::signatureRight($pharmacy, $date, 'Pemesan,') as $l) {
            $lines[] = $l;
        }
        $lines[] = '';
        $lines[] = self::e('Catatan :');
        $lines[] = self::e('- Satu Surat pesanan hanya berlaku untuk satu jenis ' . $label . '.');
        $lines[] = self::e('- Surat pesanan dibuat sekurang-kurangnya 3 (tiga) rangkap.');

        return implode(DotMatrixPrinter::CRLF, $lines);
    }

    // =====================================================================
    // Helpers
    // =====================================================================
    private static function signatureRight(Pharmacies $pharmacy, string $date, string $role): array
    {
        return [
            self::rcell($pharmacy->city . ', ' . $date, self::W),
            self::rcell($role, self::W),
            '',
            '',
            '',
            DotMatrixPrinter::bold(self::rcell($pharmacy->pharmacist, self::W)),
            self::rcell('SIPA : ' . $pharmacy->pharmacist_permit, self::W),
        ];
    }

    private static function siaLabel(Pharmacies $pharmacy): string
    {
        if ($pharmacy->permit) {
            return 'No. SIA : ' . $pharmacy->permit;
        }
        if ($pharmacy->pharmacy_registration) {
            return 'No. STR : ' . $pharmacy->pharmacy_registration;
        }

        return '';
    }

    private static function discount($row): string
    {
        $credCode = $row->creditor_code ?? optional($row->creditors)->code;
        $medCred = $row->medicines?->creditors?->firstWhere('code', $credCode)
            ?? $row->medicines?->creditors?->first();
        $disc = $medCred?->pivot?->discount;

        return $disc ? (($disc == (int) $disc ? (int) $disc : $disc) . '%') : '-';
    }

    private static function kv(string $label, $value): string
    {
        return self::cell($label, 16) . ' : ' . self::e((string) $value);
    }

    private static function line(int $w = self::W): string
    {
        return str_repeat('-', $w);
    }

    private static function bold(string $text): string
    {
        return DotMatrixPrinter::bold($text);
    }

    private static function e(string $s): string
    {
        return DotMatrixPrinter::encode($s);
    }

    private static function cell($s, int $w): string
    {
        return str_pad(self::cut((string) $s, $w), $w);
    }

    private static function rcell($s, int $w): string
    {
        return str_pad(self::cut((string) $s, $w), $w, ' ', STR_PAD_LEFT);
    }

    private static function center($s, int $w): string
    {
        return str_pad(self::cut((string) $s, $w), $w, ' ', STR_PAD_BOTH);
    }

    private static function cut(string $s, int $w): string
    {
        $s = DotMatrixPrinter::encode($s);

        return substr($s, 0, $w);
    }
}
