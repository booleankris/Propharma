<?php

namespace Tests\Unit;

use App\Services\ConsignmentService;
use PHPUnit\Framework\TestCase;

class ConsignmentServiceTest extends TestCase
{
    public function test_it_allocates_net_sales_to_receipts_in_fifo_order(): void
    {
        $allocations = (new ConsignmentService)->allocateFifo([
            11 => 10,
            12 => 8,
            13 => 5,
        ], 14);

        $this->assertSame([
            11 => 10.0,
            12 => 4.0,
            13 => 0.0,
        ], $allocations);
    }

    public function test_returns_cannot_make_allocated_sales_negative(): void
    {
        $allocations = (new ConsignmentService)->allocateFifo([11 => 10], -3);

        $this->assertSame([11 => 0.0], $allocations);
    }
}
