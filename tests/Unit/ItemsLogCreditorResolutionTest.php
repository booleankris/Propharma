<?php

namespace Tests\Unit;

use App\Exports\Stocks\StorageStockHistorySheetExport;
use App\Models\Batches;
use App\Models\Creditor;
use App\Models\ItemsLog;
use App\Models\Medicines;
use App\Models\OrderItems;
use App\Models\Pharmacies;
use App\Models\PurchaseReturn;
use App\Models\Receiving;
use App\Models\ReceivingDetails;
use App\Models\ReceivingItems;
use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class ItemsLogCreditorResolutionTest extends TestCase
{
    public function test_pembelian_resolves_matching_faktur_and_pbf_when_receiving_has_multiple_details(): void
    {
        $creditor1 = new Creditor(['code' => 'PBF01', 'name' => 'PT PBF PERTAMA']);
        $creditor2 = new Creditor(['code' => 'PBF02', 'name' => 'ANTARMITRA SEMBADA, PT']);

        $orderItem1 = new OrderItems(['id' => 101, 'medicine_id' => 10, 'creditor_code' => 'PBF01']);
        $orderItem1->setRelation('creditors', $creditor1);

        $orderItem2 = new OrderItems(['id' => 102, 'medicine_id' => 20, 'creditor_code' => 'PBF02']);
        $orderItem2->setRelation('creditors', $creditor2);

        $ri1 = new ReceivingItems([
            'id' => 201,
            'receiving_details_id' => 301,
            'order_items_id' => 101,
            'batches_id' => 501,
            'qty_received' => 5,
            'stock_unit_factor' => 1,
        ]);
        $ri1->setRelation('order_items', $orderItem1);

        $ri2 = new ReceivingItems([
            'id' => 202,
            'receiving_details_id' => 302,
            'order_items_id' => 102,
            'batches_id' => 502,
            'qty_received' => 1,
            'stock_unit_factor' => 1,
        ]);
        $ri2->setRelation('order_items', $orderItem2);

        $detail1 = new ReceivingDetails([
            'id' => 301,
            'receiving_details_code' => 'NT-26-10/0517',
            'invoice_number' => 'INV-001',
            'creditor_code' => 'PBF01',
        ]);
        $detail1->setRelation('creditor', $creditor1);
        $detail1->setRelation('receiving_items', collect([$ri1]));

        $detail2 = new ReceivingDetails([
            'id' => 302,
            'receiving_details_code' => 'NT-26-10/0518',
            'invoice_number' => 'INV-002',
            'creditor_code' => 'PBF02',
        ]);
        $detail2->setRelation('creditor', $creditor2);
        $detail2->setRelation('receiving_items', collect([$ri2]));

        $receiving = new Receiving(['id' => 401, 'code' => '2610RE0100']);
        $receiving->setRelation('receiving_details', collect([$detail1, $detail2]));

        $log = new ItemsLog([
            'id' => 901,
            'transaction_code' => '2610RE0100',
            'status' => 2,
            'medicine_id' => 20,
            'batches_id' => 502,
            'qty' => 1,
        ]);
        $log->setRelation('receiving', $receiving);

        $this->assertSame('NT-26-10/0518', $log->resolvePurchaseTransactionCode());
        $this->assertSame('ANTARMITRA SEMBADA, PT', $log->resolveCreditorName());
    }

    public function test_retur_beli_from_purchase_return_resolves_creditor_name(): void
    {
        $creditor = new Creditor(['code' => 'PBF99', 'name' => 'PT KEBAYORAN PHARMA']);
        $pr = new PurchaseReturn(['return_no' => 'RET-2610/0002', 'creditor_code' => 'PBF99']);
        $pr->setRelation('creditor', $creditor);

        $log = new ItemsLog([
            'id' => 902,
            'transaction_code' => 'RET-2610/0002',
            'status' => 4,
            'medicine_id' => 20,
            'batches_id' => 502,
            'qty' => 2,
        ]);
        $log->setRelation('receiving', null);
        $log->setRelation('purchaseReturn', $pr);

        $this->assertSame('RET-2610/0002', $log->resolvePurchaseTransactionCode());
        $this->assertSame('PT KEBAYORAN PHARMA', $log->resolveCreditorName());
    }

    public function test_storage_sheet_export_uses_matched_pbf_across_multiple_details(): void
    {
        $creditor1 = new Creditor(['code' => 'PBF01', 'name' => 'PT PBF SALAH']);
        $creditor2 = new Creditor(['code' => 'PBF02', 'name' => 'PT PBF BENAR']);

        $orderItem2 = new OrderItems(['id' => 112, 'medicine_id' => 30, 'creditor_code' => 'PBF02']);
        $orderItem2->setRelation('creditors', $creditor2);

        $ri2 = new ReceivingItems([
            'id' => 212,
            'receiving_details_id' => 312,
            'order_items_id' => 112,
            'batches_id' => 512,
            'qty_received' => 10,
            'stock_unit_factor' => 1,
        ]);
        $ri2->setRelation('order_items', $orderItem2);

        $detail1 = new ReceivingDetails(['id' => 311, 'receiving_details_code' => 'NT-26-10/0001', 'creditor_code' => 'PBF01']);
        $detail1->setRelation('creditor', $creditor1);
        $detail1->setRelation('receiving_items', collect());

        $detail2 = new ReceivingDetails(['id' => 312, 'receiving_details_code' => 'NT-26-10/0002', 'creditor_code' => 'PBF02']);
        $detail2->setRelation('creditor', $creditor2);
        $detail2->setRelation('receiving_items', collect([$ri2]));

        $receiving = new Receiving(['id' => 411, 'code' => '2610RE0200']);
        $receiving->setRelation('receiving_details', collect([$detail1, $detail2]));

        $log = new ItemsLog([
            'id' => 903,
            'date' => '2026-10-30 20:36:16',
            'transaction_code' => '2610RE0200',
            'status' => 2,
            'medicine_id' => 30,
            'batches_id' => 512,
            'qty' => 10,
            'qty_before' => 0,
            'qty_after' => 10,
        ]);
        $log->setRelation('medicines', new Medicines(['code' => 'MED30', 'name' => 'OCTASERUM']));
        $log->setRelation('batches', new Batches(['name' => 'P450C6681', 'expired_date' => '2028-01-01']));
        $log->setRelation('users', new User(['name' => 'Operator']));
        $log->setRelation('receiving', $receiving);

        $sheet = new StorageStockHistorySheetExport(
            'Factory',
            'Factory',
            collect([$log]),
            new Request(),
            new Pharmacies(['name' => 'Gudang'])
        );

        $rows = $sheet->array();
        $this->assertSame('PT PBF BENAR', $rows[5][11]);
    }
}
