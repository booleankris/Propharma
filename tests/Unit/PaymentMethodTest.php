<?php

namespace Tests\Unit;

use App\Exports\Report\BankSalesExport;
use App\Exports\Report\BankSalesSheetExport;
use App\Support\PaymentMethod;
use PHPUnit\Framework\TestCase;

class PaymentMethodTest extends TestCase
{
    public function test_report_categories_include_method_and_uppercase_bank(): void
    {
        foreach (['TRANSFER', 'DEBIT', 'QRIS'] as $method) {
            foreach (['Mandiri', 'bni', 'BCA', 'BRI', 'BTN'] as $bank) {
                $expected = $method.' '.strtoupper($bank);
                $this->assertSame($expected, BankSalesExport::resolveCategory((object) [
                    'payment_method' => strtolower($method), 'transfer_bank_name' => ' '.$bank.' ',
                ]));
                $this->assertContains($expected, BankSalesExport::STANDARD_BANKS);
            }
            $this->assertSame($method.' LAINNYA', PaymentMethod::label($method, null));
            $this->assertSame($method.' LAINNYA', PaymentMethod::label($method, '123'));
        }
        $this->assertSame('TRANSFER MANDIRI', PaymentMethod::label('TRANSFER', 'Transfer Mandiri'));
        $this->assertSame('BELUM BAYAR', PaymentMethod::label('Belum Bayar', 'Mandiri'));
        $this->assertSame('CASH', PaymentMethod::label('CASH', 'Mandiri'));
        $this->assertSame('METODE TIDAK TERCATAT MANDIRI', PaymentMethod::label(null, 'Mandiri'));
        $this->assertContains('BELUM BAYAR', BankSalesExport::STANDARD_BANKS);
        $this->assertNotContains('Mandiri', BankSalesExport::STANDARD_BANKS);
    }

    public function test_empty_report_category_does_not_fetch_other_transactions(): void
    {
        $sheet = new BankSalesSheetExport(1, '2026-09-01', '2026-09-10', '', '', null, 'semua', 'BELUM BAYAR', false, [], []);
        $method = new \ReflectionMethod($sheet, 'buildDetailRows');
        $rows = $method->invoke($sheet);
        $this->assertCount(3, $rows);
        $this->assertSame('TOTAL BELUM BAYAR', $rows[2][1]);
        $this->assertSame(0, $rows[2][9]);
    }
}
