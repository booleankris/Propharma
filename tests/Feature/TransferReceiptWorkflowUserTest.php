<?php

namespace Tests\Feature;

use App\Models\Batches;
use App\Models\Etalases;
use App\Models\Medicines;
use App\Models\MedicineTransfers;
use App\Models\MedicineTransferItems;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Tests\TestCase;

class TransferReceiptWorkflowUserTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_direct_transfer_tracks_sender_and_receiver_correctly_on_print()
    {
        // 1. Setup Users
        $sender = User::factory()->create([
            'name' => 'Pengirim Langsung',
            'pharmacy_id' => 9, // Gudang PMI
        ]);
        $receiver = User::factory()->create([
            'name' => 'Penerima Barang',
            'pharmacy_id' => 2, // Cabang 2
        ]);
        $viewer = User::factory()->create([
            'name' => 'Akun Login Viewer',
            'pharmacy_id' => 2,
        ]);

        $medicine = Medicines::first() ?? Medicines::create([
            'code' => 'MED-TEST-001',
            'name' => 'Paracetamol 500mg Test',
            'stock' => 100,
        ]);

        $batch = Batches::create([
            'pharmacy_id' => 9,
            'medicine_id' => $medicine->id,
            'name' => 'BATCH-TEST-01',
            'stock' => 50,
            'status' => 0,
        ]);

        $etalase = Etalases::firstOrCreate(['id' => 99], ['name' => 'Etalase Default']);

        // 2. Direct transfer created by $sender
        $this->actingAs($sender);
        $transfer = MedicineTransfers::create([
            'code' => 'TRF-TEST-DIR-01',
            'submission_key' => (string) Str::uuid(),
            'user_id' => $sender->id,
            'sent_by' => $sender->id,
            'status' => 0,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
        ]);

        $item = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'batches_id' => $batch->id,
            'source_batches_id' => $batch->id,
            'etalases_id' => $etalase->id,
            'qty' => 5,
            'original_qty' => 5,
            'source_type' => 'gudang',
            'status' => 0,
        ]);

        $this->assertEquals($sender->id, $transfer->sent_by);
        $this->assertNull($transfer->received_by);

        // 3. Viewer (Akun Login Viewer) prints the receipt while still pending
        $this->actingAs($viewer);
        session(['ho_pharmacy_id' => 2]);

        $response = $this->get(route('transfers.print', $transfer->id));
        $response->assertStatus(200);

        // Render view directly to assert HTML contents before PDF binary stream
        $view = view('kasir.transfers.receipt', [
            'transfer' => $transfer->fresh([
                'items.batches.medicines',
                'items.sourceBatch.pharmacy',
                'users.pharmacy',
                'sender.pharmacy',
                'receiver.pharmacy',
                'sourcePharmacy',
                'destinationPharmacy',
            ]),
            'senderName' => $sender->name,
            'receiverName' => null, // not yet accepted!
        ])->render();

        $this->assertStringContainsString('Pengirim Langsung', $view);
        $this->assertStringNotContainsString('Akun Login Viewer', $view);
        $this->assertStringContainsString('( .................................... )', $view);

        // 4. Receiver accepts the transfer
        $this->actingAs($receiver);
        session(['ho_pharmacy_id' => 2]);

        $acceptResponse = $this->post(route('transfers.accept', $transfer));
        $acceptResponse->assertRedirect();

        $transfer->refresh();
        $this->assertEquals(1, (int) $transfer->status);
        $this->assertEquals($receiver->id, (int) $transfer->received_by);

        // 5. Viewer prints after acceptance
        $this->actingAs($viewer);
        session(['ho_pharmacy_id' => 2]);

        $printResponse = $this->get(route('transfers.print', $transfer->id));
        $printResponse->assertStatus(200);

        // Inspect controller resolution logic via fresh print
        $freshTransfer = MedicineTransfers::with(['sender', 'receiver'])->findOrFail($transfer->id);
        $this->assertEquals('Pengirim Langsung', $freshTransfer->sender->name);
        $this->assertEquals('Penerima Barang', $freshTransfer->receiver->name);

        $viewAccepted = view('kasir.transfers.receipt', [
            'transfer' => $freshTransfer,
            'senderName' => $freshTransfer->sender->name,
            'receiverName' => $freshTransfer->receiver->name,
        ])->render();

        $this->assertStringContainsString('Pengirim Langsung', $viewAccepted);
        $this->assertStringContainsString('Penerima Barang', $viewAccepted);
        $this->assertStringNotContainsString('Akun Login Viewer', $viewAccepted);
    }

    public function test_transfer_request_tracks_requester_approver_and_receiver()
    {
        // 1. Setup Users
        $requester = User::factory()->create([
            'name' => 'Staf Pemohon Cabang',
            'pharmacy_id' => 2,
        ]);
        $approverGudang = User::factory()->create([
            'name' => 'Staf Gudang Pengirim',
            'pharmacy_id' => 9,
        ]);
        $receiver = User::factory()->create([
            'name' => 'Staf Penerima Fisik',
            'pharmacy_id' => 2,
        ]);
        $viewer = User::factory()->create([
            'name' => 'Admin Pengawas',
            'pharmacy_id' => 2,
        ]);

        $medicine = Medicines::first() ?? Medicines::create([
            'code' => 'MED-REQ-001',
            'name' => 'Amoxicillin 500mg Test',
            'stock' => 100,
        ]);

        $sourceBatch = Batches::create([
            'pharmacy_id' => 9,
            'medicine_id' => $medicine->id,
            'name' => 'BATCH-GUDANG-01',
            'stock' => 50,
            'status' => 0,
        ]);

        $etalase = Etalases::firstOrCreate(['id' => 99], ['name' => 'Etalase Default']);

        // 2. Requester creates Permintaan Mutasi
        $this->actingAs($requester);
        session(['ho_pharmacy_id' => 2]);

        $transfer = MedicineTransfers::create([
            'code' => 'REQ-TEST-001',
            'user_id' => $requester->id,
            'requested_by' => $requester->id,
            'sent_by' => null,
            'received_by' => null,
            'status' => 0,
            'is_request' => true,
            'source_pharmacy_id' => 9,
            'destination_pharmacy_id' => 2,
            'request_status' => 0, // Pending approval
        ]);

        $item = MedicineTransferItems::create([
            'medicine_transfer_id' => $transfer->id,
            'requested_medicine_id' => $medicine->id,
            'source_batches_id' => $sourceBatch->id,
            'etalases_id' => $etalase->id,
            'qty' => 10,
            'original_qty' => 10,
            'source_type' => 'gudang',
            'status' => 0,
        ]);

        // When still pending request, sender and receiver MUST NOT be requester or logged in user
        $this->actingAs($requester);
        $pendingView = view('kasir.transfers.receipt', [
            'transfer' => $transfer->fresh(['sourcePharmacy', 'destinationPharmacy']),
            'senderName' => null, // Pending approval has no sender yet
            'receiverName' => null,
        ])->render();

        $this->assertStringNotContainsString('Staf Pemohon Cabang', $pendingView);
        $this->assertStringContainsString('( .................................... )', $pendingView);

        // 3. Gudang Approver approves the request
        $this->actingAs($approverGudang);
        session(['ho_pharmacy_id' => 9]);

        $approveResponse = $this->post(route('transfers.requests.approve', $transfer));
        $approveResponse->assertRedirect();

        $transfer->refresh();
        $this->assertEquals(1, (int) $transfer->request_status);
        $this->assertEquals($approverGudang->id, (int) $transfer->sent_by);

        // 4. Viewer prints after approval before receiving
        $this->actingAs($viewer);
        session(['ho_pharmacy_id' => 2]);

        $viewAfterApproval = view('kasir.transfers.receipt', [
            'transfer' => $transfer->fresh(['sender', 'receiver', 'sourcePharmacy', 'destinationPharmacy']),
            'senderName' => $approverGudang->name,
            'receiverName' => null, // Not yet accepted!
        ])->render();

        $this->assertStringContainsString('Staf Gudang Pengirim', $viewAfterApproval);
        $this->assertStringNotContainsString('Admin Pengawas', $viewAfterApproval);
        $this->assertStringNotContainsString('Staf Pemohon Cabang', $viewAfterApproval);
        $this->assertStringContainsString('( .................................... )', $viewAfterApproval);

        // 5. Physical receiver accepts
        $this->actingAs($receiver);
        session(['ho_pharmacy_id' => 2]);

        $acceptResponse = $this->post(route('transfers.accept', $transfer));
        $acceptResponse->assertRedirect();

        $transfer->refresh();
        $this->assertEquals(1, (int) $transfer->status);
        $this->assertEquals($receiver->id, (int) $transfer->received_by);

        // 6. Print by viewer now reflects both actual sender and actual receiver
        $this->actingAs($viewer);
        session(['ho_pharmacy_id' => 2]);

        $finalView = view('kasir.transfers.receipt', [
            'transfer' => $transfer->fresh(['sender', 'receiver', 'sourcePharmacy', 'destinationPharmacy']),
            'senderName' => $transfer->sender->name,
            'receiverName' => $transfer->receiver->name,
        ])->render();

        $this->assertStringContainsString('Staf Gudang Pengirim', $finalView);
        $this->assertStringContainsString('Staf Penerima Fisik', $finalView);
        $this->assertStringNotContainsString('Admin Pengawas', $finalView);
    }
}
