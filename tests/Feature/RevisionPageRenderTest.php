<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class RevisionPageRenderTest extends TestCase
{
    public function test_revision_renders_complete_form_and_script_without_loading_medicine_master(): void
    {
        $source = file_get_contents(resource_path('views/orders/revision.blade.php'));
        // Render the page independently of the authenticated navigation/layout.
        $source = str_replace([
            "@extends('layouts.app')", "@section('title', 'Revisi Faktur')",
            "@section('content')", "@section('scripts')", '@endsection',
        ], '', $source);
        $html = Blade::render($source, [
            'order' => (object) ['id' => 1, 'code' => 'BPBA-TEST', 'status' => 3, 'is_consolidation' => false, 'order_items' => collect()],
            'allReceivingDetails' => collect([(object) [
                'id' => 7, 'receiving_details_code' => "NT'7", 'invoice_number' => 'FAKTUR-7',
                'invoice_date' => null, 'receiving_items' => collect(),
            ]]),
            'orphanedItems' => collect(), 'orderItemsData' => collect(),
            // No allMedicines variable: this page must never need the full medicine catalog.
        ]);
        $this->assertStringContainsString('id="add_medicine_id"', $html);
        $this->assertStringContainsString('id="add_batch"', $html);
        $this->assertStringContainsString('onclick="submitAddMedicine()"', $html);
        $this->assertStringContainsString('js/invoice-revision.js?v=', $html);
        $this->assertMatchesRegularExpression('/id="revision-page-data" type="application\/json">(.*?)<\/script>/s', $html);
        preg_match('/id="revision-page-data" type="application\/json">(.*?)<\/script>/s', $html, $matches);
        $config = json_decode($matches[1], true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame("NT'7", $config['details'][0]['code']);
        $this->assertSame([], $config['orderItems']);
    }
}
